<?php
/**
 * Get user data associated with access_token
 * @param string access_token required
 */
function dkofblogin_graphapi($access_token, $object = 'me') {
  global $dkofblogin_http_settings;
  if (!isset($access_token)) {
    throw new Exception('graphapi: No access token specified');
  }

  $graph_url = 'https://graph.facebook.com/' . $object . '?access_token=' . $access_token;
  $ch = curl_init($graph_url);
  curl_setopt_array($ch, $dkofblogin_http_settings);
  $result = curl_exec($ch);

  if (curl_errno($ch)) {
    throw new Exception(curl_error($ch));
    curl_close($ch);
  }

  curl_close($ch);
  return json_decode($result);
} // graphapi_me

