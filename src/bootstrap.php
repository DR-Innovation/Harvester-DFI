<?php
// Setting the error reporting level.
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
libxml_use_internal_errors(true);

// Setting the include path form a the enrironment.
// This environment variable should be a list of colon-seperated paths.
if(!isset($_SERVER['INCLUDE_PATH'])) {
	print("The INCLUDE_PATH env parameter must be set.\n");
	exit(1);
} else {
	set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['INCLUDE_PATH']);
}

// Enables casesensitive autoloading of classes.
// This is needed by the CHAOS SDK.
require_once("CaseSensitiveAutoload.php");
spl_autoload_extensions(".php");
spl_autoload_register("CaseSensitiveAutoload");

?>