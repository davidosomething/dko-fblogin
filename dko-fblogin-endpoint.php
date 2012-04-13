<?php
define('WP_USE_THEMES', false);
// assume in wp-content/plugins/dko-fblogin/
require_once '../../../wp-blog-header.php';

require_once 'dko-fblogin-settings.php';

if (!array_key_exists('state', $_REQUEST) || !array_key_exists('dko_fblogin_state', $_SESSION)) {
}

if ($_REQUEST['state'] == $_SESSION['dko_fblogin_state']) {
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

  if (!curl_errno($ch)) {
    $access_token = str_replace('access_token=', '', $result);
  }

  echo "<pre>Token urL:\n";
  print_r($token_url);
  echo "\n\nerror:\n";
  echo curl_error($ch);
  echo "\n\nresult:\n";
  print_r($result);
  curl_close($ch);

  if (isset($access_token)) {
    $graph_url = 'https://graph.facebook.com/me?access_token=' . $access_token;
    $ch = curl_init($graph_url);
    curl_setopt_array($ch, $dko_fblogin_http_settings);
    $result = curl_exec($ch);
    echo "<pre>";
    print_r($result);
    echo "\n";
    print_r(json_decode($result));
  }
}
