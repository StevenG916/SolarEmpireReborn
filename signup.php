<?php

require_once('inc/common.inc.php');

db_connect();

$login_name = trim(db_escape((string)($_POST['l_name'] ?? '')));

if (empty($login_name)) {
	print_header("New Account Creation");
	echo "You need to enter a Login Name.";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

if ((strcmp($login_name, htmlspecialchars($login_name))) || strlen($login_name) < 3 ||
    preg_match('/[^a-z0-9~@$%&*_+\-=£§¥²³µ¶ ]/i', $login_name)) {
	print_header("New Account Creation");
	echo "Invalid login name. No slashes, no spaces, no HTML permitted in name, and a minimum of three characters.";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

if (empty($_POST['real_name'])) {
	print_header("New Account Creation");
	echo "You need to enter a first name.";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

if ($_POST['passwd'] == $login_name) {
	print_header("New Account Creation");
	echo "It's generally regarded as bad practise to use your login name as your password.";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

$email_address = db_escape((string)($_POST['email_address'] ?? ''));
if ($email_address != ($_POST['email_address_verify'] ?? '')) {
	print_header("New Account Creation");
	echo "The email addresses you entered did not match.";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

if (empty($email_address)) {
	print_header("New Account Creation");
	echo "You need to Enter an Email Address.";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
} elseif (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
	print_header("New Account Creation");
	echo "Please Enter a Valid Email Address";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

if (($_POST['passwd'] ?? '') != ($_POST['passwd_verify'] ?? '')) {
	print_header("New Account Creation");
	echo "The passwords you entered did not match.<br>They are case-sensitive.";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

db("select login_id from user_accounts where login_name = '$login_name'");
$user = dbr(1);
if (!empty($user['login_id'])) {
	print_header("New Account Creation");
	echo "Login name already taken";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

$email_address = strtok($email_address, " ,");

db("select login_id from user_accounts where email_address = '$email_address'");
$user = dbr(1);
if (!empty($user['login_id'])) {
	print_header("New Account Creation");
	echo "There is already an account with that email address";
	echo "<p><a href=javascript:history.back()>Back to Sign-up Form</a>";
	print_footer();
	exit();
}

$auth = abs(mt_rand(0, getrandmax()));
$real_name = db_escape((string)($_POST['real_name'] ?? ''));
$passwd_hash = md5($_POST['passwd'] ?? '');

dbn("insert into user_accounts (login_name, passwd, auth, signed_up, real_name, email_address) VALUES('$login_name', '$passwd_hash', '$auth', '" . time() . "', '$real_name', '$email_address')");
$login_id = db_insert_id();

if (SENDMail == 1) {
	$message = "A new Solar Empire account has been created on " . $_SERVER['HTTP_HOST'] . " for you.\r\n
	Once you have logged into the account you will be able to join any game on the server.\r\n
	Your login name for the server is $login_name.\r\n
	Your Authorisation code is $auth.\r\n
	You will need your authorisation code the first time you log in.\r\n
	Welcome to the Server. We hope you enjoy the games.";

	if (send_mail(SERVER_NAME, $_SERVER['SERVER_ADMIN'], $_POST['real_name'], $email_address, SERVER_NAME . " Authorisation Code", $message)) {
		echo "Authorisation mail sent successfully. You will need it the first time you try to sign in.<p>";
	} else {
		echo "ERROR! - Unable to send Authorisation mail for some reason.<p>";
	}
}

insert_history($login_id, "Created Account");

print_header("New Account Created");
echo "Congratulations, your account has been set up.";
echo "<br><a href=\"login_form.php\">Click Here</a> to return to the login page.";
print_footer();

?>
