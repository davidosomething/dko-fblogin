<?php
/**
 * @TODO object orient
 */

/**
 * Get user data associated with access_token
 */
function dkofblogin_get_fb_userdata($access_token) {
  global $dko_fblogin_http_settings;
  if (isset($access_token)) {
    $graph_url = 'https://graph.facebook.com/me?access_token=' . $access_token;
    $ch = curl_init($graph_url);
    curl_setopt_array($ch, $dko_fblogin_http_settings);
    $result = curl_exec($ch);
  }

  if (curl_errno($ch)) {
    throw new Exception( curl_error($ch) );
    curl_close($ch);
  }

  curl_close($ch);
  return json_decode($result);
} // dkofblogin_get_fb_userdata

