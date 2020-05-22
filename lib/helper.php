<?php

/**
 * Helper functions
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.00
 * @since       2020.05.17
 *
 */

/*
 * Run getSSL
 */
function runGetSSLConfig($sans)
{
    if ($sans) {
        /* Rewrite getSSL config with current SANs */
        writeGetSSLConfig($sans);

        /* Start getSSL */
        exec(GETSSL_BIN . (GETSSL_BIN_OPTIONS ? ' ' . GETSSL_BIN_OPTIONS : '') . ' -w ' . GETSSL_CONFIG_PATH . ' -d ' . GETSSL_MAIN_DOMAIN, $pOutput, $pExitCode);
        if (DEBUG) {
            print("Exit code: $pExitCode\n");
            print("Output: " . print_r($pOutput, true) . "\n");
        }
        return $pExitCode;
    } else {
        return false;
    }
}

/*
 * Check install
 */
function checkInstall()
{
    if (!file_exists(GETSSL_BIN)) {
        if (DEBUG) {
            print("getSSL not found, installing ...\n");
        }

        /* @see https://github.com/srvrco/getSSL#installation */
        file_put_contents(GETSSL_BIN, fopen(GETSSL_INSTALL, 'r'));
        chmod(GETSSL_BIN, 700);

        if (file_exists(GETSSL_BIN)) {
            if (DEBUG) {
                print("getSSL installed to " . GETSSL_BIN . " ...\n");
            }

            /* updateMailSSLConfig: Postfix / Dovecot */
            updateMailSSLConfig();

            /* cron.daily */
            updateDailyCronJob();

            return true;
        } else {
            print("Error installing getSSL ... Aborting!\n");

            return false;
        }
    } else {
        return true;
    }
}

/*
 * Get active email hosts from Froxlor Control Panel database
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

/*
 * Get setting value from Froxlor database
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

/*
 * Update config value
 * `key = old_value` will be replaced by `key = value`
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

/*
 * Update Postfix / Dovecot SSL config
 */
function updateMailSSLConfig()
{
    $certFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_MAIN_DOMAIN . DIRECTORY_SEPARATOR . GETSSL_MAIN_DOMAIN . '.crt';
    $certKeyFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_MAIN_DOMAIN . DIRECTORY_SEPARATOR . GETSSL_MAIN_DOMAIN . '.key';
    $certCAFile = GETSSL_CONFIG_PATH . DIRECTORY_SEPARATOR . GETSSL_MAIN_DOMAIN . DIRECTORY_SEPARATOR . 'fullchain.crt';

    /* Postfix */
    if (POSTFIX_UPDATE_CONFIG) {
        if (DEBUG) {
            print("Updating Postfix\n");
        }
        updateConfigValue(POSTFIX_CONFIG, 'smtpd_tls_cert_file', $certFile);
        updateConfigValue(POSTFIX_CONFIG, 'smtpd_tls_key_file', $certKeyFile);
        updateConfigValue(POSTFIX_CONFIG, 'smtpd_tls_CAfile', $certCAFile);
    }

    /* Dovecot */
    if (DOVECOT_UPDATE_CONFIG) {
        if (DEBUG) {
            print("Updating Dovecot\n");
        }
        updateConfigValue(DOVECOT_CONFIG, 'ssl_cert', '<' . $certFile);
        updateConfigValue(DOVECOT_CONFIG, 'ssl_key', '<' . $certKeyFile);
        updateConfigValue(DOVECOT_CONFIG, 'ssl_ca', '<' . $certCAFile);
    }
}

/*
 * Update daily cron job
 */
function updateDailyCronJob()
{
    if (CRON_DAILY_CONFIG) {
        if (DEBUG) {
            print("Updating cron.daily\n");
        }

        file_put_contents(CRON_DAILY_FILENAME, '#!/bin/sh

# Update Let\'s Encrypt SAN certificates for Postfix / Dovecot
/usr/bin/php ' . realpath(dirname($_SERVER['SCRIPT_FILENAME'])) . DIRECTORY_SEPARATOR . basename($_SERVER['SCRIPT_FILENAME']) . " > /dev/null 2>&1\n");

        chmod(CRON_DAILY_FILENAME, 755);
    }
}

/*
 * Write getSSL config
 */
function writeGetSSLConfig($sans)
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

# Domains
SANS="' . $sans . '"
');

}
