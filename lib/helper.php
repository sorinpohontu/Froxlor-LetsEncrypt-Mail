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
 * Get active email domains from Froxlor Control Panel database
 */
function getEmailDomains()
{
    $domains = array();

    $values = $GLOBALS['db']->query('SELECT domain FROM ' . TABLE_PANEL_DOMAINS .
        ' WHERE (deactivated = 0) ' .
        'AND (isemaildomain = 1) ' .
        'AND id IN (SELECT domainid FROM ' . TABLE_MAIL_USERS . ')');

    if ($values) {
        foreach ($values as $value) {
            $domains[] = $value['domain'];
        }
    }

    return $domains;
}
