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
  } // make_certified_request()

  /**
   * login_link
   *
   * generate a link to the facebook oAuth dialog that redirects back to the
   * specified place when user accepts
   *
   * @param string $redirect_uri URL to redirect to after oAuth done
   * @return string link to login via facebook
   */
  public function login_link($redirect_uri = DKOFBLOGIN_ENDPOINT_URL) {
    if (session_id() == '') {
      session_start();
    }
    if (empty($_REQUEST['code'])) {
      $_SESSION[DKOFBLOGIN_SLUG.'_state'] = md5(uniqid(rand(), TRUE)); //CSRF protection
    }

    $options = get_option(DKOFBLOGIN_OPTIONS_KEY);
    $link_query = array(
      'client_id'     => $this->app_id,
      'redirect_uri'  => $redirect_uri,
      'state'         => $_SESSION[DKOFBLOGIN_SLUG.'_state']
    );
    if (array_key_exists('permissions', $options)) {
      $link_query['scope'] = implode(',', $options['permissions']);
    }

    // build_query does urlencoding.
    $link = 'https://www.facebook.com/dialog/oauth?' . build_query($link_query);
    return $link;
  } // login_link()

  /**
   * Get user data associated with access_token
   * @param string access_token required
   */
  public function get_object($object = 'me', $access_token = '') {
    // get access token, cached if available. Redirect back to current page if
    // we needed to reauth.
    if (!$access_token) {
      $access_token = $this->get_access_token(TRUE);
    }

    // still no access token
    if (!$access_token) {
      return null;
    }

    // ok have an access token, make the request
    $query    = build_query(array('access_token' => $access_token));
    $url      = $this->graph_baseurl . "/$object";

    try {
      $response = $this->make_request($url, $query);
      $result   = json_decode($response);
    }
    catch (Exception $e) {
      $result   = null;
    }

    // the result is an error, may have used an expired cached access token
    // try again, refreshing the user's access token in the process
    if (!is_object($result) || property_exists($result, 'error')) {
      if (!is_object($result) || $result->error->type == 'OAuthException') {
        $access_token = $this->get_access_token(FALSE);
        $query        = build_query(array('access_token' => $access_token));

        try {
          $response     = $this->make_request($url, $query);
          $result       = json_decode($response);
        }
        catch (Exception $e) {
          $result = null;
        }
      }
    }

    // @TODO expects json, validate
    return $result;
  } // graphapi_me

  /**
   * get_access_token
   *
   * gets the cached access token if this is not the first request on the page
   * otherwise gets a new access token
   *
   * @param boolean $use_cached TRUE to use static cached token, false to renew
   * @return string access token
   */
  public function get_access_token($use_cached = TRUE, $redirect_uri = DKOFBLOGIN_ENDPOINT_URL) {
    // use cached access token unless otherwise specified
    static $cached_access_token;
    if ($use_cached && $cached_access_token) {
      return $cached_access_token;
    }

    // when we go to the auth dialog, facebook redirects us back to our endpoint
    // with a code. we trade the code for an access token
    if (empty($_REQUEST['code'])) {
      // @TODO header('location: ' . $this->login_link($redirect_uri));
      return false;
    }

    // exchange the code for a new access token
    // build_query will url encode params for you
    $query = build_query(array(
      'client_id'     => $this->app_id,
      'redirect_uri'  => $redirect_uri,
      'client_secret' => $this->app_secret,
      'code'          => $_REQUEST['code']
    ));
    $url      = $this->graph_baseurl . '/oauth/access_token';
    try {
      $response = $this->make_request($url, $query);
    }
    catch (Exception $e) {
      return false;
    }

    // @TODO validate result!
    $cached_access_token = str_replace('access_token=', '', $response);
    return $cached_access_token;
  } // get_access_token()
} // end class
endif;
