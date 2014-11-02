<?php
/*
Plugin Name: Did2 AB Testing 
Plugin URI: http://did2memo.net/
Description:
	for did2's ab testing
Version: 1.0.0
Author: did2
Author URI: http://did2memo.net/
License: GPL2  
*/

define( 'DID2AB_PATH' , dirname( __FILE__ ) );

if( is_admin() ) {
	// require_once( DID2AB_PATH . '/did2-ab-testing-admin.php' );
	add_action( 'admin_init' , 'did2_ab_testing_register_setting' );
	add_action( 'admin_menu' , 'did2_ab_testing_admin_menu_hook' );
}
add_action( 'init' , 'did2_ab_testing_init_session_start' );
add_action( 'setup_theme' , 'did2_ab_testing_setup_theme' );
add_filter( 'template' , 'did2_ab_testing_template_filter' );
add_filter( 'stylesheet' , 'did2_ab_testing_stylesheet_filter' );

function did2_ab_testing_admin_menu_hook() {
	$hook = add_options_page( 'did2 A/B Testing' , 'did2 A/B Testing' , 'manage_options' , 'did2_ab_testing_options' , 'did2_ab_testing_options_page' );
}

function did2_ab_testing_register_setting() {
	register_setting( 'did2_ab_testing_options_group' , 'did2_ab_testing_options', 'did2_ab_testing_options_validator' );
}

function did2_ab_testing_options_validator ( $options ) {
	return $options;
}

// generate options page
function did2_ab_testing_options_page() {
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
}

function did2_ab_testing_template_filter( $template ) {
	$t = $_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ];
	$s = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ($t == NULL || $s == NULL){
		return $template;
	}
	return $t;
}

function did2_ab_testing_stylesheet_filter( $stylesheet ) {
	$t = $_SESSION[ 'DID2_AB_TESTING_TEMPLATE' ];
	$s = $_SESSION[ 'DID2_AB_TESTING_STYLESHEET' ];
	if ($t == NULL || $s == NULL){
		return $stylesheet;
	}
	return $s;
}

// ------------------------------------------------------------
// user functions
// ------------------------------------------------------------
function did2_ab_testing_adsense_custom_channel_id() {
	$options = get_option( 'did2_ab_testing_options' );
	$theme_dir_name = wp_get_theme()->get_stylesheet();
	if ( isset( $options[ "adsense_custom_channel_id_" . $theme_dir_name ] ) ) {
		return $options[ "adsense_custom_channel_id_" . $theme_dir_name ];
	} else {
		return "";
	}
}

?>