<?php

/**
 * Configuration
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.00
 * @since       2020.05.17
 *
 */

/*
 * Note that all Paths are without trailing slash (/)
 */

define('GETSSL_DIR', '/etc/ssl/mail');
define('LETSENCRYPT_CA', 'https://acme-v02.api.letsencrypt.org');
define('LETSENCRYPT_AGREEMENT', 'CA');
define('LETSENCRYPT_EMAIL', 'support@' . `hostname -d`);

define('CONTROL_PANEL_PATH', '/var/www/froxlor');

/* Database helper class */
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib/Db.class.php');

/* Helper functions */
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib/helper.php');

/* Froxlor Control Panel config files */
require_once (CONTROL_PANEL_PATH . DIRECTORY_SEPARATOR . 'lib/userdata.inc.php');
require_once (CONTROL_PANEL_PATH . DIRECTORY_SEPARATOR . 'lib/tables.inc.php');

/* Global database connection */
global $db;

?>
