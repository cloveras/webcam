<?php
/**
 * Viktun people gallery — thin wrapper around ../people.php
 */

define('PEOPLE_LABEL',       'Viktun webcam');
define('PEOPLE_CSS_PATH',    '../css.php');
define('PEOPLE_INTRO_HTML',  '<a href=".">Webcam</a> at Viktun. See also: <a href="../people.php">Lillevik people</a>.');
define('PEOPLE_SHOW_AURORA', false);
define('PEOPLE_DATA_DIR',    __DIR__ . '/data');

require_once __DIR__ . '/../people.php';
