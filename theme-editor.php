<?php

if ( is_admin() ) {
    add_action('admin_menu', 'did2_ab_testing_admin_menu_hook_theme_editor' );
	add_action('load-tools_page_did2_ab_testing_theme_editor', 'did2_ab_testing_theme_editor_can_redirect_hook' );
}

function did2_ab_testing_admin_menu_hook_theme_editor() {
	$handle = add_management_page('Theme Editor - Did2 AB Testing', 'Theme Editor', 'edit_themes', 'did2_ab_testing_theme_editor', 'did2_ab_testing_create_theme_editor_page');
	add_action('admin_print_styles-' . $handle, 'did2_ab_testing_enqueue_scripts');
}

function did2_ab_testing_create_theme_editor_page() {
	?>
	<?php
	did2_ab_testing_create_theme_editor_editor();
}

function did2_ab_testing_create_theme_editor_editor() {
	//wp_reset_vars( array( 'action', 'error', 'file', 'theme' ) );
//require_once( dirname( __FILE__ ) . '/../../../wp-admin/admin.php' );

	$theme = $_REQUEST['theme'];
	$action = $_REQUEST['action'];
	$error = $_REQUEST['error'];
	$file = $_REQUEST['file'];

	if ( $theme ) {
		$stylesheet = $theme;
	} else {
		$stylesheet = get_stylesheet();
	}

	$theme = wp_get_theme( $stylesheet );


	if ( ! $theme->exists() )
		wp_die( __( 'The requested theme does not exist.' ) );
	if ( $theme->errors() && 'theme_no_stylesheet' == $theme->errors()->get_error_code() )
		wp_die( __( 'The requested theme does not exist.' ) . ' ' . $theme->errors()->get_error_message() );

	$allowed_files = $theme->get_files( 'php', 1 );
	$has_templates = ! empty( $allowed_files );
	$style_files = $theme->get_files( 'css' );
	$allowed_files['style.css'] = $style_files['style.css'];
	$allowed_files += $style_files;

	$file = $_REQUEST['file'];

	if ( empty( $file ) ) {
		$relative_file = 'style.css';
		$file = $allowed_files['style.css'];
	} else {
		$relative_file = $file;
		$file = $theme->get_stylesheet_directory() . '/' . $relative_file;
	}

	validate_file_to_edit( $file, $allowed_files );
	$scrollto = isset( $_REQUEST['scrollto'] ) ? (int) $_REQUEST['scrollto'] : 0;

	switch( $action ) {
	case 'update':
		check_admin_referer( 'edit-theme_' . $file . $stylesheet );
		$newcontent = wp_unslash( $_POST['newcontent'] );
//echo 'test1';
		$location = self_admin_url('tools.php?page=did2_ab_testing_theme_editor') . '&file=' . urlencode( $relative_file ) . '&theme=' . urlencode( $stylesheet ) . '&scrollto=' . $scrollto;
//echo $location;
		if ( is_writeable( $file ) ) {
			// is_writable() not always reliable, check return value. see comments @ http://uk.php.net/is_writable
			$f = fopen( $file, 'w+' );
//echo 'test2';
			if ( $f !== false ) {
//echo 'test3';
				fwrite( $f, $newcontent );
				fclose( $f );
				$location .= '&updated=true';
				$theme->cache_delete();
			}
		}
		wp_redirect( $location );
		exit;
	default:
		require_once( ABSPATH . 'wp-admin/admin-header.php' );
		update_recently_edited( $file );
		if ( ! is_file( $file ) )
			$error = true;
		$content = '';
		if ( ! $error && filesize( $file ) > 0 ) {
			$f = fopen($file, 'r');
			$content = fread($f, filesize($file));
			$file_ext = substr( $file, strrpos( $file, '.' ) );
			if ( '.php' == $file_ext ) {
				$functions = wp_doc_link_parse( $content );
				$docs_select = '<select name="docs-list" id="docs-list">';
				$docs_select .= '<option value="">' . esc_attr__( 'Function Name&hellip;' ) . '</option>';
				foreach ( $functions as $function ) {
					$docs_select .= '<option value="' . esc_attr( urlencode( $function ) ) . '">' . htmlspecialchars( $function ) . '()</option>';
				}
				$docs_select .= '</select>';
			}
			$content = esc_textarea( $content );
		}
		?>
		<h2><?php echo __('Edit Themes') . ' by did2 A/B Testing'; ?></h2>
		<?php
		if ( isset( $_GET['updated'] ) ) : ?>
			<div id="message" class="updated"><p><?php _e( 'File edited successfully.' ) ?></p></div>
		<?php endif;

	$description = get_file_description( $file );
	$file_show = array_search( $file, array_filter( $allowed_files ) );
	if ( $description != $file_show )
		$description .= ' <span>(' . $file_show . ')</span>';
	?>
	<div class="fileedit-sub">
	<div class="alignleft">
	<h3><?php echo $theme->display('Name'); if ( $description ) echo ': ' . $description; ?></h3>
	</div>
	<div class="alignright">
		<form action="" method="post">
			<strong><label for="theme"><?php _e('Select theme to edit:'); ?> </label></strong>
			<select name="theme" id="theme">
	<?php
	foreach ( wp_get_themes( array( 'errors' => null ) ) as $a_stylesheet => $a_theme ) {
		if ( $a_theme->errors() && 'theme_no_stylesheet' == $a_theme->errors()->get_error_code() )
			continue;
		$selected = $a_stylesheet == $stylesheet ? ' selected="selected"' : '';
		echo "\n\t" . '<option value="' . esc_attr( $a_stylesheet ) . '"' . $selected . '>' . $a_theme->display('Name') . '</option>';
	}
	?>
			</select>
			<?php submit_button( __( 'Select' ), 'button', 'Submit', false ); ?>
		</form>
	</div>
	<br class="clear" />
	</div>
	<?php
		if ( $theme->errors() )
		echo '<div class="error"><p><strong>' . __( 'This theme is broken.' ) . '</strong> ' . $theme->errors()->get_error_message() . '</p></div>';
	?>
		<div id="templateside">
	<?php
	if ( $allowed_files ) :
		if ( $has_templates || $theme->parent() ) :
	?>
		<h3><?php _e('Templates'); ?></h3>
		<?php if ( $theme->parent() ) : ?>
		<p class="howto">
			<?php
			printf( __( 'This child theme inherits templates from a parent theme, %s.' ),
				'<a href="' . plugin_dir_path( __FILE__ ) . 'did2_ab_testing_theme_editor?theme=' . urlencode( $theme->get_template() ) . '">' . $theme->parent()->display('Name') . '</a>' );
			?>
		</p>		
		<?php endif; ?>
		<ul>
		<?php
		endif;
		foreach ( $allowed_files as $filename => $absolute_filename ) :
			if ( 'style.css' == $filename )
				echo "\t</ul>\n\t<h3>" . _x( 'Styles', 'Theme stylesheets in theme editor' ) . "</h3>\n\t<ul>\n";
			$file_description = get_file_description( $absolute_filename );
			if ( $file_description != basename( $filename ) )
				$file_description .= '<br /><span class="nonessential">(' . $filename . ')</span>';
			if ( $absolute_filename == $file )
				$file_description = '<span class="highlight">' . $file_description . '</span>';
			?>
			<li><a href="<?php echo self_admin_url('tools.php?page=did2_ab_testing_theme_editor'); ?>&file=<?php echo urlencode( $filename ) ?>&amp;theme=<?php echo urlencode( $stylesheet ) ?>"><?php echo $file_description; ?></a></li>
			<?php
		endforeach;
		?>
		</ul>
	<?php endif; ?>
	</div>
	<?php if ( $error ) :
		echo '<div class="error"><p>' . __('Oops, no such file exists! Double check the name and try again, merci.') . '</p></div>';
	else : ?>
		<div id="editor"><?php echo $content; ?></div>
		<form name="template" id="template" action="tools.php?page=did2_ab_testing_theme_editor" method="post">
			<!--<input type="hidden" name="page" value="did2-ab-testing/theme-editor.php">-->
			<?php wp_nonce_field( 'edit-theme_' . $file . $stylesheet ); ?>
			<div>
				<textarea cols="70" rows="30" name="newcontent" id="newcontent" aria-describedby="newcontent-description"><?php echo $content; ?></textarea>
				<input type="hidden" name="action" value="update" />
				<input type="hidden" name="file" value="<?php echo esc_attr( $relative_file ); ?>" />
				<input type="hidden" name="theme" value="<?php echo esc_attr( $theme->get_stylesheet() ); ?>" />
				<input type="hidden" name="scrollto" id="scrollto" value="<?php echo $scrollto; ?>" />
			</div>
		<?php if ( ! empty( $functions ) ) : ?>
			<div id="documentation" class="hide-if-no-js">
			<label for="docs-list"><?php _e('Documentation:') ?></label>
			<?php echo $docs_select; ?>
			<input type="button" class="button" value=" <?php esc_attr_e( 'Look Up' ); ?> " onclick="if ( '' != jQuery('#docs-list').val() ) { window.open( 'http://api.wordpress.org/core/handbook/1.0/?function=' + escape( jQuery( '#docs-list' ).val() ) + '&amp;locale=<?php echo urlencode( get_locale() ) ?>&amp;version=<?php echo urlencode( $wp_version ) ?>&amp;redirect=true'); }" />
			</div>
		<?php endif; ?>

			<div>
			<?php if ( is_child_theme() && $theme->get_stylesheet() == get_template() ) : ?>
				<p><?php if ( is_writeable( $file ) ) { ?><strong><?php _e( 'Caution:' ); ?></strong><?php } ?>
				<?php _e( 'This is a file in your current parent theme.' ); ?></p>
			<?php endif; ?>
	<?php
		if ( is_writeable( $file ) ) :
			submit_button( __( 'Update File' ), 'primary', 'submit', true );
		else : ?>
	<p><em><?php _e('You need to make this file writable before you can save your changes. See <a href="http://codex.wordpress.org/Changing_File_Permissions">the Codex</a> for more information.'); ?></em></p>
	<?php endif; ?>
			</div>
		</form>
	<?php
	endif; // $error
	?>
	<br class="clear" />
	<script type="text/javascript">
	/* <![CDATA[ */
		jQuery(document).ready(function($){
			$('#template').submit(
				function(){ $('#scrollto').val( $('#newcontent').scrollTop() ); }
			);
			$('#newcontent').scrollTop( $('#scrollto').val() );
		});
		var textarea = jQuery('textarea#newcontent');
		textarea.hide();
		var editor = ace.edit('editor');
		//editor.getSession().setUseWorker(false);
		editor.setTheme('ace/theme/github');
		<?php if ( '.php' == $file_ext ) : ?>
			editor.getSession().setMode('ace/mode/php');
		<?php elseif ( '.css' == $file_ext ) : ?>
			editor.getSession().setMode('ace/mode/css');
		<?php else : ?>
			editor.getSession().setMode('ace/mode/php');
		<?php endif; ?>
		editor.getSession().setUseSoftTabs(false);
		//jQuery('div#editor').height = 500;

		jQuery('form#template input#submit').on('click', function() {
			textarea.val(editor.getSession().getValue());
		});
	/* ]]> */
	</script>
	<?php
	break;
	}
}

function did2_ab_testing_theme_editor_can_redirect_hook() {
	$theme = $_REQUEST['theme'];
	$action = $_REQUEST['action'];
	$error = $_REQUEST['error'];
	$file = $_REQUEST['file'];

	if ( $theme ) {
		$stylesheet = $theme;
	} else {
		$stylesheet = get_stylesheet();
	}

	$theme = wp_get_theme( $stylesheet );


	if ( ! $theme->exists() )
		wp_die( __( 'The requested theme does not exist.' ) );
	if ( $theme->errors() && 'theme_no_stylesheet' == $theme->errors()->get_error_code() )
		wp_die( __( 'The requested theme does not exist.' ) . ' ' . $theme->errors()->get_error_message() );

	$allowed_files = $theme->get_files( 'php', 1 );
	$has_templates = ! empty( $allowed_files );
	$style_files = $theme->get_files( 'css' );
	$allowed_files['style.css'] = $style_files['style.css'];
	$allowed_files += $style_files;

	$file = $_REQUEST['file'];

	if ( empty( $file ) ) {
		$relative_file = 'style.css';
		$file = $allowed_files['style.css'];
	} else {
		$relative_file = $file;
		$file = $theme->get_stylesheet_directory() . '/' . $relative_file;
	}

	validate_file_to_edit( $file, $allowed_files );
	$scrollto = isset( $_REQUEST['scrollto'] ) ? (int) $_REQUEST['scrollto'] : 0;

	switch( $action ) {
	case 'update':
		check_admin_referer( 'edit-theme_' . $file . $stylesheet );
		$newcontent = wp_unslash( $_POST['newcontent'] );
//echo 'test1';
		$location = self_admin_url('tools.php?page=did2_ab_testing_theme_editor') . '&file=' . urlencode( $relative_file ) . '&theme=' . urlencode( $stylesheet ) . '&scrollto=' . $scrollto;
//echo $location;
		if ( is_writeable( $file ) ) {
			// is_writable() not always reliable, check return value. see comments @ http://uk.php.net/is_writable
			$f = fopen( $file, 'w+' );
//echo 'test2';
			if ( $f !== false ) {
				fwrite( $f, $newcontent );
				fclose( $f );
				$location .= '&updated=true';
				$theme->cache_delete();
			}
		}
		wp_redirect( $location );
		exit;
	default:
		break;
	}
}
?>