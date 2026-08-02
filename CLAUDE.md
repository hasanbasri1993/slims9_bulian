# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

SLiMS 9 "Bulian" — SENAYAN Library Management System, a PHP application for library resource management (catalog/OPAC, circulation, membership, stock-take, reporting). GPLv3. No formal automated test suite exists in this repo (no PHPUnit config, no `tests/` directory) — verification is done by running the app and exercising the affected module manually.

## Running / building

- **Requirements**: PHP >= 8.1, MySQL 5.7+/MariaDB 10.3+, PHP extensions `gd`, `pdo_mysql`/`mysqli`, `mbstring`, `gettext`, `fileinfo` (checked at boot by `SLiMS\Extension::throwIfNotFulfilled()` in `sysconfig.inc.php` — missing extensions throw immediately).
- **Docker dev environment**: copy `.env.example` to `.env` (sets `HTTP_PORT`, `DB_PORT`), then `docker compose up -d --build`. `docker-compose.yml` wires an app container to a `mariadb:lts` container seeded from `install/senayan_ddl.sql`.
- **Composer**: `composer install` for PHP deps declared in `composer.json` (Guzzle, Monolog, Elasticsearch client, ramsey/uuid, gettext/gettext, etc.). Composer PSR-0 root: `"Slims" => "src/"`.
- **First run without a DB config**: if `config/database.php` doesn't exist, `sysconfig.inc.php` redirects to `install/index.php` (web installer).
- **CLI ("Tarsius")**: `php index.php tarsius` bootstraps a `tarsius` executable script in the app root (gitignored, generated on demand). After that, run commands with `php tarsius <command>`, e.g.:
  ```
  php tarsius status
  php tarsius plugin:list
  php tarsius db:backup
  ```
  Command classes live in `lib/Cli/Commands/` (built on Symfony Console, wrapped by `SLiMS\Cli\Console`, a singleton run via `Console::getInstance()->run()`).

## Architecture

### Two front doors, two routers

- **OPAC (public catalog)** — `index.php` (repo root). Sets `INDEX_AUTH`, requires `sysconfig.inc.php`, sanitizes `$_GET`/`$_POST`, then instantiates `SLiMS\Opac` (overridable via `config('custom_opac')`). Routing is object-oriented: `$opac->onWeb(fn($opac) => $opac->handle('p')->orWelcome())->onCli()` — the `p` GET param selects the page; falls back to the welcome page. Lifecycle hooks `CONTENT_BEFORE_LOAD` / `CONTENT_AFTER_LOAD` fire around content rendering, then `$opac->parseToTemplate()` renders.
- **Admin area** — `admin/index.php`. Older, procedural style: requires `admin/default/session.inc.php` + `session_check.inc.php` for auth, then `module` class (`lib/module.inc.php`) scans `admin/modules/` (constant `MDLBS`) to build the menu. The `mod` GET param selects the module; privileges are checked via `utility::havePrivilege($current_module, 'r')`. Submenus load their content over AJAX.
- Admin modules live one-per-folder under `admin/modules/`: `bibliography`, `circulation`, `master_file`, `membership`, `reporting`, `serial_control`, `stock_take`, `system` — each a flat set of PHP scripts (`index.php`, feature scripts, `pop_*.php` popups, `iframe_*.php` iframe content, `AJAX_*.php`-style endpoints elsewhere under `admin/`).

### Bootstrap: `sysconfig.inc.php`

Required (guarded by the `INDEX_AUTH` constant) by both `index.php` and `admin/index.php`. It:
- Loads `config/env.php`, then `vendor/autoload.php` (Composer) and `lib/autoload.php` (SLiMS's own autoloader — a manual `spl_autoload_register` mapping, e.g. `SLiMS\` → `lib/`, `Slims\Opac\` → `src/Slims/Opac`; note the namespace **casing difference** between core code (`SLiMS\...`, capital LiMS, resolves to `lib/`) and the Composer PSR-0 tree (`Slims\Opac\...`, resolves to `src/Slims/Opac`).
- Defines path/URL constants: `SB` (base dir), `LIB`, `MDL`/`MDLBS` (`admin/modules`), `IMG`/`IMGBS`, `UPLOAD`, `REPO`/`REPOBS`, `SWB` (OPAC web base), `AWB` (admin web base).
- Redirects to `install/index.php` if `config/database.php` is missing.
- Opens the DB connection (`$dbs = \SLiMS\DB::getInstance('mysqli')`), loads settings into `$sysconf`, sets up localization (`SLiMS\Polyglot\Memory`), and requires `lib/helper.inc.php` (defines the global helpers below).
- Loads plugins: `\SLiMS\Plugins::getInstance()->loadPlugins();`.
- Auto-generates missing config files from `config/*.sample.php` templates via `\SLiMS\Config::createFromSampleIfNotExists(...)`.

### Config

`config/` holds both committed `*.sample.php` templates and gitignored generated `*.php` files (`database.php`, `env.php`, `csp.php`, `auth.php`, `cache.php`, `mail.php`, etc.), created on first boot from the samples. Access config anywhere via the global `config('dot.path.key', $default)` helper, backed by `SLiMS\Config` — never read these files directly.

### Plugin system

Plugins live under `plugins/`, one file/folder per plugin, scanned up to 3 folder levels deep. A plugin's main file must have `plugin.php` in its filename (matched by `strpos`) and a WordPress-style docblock header:
```php
/**
 * Plugin Name: ...
 * Plugin URI: ...
 * Description: ...
 * Version: 1.0.0
 * Author: ...
 * Author URI: ...
 */
use SLiMS\Plugins;
$plugins = Plugins::getInstance();
$plugins->register(Plugins::CONTENT_BEFORE_LOAD, function () { ... });
$plugins->registerMenu('bibliography', __('Menu Label'), __DIR__ . '/index.php');
```
- `SLiMS\Plugins` (`lib/Plugins.php`) is a singleton exposing: `register()`/static `hook()` (subscribe to a hook), `execute($hook, $params)` (fire a hook — used throughout core code), `registerMenu()`/static `menu()` (add an admin or OPAC menu entry — target modules include `bibliography`, `membership`, `master_file`, `circulation`, `stock_take`, `system`, `reporting`, `serial_control`; OPAC menu entries become `?p=menu_name`), `registerModule()`, `registerPages()`, `registerSearchEngine()`, `registerSessionDriver()`, `registerCommand()` (wires a custom Tarsius CLI command), plus a `__callStatic` shortcut (e.g. `Plugins::opac(...)`).
- Notable hook constants: `CONTENT_BEFORE_LOAD`/`CONTENT_AFTER_LOAD`, `ADMIN_SESSION_AFTER_START`, `BIBLIOGRAPHY_INIT`/`BEFORE_SAVE`/`AFTER_SAVE`/`BEFORE_UPDATE`/`AFTER_UPDATE`/`CUSTOM_FIELD_DATA`/`CUSTOM_FIELD_FORM`, `MEMBERSHIP_INIT`/`BEFORE_SAVE`/`AFTER_SAVE`/custom-field equivalents, `CIRCULATION_AFTER_SUCCESSFUL_TRANSACTION`, `MEMBER_ON_VISIT`/`NON_MEMBER_ON_VISIT`, `MODULE_MAIN_MENU_INIT`, `OAI2_INIT`, `SYSTEM_BEFORE_CONFIG_SAVE`/`AFTER_CONFIG_SAVE`. When adding a new integration point in core code, prefer firing/adding a hook constant over hardcoding a call.
- Admin pages registered via `registerMenu()` are served through the generic `admin/plugin_container.php` (URLs of the form `AWB . 'plugin_container.php?mod=...&id=...'`).
- A plugin can carry a `migration/` subfolder for schema changes (see below) and an `action/` subfolder for one-off file operations. Activation state and schema version are tracked in the `plugins` DB table's `options` JSON column.
- "Custom fields" on bibliographic/membership records (`admin/modules/bibliography/custom_fields.inc.php` and the membership equivalent) are themselves implemented via the `*_CUSTOM_FIELD_DATA`/`*_CUSTOM_FIELD_FORM` hooks — there is no separate "extension" class for this; don't confuse it with `SLiMS\Extension` (`lib/Extension.php`), which is only the PHP-runtime-extension availability checker run at boot.

### Database access

Two supported low-level access styles, both reachable via `SLiMS\DB` (`use SLiMS\DB;`):
- MySQLi: `DB::getInstance('mysqli')->query(...)` (used by core bootstrap as `$dbs`), fetch with `fetch_assoc()`/`fetch_array()`/`fetch_row()`/`fetch_object()`, escape user input with `$dbs->escape_string(...)`.
- PDO (default): `DB::getInstance()->query(...)` / `->prepare(...)->execute([...])`, fetch with `PDO::FETCH_ASSOC` etc. `DB::connection('profile_name')` targets a non-default node from `config('database.nodes')`.
- A lightweight query-builder-style extension, `SLiMS\Query`, is reachable as `DB::query(...)`; additional extensions can be registered globally with `DB::registerExtension(name: ..., class: ...)` (e.g. wrapping Illuminate's query builder) and are then called as `DB::<name>()`.
- Schema changes use `SLiMS\Table\Schema` / `SLiMS\Table\Blueprint` (`Schema::create('table', fn(Blueprint $t) => ...)`, `Schema::drop(...)`, `Schema::truncate(...)`), not raw DDL, when writing migrations.
- Migrations (core or per-plugin): a `migration/` folder with numbered files `N_ClassName.php` (e.g. `1_CreateBase.php`), each defining a class extending `SLiMS\Migration\Migration` with `up()`/`down()`. Run via `SLiMS\Migration\Runner::path($dir)->setVersion($v)->runUp()`/`runDown()`, which natural-sorts the files and tracks the applied version. The `db:migrate` Tarsius command drives this for core schema.

### Global helpers (`lib/helper.inc.php`)

Available everywhere without an import: `__($str)` (translation), `config($key, $default)` (dot-path config/DB-setting lookup), `debug()`/`debugBox()`/`dump()`/`dd()` (dev-only inspection, gated by `isDev()`), `getArrayData($array, 'a.b.c', $default)`, `isCli()`, `isDev()`, `ip()`, `writeLog($module, $userId, $type, $message)` (audit log), `xssFree($str)`, `currency($amount)`, `number($val)` (fluent numeric formatting), `v($file)` (cache-busted asset URL), `toastr($msg)` (flash notifications, chainable `->success()`/`->error()`/etc., `->native()` for plain), `redirect()` (chainable redirect helper), `flash($key, $msg)` (one-shot session messages across a redirect), `pluginUrl()`/`pluginNavigateTo()` (build URLs inside a plugin admin page).

### HTTP client (`SLiMS\Http\Client`, `lib/Http/`)

Thin wrapper over Guzzle. Static verb calls: `Client::get($url)`, `::post($url, $formFields)`, etc. Chainable: `Client::withOption(...)->withHeaders([...])->withBody(json_encode([...]))->post($url)`. File transfer helpers: `Client::download($url)->to(SB . 'images/dummy.png', [...])` and `Client::stream($url, [...])` (proxy remote content directly to output without saving to disk). Default options (timeout, TLS verify) come from `config('http.client')`.

### Templates

`template/` holds OPAC themes (`default`, `Akasia`, `libdikbud`, `lightweight`); `admin/admin_template/` holds the admin UI shell. `simbio2/` and files under `lib/` prefixed lower-case (e.g. `simbio.inc.php`) are the legacy Simbio framework layer that much of the older admin/OPAC code is still built on — expect procedural style and `global $dbs;` patterns there rather than the newer `SLiMS\*` OOP layer.

## Conventions to follow

- New cross-cutting behavior (an integration point another plugin might want to tap into) should be exposed as a `SLiMS\Plugins` hook constant + `execute()` call, not a hardcoded core change.
- New DB schema changes go through a `migration/` file + `SLiMS\Table\Schema`/`Blueprint`, not hand-written `ALTER TABLE` scattered in PHP.
- Read config through `config('...')`, never by including/parsing files in `config/` directly.
- Match the surrounding file's style: files under `lib/<PascalCaseNamespace>/` and `src/Slims/` are modern namespaced `SLiMS\...` OOP code; files under `admin/modules/`, `simbio2/`, and lowercase `lib/*.inc.php` are legacy procedural Simbio-style code using `global $dbs;`. Don't mix paradigms within a single file.
