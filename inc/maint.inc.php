<?php

if (!preg_match('/c[lg]i/i', php_sapi_name())) {
	exit('CGI or CLI only.');
}

require_once('config.inc.php');

error_reporting(E_ALL);
mt_srand();

header('Content-Type: text/plain');

$maint_link = null;

function randomStr($len)
{
	static $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	$max = strlen($chars) - 1;
	$str = '';
	for ($i = 0; $i < $len; ++$i) {
		$str .= $chars[mt_rand(0, $max)];
	}
	return $str;
}

function getVar($game, $name)
{
	global $maint_link;
	$escaped = mysqli_real_escape_string($maint_link, $name);
	$result = mysqli_query($maint_link, "SELECT `value` FROM `{$game}_db_vars` WHERE `name`='$escaped'");
	if (!$result) {
		return false;
	}
	$row = mysqli_fetch_row($result);
	return $row ? $row[0] : false;
}

$maint_link = @mysqli_connect(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE);
if (!$maint_link) {
	exit('Could not connect to database: ' . mysqli_connect_error());
}

?>
