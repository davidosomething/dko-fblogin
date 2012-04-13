<?php
/**
 * dko-fblogin/endpoint.php
 * The user gets to this file directly, .htaccess masks the URL
 */
define('WP_USE_THEMES', false);
// assume in wp-content/plugins/dko-fblogin/
require_once '../../../wp-blog-header.php';
require_once 'config.php';

if (!array_key_exists('state', $_REQUEST) || !array_key_exists('dko_fblogin_state', $_SESSION)) {
  echo 'Missing state, maybe CSRF';
  exit;
}

if ($_REQUEST['state'] != $_SESSION['dko_fblogin_state']) {
  echo 'Invalid state, maybe CSRF';
  exit;
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

/* ==|== Get user data ====================================================== */
if (isset($access_token)) {
  $graph_url = 'https://graph.facebook.com/me?access_token=' . $access_token;
  $ch = curl_init($graph_url);
  curl_setopt_array($ch, $dko_fblogin_http_settings);
  $result = curl_exec($ch);
}

if (curl_errno($ch)) {
  print_r( curl_error($ch) );
  exit;
}
else {
  $data = json_decode($result);
}
curl_close($ch);

/* ==|== Look for existing user by fb id ==================================== */
if (isset($data)) {
  $user_query = new WP_User_Query(array(
    'meta_key'      => DKOFBLOGIN_SLUG . '_fbid',
    'meta_value'    => $data->id,
    'meta_compare'  => '='
  ));

  echo '<pre>';
  print_r($user_query);
  echo "\n\n";

  $user_query = new WP_User_Query(array(
    'meta_key'      => 'nickname',
    'meta_value'    => 'dotrakoun',
    'meta_compare'  => '='
  ));

  print_r($user_query);
}
