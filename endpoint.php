<?php
/**
 * dko-fblogin/endpoint.php
 * The user gets to this file directly, .htaccess masks the URL
 * @TODO object orient
 */
define('WP_USE_THEMES', false);
// assume in wp-content/plugins/dko-fblogin/
require_once '../../../wp-blog-header.php';
require_once 'config.php';
require_once 'fbapi.php';

/* ==|== no haxors ========================================================== */
if (!array_key_exists('state', $_REQUEST) || !array_key_exists('dko_fblogin_state', $_SESSION)) {
  echo 'Missing state, maybe CSRF';
  exit;
}

if ($_REQUEST['state'] != $_SESSION['dko_fblogin_state']) {
  echo 'Invalid state, maybe CSRF';
  exit;
}

/**
 * by using the update_user_meta we ensure only one FB acct per WP user
 */
function dkofblogin_setfbmeta($user_id, $fbid, $access_token) {
  update_user_meta($user_id, DKOFBLOGIN_USERMETA_KEY_FBID, $fbid);
  update_user_meta($user_id, DKOFBLOGIN_USERMETA_KEY_TOKEN, $access_token);
}

/**
 * login user by full userdata
 */
function dkofblogin_login($user_data) {
  $user_id = $user_data->ID;
  wp_set_current_user($user_id);
  wp_set_auth_cookie($user_id, true);
  do_action('wp_login', $user_data->user_login);
}

/**
 * redirect to $location or $default with $status
 */
function dkofblogin_redirect($location = '', $default = '', $status = 302) {
  if ($location) {
    header('location: ' . $location, true, $status); // send 302 Found header
    exit; // just in case
  }
  // no login location defined, go to user's wordpress admin profile
  if (!$default) {
    $default = admin_url('profile.php');
  }
  header('location: ' . $default, true, $status); // send 302 Found header
  exit; // just in case
}

/**
 * checks if $username is taken, if so add a number. Recurse until not taken.
 * @TODO this could take a while, consider another method
 */
function dkofblogin_createusername($username, $prefix = '') {
  $attempt = $username . $prefix;
  $taken = get_user_by('login', $attempt);
  if (!$taken) {
    return $attempt;
  }
  if (!$prefix) {
    $prefix = 0;
  }
  $prefix = $prefix + 1;
  return dkofblogin_createusername($username, $prefix);
}


/* ==|== Get an access token ================================================ */
$options = get_option(DKOFBLOGIN_SLUG . '_options');
$token_url = 'https://graph.facebook.com/oauth/access_token?'
  . 'client_id=' . $options['app_id']
  . '&redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT)
  . '&client_secret=' . $options['app_secret']
  . '&code=' . $_REQUEST['code'];
$ch = curl_init($token_url);
curl_setopt_array($ch, $dko_fblogin_http_settings);
$result = curl_exec($ch);
if (curl_errno($ch) == 60) { // CURLE_SSL_CACERT
  // Invalid or no certificate authority found, using bundled information
  curl_setopt_array($ch, $dko_fblogin_http_settings);
  curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/fb_ca_chain_bundle.crt');
  $result = curl_exec($ch);
}

if (curl_errno($ch)) {
  print_r( curl_error($ch) );
  exit;
}
else {
  $access_token = str_replace('access_token=', '', $result);
}
curl_close($ch);

$fb_data = dkofblogin_get_fb_userdata($access_token);

/* ==|== Look for existing user by fb id ==================================== */
if (!isset($fb_data)) { // got access token
  echo 'Error parsing fb user data';
  exit;
}

$user_query = new WP_User_Query(array(
  'meta_key'      => DKOFBLOGIN_USERMETA_KEY_FBID,
  'meta_value'    => $fb_data->id,
  'meta_compare'  => '='
));

if (!$user_query->total_users) { // user not found by fbid, try fb primary email
  $user_query = new WP_User_Query(array(
    'meta_key'      => 'email',
    'meta_value'    => $fb_data->email,
    'meta_compare'  => '='
  ));
}

/* ok determined if fb user exists or not, what to do with the fb user? */
if (!$user_query->total_users) { // user doesn't exist
  $current_user_id = get_current_user_id();
  if ($current_user_id) { // user is already logged in, associate with logged in user
    dkofblogin_setfbmeta($current_user_id, $fb_data->id, $access_token);
    dkofblogin_redirect($options['login_redirect'], admin_url('profile.php'));
  }
  else { // not logged in, doesn't exist, so create new WP account
    $wp_userdata = array();
    $wp_userdata['user_login'] = dkofblogin_createusername($fb_data->username);
    $wp_userdata['user_email'] = $fb_data->email;
    $wp_userdata['first_name'] = $fb_data->first_name;
    $wp_userdata['last_name'] = $fb_data->last_name;
    $user_id = wp_create_user($wp_userdata);
    $user_data = get_userdata($user_id);
    dkofblogin_login($user_data); // log in
    dkofblogin_setfbmeta($user_id, $fb_data->id, $access_token);
    dkofblogin_redirect($options['register_redirect'], admin_url('profile.php')); // collect missing info
  }
}
else { // fb id already associated with an account, log WP user in
  $user_data = $user_query->results[0];
  dkofblogin_login($user_data);
  dkofblogin_redirect($options['login_redirect'], admin_url('profile.php'));
}
exit;
