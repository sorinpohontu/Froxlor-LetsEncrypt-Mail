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

/* Check getSSL */
if (!file_exists(GETSSL_BIN)) {
    print("getSSL not found, installing ...\n");

    /* @see https://github.com/srvrco/getSSL#installation */
    file_put_contents(GETSSL_BIN, fopen(GETSSL_INSTALL, 'r'));
    chmod(GETSSL_BIN, 700);
    if (file_exists(GETSSL_BIN)) {
        print("getSSL installed to " . GETSSL_BIN . " ...\n");

        /* Create getSSL config path */
        mkdir(GETSSL_CONFIG_PATH, 755);
    } else {
        print("Error installing getSSL ... Aborting!\n");
        exit;
    }
}

try {
    /* Database connection */
    $db = new Db($sql['host'], $sql['db'], $sql['user'], $sql['password']);

    $sans = getEmailHosts($db);
    if ($sans) {
        /* Rewrite getSSL config with current SANs */
        writeGetSSLConfig($sans);

        /* Start getSSL */
        exec(GETSSL_BIN . GETSSL_BIN_OPTIONS . ' -w ' . GETSSL_CONFIG_PATH . ' -d ' . GETSSL_MAIN_DOMAIN, $pOutput, $pExitCode);
        if (DEBUG) {
            print("Exit code: $pExitCode\n");
            print("Output: " . print_r($pOutput, true) . "\n");
        }
    }
} catch (PDOException $e) {
    print("Error connecting to Control Panel database!\n");
}

?>
