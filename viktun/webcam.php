<?php
/**
 * Viktun webcam — thin wrapper around ../webcam.php
 *
 * Sets camera-specific constants before loading the shared webcam engine.
 * Images live in webcam/viktun/YYYY/MM/DD/YYYYMMDDHHMMSS.jpg
 */

define('CAM_LABEL',           'Viktun webcam');
define('CAM_FILE_PREFIX',     'Viktun_01_');
define('CAM_FILE_PREFIX_ALT', '');
define('CAM_IS_PRIMARY',      false);
define('CAM_SHOW_PEOPLE',     true);
define('CAM_CSS_PATH',        '../css.php');
define('CAM_INTRO_HTML',      '<a href=".">Webcam</a> at Viktun. See also: <a href="../">Lillevik webcam</a>.');

require_once __DIR__ . '/../webcam.php';
