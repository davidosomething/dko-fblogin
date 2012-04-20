<?php
/**
 * dko-fblogin/fb_link.php
 */

/* ==|== no haxors ========================================================== */
if (array_key_exists('error_msg', $_REQUEST)) {
  throw new Exception(htmlspecialchars($_REQUEST['error_msg']));
}
if (!array_key_exists('state', $_REQUEST) || !array_key_exists('dko_fblogin_state', $_SESSION)) {
  throw new Exception('Missing state, maybe CSRF');
}
if ($_REQUEST['state'] != $_SESSION['dko_fblogin_state']) {
  throw new Exception('Invalid state, maybe CSRF');
}

/* ==|== Get an access token ================================================ */
$access_token = $this->get_access_token();

/* ==|== Look for existing user by fb id ==================================== */
$fb_data = false;
if ($access_token) {
  $fb_data = dkofblogin_graphapi($access_token, 'me');
}
if (!$fb_data) { // got access token
  throw new Exception('Couldn\'t get or parse user data.');
}

$user_data = $this->get_user_by_fbdata($fb_data);
if ($user_data) { // found user, update token, login, redirect to profile
  $user_id = $user_data->ID;
  $this->setfbmeta($user_id, $fb_data->id, $access_token);
  $this->wp_login($user_id);
  $this->redirect($options['login_redirect'], admin_url('profile.php'));
  exit; // just in case
}

// NEW FB ID! Associate with logged in user?
$user_id = get_current_user_id();
if ($user_id) { // user is already logged in, associate with logged in user
  $this->setfbmeta($user_id, $fb_data->id, $access_token);
  $this->redirect($options['login_redirect'], admin_url('profile.php'));
  exit; // just in case
}

// NEW FB ID! Associate with NEW WP account
$wp_userdata = array();
if (property_exists($fb_data, 'username')) {
  $wp_userdata['user_login'] = $this->create_username($fb_data->username);
}
else { // use email as username if no fb username
  $emailname = strstr($fb_data->email, '@', true);
  $wp_userdata['user_login'] = $this->create_username($emailname);
}
$wp_userdata['user_pass']   = wp_generate_password();
$wp_userdata['user_email']  = $fb_data->email;
$wp_userdata['first_name']  = $fb_data->first_name;
$wp_userdata['last_name']   = $fb_data->last_name;
var_dump($wp_userdata);
$user_id = wp_insert_user($wp_userdata);
$this->setfbmeta($user_id, $fb_data->id, $access_token);

// email user with non-fb login details
$message = '<p>Hi ' . $fb_data->name . ',</p>';
$message .= '<p>You logged in via Facebook on ' . bloginfo('name');
$message .= ' so we created an account for you. Keep this email for reference.</p>';
$message .= '<p>You can always login via your linked Facebook account or use';
$message .= ' the following username and password:</p><ul><li>';
$message .= "username: " . $wp_userdata['user_login'];
$message .= "</li><li>password: " . $wp_userdata['user_pass'] . '</li></ul>';

$headers = 'From: ' . esc_attr(get_bloginfo('name')) . ' <' . get_bloginfo('admin_email') . ">\r\n";
add_filter('wp_mail_content_type', function() { return 'text/html'; });
wp_mail($fb_data->email, 'Your account on ' . esc_attr(get_bloginfo('name')), $message, $headers);

// login user and redirect
$this->wp_login($user_id); // log in if subscriber
$this->redirect($options['register_redirect'], admin_url('profile.php'));


// 403 unauthorized, shouldn't ever get to this point
header('location: ' . site_url(), true, 403); exit;
