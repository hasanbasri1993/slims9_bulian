SLiMS 9 Bulian
===============
SENAYAN Library Management System (SLiMS) version 9 Codename Bulian

SLiMS is free open source software for library resources management
(such as books, journals, digital document and other library materials)
and administration such as collection circulation, collection management,
membership, stock taking and many other else.

SLiMS is licensed under GNU GPL version 3. Please read "GPL-3.0 License.txt"
to learn more about GPL.

### System Requirements
- PHP version 7.4;
- MySQL version 5.7 and or MariaDB version 10.3;
- PHP GD enabled
- PHP gettext enabled
- PHP mbstring enabled

### Set ENV

Add bottom .htacces
```
<IfModule mod_env.c>
SetEnv servername xxxxxx
SetEnv username xxxxxx
SetEnv password xxxxxx
SetEnv dbname xxxxxx
SetEnv DB_HOST xxxxxx
SetEnv DB_PORT xxxxxx
SetEnv DB_NAME xxxxxx
SetEnv DB_USERNAME xxxxxx
SetEnv DB_PASSWORD xxxxxx
</IfModule>
```