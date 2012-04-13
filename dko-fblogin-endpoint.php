<?php
define('WP_USE_THEMES', false);
require_once '../wp-blog-header.php';
require_once dirname(__FILE__) . '/dko-fblogin-settings.php';

if (!array_key_exists('state', $_REQUEST) || !array_key_exists('dko_fblogin_state', $_SESSION)) {
  die();
  header('location: ' . home_url());
}

if ($_REQUEST['state'] == $_SESSION['dko_fblogin_state']) {
  $options = get_option(DKOFBLOGIN_SLUG . '_options');
  $token_url = 'https://graph.facebook.com/oauth/access_token?'
    . 'client_id=' . $options['app_id']
    . '&redirect_uri=' . urlencode(DKOFBLOGIN_ENDPOINT)
    . '&client_secret=' . $options['app_secret']
    . '&code=' . $_REQUEST['code'];

  echo '<pre>';
  $response = file_get_contents($token_url);
  print_r($response);
  echo '</pre>';

  exit();
  $response = wp_remote_get($token_url, $dko_fblogin_http_settings);

  if (is_wp_error($response)) {
    echo '<h3>error: ', $response->get_error_code(), '</h3>';
    echo '<p>', $response->get_error_message(), '</p>';
    echo '<pre>'; print_r($response->get_error_data()); echo '</pre>';
    exit;
  }
  elseif (200 == wp_remote_retrieve_response_code($response)) {
    $body = wp_remote_retrieve_body($response);
    print_r($response);
    print_r($body);
    //str_replace('access_token=', '', $response['body']);
  }

  /*
  $type = strtoupper($type);
  if (empty($obj)) return null;
  $url = 'https://graph.facebook.com/'. $obj;
  if (!empty($connection)) $url .= '/'.$connection;
  if ($type == 'GET') $url .= '?'.http_build_query($args);
  $args['sslverify']=0;

  if ($type == 'POST') {
    $data = wp_remote_post($url, $args);
  } else if ($type == 'GET') {
    $data = wp_remote_get($url, $args);
  } 
  
  if ($data && !is_wp_error($data)) {
    $resp = json_decode($data['body'],true);
    return $resp;
  }
  
  return false;

/*  $graph_url = "https://graph.facebook.com/me?access_token=" . $params['access_token'];

  $user = json_decode(file_get_contents($graph_url));
  echo("Hello " . $user->name);
 */
}
else {
  echo("The state does not match. You may be a victim of CSRF.");
}

 ?>
