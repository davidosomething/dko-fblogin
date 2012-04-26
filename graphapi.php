<?php
/**
 * graphapi.php
 *
 * Make requests to the Facebook Graph API
 * Reinventing facebook-php-sdk >_>
 *
 * @TODO just extend facebook's official SDK classes
 */

class DKOFBLogin_Graph_API
{
  public $curlopts      = array();
  public $graph_baseurl = 'https://graph.facebook.com';
  protected $app_id     = '';
  protected $app_secret = '';

  /**
   * __construct
   *
   * @return void
   */
  public function __construct($app_id, $app_secret) {
    $this->app_id     = $app_id;
    $this->app_secret = $app_secret;

    if (!defined('SERVER_ENVIRONMENT') || in_array(SERVER_ENVIRONMENT, array('STAGE', 'PROD'))) {
      $this->curlopts = array(
        CURLOPT_SSL_VERIFYHOST => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSLVERSION => 3 // fixes everything :D
      );
    }
    elseif (in_array(SERVER_ENVIRONMENT, array('LOCAL', 'DEV'))) { // local or dev
      $this->curlopts = array(
        CURLOPT_SSL_VERIFYHOST  => false,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_SSLVERSION      => 3,
        CURLOPT_VERBOSE         => 1
      );
    }
  } // __construct

  /**
   * Get user data associated with access_token
   * @param string access_token required
   */
  public function get_object($object = 'me', $access_token = '') {
    if (!$access_token) {
      $access_token = $this->get_access_token();
    }
    $graph_query = array('access_token' => $access_token);
    $graph_url  = $this->graph_baseurl."/$object?".build_query($graph_query);
    $result     = $this->make_request($graph_url);

    // @TODO expects json, validate
    $json_body  = json_decode($result);
    return $json_body;
  } // graphapi_me

  /**
   * get_access_token
   *
   * @return string access token
   */
  public function get_access_token() {
    // use cached access token unless otherwise specified
    static $cached_access_token;
    if ($cached_access_token) {
      return $cached_access_token;
    }

    // get a new access token
    // build_query will url encode params for you
    $token_query = array(
      'client_id'     => $this->app_id,
      'redirect_uri'  => DKOFBLOGIN_ENDPOINT_URL,
      'client_secret' => $this->app_secret,
      'code'          => $_REQUEST['code']
    );
    $token_url  = $this->graph_baseurl.'/oauth/access_token?' . build_query($token_query);

    $result     = $this->make_request($token_url);
    // @TODO validate result!

    $cached_access_token = str_replace('access_token=', '', $result);
    return $cached_access_token;
  } // get_access_token()

  /**
   * make_request
   *
   * @TODO handle expired access tokens
   * @param string $url
   * @return string response
   */
  public function make_request($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, $this->curlopts);

    $result = curl_exec($ch);

    if (curl_errno($ch) == 60) { // CURLE_SSL_CACERT
      // Invalid or no certificate authority found, using bundled information
      curl_setopt_array($ch, $this->curlopts);
      curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/fb_ca_chain_bundle.crt');
      $result = curl_exec($ch);
    }

    if (curl_errno($ch)) {
      // @TODO wp_die($msg, $title, $args=array())
      throw new Exception(curl_error($ch));
    }

    curl_close($ch);
    return $result;
  }

} // end class
