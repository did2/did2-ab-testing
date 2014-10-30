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
	add_options_page( 'A/B Testing' , 'A/B Testing' , 'manage_options' , basename( __FILE__ ) , 'did2_ab_testing_options_page' );
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
<h2>Did2 A/B Testing</h2>
<form method="post" action="options.php">
<?php settings_fields( 'did2_ab_testing_options_group' ); ?>
<?php do_settings_sections( 'did2_ab_testing_options_group' ); ?>
<?php $options = get_option( 'did2_ab_testing_options' ); ?>
<table class="theme-ratio">
<thead>
	<tr>
		<th>Theme</th>
		<th>selection ratio (e.g. percentage)</th>
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
</tr>
<?php
	}
?>
</tbody>
</table>
<?php submit_button(); ?>
</form>
</div>
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
	if ( $sum == 0 ) {
		return TRUE;
	} else {
		// ksort( $ratios );
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

?>
