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

define('DEBUG', true);

/* First domain is fqdn hostname */
define('GETSSL_MAIN_DOMAIN', trim(`hostname --fqdn`));

define('GETSSL_INSTALL', 'https://raw.githubusercontent.com/srvrco/getssl/master/getssl');

define('GETSSL_BIN', '/usr/local/bin/getssl');
define('GETSSL_BIN_OPTIONS', '');
define('GETSSL_CONFIG_PATH', '/etc/ssl/mail');
define('GETSSL_CONFIG', GETSSL_CONFIG_PATH . '/getssl.cfg');
define('GETSSL_ACCOUNT_KEY', GETSSL_CONFIG_PATH . '/account.key');

define('LETSENCRYPT_CA', 'https://acme-v02.api.letsencrypt.org');
define('LETSENCRYPT_AGREEMENT', 'https://letsencrypt.org/documents/LE-SA-v1.2-November-15-2017.pdf');
define('LETSENCRYPT_EMAIL', 'support@' . trim(`hostname -d`));
define('LETSENCRYPT_ALLOW_RENEW_DAYS', 60);

define('CONTROL_PANEL_PATH', '/var/www/froxlor');
define('CONTROL_PANEL_ACME_CHALLENGE', '/var/www/letsencrypt/.well-known/acme-challenge/');

define('MAIL_HOST', 'mail');

define('RELOAD_CMD', 'service dovecot restart && service postfix restart');

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
