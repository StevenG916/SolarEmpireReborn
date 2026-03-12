<?php

mt_srand();

require_once('config.inc.php');

$inputArray = array_map(function($input) {
	return str_replace(array('"', '\''), '', $input);
}, array_merge($_GET, $_POST, $_REQUEST, $_COOKIE));
extract($inputArray);

//initial declarations for certain global vars
$db_name = "";
$p_user = array();
$game_info = array();


/**********************
Page Display Functions
***********************/

function print_header($title)
{
	global $user_options, $directories;

	$style = esc(URL_SHORT . '/css/style' . (isset($user_options['color_scheme']) ?
	 $user_options['color_scheme'] : 1) . '.css');
	$title = esc($title);
	$js = esc(URL_SHORT . '/js/common.js');

	print <<<END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>$title &laquo; Solar Empire</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="stylesheet" href="$style" />
<script type="text/javascript" src="$js"></script>
</head>
<body>

END;
}

function print_footer()
{
	print <<<END

</body>
</html>
END;
}


/**********************
Input Checking Functions
**********************/

function valid_input($input)
{
	return preg_match('/^[a-z0-9~@$%&*_+-=£§¥²³µ¶]+$/i', $input);
}

function correct_name($input)
{
	$input = htmlspecialchars(strip_tags($input));
	return trim(preg_replace('/[^a-z0-9~@$%&*_+-=£§¥²³µ¶ .]/i', '', $input));
}

function recursive_stripslashes(&$var) {
	foreach ($var AS $key => $value) {
		if (is_array($value)) {
			recursive_stripslashes($value);
		} else {
			$var[$key] = stripslashes($value);
		}
	}
}


/**********************
* Database Functions (mysqli)
**********************/

function dbDie()
{
	global $database_link;
	headers_sent() or header('Content-Type: text/plain');
	$error = $database_link ? mysqli_error($database_link) : 'No connection';
	print "ERROR!\n" . $error . "\nBACKTRACE\n";
	var_dump(debug_backtrace());
	exit();
}

function db_connect()
{
	global $database_link;
	$database_link = @mysqli_connect(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE) or
	 die("No connection to the Database could be created.<p>The following error was reported:<br><b>" . mysqli_connect_error() . "</b>");
}

function db($string)
{
	global $db_func_query, $database_link;
	$db_func_query = mysqli_query($database_link, $string);
	if (!$db_func_query) dbDie();
}

function dbr($rest_type = 0)
{
	global $db_func_query;
	if ($rest_type == 0) {
		return mysqli_fetch_array($db_func_query, MYSQLI_BOTH);
	} else {
		return mysqli_fetch_assoc($db_func_query);
	}
}

function db2($string)
{
	global $db_func_query2, $database_link;
	$db_func_query2 = mysqli_query($database_link, $string);
	if (!$db_func_query2) dbDie();
}

function dbr2($rest_type = 0)
{
	global $db_func_query2;
	if ($rest_type == 0) {
		return mysqli_fetch_array($db_func_query2, MYSQLI_BOTH);
	} else {
		return mysqli_fetch_assoc($db_func_query2);
	}
}

function dbn($string)
{
	global $database_link;
	mysqli_query($database_link, $string) or dbDie();
}

// Convenience: get last insert id
function db_insert_id()
{
	global $database_link;
	return mysqli_insert_id($database_link);
}

// Escape a string for safe use in queries
function db_escape($string)
{
	global $database_link;
	return mysqli_real_escape_string($database_link, $string);
}


/**********************
HTML Table Functions
***********************/

function make_table($input, $width = "") {
	$ret_str = "<table cellspacing=1 cellpadding=2 border=0 $width><tr bgcolor=#555555>";
	foreach ($input as $value) {
		$ret_str .= "\n<td>$value</td>";
	}
	return $ret_str . "\n</tr>";
}

function make_row($input) {
	$ret_str = "\n<tr bgcolor=#333333 align=left>";
	foreach ($input as $value) {
		if ((ord($value) < 48) || (ord($value) > 57)) {
			$ret_str .= "\n<td>$value</td>";
		} else {
			$ret_str .= "\n<td><b>$value</b></td>";
		}
	}
	return $ret_str . "\n</tr>";
}

$msgColours = array(
	'blue'   => '0000FF',
	'lime'   => '00FF00',
	'green'  => '00CC00',
	'red'    => 'FF0000',
	'black'  => '000000',
	'white'  => 'FFFFFF',
	'yellow' => 'FFFF00',
	'cyan'   => '00FFFF',
	'pink'   => 'FF00FF',
	'purple' => 'CC66CC',
	'orange' => 'FFCC00'
);

$colImplode = implode('|', array_keys($msgColours));

$smileTypes = array(
	'happy', 'mad', 'sad', 'surp', 'tongue', 'wink', 'oh',
	'unsure', 'cool', 'laugh', 'blush', 'sealed'
);
$smileSets = array('', 'war', 'cow', 'pirate', 'evil');

$smTImplode = implode('|', $smileTypes);
$smSImplode = implode('|', $smileSets);

function mcit($text)
{
	global $msgColours, $colImplode;

	$text = preg_replace('/\[link ?\'(.*?)\']([\S\s]*?)\[\/link\]/', '<a href="\1" target="_blank">\2</a>',
	preg_replace_callback('/\[color \'(' . $colImplode . ')\'\]([\S\s]*?)\[\/color\]/i',
		function($m) use ($msgColours) {
			return '<span style="color: #' . $msgColours[$m[1]] . ';">' . $m[2] . '</span>';
		},
	preg_replace('/\[color \'(#[0-9A-F]{6})\'\]([\S\s]*?)\[\/color\]/i', '<span style="color: \1;">\2</span>',
	preg_replace('/\[b\]([\S\s]*?)\[\/b\]/i', '<b>\1</b>',
	preg_replace('/\[i\]([\S\s]*?)\[\/i\]/i', '<i>\1</i>',
	preg_replace('/\[hr\]/i', '<hr />',
	preg_replace('/[^ \n]{128,}/', '<strong>Attempted spam!</strong> ',
	preg_replace_callback('/(\n+)/', function($m) {
		return str_repeat("<br />", strlen($m[1]) === 1 ? 1 : 2) . "\n";
	},
	str_replace("\r", '',
	htmlentities(trim($text)))))))))));

	return $text;
}

function quick_row($name, $value)
{
	return "\t<tr align=\"left\">\n\t\t<td bgcolor=\"#555555\">$name</td>\n" .
	 "\t\t<td bgcolor=\"#333333\">$value</td>\n\t</tr>\n";
}


/**********
Data update/insertion Functions
**********/

function insert_history($l_id, $i_text)
{
	global $db_name, $database_link;

	if (empty($db_name)) {
		$db_name = "None";
	}

	dbn('INSERT INTO user_history VALUES (' . (int)$l_id . ', ' . time() .
	 ', \'' . db_escape($db_name) . '\', \'' .
	 db_escape($i_text) . '\', \'' .
	 db_escape($_SERVER['REMOTE_ADDR']) . '\', \'' .
	 db_escape($_SERVER['HTTP_USER_AGENT']) . '\')');
}

function post_news($headline)
{
	global $login_id, $db_name, $database_link;

	db_connect();

	dbn('INSERT INTO ' . $db_name .
	 '_news (timestamp, headline, login_id) VALUES (' . time() . ', \'' .
	 db_escape($headline) . '\', ' . (int)$login_id . ')');
}

function send_mail($myname, $myemail, $contactname, $contactemail, $subject, $message)
{
	$headers = "MIME-Version: 1.0\n";
	$headers .= "Content-type: text/plain; charset=iso-8859-1\n";
	$headers .= "From: \"" . $myname . "\" <" . $myemail . ">\n";
	return (mail("\"" . $contactname . "\" <" . $contactemail . ">", $subject, $message, $headers));
}


/********************
Ship Information Functions
********************/

function discern_size($size)
{
	$sizes = [1=>'Tiny',2=>'Very Small',3=>'Small',4=>'Medium',5=>'Large',6=>'Very Large',7=>'Huge',8=>'Gigantic'];
	return $sizes[$size] ?? '';
}


/********************
Authorisation Checking Functions
********************/

function check_auth()
{
	global $session_id, $login_id, $db_name, $p_user, $game_info, $database_link;

	db("select * from user_accounts where login_id = '" . (int)$login_id . "' && session_id = '" . db_escape($_COOKIE['session_id'] ?? '') . "'");
	$p_user = dbr(1);

	if ($login_id == 1) {
		db("select * from se_games where session_id = '" . db_escape($session_id) . "'");
		$game_info = dbr(1);
		$p_user['session_id'] = $game_info['session_id'];
		$p_user['session_exp'] = $game_info['session_exp'];
		$db_name = $game_info['db_name'];
	}

	$next_exp = time() + SESSION_TIME_LIMIT;

	if ($session_id == '' || $login_id == 0 || $session_id != $p_user['session_id'] ||
	     $p_user['session_exp'] < time()) {
		setcookie("p_pass", '', time() - 20);
		setcookie("session_id", '', time() - 20);
		setcookie("login_id", '', time() - 20);
		header('Location: login_form.php');
		exit;
	} elseif ($login_id != 1) {
		dbn("update user_accounts set session_exp = '$next_exp', page_views = page_views + 1 where login_id = '" . (int)$login_id . "'");
		++$p_user['page_views'];
		$p_user['session_exp'] = $next_exp;
		$db_name = $p_user['in_game'];
	} elseif ($login_id == 1) {
		dbn("update se_games set session_exp = '$next_exp' where db_name = '" . db_escape($db_name) . "'");
		$p_user['session_exp'] = $next_exp;
	}
}

function gameVars($db_name)
{
	global $database_link;
	$options = mysqli_query($database_link, "SELECT `name`, `value` from `{$db_name}_db_vars`");
	if (!$options) return;
	while (list($name, $value) = mysqli_fetch_row($options)) {
		$GLOBALS[$name] = (int)$value;
	}
}


/********************
Calculating Functions
*********************/

function score_func($login_id, $full)
{
	global $score_method, $db_name;

	if ($full != 1) {
		db("select value from {$db_name}_db_vars where name = 'score_method'");
		$alpha_var = dbr();
		$score_method = $alpha_var['value'];
		$extra_text = "login_id = '$login_id'";
		$plan_text = "login_id = '$login_id'";
	} else {
		$extra_text = $plan_text = "login_id != " . ADMIN_ID;
	}
	if ($score_method == 1) {
		dbn("update {$db_name}_users set score = (fighters_killed + (ships_killed * 10)) - (fighters_lost * 0.75 + (ships_lost * 5)) where " . $extra_text);
	} elseif ($score_method == 2) {
		dbn("update {$db_name}_users set score = ships_killed_points - (ships_lost_points * 0.5) where " . $extra_text);
	} elseif ($score_method == 3) {
		db("select sum(fighters) + sum(point_value), login_id from {$db_name}_ships where " . $extra_text . " GROUP BY login_id");
		db2("select sum(fighters), login_id from {$db_name}_planets where " . $plan_text . " GROUP BY login_id");
		if ($full == 1) {
			while ($plan_array = dbr2()) {
				$temp_plan_array[$plan_array['login_id']] = $plan_array[0];
			}
			while ($ship_array = dbr()) {
				$ship_array[0] += $temp_plan_array[$ship_array['login_id']];
				dbn("update {$db_name}_users set score = '$ship_array[0]' where login_id = '$ship_array[login_id]'");
			}
			dbn("update {$db_name}_users set score = 0 where score < 0");
		} else {
			$ship_array = dbr();
			$plan_array = dbr2();
			$ship_array[0] += $plan_array[0];
			dbn("update {$db_name}_users set score = '$ship_array[0]' where login_id = '$login_id'");
		}
	}
}

function calc_perc($num1, $num2)
{
	if ($num1 == 0 || $num2 == 0) {
		return "$num1 (0%)";
	} else {
		$result = number_format(($num1 / $num2) * 100, 2, '.', '');
		return number_format($num1) . " (" . $result . "%)";
	}
}

function empty_bays(&$ship)
{
	$ship['empty_bays'] = $ship['cargo_bays'] - $ship['metal'] -
	 $ship['fuel'] - $ship['elect'] - $ship['colon'] - $ship['organ'];
}


/*********************
Misc Functions
*********************/

function popup_help($topic, $height, $width, $string = "Info")
{
	return '<a href="' . esc($topic) . '" onclick="popup(\'' . $topic .
	 '\', ' . (int)$height . ',' . $width . '); return false;">' . $string .
	 '</a>';
}

function create_rand_string($length)
{
	$salt = "abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ123456789";
	$ret_str = "";
	for ($i = 0; $i < $length; $i++) {
		$ret_str .= substr($salt, mt_rand() % strlen($salt), 1);
	}
	return $ret_str;
}

function make_ship($ship_parts, $ship_owner)
{
	global $db_name, $database_link;
	dbn("insert into {$db_name}_ships (ship_name, login_id, login_name, clan_id, shipclass, class_name, class_name_abbr, fighters, max_fighters, max_shields, cargo_bays, mine_rate_metal, mine_rate_fuel, config, size, upgrades, move_turn_cost, point_value, num_ot, num_dt, num_pc, num_ew) values('" . trim((string)$ship_parts['ship_name']) . "', '$ship_owner[login_id]', '$ship_owner[login_name]', '$ship_owner[clan_id]', '$ship_parts[type_id]', '$ship_parts[name]', '$ship_parts[class_abbr]', '$ship_parts[fighters]', '$ship_parts[max_fighters]', '$ship_parts[max_shields]', '$ship_parts[cargo_bays]', '$ship_parts[mine_rate_metal]', '$ship_parts[mine_rate_fuel]', '$ship_parts[config]', '$ship_parts[size]', '$ship_parts[upgrades]', '$ship_parts[move_turn_cost]', '$ship_parts[point_value]', '$ship_parts[num_ot]', '$ship_parts[num_dt]', '$ship_parts[num_pc]', '$ship_parts[num_ew]')");
	return db_insert_id();
}

function esc($str)
{
	return htmlspecialchars($str);
}

?>
