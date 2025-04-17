<?php
// -----------------------------------------------------------------------
// DEFINE SEPERATOR ALIASES
// -----------------------------------------------------------------------
define("URL_SEPARATOR", '/');

define("DS", DIRECTORY_SEPARATOR);

$directorioPadre = dirname(__DIR__);
// -----------------------------------------------------------------------
// DEFINE ROOT PATHS
// -----------------------------------------------------------------------
defined('SITE_ROOT') ? null : define('SITE_ROOT', realpath(dirname(__FILE__)));
define("LIB_PATH_INC", SITE_ROOT . DS);
define('BASE_URL', '/franke/'); // o '/' si está en raíz


require_once(LIB_PATH_INC . 'db.php');
require_once(LIB_PATH_INC . 'functions.php');
require_once(LIB_PATH_INC . 'auth.php');
require_once(LIB_PATH_INC . 'permissions.php');
require_once(LIB_PATH_INC . 'sql.php');
require_once(LIB_PATH_INC . 'session.php');



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
