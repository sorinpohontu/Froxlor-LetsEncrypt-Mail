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
 * Get active email hosts from Froxlor Control Panel database
 */
function getEmailHosts()
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
 * Write getSSL config
 */
function writeGetSSLConfig($sans)
{
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
ACL=(\'' . CONTROL_PANEL_ACME_CHALLENGE . '\')
USE_SINGLE_ACL="true"

# Domains
SANS="' . $sans . '"
');

}
