<?php
/**
 * Viktun people gallery — thin wrapper around ../people.php
 */

// Password protection
require_once __DIR__ . '/auth.php';
if (!isset($_SERVER['PHP_AUTH_USER']) ||
    $_SERVER['PHP_AUTH_USER'] !== VIKTUN_USER ||
    $_SERVER['PHP_AUTH_PW']   !== VIKTUN_PASS) {
    header('WWW-Authenticate: Basic realm="Viktun webcam"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Access denied.');
}

define('PEOPLE_LABEL',       'Viktun webcam');
define('PEOPLE_CSS_PATH',    '../css.php');
define('PEOPLE_INTRO_HTML',  '<a href=".">Webcam</a> at Viktun. See also: <a href="../people.php">Lillevik people</a>.');
define('PEOPLE_SHOW_AURORA', false);
define('PEOPLE_DATA_DIR',    __DIR__ . '/data');

require_once __DIR__ . '/../people.php';
