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
 * @version     1.00
 * @since       2020.05.26
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
        logSyslog(LOG_DEBUG, "Exit code: $pExitCode");
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

            /* updateMailSSLConfig: Postfix / Dovecot */
            updateMailSSLConfig();

            /* cron.daily */
            updateDailyCronJob();

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
        ' AND (letsencrypt = 1) ' .
        ' AND (parentdomainid = 0)'
    );

    if ($domains) {
        foreach ($domains as $domain) {
            if ($domain['wwwserveralias'] == 1) {
                $result[$domain['domain']] = 'www.' . $domain['domain'];
            }

            // Get all defined subdomains for current domain
            $subDomains = $GLOBALS['db']->query('SELECT id, domain, wwwserveralias FROM ' . TABLE_PANEL_DOMAINS .
                ' WHERE (deactivated = 0) ' .
                ' AND (letsencrypt = 1) ' .
                ' AND (parentdomainid = ' . $domain['id'] . ')'
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
            } else {
                $result[$domain['domain']] = '';
            }

        }
    }

    return $result;
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
 * Update Postfix / Dovecot SSL config
 */
function updateMailSSLConfig()
{
    $certFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . '.crt';
    $certKeyFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . DIRECTORY_SEPARATOR . GETSSL_HOSTNAME . '.key';
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
}

/**
 * Update daily cron job
 */
function updateDailyCronJob()
{
    if (CRON_DAILY_CONFIG) {
        if (DEBUG) {
            logSyslog(LOG_DEBUG, 'Updating cron.daily');
        }

        file_put_contents(CRON_DAILY_FILENAME, '#!/bin/sh

# Update Let\'s Encrypt Froxlor certificates
/usr/bin/php ' . realpath(dirname($_SERVER['SCRIPT_FILENAME'])) . DIRECTORY_SEPARATOR . basename($_SERVER['SCRIPT_FILENAME']) . " > /dev/null 2>&1\n");

        chmod(CRON_DAILY_FILENAME, 755);
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

# The command needed to reload apache / nginx or whatever you use
RELOAD_CMD="' . RELOAD_CMD . '"

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
