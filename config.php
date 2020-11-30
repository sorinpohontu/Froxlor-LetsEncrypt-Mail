<?php

/**
 *
 * Let's Encrypt SAN certificates for Postfix / Dovecot on Froxlor Control Panel
 *
 * Configuration
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.20
 * @since       2020.11.26
 *
 */

/* Froxlor Control Panel configuration */
define('CONTROL_PANEL_PATH', '/var/www/froxlor');

/* Main domain is machine FQDN */
define('GETSSL_HOSTNAME', trim(`hostname --fqdn`));
define('GETSSL_BIN', '/usr/local/bin/getssl');
define('GETSSL_BIN_OPTIONS', '-q -u');
define('GETSSL_CONFIG_PATH', '/etc/ssl/letsencrypt');
define('GETSSL_CONFIG', GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . 'getssl.cfg');
define('GETSSL_ACCOUNT_KEY', GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . 'account.key');
define('GETSSL_INSTALL', 'https://raw.githubusercontent.com/srvrco/getssl/master/getssl');

define('LETSENCRYPT_CA', 'https://acme-v02.api.letsencrypt.org');
define('LETSENCRYPT_AGREEMENT', 'https://letsencrypt.org/documents/LE-SA-v1.2-November-15-2017.pdf');
define('LETSENCRYPT_EMAIL', 'support@' . trim(`hostname -d`));
define('LETSENCRYPT_ALLOW_RENEW_DAYS', 60);

define('MAIL_HOST', 'mail');

define('RELOAD_CMD', 'service dovecot stop && service postfix stop && service postfix start && service dovecot start');

define('POSTFIX_UPDATE_CONFIG', true);
define('POSTFIX_CONFIG', '/etc/postfix/main.cf');

define('DOVECOT_UPDATE_CONFIG', true);
define('DOVECOT_CONFIG', '/etc/dovecot/conf.d/10-ssl.conf');

define('CRON_DAILY_CONFIG', true);
define('CRON_DAILY_FILENAME', '/etc/cron.daily/lets-encrypt-mail-san');

/* Show/hide debug info */
define('DEBUG', true);
define('DEBUG_EXTENDED', false);
define('DEBUG_LOG_IDENT', 'getssl-froxlor');

/* Database helper class */
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib/Db.class.php');

/* Helper functions */
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helper.php');

/* Froxlor Control Panel config files */
require_once (CONTROL_PANEL_PATH . DIRECTORY_SEPARATOR . 'lib/userdata.inc.php');
require_once (CONTROL_PANEL_PATH . DIRECTORY_SEPARATOR . 'lib/tables.inc.php');

/* Global database connection */
global $db;

?>
