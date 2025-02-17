<?php
/**
 * @Source by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Created Date       : 05/11/20 21.33
 * @File name          : plugins.php
 * @Modified by        : Drajat Hasan (drajathasan20@gmail.com)
 */

use SLiMS\DB;
use SLiMS\Json;
use SLiMS\Jquery;
use SLiMS\Plugins;
use SLiMS\Parcel\Package;
use SLiMS\Migration\Runner;
use SLiMS\Migration\Action;

define('INDEX_AUTH', 1);

require __DIR__ . '/../../../sysconfig.inc.php';

require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';

$plugins = Plugins::getInstance();
$upload_success = false;

if (count($_POST) == 0) $_POST = json_decode(file_get_contents('php://input'), true);

if (isset($_POST['enable'])) {
    $id = $_POST['id'];
    $plugin = array_filter($plugins->getPlugins(), function ($plugin) use ($id) {
            return $plugin->id === $id;
        })[$id] ?? die(isset($_POST['format']) ? json_encode(['status' => false, 'message' => __('Plugin not found')]) : toastr(__('Plugin not found'))->error());

    try {
        if ($plugin->action->is_exist) Action::setDirectory($plugin->action->directory);

        if ($_POST['enable']) {
            $options = ['version' => $plugin->version];

            $query = DB::getInstance()->prepare('INSERT INTO plugins (id, path, options, created_at, deleted_at, uid) VALUES (:id, :path, :options, :created_at, :deleted_at, :uid)');
            if ($plugins->isActive($plugin->id))
                $query = DB::getInstance()->prepare('UPDATE `plugins` SET `path` = :path, `options` = :options, `updated_at` = :created_at, `deleted_at` = :deleted_at, `uid` = :uid WHERE `id` = :id');

            // run migration if available
            if ($plugin->migration->is_exist) {
                $options[Plugins::DATABASE_VERSION] = Runner::path($plugin->path)->setVersion($plugin->migration->{Plugins::DATABASE_VERSION})->runUp();
                $query->bindValue(':options', json_encode($options));
            } else {
                $query->bindValue(':options', null);
            }

            if ($plugin->action->is_exist) $action = Action::preEnable();

            $query->bindValue(':id', $id);
            $query->bindValue(':path', $plugin->path);
            $query->bindValue(':created_at', date('Y-m-d H:i:s'));
            $query->bindValue(':deleted_at', null);
            $query->bindValue(':uid', $_SESSION['uid']);
            $message = sprintf(__('Plugin %s enabled'), $plugin->name);
            $run = $query->execute();

            if ($plugin->action->is_exist) Action::postEnable();

        } else {
            if ($plugin->action->is_exist) $action = Action::preDisable();
            
            if ($plugin->migration->is_exist && !$_POST['runDown']) {
                $query = DB::getInstance()->prepare("UPDATE plugins SET deleted_at = :deleted_at WHERE id = :id");
                $query->bindValue('deleted_at', date('Y-m-d H:i:s'));
            } elseif ($plugin->migration->is_exist && $_POST['runDown']) {
                Runner::path($plugin->path)->setVersion($plugin->migration->{Plugins::DATABASE_VERSION})->runDown();
                $query = DB::getInstance()->prepare("DELETE FROM plugins WHERE id = :id");
            } else {
                $query = DB::getInstance()->prepare("DELETE FROM plugins WHERE id = :id");
            }
            $query->bindValue(':id', $id);
            $message = sprintf(__('Plugin %s disabled'), $plugin->name);
            $run = $query->execute();

            if ($plugin->action->is_exist) Action::postDisable();
        }

        if (!$run) $message = __('Something error : turn on development mode to get more information');

        if (isset($_POST['format'])) echo Json::stringify(['status' => (bool)$run, 'message' => $message])->withHeader();
        else toastr($message . ' 1')->{($run === false ? 'error' : 'success')};

    } catch (Exception $exception) {
        if (isset($_POST['format'])) echo Json::stringify(['status' => false, 'message' => $exception->getMessage()])->withHeader();
        else toastr($exception->getMessage() . ' 2')->error();
    }

    // redirect content
    if ($upload_success) {
        Jquery::raw('colorbox.close()');
        redirect()->simbioAJAX(AWB . 'modules/system/plugins.php');
    }
    exit();
}