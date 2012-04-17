<?php
/**
 * @TODO object orient
 */

/**
 * Get access token from API or user meta if logged in
 * if user_id passed use meta otherwise request from api
 * @param int user_id optional user_id to get meta from
 */
function dkofblogin_get_access_token($user_id = 0) {
  if ($user_id) {
    return get_the_author_meta(DKOFBLOGIN_USERMETA_KEY_TOKEN, $user_id);
  }

  global $dkofblogin_http_settings;
  $is_configured = get_option(DKOFBLOGIN_SLUG . '_is_configured');
  if (!$is_configured) {
    throw new Exception('dko-fblogin is not configured properly');
  }

  $options = get_option(DKOFBLOGIN_SLUG . '_options');
  $token_url = 'https://graph.facebook.com/oauth/access_token?'
    . 'client_id=' . $options['app_id']
    . '&redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT)
    . '&client_secret=' . $options['app_secret']
    . '&code=' . $_REQUEST['code'];
  $ch = curl_init($token_url);
  curl_setopt_array($ch, $dkofblogin_http_settings);
  $result = curl_exec($ch);
  if (curl_errno($ch) == 60) { // CURLE_SSL_CACERT
    // Invalid or no certificate authority found, using bundled information
    curl_setopt_array($ch, $dkofblogin_http_settings);
    curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/fb_ca_chain_bundle.crt');
    $result = curl_exec($ch);
  }

  if (curl_errno($ch)) {
    throw new Exception(curl_error($ch));
  }

  curl_close($ch);
  return str_replace('access_token=', '', $result);
} // dkofblogin_get_access_token()

/**
 * Get user data associated with access_token
 * @param string access_token required
 */
function dkofblogin_get_fb_userdata($access_token) {
  global $dkofblogin_http_settings;
  if (!isset($access_token)) {
    throw new Exception('get_fb_userdata: No access token specified');
  }

  $graph_url = 'https://graph.facebook.com/me?access_token=' . $access_token;
  $ch = curl_init($graph_url);
  curl_setopt_array($ch, $dkofblogin_http_settings);
  $result = curl_exec($ch);

  if (curl_errno($ch)) {
    throw new Exception(curl_error($ch));
    curl_close($ch);
  }

  curl_close($ch);
  return json_decode($result);
} // dkofblogin_get_fb_userdata

