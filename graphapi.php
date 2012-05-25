<?php
/**
 * graphapi.php
 *
 * Make requests to the Facebook Graph API
 * Reinventing facebook-php-sdk >_>
 *
 * @TODO just extend facebook's official SDK classes
 */

if (!class_exists('DKOWPPlugin_API')):
  require_once dirname( __FILE__ ) . '/framework/api.php';
endif;

if (!class_exists('DKOFBLogin_Graph_API')):
class DKOFBLogin_Graph_API extends DKOWPPlugin_API
{
  public $graph_baseurl = 'https://graph.facebook.com';
  protected $app_id     = '';
  protected $app_secret = '';

  /**
   * __construct
   *
   * @return void
   */
  public function __construct($app_id, $app_secret) {
    parent::__construct();
    $this->app_id     = $app_id;
    $this->app_secret = $app_secret;

    add_filter('dkowppplugin_api_after_request', array(&$this, 'make_certified_request'), 10, 3);
  } // __construct

  /**
   * make_certified_request
   *
   * This is a filter to specifically handle facebook cURL requests using
   * DKOWPPlugin_API::make_request()
   * Adds a local certificate file if the first cURL request failed
   *
   * @param object $ch last used CURL handler
   * @param string $url the url last requested
   * @param mixed $result
   * @return void
   */
  public function make_certified_request($result, $ch, $url) {
    if (curl_errno($ch) == 60) { // CURLE_SSL_CACERT
      // Invalid or no certificate authority found, using bundled information
      curl_setopt($ch, CURLOPT_CAINFO, dirname( __FILE__ ) . '/fb_ca_chain_bundle.crt');
      $result = curl_exec($ch);
    }
    return $result;
  }

  /**
   * Get user data associated with access_token
   * @param string access_token required
   */
  public function get_object($object = 'me', $access_token = '') {
    if (!$access_token) {
      $access_token = $this->get_access_token();
    }
    if (!$access_token) {
      return false;
    }
    $query = build_query(array('access_token' => $access_token));

    $url = $this->graph_baseurl . "/$object";
    $response = $this->make_request($url, $query);
    $result = json_decode($response);

    if (property_exists($result, 'error')) {
      if ($result->error->type == 'OAuthException') {
        $access_token = $this->get_access_token(false);
        $query = build_query(array('access_token' => $access_token));
        $response = $this->make_request($url, $query);
        $result = json_decode($response);
      }
    }

    // @TODO expects json, validate
    return $result;
  } // graphapi_me

  /**
   * get_access_token
   *
   * @param boolean $use_cached TRUE to use static cached token, false to renew
   * @return string access token
   */
  public function get_access_token($use_cached = TRUE) {
    // use cached access token unless otherwise specified
    static $cached_access_token;
    if ($use_cached && $cached_access_token) {
      return $cached_access_token;
    }

    if (!isset($_REQUEST['code'])) {
      return false;
      /*
      throw new Exception('Can\'t get access token: missing code from facebook');
      exit;
       */
    }

    // get a new access token
    // build_query will url encode params for you
    $query = build_query(array(
      'client_id'     => $this->app_id,
      'redirect_uri'  => DKOFBLOGIN_ENDPOINT_URL,
      'client_secret' => $this->app_secret,
      'code'          => $_REQUEST['code']
    ));
    $url      = $this->graph_baseurl.'/oauth/access_token';
    $response = $this->make_request($url, $query);
    // @TODO validate result!
    $cached_access_token = str_replace('access_token=', '', $response);
    return $cached_access_token;
  } // get_access_token()
} // end class
endif;
