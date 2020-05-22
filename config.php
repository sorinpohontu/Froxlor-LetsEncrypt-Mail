<?php

/**
 * Configuration
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.10
 * @since       2020.05.20
 *
 */

/*
 * Note that all Paths are without trailing slash (/)
 */

/* Froxlor Control Panel configuration */
define('CONTROL_PANEL_PATH', '/var/www/froxlor');

/* Main domain is machine FQDN */
define('GETSSL_MAIN_DOMAIN', trim(`hostname --fqdn`));
define('GETSSL_BIN', '/usr/local/bin/getssl');
define('GETSSL_BIN_OPTIONS', '-u');
define('GETSSL_CONFIG_PATH', '/etc/ssl/mail');
define('GETSSL_CONFIG', GETSSL_CONFIG_PATH . '/getssl.cfg');
define('GETSSL_ACCOUNT_KEY', GETSSL_CONFIG_PATH . '/account.key');
define('GETSSL_INSTALL', 'https://raw.githubusercontent.com/srvrco/getssl/master/getssl');

define('LETSENCRYPT_CA', 'https://acme-v02.api.letsencrypt.org');
define('LETSENCRYPT_AGREEMENT', 'https://letsencrypt.org/documents/LE-SA-v1.2-November-15-2017.pdf');
define('LETSENCRYPT_EMAIL', 'support@' . trim(`hostname -d`));
define('LETSENCRYPT_ALLOW_RENEW_DAYS', 60);

define('MAIL_HOST', 'mail');

define('RELOAD_CMD', 'service dovecot restart && service postfix restart');

define('POSTFIX_UPDATE_CONFIG', true);
define('POSTFIX_CONFIG', '/etc/postfix/main.cf');

define('DOVECOT_UPDATE_CONFIG', true);
define('DOVECOT_CONFIG', '/etc/dovecot/conf.d/10-ssl.conf');

define('CRON_DAILY_CONFIG', true);
define('CRON_DAILY_FILENAME', '/etc/cron.daily/lets-encrypt-mail-san');

/* Show/hide debug info */
define('DEBUG', true);

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
