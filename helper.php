<?php

/**
 *
 * Let's Encrypt SAN certificates for Postfix / Dovecot on Froxlor Control Panel
 *
 * Helper functions
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.20
 * @since       2020.11.26
 *
 */

/**
 * Run getSSL
 *
 * @param  string $domain
 * @param  string $sans
 * @return boolean
 */
function runGetSSL($domain, $sans = NULL)
{
    /* Configure getSSL main config */
    writeGetSSLMainConfig();

    if ($sans) {
        writeGetSSLConfig($domain, $sans);
    }

    /* Start getSSL */
    exec(GETSSL_BIN . (GETSSL_BIN_OPTIONS ? ' ' . GETSSL_BIN_OPTIONS : '') . ' -w ' . GETSSL_CONFIG_PATH . ' -d ' . $domain, $pOutput, $pExitCode);
    if (DEBUG) {
        logSyslog(LOG_DEBUG, "runGetSSL for $domain exit code: $pExitCode");
    }
    if (DEBUG_EXTENDED) {
        logSyslog(LOG_DEBUG, $pOutput);
    }

    return $pExitCode;
}

/**
 * checkInstall
 *
 * @return boolean
 */
function checkInstall()
{
    if (!file_exists(GETSSL_BIN)) {
        if (DEBUG) {
            logSyslog(LOG_DEBUG, 'getSSL not found, installing ...');
        }

        /* @see https://github.com/srvrco/getSSL#installation */
        file_put_contents(GETSSL_BIN, fopen(GETSSL_INSTALL, 'r'));
        chmod(GETSSL_BIN, 700);

        if (file_exists(GETSSL_BIN)) {
            if (DEBUG) {
                logSyslog(LOG_DEBUG, 'getSSL installed to ' . GETSSL_BIN);
            }

            /* updateServicesConfig: Postfix / Dovecot / Froxlor */
            updateServicesConfig();

            /* CronJob */
            updateCronJob();

            return true;
        } else {
            logSyslog(LOG_ERR, 'Error installing getSSL ... Aborting!');

            return false;
        }
    } else {
        return true;
    }
}

/**
 * getDBEmailHosts
 * Get active email hosts from Froxlor Control Panel database
 *
 * @return string
 */
function getDBEmailHosts()
{
    $mailHosts = '';

    $values = $GLOBALS['db']->query('SELECT domain FROM ' . TABLE_PANEL_DOMAINS .
        ' WHERE (deactivated = 0) ' .
        'AND (isemaildomain = 1) ' .
        'AND id IN (SELECT domainid FROM ' . TABLE_MAIL_USERS . ')');

    if ($values) {
        foreach ($values as $value) {
            if ($mailHosts <> '') {
                $mailHosts .= ',';
            }
            $mailHosts .= MAIL_HOST . '.' . $value['domain'];
        }
    }

    return $mailHosts;
}

/**
 * getSSLDomains
 * Get active LetsEncrypt domains from Froxlor Control Panel database
 *
 * @return string
 */
function getSSLDomains()
{
    $result = array();

    $domains = $GLOBALS['db']->query('SELECT id, domain, wwwserveralias FROM ' . TABLE_PANEL_DOMAINS .
        ' WHERE (deactivated = 0) ' .
        ' AND (email_only = 0) ' .
        ' AND (letsencrypt = 1) ' .
        ' AND (aliasdomain IS NULL) ' .
        ' AND (parentdomainid = 0) ' .
        ' ORDER BY domain'
    );

    if ($domains) {
        foreach ($domains as $domain) {
            $result[$domain['domain']] = '';

            if ($domain['wwwserveralias'] == 1) {
                $result[$domain['domain']] = 'www.' . $domain['domain'];
            }

            // Get all defined subdomains for current domain
            $subDomains = $GLOBALS['db']->query('SELECT id, domain, wwwserveralias FROM ' . TABLE_PANEL_DOMAINS .
                ' WHERE (deactivated = 0) ' .
                ' AND (email_only = 0) ' .
                ' AND (letsencrypt = 1) ' .
                ' AND (aliasdomain IS NULL) ' .
                ' AND (parentdomainid = ' . $domain['id'] . ')' .
                ' ORDER BY domain'
            );

            if (count($subDomains) > 0) {
                foreach ($subDomains as $subDomain) {
                    // WWW alias
                    if ($subDomain['wwwserveralias'] == 1) {
                        if (strpos($result[$domain['domain']], 'www.' . $subDomain['domain']) === false) {
                            if (isset($result[$domain['domain']])) {
                                $result[$domain['domain']] .= ',www.' . $subDomain['domain'];
                            } else {
                                $result[$domain['domain']] = 'www.' . $subDomain['domain'];
                            }
                        }
                    }

                    if (isset($result[$domain['domain']])) {
                        $result[$domain['domain']] .= ',';
                    }

                    if (isset($result[$domain['domain']])) {
                        $result[$domain['domain']] .= $subDomain['domain'];
                    } else {
                        $result[$domain['domain']] = $subDomain['domain'];
                    }
                }
            }
        }
    }

    return $result;
}

/**
 * updateSSLDomainCertificate
 * Update SSL Certificate in Froxlor Control Panel database
 *
 * @return integer
 */
function updateSSLDomainCertificate($domain)
{
    $result = -1;

    // Path where all certificates are stored
    $domainSSLPath = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . $domain;

    // Get domainID
    $domainId = $GLOBALS['db']->single('SELECT id FROM ' . TABLE_PANEL_DOMAINS . ' WHERE domain = :domain', array('domain' => $domain));
    if ($domainId) {
        // Delete subdomains in TABLE_PANEL_DOMAIN_SSL_SETTINGS because they are included as SANS
        $delSubdomains = $GLOBALS['db']->query('DELETE FROM ' . TABLE_PANEL_DOMAIN_SSL_SETTINGS . ' WHERE domainid IN ' .
            '(SELECT id FROM ' . TABLE_PANEL_DOMAINS . ' WHERE parentdomainid = :domainid)', array('domainid' => $domainId));

        if ((DEBUG) && ($delSubdomains > 0)) {
            logSyslog(LOG_DEBUG, 'updateSSLDomainCertificate: Deleted ' . $delSubdomains . ' of domain [' . $domain . ']');
        }

        // Make sure the certificate file exists
        if (file_exists($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.crt')) {
            // Read local certificate
            $sslCrt = file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.crt');

            // Certificate info
            $sslCrtInfo = openssl_x509_parse($sslCrt);

            // Check if a record exists for current domain
            $sslDomainId = $GLOBALS['db']->single('SELECT id FROM ' . TABLE_PANEL_DOMAIN_SSL_SETTINGS . ' WHERE domainid = :domainid', array('domainid' => $domainId));

            if ($sslDomainId) {
                // Check certificate changes
                $sslCrtDatabase = $GLOBALS['db']->single('SELECT ssl_cert_file FROM ' . TABLE_PANEL_DOMAIN_SSL_SETTINGS . ' WHERE id = :id', array('id' => $sslDomainId));
                if ($sslCrt != $sslCrtDatabase) {
                    // Update certificate in TABLE_PANEL_DOMAIN_SSL_SETTINGS
                    $certificate = $GLOBALS['db']->query('UPDATE ' . TABLE_PANEL_DOMAIN_SSL_SETTINGS . ' SET ssl_csr_file = :ssl_csr_file, ssl_cert_file = :ssl_cert_file, ssl_key_file = :ssl_key_file, ssl_ca_file = :ssl_ca_file, ssl_cert_chainfile = :ssl_cert_chainfile, ssl_fullchain_file = :ssl_fullchain_file, expirationdate = :expirationdate ' .
                        ' WHERE id = :id', array(
                            'id'                => $sslDomainId,
                            'ssl_csr_file'       => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.csr'),
                            'ssl_cert_file'      => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.crt'),
                            'ssl_key_file'       => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.key'),
                            'ssl_ca_file'        => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . 'chain.crt'),
                            'ssl_cert_chainfile' => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . 'chain.crt'),
                            'ssl_fullchain_file' => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . 'fullchain.crt'),
                            'expirationdate'     => date('Y-m-d H:i:s', $sslCrtInfo['validTo_time_t']),
                        )
                    );

                    $result = 0;
                    if (DEBUG) {
                        logSyslog(LOG_DEBUG, 'updateSSLDomainCertificate: Updated certificate for domain ' . $domain);
                    }
                } else {
                    $result = 1;
                    if (DEBUG) {
                        logSyslog(LOG_DEBUG, 'updateSSLDomainCertificate: Certificate for domain ' . $domain . ' is unchanged.');
                    }
                }
            } else {
                // Add certificate in TABLE_PANEL_DOMAIN_SSL_SETTINGS
                $certificate = $GLOBALS['db']->query('INSERT INTO ' . TABLE_PANEL_DOMAIN_SSL_SETTINGS . ' (domainid, ssl_csr_file, ssl_cert_file, ssl_key_file, ssl_ca_file, ssl_cert_chainfile, ssl_fullchain_file, expirationdate)' .
                    'VALUES (:domainid, :ssl_csr_file, :ssl_cert_file, :ssl_key_file, :ssl_ca_file, :ssl_cert_chainfile, :ssl_fullchain_file, :expirationdate)', array(
                        'domainid'           => $domainId,
                        'ssl_csr_file'       => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.csr'),
                        'ssl_cert_file'      => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.crt'),
                        'ssl_key_file'       => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . $domain . '.key'),
                        'ssl_ca_file'        => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . 'chain.crt'),
                        'ssl_cert_chainfile' => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . 'chain.crt'),
                        'ssl_fullchain_file' => file_get_contents($domainSSLPath . DIRECTORY_SEPARATOR . 'fullchain.crt'),
                        'expirationdate'     => date('Y-m-d H:i:s', $sslCrtInfo['validTo_time_t']),
                    )
                );

                $result = 0;
                if (DEBUG) {
                    logSyslog(LOG_DEBUG, 'updateSSLDomainCertificate: Added certificate for domain ' . $domain);
                }
            }
        } else {
            $result = -1;
            if (DEBUG) {
                logSyslog(LOG_DEBUG, 'updateSSLDomainCertificate: Missing certificate for domain ' . $domain);
            }
        }
    } else {
        $result = -10;
        if (DEBUG) {
            logSyslog(LOG_DEBUG, 'updateSSLDomainCertificate: Unknown domain ' . $domain);
        }
    }


    return $result;
}

/**
 * triggerCron
 * Trigger a cron task for Froxlor
 *
 * @param  integer $type
 * @param  string  $data
 * @return integer
 */
function triggerCron($type, $data = '')
{
    return $GLOBALS['db']->query('INSERT INTO ' . TABLE_PANEL_TASKS . ' (type, data) VALUES (:type, :data)', array('type' => $type, 'data' => $data));
}

/**
 * getDBSetting
 * Get setting value from Froxlor database
 *
 * @param  string $varname
 * @return string
 */
function getDBSetting($varname)
{
    $value = $GLOBALS['db']->query('SELECT value FROM ' . TABLE_PANEL_SETTINGS . ' WHERE varname = "' . $varname . '"');
    if ($value) {
        return $value[0]['value'];
    } else {
        return NULL;
    }
}

/**
 * setDBSetting
 * Set setting value from Froxlor database
 *
 * @param  string $varname
 * @param  string $value
 * @return string
 */
function setDBSetting($varname, $value)
{
    return $GLOBALS['db']->query('UPDATE ' . TABLE_PANEL_SETTINGS . ' SET value = "' . $value . '" WHERE varname = "' . $varname . '"');
}

/**
 * updateConfigValue
 *
 * @param  string $fileName
 * @param  string $key
 * @param  string $value
 * @return boolean
 */
function updateConfigValue($fileName, $key, $value)
{
    $pattern = '/^(' . $key . '\s=\s)(.*)$/m';
    $fileContent = file_get_contents($fileName);

    if (file_exists($fileName)) {
        if (preg_match($pattern, $fileContent)) {
            file_put_contents($fileName, preg_replace($pattern, '$1' . $value, $fileContent));
        }
    } else {
        return false;
    }
}

/**
 * Log message to syslog
 *
 * @param  int $priority
 * @param  string|array $message
 */
function logSyslog($priority, $message)
{
    openlog(DEBUG_LOG_IDENT, LOG_PID, LOG_LOCAL0);

    if (is_array($message)) {
        foreach ($message as $line) {
            syslog($priority, $line);
        }
    } else {
        syslog($priority, $message);
    }
    closelog();
}

/**
 * Update Postfix / Dovecot / Frolxor config
 */
function updateServicesConfig()
{
    $certFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . '.crt';
    $certKeyFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . '.key';
    $certChainFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . DIRECTORY_SEPARATOR . 'chain.crt';
    $certCAFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . DIRECTORY_SEPARATOR . 'fullchain.crt';

    /* Postfix */
    if (POSTFIX_UPDATE_CONFIG) {
        if (DEBUG) {
            logSyslog(LOG_DEBUG, 'Updating Postfix');
        }
        updateConfigValue(POSTFIX_CONFIG, 'smtpd_tls_cert_file', $certFile);
        updateConfigValue(POSTFIX_CONFIG, 'smtpd_tls_key_file', $certKeyFile);
        updateConfigValue(POSTFIX_CONFIG, 'smtpd_tls_CAfile', $certCAFile);
    }

    /* Dovecot */
    if (DOVECOT_UPDATE_CONFIG) {
        if (DEBUG) {
            logSyslog(LOG_DEBUG, 'Updating Dovecot');
        }
        updateConfigValue(DOVECOT_CONFIG, 'ssl_cert', '<' . $certFile);
        updateConfigValue(DOVECOT_CONFIG, 'ssl_key', '<' . $certKeyFile);
        updateConfigValue(DOVECOT_CONFIG, 'ssl_ca', '<' . $certCAFile);
    }

    /* Froxlor */
    if (FROXLOR_UPDATE_CONFIG) {
        if (DEBUG) {
            logSyslog(LOG_DEBUG, 'Updating Froxlor config');
        }

        // `Settings -> Froxlor VirtualHost settings -> Enable Let's Encrypt for the froxlor vhost`: No
        setDBSetting('le_froxlor_enabled', 0);

        // `Settings -> SSL settings`
        // Enable Let's Encrypt: Allow LE settings
        setDBSetting('leenabled', 1);
        // `Choose Let's Encrypt ACME implementation`: ACME v1 ()
        setDBSetting('leapiversion', 1);

        // Path to the SSL certificate / Keyfile / CertificateChainFile / CA certificate
        setDBSetting('ssl_cert_file', $certFile);
        setDBSetting('ssl_key_file', $certKeyFile);
        setDBSetting('ssl_cert_chainfile', $certChainFile);
        setDBSetting('ssl_ca_file', $certCAFile);

        // Trigger cron type '1: Rebuilding webserver-configuration'
        triggerCron(1);

        // Disable LE cron
        $GLOBALS['db']->query('UPDATE ' . TABLE_PANEL_CRONRUNS . ' SET isactive = 0 WHERE module = "froxlor/letsencrypt"');

        // Trigger cron type '99: Rebuilding cron.d file`
        triggerCron('99');
    }
}

/**
 * Update cron job
 */
function updateCronJob()
{
    if (CRON_CONFIG) {
        if (DEBUG) {
            logSyslog(LOG_DEBUG, 'Updating cron config');
        }

        file_put_contents(CRON_FILENAME, '#!/bin/sh

# Update Let\'s Encrypt Froxlor certificates
/usr/bin/php ' . realpath(dirname($_SERVER['SCRIPT_FILENAME'])) . DIRECTORY_SEPARATOR . basename($_SERVER['SCRIPT_FILENAME']) . " > /dev/null 2>&1\n");

        chmod(CRON_FILENAME, 755);
    }
}

/**
 * Write getSSL Main config
 */
function writeGetSSLMainConfig()
{
    /* Check getSSL config path */
    if (!file_exists(GETSSL_CONFIG_PATH)) {
        mkdir(GETSSL_CONFIG_PATH, 755);
    }

    /* Get letsencryptchallengepath from Froxlor Control Panel */
    $acmeChallengePath = getDBSetting('letsencryptchallengepath') .
        DIRECTORY_SEPARATOR . '.well-known' .
        DIRECTORY_SEPARATOR . 'acme-challenge' .
        DIRECTORY_SEPARATOR;

    file_put_contents(GETSSL_CONFIG, '
# CA Server
CA="' . LETSENCRYPT_CA . '"

# The agreement that must be signed with the CA
AGREEMENT="' . LETSENCRYPT_AGREEMENT . '"

# Let\'s Encrypt email account
ACCOUNT_EMAIL="' . LETSENCRYPT_EMAIL . '"
ACCOUNT_KEY_LENGTH=4096
ACCOUNT_KEY="' . GETSSL_ACCOUNT_KEY . '"
ACCOUNT_KEY_TYPE="rsa"
PRIVATE_KEY_ALG="rsa"
REUSE_PRIVATE_KEY="true"

# The time period within which you want to allow renewal of a certificate
RENEW_ALLOW="' . LETSENCRYPT_ALLOW_RENEW_DAYS . '"

# ACME Challenge Location
ACL=(\'' . $acmeChallengePath . '\')
USE_SINGLE_ACL="true"
');

}

/**
 * Write getSSL config
 *
 * @param  string $domain
 * @param  string $sans
 */
function writeGetSSLConfig($domain, $sans)
{
    /* Check getSSL config path */
    if (!file_exists(GETSSL_CONFIG_PATH)) {
        mkdir(GETSSL_CONFIG_PATH, 755);
    }

    /* Check getSSL domain config path */
    if (!file_exists(GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . $domain)) {
        mkdir(GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . $domain, 755);
    }

    file_put_contents(GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . $domain . DIRECTORY_SEPARATOR . 'getssl.cfg', '# SANs
SANS="' . $sans . '"
');

}
