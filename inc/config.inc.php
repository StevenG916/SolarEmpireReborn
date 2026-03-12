<?php

// Database config — reads from environment variables if set (Docker), falls back to defaults
define("DATABASE_HOST",     getenv('DB_HOST')     ?: "db");
define("DATABASE",          getenv('DB_NAME')     ?: "solarempire");
define("DATABASE_USER",     getenv('DB_USER')     ?: "solarempire");
define("DATABASE_PASSWORD", getenv('DB_PASSWORD') ?: "solarempire");

// Send authorisation mail. Set to 1 to send, 0 not to send.
define("SENDMail", getenv('SEND_MAIL') ? (int)getenv('SEND_MAIL') : 0);

// Whatever you want to call the server
define("SERVER_NAME", getenv('SERVER_NAME') ?: "My Solar Empire Server");

if (isset($_SERVER['HTTP_HOST'])) {
	define('URL_SHORT', dirname($_SERVER['SCRIPT_NAME']));
	define('URL_FULL', 'http://' . $_SERVER['HTTP_HOST'] . URL_SHORT);
}

$code_base = 'Generic SE 2.9.1';

// Length of time a user may be inactive before auto logout. In seconds.
define("SESSION_TIME_LIMIT", 3600);

define("ADMIN_ID", 1);
define("OWNER_ID", 1);

// PHP errors
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));

?>
