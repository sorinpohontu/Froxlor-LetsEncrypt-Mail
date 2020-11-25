<?php

/**
 * Let's Encrypt SAN certificates for Postfix / Dovecot on Froxlor Control Panel
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.00
 * @since       2020.05.22
 *
 */

/* Config */
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php');

/* Check if tools are installed */
if (checkInstall()) {
    try {
        /* Database connection */
        $db = new Db($sql['host'], $sql['db'], $sql['user'], $sql['password']);

        /* Generate a single SSL certificate for all email hosts with an active email accounts (including GETSSL_HOSTNAME) */
        // runGetSSL(GETSSL_HOSTNAME, getDBEmailHosts($db));

        /* Generate SSL certificates for all defined SSL Domains */
        $domains = getSSLDomains();
        if ($domains) {
            foreach ($domains as $domain => $sans) {
                if (runGetSSL($domain, $sans) == 0) {
                    //
                    updateSSLDomainCertificate($domain);
                }
            }
        }
    } catch (PDOException $e) {
        logSyslog(LOG_ERR, 'Error connecting to Control Panel database!');
    }
}

?>
