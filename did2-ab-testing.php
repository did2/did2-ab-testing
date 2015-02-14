<?php
/*
Plugin Name: Did2 AB Testing 
Plugin URI: http://did2memo.net/
Description:
	A WordPress plugin for did2's ab testing
Version: 1.0.0
Author: did2
Author URI: http://did2memo.net/
License: GPL2  
*/

session_start ();

define( 'DID2AB_PATH' , dirname( __FILE__ ) );
require_once dirname(__FILE__) . '/diff-themes.php';
require_once dirname(__FILE__) . '/theme-editor.php';
require_once dirname(__FILE__) . '/plugin-editor.php';

if( is_admin() ) {
	// require_once( DID2AB_PATH . '/did2-ab-testing-admin.php' );
	add_action('admin_init', 'did2_ab_testing_register_setting' );
	add_action('admin_menu', 'did2_ab_testing_admin_menu_hook' );
	add_action('admin_menu', 'did2_ab_testing_admin_menu_hook_diff_themes' );

	//add_action('admin_enqueue_scripts', 'did2_ab_testing_enqueue_scripts');
}

//add_action( 'init' , 'did2_ab_testing_init_session_start' );
add_action( 'setup_theme' , 'did2_ab_testing_setup_theme' );
add_action( 'plugins_loaded' , 'did2_ab_testing_plugins_loaded' );

add_action( 'template_redirect', 'did2_ab_testing_start_buffering', 1);

function did2_ab_testing_plugins_loaded() {
    add_filter( 'template' , 'did2_ab_testing_template_filter' );
    add_filter( 'stylesheet' , 'did2_ab_testing_stylesheet_filter' );
}

function did2_ab_testing_admin_menu_hook() {
	$handle = add_options_page( 'did2 A/B Testing' , 'did2 A/B Testing' , 'manage_options' , 'did2_ab_testing_options' , 'did2_ab_testing_options_page' );
	add_action('admin_print_styles-' . $handle, 'did2_ab_testing_enqueue_scripts');
}

function did2_ab_testing_register_setting() {
	register_setting( 'did2_ab_testing_options_group' , 'did2_ab_testing_options', 'did2_ab_testing_options_validator' );
}

function did2_ab_testing_options_validator ( $options ) {
	return $options;
}

function did2_ab_testing_enqueue_scripts( $hook ) {
	//switch ( $hook ) {
	//	case "did2_ab_testing_options":
	//	case "did2_ab_testing_plugin_editor":
		wp_enqueue_style('did2_ab_testing_style',  plugins_url('style.css', __FILE__), array());
		wp_enqueue_script('did2_ab_testing_script_ace',  plugins_url('ace/src-noconflict/ace.js', __FILE__), array('jquery'));
	
		//wp_enqueue_style('did2_ab_testing_style_bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css', array());
		//wp_enqueue_script('did2_ab_testing_script_bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js', array());
	//}
}

function duplicate_theme( $from_theme_dir_name, $to_theme_dir_name = '', $to_theme_name = '') {
	global $wp_filesystem;
	//require_once(ABSPATH . 'wp-admin/includes/file.php');

	$from_theme = wp_get_theme( $from_theme_dir_name );
	if ( ! $from_theme->exists() )
		wp_die( 'Original Theme:' . $from_theme_dir_name . ' does not exist.' );

	$to_theme = wp_get_theme( $to_theme_dir_name );
	if ( $to_theme->exists() )
		wp_die( 'New Theme:' . $to_theme_dir_name . ' already exists.' );		

	$redirect = wp_nonce_url( 'options-general.php?page=did2_ab_testing_options', '', false, false, array( 'copy_from', 'new_name', 'new_dir_name') );
	if ( false === ($credentials = request_filesystem_credentials($redirect)) ) {
		$data = ob_get_contents();
		ob_end_clean();
		if ( ! empty($data) ){
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! WP_Filesystem($credentials) ) {
		request_filesystem_credentials($redirect, '', true); // Failed to connect, Error and request again
		$data = ob_get_contents();
		ob_end_clean();
		if ( ! empty($data) ) {
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! is_object($wp_filesystem) )
		return new WP_Error('fs_unavailable', __('Could not access filesystem.'));

	if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
		return new WP_Error('fs_error', __('Filesystem error.'), $wp_filesystem->errors);

	$themes_dir = $wp_filesystem->wp_themes_dir();
	if ( empty( $themes_dir ) ) {
		return new WP_Error( 'fs_no_themes_dir', __( 'Unable to locate WordPress theme directory.' ) );
	}

	$themes_dir = trailingslashit( $themes_dir );
	$from_theme_dir_path = $themes_dir . $from_theme_dir_name;
	$to_theme_dir_path = $themes_dir . $to_theme_dir_name;

	$wp_filesystem->mkdir( $to_theme_dir_path );
	$copy_dired = copy_dir( $from_theme_dir_path, $to_theme_dir_path );

	if (is_wp_error($copy_dired)) {
		echo $from_theme_dir_path . '<br />';
		echo $to_theme_dir_path . '<br />';
		echo $copy_dired->get_error_code() . '<br />';
		echo $copy_dired->get_error_message() . '<br />';
		echo $copy_dired->get_error_data() . '<br />';
	}

	// change template name
	$style_file = trailingslashit($to_theme_dir_path) . 'style.css';
	$style_file_contents = $wp_filesystem->get_contents($style_file);
	if ( false === $style_file_contents ) {
		wp_die('read error: ' . $style_file);
	}
	$style_file_contents = preg_replace('/Theme Name: .+/', "Theme Name: $to_theme_name", $style_file_contents);
	if ( false === ($wp_filesystem->put_contents($style_file, $style_file_contents)) ) {
		wp_die('write error: ' . $style_file);
	}
}

function delete_theme_0( $theme_dir_name) {
	global $wp_filesystem;
	
	$theme = wp_get_theme( $theme_dir_name );
	if ( ! $theme->exists() )
		wp_die( 'Theme to be Deleted:' . $theme_dir_name . ' does not exist.' );

	$redirect = wp_nonce_url( 'options-general.php?page=did2_ab_testing_options', '', false, false, array( 'theme_dir' ) );
	if ( false === ($credentials = request_filesystem_credentials($redirect)) ) {
		$data = ob_get_contents();
		ob_end_clean();
		if ( ! empty($data) ){
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! WP_Filesystem($credentials) ) {
		request_filesystem_credentials($redirect, '', true); // Failed to connect, Error and request again
		$data = ob_get_contents();
		ob_end_clean();
		if ( ! empty($data) ) {
			include_once( ABSPATH . 'wp-admin/admin-header.php');
			echo $data;
			include( ABSPATH . 'wp-admin/admin-footer.php');
			exit;
		}
		return;
	}

	if ( ! is_object($wp_filesystem) )
		return new WP_Error('fs_unavailable', __('Could not access filesystem.'));

	if ( is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->get_error_code() )
		return new WP_Error('fs_error', __('Filesystem error.'), $wp_filesystem->errors);

	$themes_dir = $wp_filesystem->wp_themes_dir();
	if ( empty( $themes_dir ) ) {
		return new WP_Error( 'fs_no_themes_dir', __( 'Unable to locate WordPress theme directory.' ) );
	}

	$themes_dir = trailingslashit( $themes_dir );
	$theme_dir_path = trailingslashit($themes_dir . $theme_dir_name);
	
	$active = wp_get_theme()->get_stylesheet();
	
	if ( $active == $theme_dir_name ) {
		return false;
	}
	
	$wp_filesystem->delete( $theme_dir_path, true );
	
	return true;
}

add_action('admin_init', 'did2_ab_testing_process_post');
//add_action('admin_post_duplicate', 'did2_ab_testing_process_post');
function did2_ab_testing_process_post() {
	// ------------------------------------------------------------
	// POST
	// ------------------------------------------------------------
	if ( isset ( $_POST ['Duplicate'] ) && check_admin_referer( 'did2_ab_testing_duplicate', 'did2_ab_testing_nonce' )) {
		//echo $_POST ['copy_from'] . ', ' . $_POST ['new_dir_name'] . ', ' . $_POST ['new_name'];
		duplicate_theme( $_POST ['copy_from'], $_POST ['new_dir_name'], $_POST ['new_name'] );
		wp_redirect( admin_url('options-general.php?page=did2_ab_testing_options&duplicated=true') );
		exit;
	}
	
	if ( isset ( $_POST ['Delete'] ) && check_admin_referer( 'did2_ab_testing_delete', 'did2_ab_testing_nonce' )) {
		$_POST ['theme_dir'];
		$result = delete_theme_0( $_POST ['theme_dir'] );
		if($result) {
			wp_redirect( admin_url('options-general.php?page=did2_ab_testing_options&deleted=true') );
		} else {
			wp_redirect( admin_url('options-general.php?page=did2_ab_testing_options&deleted=current') );
		}
		exit;
	}
	
	if ( isset ( $_POST ['SelectAdminTheme'] ) && check_admin_referer( 'did2_ab_testing_select_admin_theme', 'did2_ab_testing_nonce' )) {
		update_option( 'did2_ab_testing_theme_dir_name_for_admin', $_POST[ 'admin_theme' ]);
		wp_redirect( admin_url('options-general.php?page=did2_ab_testing_options&select_admin_theme=true') );
		exit;
	}
	
	if ( isset ( $_POST ['ResetAuth']) && check_admin_referer( 'did2_ab_testing_reset_auth', 'did2_ab_testing_nonce' )) {
		update_option( 'did2_ab_testing_access_token', "" );
		wp_redirect( admin_url('options-general.php?page=did2_ab_testing_options&reset_auth=true') );
		exit;
	}
	
	if ( isset ( $_POST ['SaveOAuthSettings']) && check_admin_referer( 'did2_ab_testing_save_oauth_settings', 'did2_ab_testing_nonce' )) {
		update_option( 'did2_ab_testing_oauth_client_id', $_POST['did2_ab_testing_oauth_client_id'] );
		update_option( 'did2_ab_testing_oauth_client_secret', $_POST['did2_ab_testing_oauth_client_secret'] );
		wp_redirect( admin_url('options-general.php?page=did2_ab_testing_options&save_oauth_settings=true') );
		exit;
	}
	
	if ( isset ( $_POST ['SaveAccessToken'] ) && check_admin_referer( 'did2_ab_testing_save_access_token', 'did2_ab_testing_nonce' )) {
		try {
		set_include_path ( DID2AB_PATH . '/google-api-php-client/src/'. PATH_SEPARATOR . get_include_path () );
		require_once 'Google/Client.php';
		require_once 'Google/Service/AdSense.php';
		$client = new Google_Client();
		$client->setAccessType ( 'offline' );
		$client->setRedirectUri ( 'urn:ietf:wg:oauth:2.0:oob' );
		
		$client->setClientId ( get_option( 'did2_ab_testing_oauth_client_id' ) );
		$client->setClientSecret ( get_option( 'did2_ab_testing_oauth_client_secret' ) );
		
		$adsense = new Google_Service_AdSense ( $client );
		
		$client->setScopes ( array (
			//"https://www.googleapis.com/auth/adsense.readonly"
			"https://www.googleapis.com/auth/adsense"
		));
		
		$client->authenticate ( $_REQUEST ['did2_ab_testing_access_token'] );
		update_option ( 'did2_ab_testing_access_token_user', 'default');
		update_option ( 'did2_ab_testing_access_token', $client->getAccessToken() );
		
		wp_redirect( admin_url('options-general.php?page=did2_ab_testing_options&save_access_token=true') );
		exit;
		} catch(exception $e) {
			echo $e;
			exit;
		}
	}
}

// generate options page
function did2_ab_testing_options_page() {
session_start();

if ( isset($_GET['duplicated']) && ! isset($_GET['settings-updated']) ) {
	?>
	<div class="updated">
		<p>Duplication Complete.</p>
	</div>
	<?php
}

if ( isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
	?>
	<div class="updated">
		<p>Deletion Complate.</p>
	</div>
	<?php
} else if ( isset($_GET['deleted']) && $_GET['deleted'] == 'current' ) {
	?>
	<div class="updated">
		<p>Could Not be Deleted.</p>
	</div>
	<?php
}

if ( isset($_GET['reset_auth']) && ! isset($_GET['settings-updated']) ) {
	?>
	<div class="updated">
		<p>Reset Authentification Complete.</p>
	</div>
	<?php
}

if ( isset($_GET['save_oauth_settings']) && ! isset($_GET['settings-updated']) ) {
	?>
	<div class="updated">
		<p>Client ID and Client Scecret were saved.</p>
	</div>
	<?php
}

if ( isset ( $_GET ['save_access_token'] ) && ! isset( $_GET['settings-updated']) ) {
	?>
	<div class="updated">
		<p>Access Code was accepted and Access Token was saved.</p>
	</div>
	<?php
}

?>

<div class="wrap">
<h2>did2 A/B Testing Settings</h2>
<hr />

<form method="post" action="options.php">
<?php settings_fields( 'did2_ab_testing_options_group' ); ?>
<?php do_settings_sections( 'did2_ab_testing_options_group' ); ?>
<?php $options = get_option( 'did2_ab_testing_options' ); ?>

<?php if( $can_use_api ) : ?>
<h3>Ratio Settings</h3>
<?php else : ?>
<h3>Ratio Settings and AdSense Channel Settings</h3>
<?php endif; ?>

<?php 
	$can_use_api = !(! get_option( 'did2_ab_testing_access_token') || get_option( 'did2_ab_testing_access_token' ) == "");
?>

<table class="theme_list">
<thead>
	<tr class="group">
		<th rowspan="2">Tools&nbsp;</th>
		<th colspan="3">Template Auto Switch Settings</th>
		<th colspan="1">AdSense Settings</th>
		<?php if( $can_use_api ) : ?>
			<th colspan="2" valign="center" style="vertical-align: center">
				<span id="did2_ab_testing_reset_auth_form" method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
					<table style="width: 100%;"><thead><tr>
					<th>AdSense Report</th>
					<th style="text-align: right;" >
					<input
						type="submit"
						name="ResetAuth"
						class="button"
						value="Clear Auth"
						onclick="
							var form = jQuery('<form/>', {method: 'post', action: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'});
							form.append(jQuery('<?php echo htmlspecialchars( wp_nonce_field('did2_ab_testing_reset_auth', 'did2_ab_testing_nonce', false, false) ); ?>'));
							form.append(jQuery('<input />', {type: 'hidden', name: '_wp_http_referer', value: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'}));
							form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'reset_auth'}));
							form.append(jQuery('<input />', {type: 'hidden', name: 'ResetAuth', value: 'ResetAuth'}));
							form.submit();
							return false;
						"
						style="font-weight: normal;"
					>
					</th>
					</tr></thead></table>
				</span>
			</th>
		<?php else: ?>
			<th rowspan="2">AdSense Report</th>
		<?php endif; ?>
	</tr>
	<tr>
		<th class="theme_name">Theme Name</th>
		<th class="theme_name">Theme Directory Name
		<th>Ratio (%)</th>
		<th>Custom Channel ID</th>
		<?php if( $can_use_api ) : ?>
			<th>PV</th>
			<th>RPM</th>
		<?php else: ?>

		<?php endif; ?>
	</tr>
</thead>

<tbody id="themes">
<?php
	$themes = wp_get_themes();
	
	if( $can_use_api ) {
		$adsense_result = array();
		$adsense_result['MAX_PV'] = 1;
		$adsense_result['MAX_RPM'] = 1.0;
		
		foreach( $themes as $theme_dir_name => $theme) {
			if(isset( $options[ "adsense_custom_channel_id_" . $theme_dir_name ] ) && $options[ "adsense_custom_channel_id_" . $theme_dir_name ] > 0) {
				$channel = $options[ "adsense_custom_channel_id_" . $theme_dir_name ];
				if( get_option( 'did2_ab_testing_access_token' ) ){
					require_once DID2AB_PATH . '/google-adsense-dashboard-for-wp/function.php';
					$auth = new AdSenseAuth();
					$auth->authenticate ( 'default' );
					$adSense = $auth->getAdSenseService();
					
					$from = date ( 'Y-m-d', time () - 13 * 24 * 60 * 60 );
					$to = date ( 'Y-m-d', time ());
					$optParams = array (
						'metric' => array (
							'PAGE_VIEWS', 'PAGE_VIEWS_RPM'
						),
						//'dimension' => 'DATE',//'CUSTOM_CHANNEL_ID',
						//'sort' => 'DATE',//'CUSTOM_CHANNEL_ID',
						'filter' => array(
							'CUSTOM_CHANNEL_ID=@' . $channel
						),
						'useTimezoneReporting' => '1'//get_option ( 'gads_dash_timezone' ) 
					);
					
					try {
						/*$serial = 'gadsdash_qr1' . str_replace ( array (
							',',
							'-',
							date ( 'Y' ) 
							), "", $from . $to . 1 . $query_adsense );
						$transient = get_transient ( $serial );
						*/ //if (empty ( $transient )) {
						//echo 'ppp';
						echo '<!-- ';
						var_dump ($optParams);
						echo ' -->';
						//$from = '2015-01-30';
						//$to = '2015-02-04';
							$data = $adSense->reports->generate ( $from, $to, $optParams );
						//	set_transient ( $serial, $data, 60/*get_option ( 'gads_dash_cachetime' )*/ );
							echo '<!-- ';
							var_dump( $data );
							echo ' -->';
							//echo '<b>' . $data['totals'][0] . '</b>';
							$adsense_result[$theme_dir_name]['PV'] = $data['totals'][0];
							$adsense_result['MAX_PV'] = max( $adsense_result[$theme_dir_name]['PV'], $adsense_result['MAX_PV'] );
							$adsense_result[$theme_dir_name]['RPM'] = $data['totals'][1];
							$adsense_result['MAX_RPM'] = max( $adsense_result[$theme_dir_name]['RPM'], $adsense_result['MAX_RPM'] );
						//} else {
						//	$data = $transient;
						//}
					} catch ( exception $e ) {
						//if (get_option ( '_token' )) {
							//echo did2_ab_testing_pretty_error ( $e );
							$adsense_result[$theme_dir_name]['PV'] = -1;
							$adsense_result[$theme_dir_name]['RPM'] = -1;
							echo '<!--' . $e . '-->';
							return;
						//}
					}
				} else {
					echo 'no access token';
				}
			} else {
				//$adsense_result[$theme_dir_name]['RPM'] = 0;
			}
		}
	}
?>

<?php foreach( $themes as $theme_dir_name => $theme ) : ?>
	<?php $theme_dir_name_esc = str_replace( array( '.', '-' ), array( '_dot_', '_minus_' ), $theme_dir_name ); ?>
	
	<tr class="theme">
		<td class="tool_buttons">
			
			<input type="button" value="Edit" class="button" onclick="window.open('<?php echo admin_url('tools.php?page=did2_ab_testing_theme_editor&theme=' . $theme_dir_name); ?>');" />
			
			<input type="button" value="Copy" class="button" onclick="jQuery('#duplicate_theme_<?php echo $theme_dir_name_esc; ?>').fadeIn();" />
			
			<input type="button" value="Delete" class="button" onclick="jQuery('#delete_theme_<?php echo $theme_dir_name_esc; ?>').fadeIn();" />
			
			<!-- diff -->
			<script type="text/javascript">
			<!--

			// -->
			</script>
			<input type="button" value="Diff" class="button button_diff button_diff_a" id="button_diff_a_<?php echo $theme_dir_name_esc; ?>" onclick="
				if(jQuery(this).hasClass('diff_a_selected')) {
					jQuery(this).removeClass('diff_a_selected');
					jQuery('.button_diff_a').attr('disabled', false);
					jQuery('.button_diff_a').attr('value', 'Diff');
					jQuery('.button_diff_b').hide();
					jQuery('.button_diff_cancel').hide();
				} else {
					jQuery(this).addClass('diff_a_selected');
					jQuery('#button_diff_cancel_<?php echo $theme_dir_name_esc; ?>').fadeIn();
					jQuery('.button_diff_b_<?php echo $theme_dir_name_esc; ?>').fadeIn();
					jQuery('.button_diff_a').attr('value', 'Diff(A)');
					jQuery('.button_diff_a').not('#button_diff_a_<?php echo $theme_dir_name_esc; ?>').attr('disabled', true);
				}" />
			<?php foreach( $themes as $theme_dir_name_diff => $theme_diff ) : ?>
				<?php $theme_dir_name_diff_esc = str_replace( array( '.', '-' ), array( '_dot_', '_minus_' ), $theme_dir_name_diff ); ?>
				<?php if ( $theme_dir_name_diff == $theme_dir_name ) : ?>
					<input type="button" value="Cancel" class="button button_diff_cancel" id="button_diff_cancel_<?php echo $theme_dir_name_esc; ?>" style="display: none;"
						onclick="
							jQuery('#button_diff_a_<?php echo $theme_dir_name_esc; ?>').removeClass('diff_a_selected');
							jQuery('.button_diff_a').attr('disabled', false);
							jQuery('.button_diff_a').attr('value', 'Diff');
							jQuery('.button_diff_b').hide();
							jQuery('.button_diff_cancel').hide();
						"
					/>
				<?php else : ?>
					<input type="button" value="Diff(B)" class="button button_diff button_diff_b button_diff_b_<?php echo $theme_dir_name_diff_esc; ?>" style="display: none;"
						onclick="
							var form = jQuery('<form/>', {method: 'get', action: 'tools.php'});
							form.append(jQuery('<input />', {type: 'hidden', name: 'page', value: 'did2-ab-testing/diff-themes.php'}));
							form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'diff'}));
							form.append(jQuery('<input />', {type: 'hidden', name: 'theme_a', value: '<?php echo $theme_dir_name; ?>'}));
							form.append(jQuery('<input />', {type: 'hidden', name: 'theme_b', value: '<?php echo $theme_dir_name_diff; ?>'}));
							form.submit();
							return false;
						"
					/>
				<?php endif; ?>
			<?php endforeach; ?>
		</td>
		<td class="theme_name">
			<?php if ( $theme_dir_name == get_option( 'did2_ab_testing_theme_dir_name_for_admin' ) ) : ?>
				<a
					class="admin_theme"
					href="#"
					onclick="
						var form = jQuery('<form/>', {method: 'post', action: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'});
						form.append(jQuery('<?php echo htmlspecialchars( wp_nonce_field('did2_ab_testing_select_admin_theme', 'did2_ab_testing_nonce', false, false) ); ?>'));
						form.append(jQuery('<input />', {type: 'hidden', name: '_wp_http_referer', value: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'}));
						form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'select_admin_theme'}));
						form.append(jQuery('<input />', {type: 'hidden', name: 'SelectAdminTheme', value: 'SelectAdminTheme'}));
						form.append(jQuery('<input />', {type: 'hidden', name: 'admin_theme', value: ''}));
						form.submit();
						return false;
					"
				>[admin]</a>
			<?php else : ?>
				<a
					class="not_admin_theme"
					href="#"
					onclick="
						var form = jQuery('<form/>', {method: 'post', action: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'});
						form.append(jQuery('<?php echo htmlspecialchars( wp_nonce_field('did2_ab_testing_select_admin_theme', 'did2_ab_testing_nonce', false, false) ); ?>'));
						form.append(jQuery('<input />', {type: 'hidden', name: '_wp_http_referer', value: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'}));
						form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'select_admin_theme'}));
						form.append(jQuery('<input />', {type: 'hidden', name: 'SelectAdminTheme', value: 'SelectAdminTheme'}));
						form.append(jQuery('<input />', {type: 'hidden', name: 'admin_theme', value: '<?php echo $theme_dir_name; ?>'}));
						form.submit();
						return false;
					"
				>[admin]</a>
			<?php endif; ?>
			
			<?php echo $theme->get( 'Name' ); ?>
		</td>
		<td class="theme_name">
			<?php echo $theme_dir_name; ?>
		</td>
		<td class="ratio">
			<input
				type="text"
				name="did2_ab_testing_options[<?php echo $theme_dir_name; ?>]"
				value="<?php echo ( isset( $options[ $theme_dir_name ] ) ? $options[ $theme_dir_name ] : 0 ); ?>"
			>
		</td>
		<td class="custom_channel_id">
			<input
				type="text"
				name="did2_ab_testing_options[adsense_custom_channel_id_<?php echo $theme_dir_name; ?>]"
				value="<?php echo ( isset( $options[ "adsense_custom_channel_id_" . $theme_dir_name ] ) ? $options[ "adsense_custom_channel_id_" . $theme_dir_name ] : 0 ); ?>"
				size="10"
			>
			<?php if ( $can_use_api ) : ?>
				<input type="button" name="did2_ab_testing_new_custom_channel_<?php echo $theme_dir_name_esc; ?>" value="Get" class="button"
					onclick="jQuery('#create_custom_channel_<?php echo $theme_dir_name_esc; ?>').fadeIn();"
				/>
			<?php else : ?>
				<input type="button" name="did2_ab_testing_new_custom_channel_<?php echo $theme_dir_name_esc; ?>" value="Get" class="button"
					onclick='window.open("https://www.google.com/adsense/app#myads-viewall-channels/product=SELF_SERVICE_CONTENT_ADS")'
				/>
			<?php endif; ?>
		</td>
		<?php if( $can_use_api ) : ?>
			<td class="pv">
				<?php
					$pv = $adsense_result[$theme_dir_name]['PV'];
					if ( $pv >= 0 ) :
						$pv_percentage = (100 * $pv) / $adsense_result['MAX_PV'];
				?>
						<span class="val"><?php echo $pv; ?></span><div class="max"><span class="bar" style="width: <?php echo $pv_percentage; ?>%;">&nbsp;</span></div>
				<?php else : ?>
						<span class="val">FATAL ERROR</span>
				<?php endif; ?>
			</td>
			<td class="rpm">
				<?php
					$rpm = $adsense_result[$theme_dir_name]['RPM'];
					if ( $rpm >= 0) :
						$rpm_percentage = (100 * $rpm) / $adsense_result['MAX_RPM'];
				?>
					<span class="val"><?php echo $rpm; ?></span><div class="max"><span class="bar" style="width: <?php echo $rpm_percentage; ?>%;">&nbsp;</span></div>
				<?php else : ?>
					<span class="val">FATAL ERROR</span>
				<?php endif; ?>
			</td>
		<?php else : ?>
			<?php if ( ! $auth_form_printed ) : ?>
				<?php $auth_form_printed = true; ?>
			
				<td rowspan="<?php echo count($themes); ?>" id="google_auth">
					<ol>
						<li>Access to the <a href="https://console.developers.google.com/" target="_blank" title="google developers console">google developers console</a> and create new project.</li>
						<li>Generate Client ID and Client Secret for native applications.</li>
						<li>Save them via the following form.</li>
					</ol>
					<div id="did2_ab_testing_save_oauth_settings_form" method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
						<table class="">
							<tbody id="">
								<tr valign="center">
									<th scope="row" style="text-align: left;">Client ID</th>
									<td>
										<input type="text" name="did2_ab_testing_oauth_client_id" value="<?php echo get_option( 'did2_ab_testing_oauth_client_id' ); ?>" size="35" />
									</td>
								</tr>
								<tr valign="center">
									<th scope="row" style="text-align: left;">Client Secret</th>
									<td>
										<input type="text" name="did2_ab_testing_oauth_client_secret" value="<?php echo get_option( 'did2_ab_testing_oauth_client_secret' ); ?>" size="35" />
									</td>
								</tr>
							</tbody>
						</table>
						<input
							type="submit"
							name="SaveOAuthSettings"
							class="button button-primary"
							value="Save Client ID and Client Secret"
							onclick="
								var form = jQuery('<form/>', {method: 'post', action: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'});
								jQuery('#did2_ab_testing_save_oauth_settings_form input').each(function(){
									var input = jQuery(this);
									form.append(jQuery('<input />', {type: input.attr('type'), name: input.attr('name'), value: input.attr('value')}));
								});
								form.append(jQuery('<?php echo htmlspecialchars( wp_nonce_field('did2_ab_testing_save_oauth_settings', 'did2_ab_testing_nonce', false, false) ); ?>'));
								form.append(jQuery('<input />', {type: 'hidden', name: '_wp_http_referer', value: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'}));
								form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'save_oauth_settings'}));
								form.append(jQuery('<input />', {type: 'hidden', name: 'SaveOAuthSettings', value: 'SaveOAuthSettings'}));
								form.submit();
								return false;
							"
						/>
					</div>
					
					<?php if( get_option( 'did2_ab_testing_oauth_client_id' ) && get_option( 'did2_ab_testing_oauth_client_secret' )) : ?>
						<?php
						try {
							set_include_path ( DID2AB_PATH . '/google-api-php-client/src/'. PATH_SEPARATOR . get_include_path () );
							require_once 'Google/Client.php';
							require_once 'Google/Service/AdSense.php';
							
							$client = new Google_Client();
							$client->setAccessType ( 'offline' );
							$client->setRedirectUri ( 'urn:ietf:wg:oauth:2.0:oob' );
							
							$client->setClientId ( get_option( 'did2_ab_testing_oauth_client_id' ) );
							$client->setClientSecret ( get_option( 'did2_ab_testing_oauth_client_secret' ) );
							
							$adsense = new Google_Service_AdSense ( $client );
							
							$client->setScopes ( array (
								//"https://www.googleapis.com/auth/adsense.readonly"
								"https://www.googleapis.com/auth/adsense"
							));
							$authUrl = $client->createAuthUrl ();
						?>
							
							<ol start="4">
								<li>Access to an <a href="<?php echo $authUrl; ?>" target="_blank">auth page</a> and obtain your access code.</li>
								<li>Save the token via the following form.</li>
							</ol>
							<div id="did2_ab_testing_save_access_code" name="input" action="#" method="POST">
								<table class="">
									<tbody id="">
										<tr valign="center">
											<th scope="row" style="text-align: left;"><b><?php echo __ ( "Access Code", 'did2_ab_testing' ); ?></b></th>
											<td>
												<input type="text" name="did2_ab_testing_access_token" value="" size="35">
											</td>
										</tr>
									</tbody>
								</table>

								<input type="submit" class="button button-primary" name="did2_ab_testing_authorize" value="<?php echo __ ( "Save Access Code", 'did2_ab_testing' ); ?>"
									onclick = "
										var form = jQuery('<form/>', {method: 'post', action: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'});
										jQuery('#did2_ab_testing_save_access_code input').each(function(){
											var input = jQuery(this);
											form.append(jQuery('<input />', {type: input.attr('type'), name: input.attr('name'), value: input.attr('value')}));
										});
										form.append(jQuery('<?php echo htmlspecialchars( wp_nonce_field('did2_ab_testing_save_access_token', 'did2_ab_testing_nonce', false, false) ); ?>'));
										form.append(jQuery('<input />', {type: 'hidden', name: '_wp_http_referer', value: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'}));
										form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'save_oauth_settings'}));
										form.append(jQuery('<input />', {type: 'hidden', name: 'SaveAccessToken', value: 'SaveAccessToken'}));
										form.submit();
										return false;		
									"
								/>
							</div>
						<?php
						} catch (exception $e ) {
							echo $e;
							return;
						}
						?>
					<?php endif; ?>
				</td>
			<?php endif; ?>
		<?php endif; ?>
	</tr>
	
	<tr id="duplicate_theme_<?php echo $theme_dir_name_esc; ?>" class="tools duplicate_theme">
		<script type="text/javascript">
		<!--
			function did2_ab_testing_duplicate_template_form_<?php echo $theme_dir_name_esc; ?>() {
				var form = jQuery('<form/>', {method: 'post', action: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'});
				jQuery('#duplicate_theme_<?php echo $theme_dir_name_esc; ?> input').each(function(){
					var input = jQuery(this);
					form.append(jQuery('<input />', {type: input.attr('type'), name: input.attr('name'), value: input.attr('value')}));
				});
				form.append(jQuery('<?php echo wp_nonce_field('did2_ab_testing_duplicate', 'did2_ab_testing_nonce', false, false); ?>'));
				form.append(jQuery('<input />', {type: 'hidden', name: '_wp_http_referer', value: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'}));
				form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'duplicate'}));
				form.append(jQuery('<input />', {type: 'hidden', name: 'copy_from', value: '<?php echo $theme_dir_name; ?>'}));
				form.append(jQuery('<input />', {type: 'hidden', name: 'Duplicate', value: 'Duplicate'}));
				form.submit();
				return false;
			}
		// -->
		</script>
		<?php
			$timestamp = date( 'Y-m-d-H-i', current_time('timestamp', 0) );
			$new_theme_name = preg_replace('/20[0-9][0-9]-[0-1][0-9]-[0-3][0-9]-[0-2][0-9]-[0-5][0-9]/', $timestamp, $theme->get('Name'));
			if ( $new_theme_name == $theme->get('Name') ) {
				$new_theme_name .= '_' . $timestamp;
			}
			$new_theme_dir_name = preg_replace('/20[0-9][0-9]-[0-1][0-9]-[0-3][0-9]-[0-2][0-9]-[0-5][0-9]/', $timestamp, $theme_dir_name);
			if ( $new_theme_dir_name == $theme_dir_name ) {
				$new_theme_dir_name .= '_' . $timestamp;
			}
		?>
		<td></td>
		<td class="theme_name">
			New Template Name:<br />
			<input type="text" name="new_name" value="<?php echo $theme->get('Name') . '_' . date( 'Y-m-d-H-i', current_time('timestamp', 0) ); ?>" />
		</td>
		<td class="theme_name">
			New Theme Directory Name:<br />
			<input type="text" name="new_dir_name" value="<?php echo $theme_dir_name . '_' . date( 'Y-m-d-H-i', current_time('timestamp', 0) ); ?>" />
		</td>
		<?php if( $can_use_api ) : ?>
			<td colspan="4" class="tool_buttons">
				<input type="submit" name="Duplicate" value="New" class="button button-primary" onclick="did2_ab_testing_duplicate_template_form_<?php echo $theme_dir_name_esc; ?>(); return false;" />
				<input type="button" name="cancel-copy-<?php echo $theme_dir_name_esc; ?>" value="Cancel" class="button"
					onclick="jQuery('#duplicate_theme_<?php echo $theme_dir_name_esc; ?>').hide();"
				/>
			</td>
		<?php else: ?>
			<td colspan="1" class="tool_buttons">
				<input type="submit" name="copy-<?php echo $theme_dir_name_esc; ?>" value="New" class="button button-primary" onsubmit="did2_ab_testing_duplicate_template_form_<?php echo $theme_dir_name_esc; ?>(); return false;" />
				<input type="button" name="cancel-copy-<?php echo $theme_dir_name_esc; ?>" value="Cancel" class="button"
					onclick="jQuery('#duplicate_theme_<?php echo $theme_dir_name_esc; ?>').hide();"
				/>
			</td>
		<?php endif; ?>
	</tr>
	
	<tr id="delete_theme_<?php echo $theme_dir_name_esc; ?>" class="tools delete_theme">
		<script type="text/javascript">
		<!--
			function did2_ab_testing_delete_template_form_<?php echo $theme_dir_name_esc; ?>() {
				var form = jQuery('<form/>', {method: 'post', action: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'});
				form.append(jQuery('<?php echo wp_nonce_field('did2_ab_testing_delete', 'did2_ab_testing_nonce', false, false); ?>'));
				form.append(jQuery('<input />', {type: 'hidden', name: '_wp_http_referer', value: '<?php echo esc_url($_SERVER['REQUEST_URI']); ?>'}));
				form.append(jQuery('<input />', {type: 'hidden', name: 'theme_dir', value: '<?php echo $theme_dir_name; ?>'}));
				form.append(jQuery('<input />', {type: 'hidden', name: 'action', value: 'delete'}));
				form.append(jQuery('<input />', {type: 'hidden', name: 'Delete', value: 'Delete'}));
				form.submit();
				return false;
			}
		// -->
		</script>
		<td></td>
		<td colspan="2" class="message">
			Are you sure you want to delete '<?php echo $theme_dir_name; ?>' directory?
		</td>
		<?php if( $can_use_api ) : ?>
			<td colspan="4" class="tool_buttons">
				<input type="submit" name="Delete" value="Delete" class="button button-primary" onclick="did2_ab_testing_delete_template_form_<?php echo $theme_dir_name_esc; ?>(); return false;" />
				<input type="button" name="cancel-delete-<?php echo $theme_dir_name_esc; ?>" value="Cancel" class="button"
					onclick="jQuery('#delete_theme_<?php echo $theme_dir_name_esc; ?>').hide();"
				/>
			</td>
		<?php else: ?>
			<td colspan="1" class="tool_buttons">
				<input type="submit" name="Delete" value="Delete" class="button button-primary" onsubmit="did2_ab_testing_delete_template_form_<?php echo $theme_dir_name_esc; ?>(); return false;" />
				<input type="button" name="cancel-delete-<?php echo $theme_dir_name_esc; ?>" value="Cancel" class="button"
					onclick="jQuery('#delete_theme_<?php echo $theme_dir_name_esc; ?>').hide();"
				/>
			</td>
		<?php endif; ?>
	</tr>
	
	<tr id='create_custom_channel_<?php echo $theme_dir_name_esc; ?>' class="tools create_custom_channel">
		<td></td>
		<td colspan="4" class="message" style="text-align: left;">
			<p>
			Unfortunately Google AdSense Management API does not support any features to create new custom channels.<br />
			So, you have to visit AdSense page and create new custom channels manually.
			</p>
			<p>
			Instructions to get a new 'Custom Channel ID':
			</p>
			<ol>
				<li>Visit <a href="https://www.google.com/adsense/app#myads-viewall-channels/product=SELF_SERVICE_CONTENT_ADS" target="_blank">My ads&gt;Content&gt;Custom Channels</a>.</li>
				<li>Click [New custom channel] button.</li>
				<li>
					Enter a new custom channel's name in [Name] text box <br />
					(You may copy and use
						<input type="text" value="did2_ab_testing_<?php echo $theme_dir_name; ?>" size="40"
							onclick="this.select(0, this.value.length)"
						/>
					for the name).
				</li>
				<li>Click [Save] button (Do NOT select any units in [Ad units]).</li>
				<li>Find the new custom channel from the list and Copy its [ID]</li>
			</ol>
			<p>
			Instructions to register the new 'Custom Channel ID' for a theme:
			</p>
			<ol>
				<li>Come back to this page and Enter the ID to [Custom Channel ID] text box in the table.</li>
				<li>Click [Save] button.</li>
			</ol>
			<p>
			<input type="button" name="cancel_create_custom_channel_<?php echo $theme_dir_name_esc; ?>" value="Close" class="button"
				onclick="jQuery('#create_custom_channel_<?php echo $theme_dir_name_esc; ?>').hide();"
			/>
			</p>
		</td>
	</tr>
<?php endforeach; ?>

</tbody>
</table>

<?php submit_button(); ?>

<h4>How to use &quot;adsense custom channel id&quot;</h4>

<p>In templates, you can call &quot;did2_ab_testing_adsense_custom_channel()&quot; function, which returns (not prints) <b>adsense custom channel id</b> configured in this page for the selected template.</p>
<h5>A sample for synchronized ad code:</h5>
<blockquote>
<pre>&lt;script&gt;
...
google_ad_width = 728;
google_ad_height = 90;
&lt;?php if ( function_exists ( &quot;did2_ab_testing_adsense_custom_channel&quot; ) ) : ?&gt;
    google_ad_channel = &quot;&lt;?php echo did2_ab_testing_adsense_custom_channel(); ?&gt;&quot;;
&lt;?php endif; ?&gt;
&lt;/script&gt;</pre>
</blockquote>

<h5>Another sample for asynchronized ad code:</h5>
<blockquote>
<pre>(adsbygoogle = window.adsbygoogle || []).push({
&lt;?php if ( function_exists ( &quot;did2_ab_testing_adsense_custom_channel&quot; ) ) : ?&gt;
    params: { google_ad_channel: &quot;&lt;?php echo did2_ab_testing_adsense_custom_channel(); ?&gt;&quot;}
&lt;?php endif; ?&gt;
});</pre>
</blockquote>

<h5>Reference</h5>
https://support.google.com/adsense/answer/1354736?hl=en

</form>

<h3>Global Settings</h3>

<?php
}

// call session_start function
function did2_ab_testing_init_session_start() {
	session_start ();
}

// switch themes
function did2_ab_testing_setup_theme() {
	$t = $_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ];
	$s = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ($t != NULL || $s != NULL){
		// do not switch twice at one session.
	}

	$options = get_option( 'did2_ab_testing_options' );
	
	$ratios = array();
	$themes = wp_get_themes();
	$sum = 0;
	foreach( $themes as $theme_dir_name => $theme ) {
		if ( isset ( $options[ $theme_dir_name ] ) ) {
			$ratio = 0 + $options[ $theme_dir_name ] + 0;
		} else {
			$ratio = 0;
		}
		$ratios[ $ratio ] = $theme_dir_name;
		$sum += $ratio;
	}
	if ( $sum != 0 ) {
		// krsort( $ratios );
		$rand = mt_rand( 1 , $sum );
		foreach( $ratios as $ratio => $theme_dir_name ) {
			$rand -= $ratio;
			if ( $rand <= 0 ) {
				$_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ] = $themes[ $theme_dir_name ][ 'Template' ];
				$_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ] = $themes[ $theme_dir_name ][ 'Stylesheet' ];
				break;
			}
		}
	}

	$x_wp_template = $_SERVER[ 'HTTP_X_WP_TEMPLATE' ];
	if($x_wp_template != "" && isset($themes[$x_wp_template])) {
		$_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ] = $themes[ $x_wp_template ][ 'Template' ];
		$_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ] = $themes[ $x_wp_template ][ 'Stylesheet' ];
	}
	
	return true;
}

function did2_ab_testing_template_filter( $template ) {
	//if ( is_user_logged_in() && strlen( get_option( 'did2_ab_testing_theme_dir_name_for_admin' ) ) > 0) {
	if ( current_user_can('manage_options') && strlen( get_option( 'did2_ab_testing_theme_dir_name_for_admin' ) ) > 0) {
		$themes = wp_get_themes();
		foreach( $themes as $theme_dir_name => $theme) {
			if ( $theme_dir_name == get_option( 'did2_ab_testing_theme_dir_name_for_admin' ) ) {
				return $themes[ $theme_dir_name ][ 'Template' ];
			}
		}
	}
	
	$t = $_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ];
	$s = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ($t != NULL && $s != NULL){
		return $t;
	}
	return $template;
}

function did2_ab_testing_stylesheet_filter( $stylesheet ) {
	if ( current_user_can('manage_options') && strlen( get_option( 'did2_ab_testing_theme_dir_name_for_admin' ) ) > 0) {
		$themes = wp_get_themes();
		foreach( $themes as $theme_dir_name => $theme) {
			if ( $theme_dir_name == get_option( 'did2_ab_testing_theme_dir_name_for_admin' ) ) {
				return $themes[ $theme_dir_name ][ 'Stylesheet' ];
			}
		}
	}
	
	$t = $_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ];
	$s = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ($t != NULL && $s != NULL){
		return $s;
	}
	return $stylesheet;
}

function did2_ab_testing_start_buffering() {
	ob_start('did2_ab_testing_end_buffering');
}

function did2_ab_testing_end_buffering( $content ) {
	if ( stripos($content,"<html") === false || stripos($content,"<xsl:stylesheet") !== false ) { return $content;}
	
	if ( function_exists ( "did2_ab_testing_adsense_custom_channel_id" ) ) {
		// for asynchronized code
		$content = preg_replace(preg_quote('/(adsbygoogle = window.adsbygoogle || []).push({})/'),
		'(adsbygoogle = window.adsbygoogle || []).push({ params: { google_ad_channel:"' . did2_ab_testing_adsense_custom_channel_id() . '"}})', $content);
		
		// for synchronized ad code
		$content = preg_replace(preg_quote('/google_ad_height = /'),
		'google_ad_channel = "' . did2_ab_testing_adsense_custom_channel_id() . '"; google_ad_height = ', $content);
	}
	
	return $content;
}

// ------------------------------------------------------------
// user functions
// ------------------------------------------------------------
function did2_ab_testing_adsense_custom_channel_id() {
	$options = get_option( 'did2_ab_testing_options' );
	$theme_dir_name = wp_get_theme()->get_stylesheet();
	//$theme_dir_name = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ( isset( $options[ "adsense_custom_channel_id_" . $theme_dir_name ] ) ) {
		return $options[ "adsense_custom_channel_id_" . $theme_dir_name ];
	} else {
		return "";
	}
}

?>