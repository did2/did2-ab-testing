<?php
/**
 * Author: did2
 * Author URI: http://did2memo.net/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Prefix: did2_ab_testing
 */

/**
 * Author: Alin Marcu
 * Author URI: http://deconf.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

function did2_ab_testing_safe_get($key) {
	if (array_key_exists ( $key, $_POST )) {
		return $_POST [$key];
	}
	return false;
}
function gads_dash_pretty_error($e) {
	return '<p>'.esc_html($e->getMessage()).'</p><p>'. __('For further help and support go to', 'did2_ab_testing' ).' <a href="http://did2memo.net/" target="_blank">' . __ ( "Deconf Help Center", 'did2_ab_testing' ) . '</a></p>';
}
class AdSenseAuth {
	protected $client;
	protected $adSenseService;
	private $user, $authUrl;
	public function __construct() {
		// If at least PHP 5.3.0 use the autoloader, if not try to edit the include_path
		//if (version_compare ( PHP_VERSION, '5.3.0' ) >= 0) {
		//	require 'vendor/autoload.php';
		//} else {
			set_include_path ( DID2AB_PATH . '/google-api-php-client/src/'. PATH_SEPARATOR . get_include_path () );
			// Include GAPI client
			//if (! class_exists ( 'Google_Client' )) {
				require_once 'Google/Client.php';
			//}
			// Include GAPI AdSense Service
			//if (! class_exists ( 'Google_Service_AdSense' )) {
				require_once 'Google/Service/AdSense.php';
			//}
		//}
		$this->client = new Google_Client ();
		$this->client->setAccessType ( 'offline' );
		//$this->client->setApplicationName ( 'did2 AB Testing 1' );
		$this->client->setRedirectUri ( 'urn:ietf:wg:oauth:2.0:oob' );
		
		//if (get_option ( 'did2_ab_testing_userapi' )) {
		//	$this->client->setClientId ( get_option ( 'did2_ab_testing_clientid' ) );
		//	$this->client->setClientSecret ( get_option ( 'did2_ab_testing_clientsecret' ) );
		//	$this->client->setDeveloperKey ( get_option ( 'did2_ab_testing_apikey' ) );
		//} else {
			$this->client->setClientId ( '153026819782-lgu4cg9uepvvi9bj5fhd38v8nq70trr0.apps.googleusercontent.com' );
			$this->client->setClientSecret ( 'UXIRBK5BiVtrQJDtAg5_vNvB' );
			//$this->client->setDeveloperKey ( '' );
		//}
		$this->adSenseService = new Google_Service_AdSense ( $this->client );
	}
	function did2_ab_testing_store_token($user, $token) {
		update_option ( 'did2_ab_testing_access_token_user', $user );
		update_option ( 'did2_ab_testing_access_token', $token );
	}
	function did2_ab_testing_get_token() {
		//echo 'token+' . get_option( 'did2_ab_testing_access_token' ) . '+token';
		if ( get_option( 'did2_ab_testing_access_token' ) ) {
			return get_option( 'did2_ab_testing_access_token' );
		} else {
			return;
		}
	}
	public function did2_ab_testing_reset_token() {
		update_option ( 'did2_ab_testing_access_token', "" );
	}
	function did2_ab_testing_clear_cache() {
		global $wpdb;
		$sqlquery = $wpdb->query ( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_did2_ab_testing%%'" );
		$sqlquery = $wpdb->query ( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_did2_ab_testing%%'" );
	}
	function authenticate($user) {
		$this->user = $user;
		$token = $this->did2_ab_testing_get_token ();
		
		if (isset ( $token )) {
			$this->client->setAccessToken ( $token );
			return $this->client->getAccessToken();
		} else {
			$this->client->setScopes ( array (
					"https://www.googleapis.com/auth/adsense.readonly" 
			) );
			$this->authUrl = $this->client->createAuthUrl ();
			if (! isset ( $_REQUEST ['did2_ab_testing_authorize'] )) {
				if (! current_user_can ( 'manage_options' )) {
					_e ( "Ask an admin to authorize this Application", 'did2_ab_testing' );
					return;
				}
				
				echo '<div style="padding:20px;">' . __ ( "Use this link to get your access code:", 'did2_ab_testing' ) . ' <a href="' . $this->authUrl . '" target="_blank">' . __ ( "Get Access Code", 'gads-dash' ) . '</a>';
				echo '<form name="input" action="#" method="POST">
							<p><b>' . __ ( "Access Code:", 'did2_ab_testing' ) . ' </b><input type="text" name="did2_ab_testing_access_token" value="" size="35"></p>
							<input type="submit" class="button button-primary" name="did2_ab_testing_authorize" value="' . __ ( "Save Access Code", 'did2_ab_testing' ) . '"/>
						</form>
					</div>';
				return;
			} else if (isset ( $_REQUEST ['did2_ab_testing_access_token'] )) {
				echo 'bbb';
				$this->client->authenticate ( $_REQUEST ['did2_ab_testing_access_token'] );
				$this->did2_ab_testing_store_token ( $this->user, $this->client->getAccessToken () );
			} else {
				$adminurl = admin_url ( "#did2_ab_testing-widget" );
				echo '<script> window.location="' . $adminurl . '"; </script> ';
			}
		}
	}
	function getAdSenseService() {
		return $this->adSenseService;
	}
	function did2_ab_testing_refreshToken() {
		if ($this->client->getAccessToken () != null) {
			$this->did2_ab_testing_store_token ( 'default', $this->client->getAccessToken () );
		}
	}
}

?>