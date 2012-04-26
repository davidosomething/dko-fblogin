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

    // add_filter('dkowppplugin_api_after_request', array(&$this, 'make_certified_request'), 10, 3);
  } // __construct

  /**
   * make_certified_request
   *
   * This is a filter to specifically handle facebook CURL requests using
   * DKOWPPlugin_API::make_request()
   *
   * @param object $ch last used CURL handler
   * @param string $url the url last requested
   * @param mixed $result
   * @return void
   */
  public function make_certified_request($result, $ch, $url) {
    if (curl_errno($ch) == 60) { // CURLE_SSL_CACERT
      // Invalid or no certificate authority found, using bundled information
      curl_setopt_array($ch, $this->curlopts);
      curl_setopt($ch, CURLOPT_URL, $url);
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

    if (!isset($_REQUEST['code'])) {
      throw new Exception('Can\'t get access token: missing code from facebook');
      exit;
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
} // end class
endif;
