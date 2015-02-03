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

	add_action('admin_enqueue_scripts', 'did2_ab_testing_enqueue_scripts');
}

//add_action( 'init' , 'did2_ab_testing_init_session_start' );
add_action( 'setup_theme' , 'did2_ab_testing_setup_theme' );
add_action( 'plugins_loaded' , 'did2_ab_testing_plugins_loaded' );

function did2_ab_testing_plugins_loaded() {
    add_filter( 'template' , 'did2_ab_testing_template_filter' );
    add_filter( 'stylesheet' , 'did2_ab_testing_stylesheet_filter' );
}

function did2_ab_testing_admin_menu_hook() {
	$hook = add_options_page( 'did2 A/B Testing' , 'did2 A/B Testing' , 'manage_options' , 'did2_ab_testing_options' , 'did2_ab_testing_options_page' );
}

function did2_ab_testing_register_setting() {
	register_setting( 'did2_ab_testing_options_group' , 'did2_ab_testing_options', 'did2_ab_testing_options_validator' );
}

function did2_ab_testing_options_validator ( $options ) {
	return $options;
}

function did2_ab_testing_enqueue_scripts() {
	wp_enqueue_style('did2_ab_testing_style',  plugins_url('style.css', __FILE__), array());
	wp_enqueue_script('did2_ab_testing_style',  plugins_url('ace/src-noconflict/ace.js', __FILE__), array('jquery'));
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
}

// generate options page
function did2_ab_testing_options_page() {

if ( isset($_GET['duplicated']) && ! isset($_GET['settings-updated']) ) {
	?>
	<div class="updated">
	<p>Duplication Complete.</p>
	</div>
	<?php
}
?>

<div class="wrap">
<h2>did2 A/B Testing Settings</h2>

<form method="post" action="options.php">
<?php settings_fields( 'did2_ab_testing_options_group' ); ?>
<?php do_settings_sections( 'did2_ab_testing_options_group' ); ?>
<?php $options = get_option( 'did2_ab_testing_options' ); ?>

<h3>Google Auth Settings</h3>

<table class="google-account">
<tbody id="google-account">
<tr valign="top">
	<th scope="row">Google Authorization Code for AdSense</th>
	<td>
		<input
			type="text"
			style="width:100%;"
			name="did2_ab_testing_options[google_adsense_authorization_code]"
			value="<?php echo ( isset( $options[ 'google_adsense_authorization_code' ] ) ? $options[ 'google_adsense_authorization_code' ] : '' ); ?>"
		>
		<br />
		Input your authorization code you obtain in <a href="https://accounts.google.com/o/oauth2/auth?scope=https://www.googleapis.com/auth/adsense.readonly&response_type=code&access_type=offline&redirect_uri=urn:ietf:wg:oauth:2.0:oob&approval_prompt=auto&client_id=153026819782-lgu4cg9uepvvi9bj5fhd38v8nq70trr0.apps.googleusercontent.com&hl=ja&from_login=1&as=&pli=1&authuser=0" target="_blank" title="">google.com</a>.
	</td>
</tr>
</tbody>
</table>

<h3>All Templates</h3>

<table class="theme-ratio">
<thead>
	<tr>
		<th>Theme</th>
		<th>Ratio (%)</th>
		<th>AdSense Custom Channel ID</th>
	</tr>
</thead>
<tbody id="themes">
<?php
	$themes = wp_get_themes();
	foreach( $themes as $theme_dir_name => $theme ) {
?>
<tr class="theme">
	<td><?php echo $theme_dir_name; ?></td>
	<td>
		<input
			type="text"
			name="did2_ab_testing_options[<?php echo $theme_dir_name; ?>]"
			value="<?php echo ( isset( $options[ $theme_dir_name ] ) ? $options[ $theme_dir_name ] : 0 ); ?>"
		>
	</td>
	<td>
		<input
			type="text"
			name="did2_ab_testing_options[adsense_custom_channel_id_<?php echo $theme_dir_name; ?>]"
			value="<?php echo ( isset( $options[ "adsense_custom_channel_id_" . $theme_dir_name ] ) ? $options[ "adsense_custom_channel_id_" . $theme_dir_name ] : 0 ); ?>"
		>
	</td>
</tr>
<?php
	}
?>
</tbody>
</table>

<h4>How to use &quot;adsense custom channel id&quot;</h4>
<p>In templates, you can call &quot;did2_ab_testing_adsense_custom_channel()&quot; function, which returns (not prints) adsense custom channel id for the selected template.</p>
<h5>A sample for synchronized ad code:</h5>
<pre>&lt;script&gt;
...
google_ad_width = 728;
google_ad_height = 90;
&lt;?php if ( function_exists ( &quot;did2_ab_testing_adsense_custom_channel&quot; ) ) : ?&gt;
    google_ad_channel = &quot;&lt;?php echo did2_ab_testing_adsense_custom_channel(); ?&gt;&quot;;
&lt;?php endif; ?&gt;
&lt;/script&gt;</pre>
<h5>Another sample for asynchronized ad code:</h5>
<pre>(adsbygoogle = window.adsbygoogle || []).push({
&lt;?php if ( function_exists ( &quot;did2_ab_testing_adsense_custom_channel&quot; ) ) : ?&gt;
    params: { google_ad_channel: &quot;&lt;?php echo did2_ab_testing_adsense_custom_channel(); ?&gt;&quot;}
&lt;?php endif; ?&gt;
});</pre>
<h5>Reference</h5>
https://support.google.com/adsense/answer/1354736?hl=en

<?php submit_button(); ?>
</form>

<h2>Duplicate Template</h2>

<form name="did_ab_testing_duplicate_template_form" method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
	<?php wp_nonce_field('did2_ab_testing_duplicate', 'did2_ab_testing_nonce'); ?>
	<input type="hidden" name="action" value="duplicate">
	<table class="">
	<tbody id="">
	<tr valign="center">
		<th scope="row" style="text-align: left;">Copy FROM</th>
		<td>
			<select name="copy_from">
				<?php
				$themes = wp_get_themes();
				foreach( $themes as $theme_dir_name => $theme ) {
				?>
					<option value="<?php echo $theme_dir_name; ?>"><?php echo $theme->get( 'Name' ) . ' (' . $theme_dir_name . ')' ; ?></option>	
				<?php
				}
				?>
			</select>
		</td>
	</tr>
	<tr valign="center">
		<th scope="row" style="text-align: left;">New Template Name</th>
		<td>
			<input type="text" name="new_name" style="width:100%;" value="New Template Name Here">
		</td>
	</tr>
	<tr valign="center">
		<th scope="row" style="text-align: left;">New Template Directory Name</th>
		<td>
			<input type="text" name="new_dir_name" style="width:100%;" value="new-template-dir-name-here">
		</td>
	</tr>
	</tbody>
	</table>
	<input
		type="submit"
		name="Duplicate"
		class="button button-primary"
		value="Duplicate"
	>
</form>

<h2>Diff Two Templates</h2>

<form name="did_ab_testing_diff_template_form" method="get" action="tools.php">
	<input type="hidden" name="page" value="did2-ab-testing/diff-themes.php">
	<input type="hidden" name="actioon" value="diff">
			<select name="theme_a">
				<?php
				$themes = wp_get_themes();
				foreach( $themes as $theme_dir_name => $theme ) {
				?>
					<option value="<?php echo $theme_dir_name; ?>"><?php echo $theme->get( 'Name' ) . ' (' . $theme_dir_name . ')' ; ?></option>	
				<?php
				}
				?>
			</select>

			<select name="theme_b">
				<?php
				$themes = wp_get_themes();
				foreach( $themes as $theme_dir_name => $theme ) {
				?>
					<option value="<?php echo $theme_dir_name; ?>"><?php echo $theme->get( 'Name' ) . ' (' . $theme_dir_name . ')' ; ?></option>	
				<?php
				}
				?>
			</select>
	<input
		type="submit"
		name="submit"
		class="button button-primary"
		value="Show Diff"
	>
</form>
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
		// return TRUE;
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
	$t = $_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ];
	$s = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ($t != NULL && $s != NULL){
		return $t;
	}
	return $template;
}

function did2_ab_testing_stylesheet_filter( $stylesheet ) {
	$t = $_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ];
	$s = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ($t != NULL && $s != NULL){
		return $s;
	}
	return $stylesheet;
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