<?php

/**
 * Lets Encrypt SAN certificates for Postfix / Dovecot on Froxlor Control Panel
 *
 * @author      Sorin Pohontu <sorin@frontline.ro>
 * @copyright   2020 Frontline softworks <http://www.frontline.ro>
 *
 * @version     1.00
 * @since       2020.05.17
 *
 */

/* Config */
require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php');

/* Database connection */
try {
    $db = new Db($sql['host'], $sql['db'], $sql['user'], $sql['password']);
} catch (PDOException $e) {
    print('Error connecting to Control Panel database!');
}

if ($db) {
    $domains = getEmailDomains($db);
    if ($domains) {

    }
} else {
    print('Error connecting to Control Panel database!');
}

?>
