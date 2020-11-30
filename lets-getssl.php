<?php

/**
 * Let's Encrypt SAN certificates for Postfix / Dovecot on Froxlor Control Panel
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.20
 * @since       2020.11.30
 *
 */

// Config
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php');

try {
    // Database connection
    $db = new Db($sql['host'], $sql['db'], $sql['user'], $sql['password']);

    // Check requirements
    if (checkInstall()) {
        // Generate a single SSL certificate for all email hosts with an active email accounts (including GETSSL_HOSTNAME)
        runGetSSL(GETSSL_HOSTNAME, getDBEmailHosts($db));

        // Check if certificate was updated in last minute, run 'RELOAD_CMD'
        if ((time() - filemtime(GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . '.crt')) <= 60) {
            exec(RELOAD_CMD);
        }

        $triggerCron = false;
        // Generate certificates for defined LetsEncrypt domains
        $domains = getSSLDomains();
        if ($domains) {
            foreach ($domains as $domain => $sans) {
                if (runGetSSL($domain, $sans) >= 0)  {
                    // Update domain certificate in database
                    $result = updateSSLDomainCertificate($domain);
                    if (($result == 0) && ($triggerCron == false)) {
                        $triggerCron = true;
                    }
                }
            }
        }
        // Trigger cron type '1: Rebuilding webserver-configuration' if there are changes
        if ($triggerCron) {
            triggerCron(1);
        }
    }
} catch (PDOException $e) {
    logSyslog(LOG_ERR, 'Error connecting to Control Panel database!');
}

?>
