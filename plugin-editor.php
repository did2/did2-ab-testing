<?php

if ( is_admin() ) {
    add_action('admin_init', 'did2_ab_testing_plugin_editor_come_back_redirect' );
    add_action('admin_menu', 'did2_ab_testing_admin_menu_hook_plugin_editor' );
	add_action('load-tools_page_did2_ab_testing_plugin_editor', 'did2_ab_testing_plugin_editor_can_redirect_hook' );
}

function did2_ab_testing_admin_menu_hook_plugin_editor() {
	$handle = add_management_page('Plugin Editor - Did2 AB Testing', 'Plugin Editor', 'edit_plugins', 'did2_ab_testing_plugin_editor', 'did2_ab_testing_create_plugin_editor_page');
	add_action('admin_print_styles-' . $handle, 'did2_ab_testing_enqueue_scripts');
	add_action('admin_print_scripts', 'did2_ab_testing_plugin_editor_js');
}

function did2_ab_testing_create_plugin_editor_page() {
	?>
	<?php
	did2_ab_testing_create_plugin_editor_editor();
}

function did2_ab_testing_create_plugin_editor_editor() {
	if ( is_multisite() && ! is_network_admin() ) {
		wp_redirect( network_admin_url( 'plugin-editor.php' ) );
		exit();
	}

	//if ( !current_user_can('edit_plugins') )
	//	wp_die( __('You do not have sufficient permissions to edit plugins for this site.') );

	$title = __("Edit Plugins");
	$parent_file = 'plugins.php';

	$plugin = $_REQUEST['plugin'];
	$action = $_REQUEST['action'];
	$error = $_REQUEST['error'];
	$file = $_REQUEST['file'];

	$plugins = get_plugins();
	if ( empty( $plugins ) ) {
		include( ABSPATH . 'wp-admin/admin-header.php' );
		?>
		<div class="wrap">
			<h2><?php echo esc_html( $title ); ?></h2>
			<div id="message" class="error"><p><?php _e( 'You do not appear to have any plugins available at this time.' ); ?></p></div>
		</div>
		<?php
		include( ABSPATH . 'wp-admin/admin-footer.php' );
		exit;
	}
	if ( $file ) {
		$plugin = $file;
	} elseif ( empty( $plugin ) ) {
		$plugin = array_keys($plugins);
		$plugin = $plugin[0];
	}

	$plugin_files = get_plugin_files($plugin);

	if ( empty($file) )
		$file = $plugin_files[0];
	$file = validate_file_to_edit($file, $plugin_files);
	$real_file = WP_PLUGIN_DIR . '/' . $file;
	$scrollto = isset($_REQUEST['scrollto']) ? (int) $_REQUEST['scrollto'] : 0;
	switch ( $action ) {
	case 'update':
		wp_die('update action have to be processed before this function.');
	default:

		/* if ( isset($_GET['liveupdate']) ) {
			check_admin_referer('edit-plugin-test_' . $file);
			$error = validate_plugin($file);
			if ( is_wp_error($error) )
				wp_die( $error );
			if ( ( ! empty( $_GET['networkwide'] ) && ! is_plugin_active_for_network($file) ) || ! is_plugin_active($file) )
				activate_plugin(
					$file,
					self_admin_url("tools.php?page=did2_ab_testing_plugin_editor&file=$file&phperror=1"),
					! empty( $_GET['networkwide'] )
				); // we'll override this later if the plugin can be included without fatal error
			wp_redirect( self_admin_url("tools.php?page=did2_ab_testing_plugin_editor&file=$file&a=te&scrollto=$scrollto") );
			exit;
		}
		*/

		// List of allowable extensions
		$editable_extensions = array('php', 'txt', 'text', 'js', 'css', 'html', 'htm', 'xml', 'inc', 'include');

		/**
		 * Filter file type extensions editable in the plugin editor.
		 *
		 * @since 2.8.0
		 *
		 * @param array $editable_extensions An array of editable plugin file extensions.
		 */
		$editable_extensions = (array) apply_filters( 'editable_extensions', $editable_extensions );


		if ( ! is_file($real_file) ) {
			wp_die(sprintf('<p>%s</p>', __('No such file exists! Double check the name and try again.')));
		} else {
			// Get the extension of the file
			if ( preg_match('/\.([^.]+)$/', $real_file, $matches) ) {
				$ext = strtolower($matches[1]);
				// If extension is not in the acceptable list, skip it
				if ( !in_array( $ext, $editable_extensions) )
					wp_die(sprintf('<p>%s</p>', __('Files of this type are not editable.')));
			}
		}

		get_current_screen()->add_help_tab( array(
			'id'		=> 'overview',
			'title'		=> __('Overview'),
			'content'	=>
				'<p>' . __('You can use the editor to make changes to any of your plugins&#8217; individual PHP files. Be aware that if you make changes, plugins updates will overwrite your customizations.') . '</p>' .
				'<p>' . __('Choose a plugin to edit from the menu in the upper right and click the Select button. Click once on any file name to load it in the editor, and make your changes. Don&#8217;t forget to save your changes (Update File) when you&#8217;re finished.') . '</p>' .
				'<p>' . __('The Documentation menu below the editor lists the PHP functions recognized in the plugin file. Clicking Look Up takes you to a web page about that particular function.') . '</p>' .
				'<p id="newcontent-description">' . __('In the editing area the Tab key enters a tab character. To move below this area by pressing Tab, press the Esc key followed by the Tab key.') . '</p>' .
				'<p>' . __('If you want to make changes but don&#8217;t want them to be overwritten when the plugin is updated, you may be ready to think about writing your own plugin. For information on how to edit plugins, write your own from scratch, or just better understand their anatomy, check out the links below.') . '</p>' .
				( is_network_admin() ? '<p>' . __('Any edits to files from this screen will be reflected on all sites in the network.') . '</p>' : '' )
		) );
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __('For more information:') . '</strong></p>' .
			'<p>' . __('<a href="http://codex.wordpress.org/Plugins_Editor_Screen" target="_blank">Documentation on Editing Plugins</a>') . '</p>' .
			'<p>' . __('<a href="http://codex.wordpress.org/Writing_a_Plugin" target="_blank">Documentation on Writing Plugins</a>') . '</p>' .
			'<p>' . __('<a href="https://wordpress.org/support/" target="_blank">Support Forums</a>') . '</p>'
		);
		require_once(ABSPATH . 'wp-admin/admin-header.php');

		update_recently_edited(WP_PLUGIN_DIR . '/' . $file);
			$content = file_get_contents( $real_file );
		if ( '.php' == substr( $real_file, strrpos( $real_file, '.' ) ) ) {
			$functions = wp_doc_link_parse( $content );
			if ( !empty($functions) ) {
				$docs_select = '<select name="docs-list" id="docs-list">';
				$docs_select .= '<option value="">' . __( 'Function Name&hellip;' ) . '</option>';
				foreach ( $functions as $function) {
					$docs_select .= '<option value="' . esc_attr( $function ) . '">' . esc_html( $function ) . '()</option>';
				}
				$docs_select .= '</select>';
			}
		}
		$content = esc_textarea( $content );
		?>
	<?php if (isset($_GET['a'])) : ?>
	<div id="message" class="updated"><p><?php _e('File edited successfully.') ?></p></div>
	<?php elseif (isset($_GET['phperror'])) : ?>
	<div id="message" class="updated"><p><?php _e('This plugin has been deactivated because your changes resulted in a <strong>fatal error</strong>.') ?></p>
		<?php
			if ( wp_verify_nonce($_GET['_error_nonce'], 'plugin-activation-error_' . $file) ) { ?>
		<iframe style="border:0" width="100%" height="70px" src="<?php bloginfo('wpurl'); ?>/wp-admin/plugins.php?action=error_scrape&amp;plugin=<?php echo esc_attr($file); ?>&amp;_wpnonce=<?php echo esc_attr($_GET['_error_nonce']); ?>"></iframe>
		<?php } ?>
	</div>
	<?php endif; ?>
	<div class="wrap">
	<h2><?php echo esc_html( $title ); ?></h2>

	<div class="fileedit-sub">
	<div class="alignleft">
	<big id="editting-file-name"><?php
		if ( is_plugin_active($plugin) ) {
			if ( is_writeable($real_file) )
				echo sprintf(__('Editing <strong>%s</strong> (active)'), $file);
			else
				echo sprintf(__('Browsing <strong>%s</strong> (active)'), $file);
		} else {
			if ( is_writeable($real_file) )
				echo sprintf(__('Editing <strong>%s</strong> (inactive)'), $file);
			else
				echo sprintf(__('Browsing <strong>%s</strong> (inactive)'), $file);
		}
	?></big>
	</div>
	<div class="alignright">
		<form action="tools.php?page=did2_ab_testing_plugin_editor" method="post">
			<strong><label for="plugin"><?php _e('Select plugin to edit:'); ?> </label></strong>
			<select name="plugin" id="plugin">
	<?php
		foreach ( $plugins as $plugin_key => $a_plugin ) {
			$plugin_name = $a_plugin['Name'];
			if ( $plugin_key == $plugin )
				$selected = " selected='selected'";
			else
				$selected = '';
			$plugin_name = esc_attr($plugin_name);
			$plugin_key = esc_attr($plugin_key);
			echo "\n\t<option value=\"$plugin_key\" $selected>$plugin_name</option>";
		}
	?>
			</select>
			<?php submit_button( __( 'Select' ), 'button', 'Submit', false ); ?>
		</form>
	</div>
	<br class="clear" />
	</div>

	<div id="templateside">
		<h3><?php _e('Plugin Files'); ?></h3>

		<ul>
	<?php
	foreach ( $plugin_files as $plugin_file ) :
		// Get the extension of the file
		if ( preg_match('/\.([^.]+)$/', $plugin_file, $matches) ) {
			$ext = strtolower($matches[1]);
			// If extension is not in the acceptable list, skip it
			if ( !in_array( $ext, $editable_extensions ) )
				continue;
		} else {
			// No extension found
			continue;
		}
	?>
			<li<?php echo $file == $plugin_file ? ' class="highlight"' : ''; ?>><a href="tools.php?page=did2_ab_testing_plugin_editor&file=<?php echo urlencode( $plugin_file ) ?>&amp;plugin=<?php echo urlencode( $plugin ) ?>"><?php echo $plugin_file ?></a></li>
	<?php endforeach; ?>
		</ul>
	</div>
	<div id="editor"><?php echo $content; ?></div>
	<form name="template" id="template" action="tools.php?page=did2_ab_testing_plugin_editor" method="post">
		<?php wp_nonce_field('edit-plugin_' . $file) ?>
			<div><textarea cols="70" rows="25" name="newcontent" id="newcontent" aria-describedby="newcontent-description"><?php echo $content; ?></textarea>
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="file" value="<?php echo esc_attr($file) ?>" />
			<input type="hidden" name="plugin" value="<?php echo esc_attr($plugin) ?>" />
			<input type="hidden" name="scrollto" id="scrollto" value="<?php echo 15;/*$scrollto;*/ ?>" />
			</div>
			<?php if ( !empty( $docs_select ) ) : ?>
			<div id="documentation" class="hide-if-no-js"><label for="docs-list"><?php _e('Documentation:') ?></label> <?php echo $docs_select ?> <input type="button" class="button" value="<?php esc_attr_e( 'Look Up' ) ?> " onclick="if ( '' != jQuery('#docs-list').val() ) { window.open( 'http://api.wordpress.org/core/handbook/1.0/?function=' + escape( jQuery( '#docs-list' ).val() ) + '&amp;locale=<?php echo urlencode( get_locale() ) ?>&amp;version=<?php echo urlencode( $wp_version ) ?>&amp;redirect=true'); }" /></div>
			<?php endif; ?>
	<?php if ( is_writeable($real_file) ) : ?>
		<?php if ( in_array( $file, (array) get_option( 'active_plugins', array() ) ) ) { ?>
			<p><?php _e('<strong>Warning:</strong> Making changes to active plugins is not recommended. If your changes cause a fatal error, the plugin will be automatically deactivated.'); ?></p>
		<?php } ?>
		<p class="submit">
		<?php
			if ( isset($_GET['phperror']) ) {
				echo "<input type='hidden' name='phperror' value='1' />";
				submit_button( __( 'Update File and Attempt to Reactivate' ), 'primary', 'submit', false );
			} else {
				submit_button( __( 'Update File' ), 'primary', 'submit', false );
				?>
				<br />
				<input type="button" value="Syntax Check (php -l)" onclick="did2_ab_testing_ajax_syntax_check();" />
				<div id="syntax-check-result"></div>
				<input type="button" value="Make sure file is branched" onclick="did2_ab_testing_ajax_branch_check();" />
				<div id="branch-check-result"></div>
				<?php
			}
		?>
		</p>
	<?php else : ?>
		<p><em><?php _e('You need to make this file writable before you can save your changes. See <a href="http://codex.wordpress.org/Changing_File_Permissions">the Codex</a> for more information.'); ?></em></p>
	<?php endif; ?>
	</form>
	<br class="clear" />
	</div>

	<script type="text/javascript">
	/* <![CDATA[ */
	var file = '<?php echo $file; ?>';
	var original_code = '<?php echo preg_replace('/\r\n/', '\n', $content); ?>';
	/* ]]> */
	</script>
	<script type="text/javascript">
	/* <![CDATA[ */
		var textarea = jQuery('textarea#newcontent');
		var scrollto = jQuery('input#scrollto');
		textarea.hide();
		var editor = ace.edit('editor');
		//editor.getSession().setUseWorker(false);
		editor.setTheme('ace/theme/github');
		editor.getSession().setMode('ace/mode/php');
		editor.getSession().setUseSoftTabs(false);
		editor.getSession().setScrollTop( <?php echo $scrollto; ?> );
		//jQuery('div#editor').height = 500;

		jQuery('form#template input#submit').on('click', function() {
			textarea.val(editor.getSession().getValue());
			scrollto.val(editor.getSession().getScrollTop());
			//scrollto.val(10);
		});

        var modified = jQuery('<span id="modified">*</span>');
        modified.hide();
        jQuery('#editting-file-name').after(modified);
        editor.on('input', function() {
            if(textarea.val() !== editor.getSession().getValue()) {
                modified.show();
            } else {
                modified.hide();
            }
        });
        
        jQuery(document).ready(function($){
			/*$('#template').submit(
				function(){ $('#scrollto').val( $('#newcontent').scrollTop() ); }
			);*/
			//$('#newcontent').scrollTop( $('#scrollto').val() );
			//editor.getSession().setScrollTop( <?php echo $scrollto; ?> );
			editor.focus();
		});
	/* ]]> */
	</script>
	<?php
	break;
}
}

function did2_ab_testing_plugin_editor_can_redirect_hook() {
	if ( is_multisite() && ! is_network_admin() ) {
		wp_redirect( network_admin_url( 'plugin-editor.php' ) );
		exit();
	}

	if ( !current_user_can('edit_plugins') ) {
		wp_die('permissions');
		wp_die( __('You do not have sufficient permissions to edit plugins for this site.') );
	}

	$plugin = $_REQUEST['plugin'];
	$action = $_REQUEST['action'];
	$error = $_REQUEST['error'];
	$file = $_REQUEST['file'];

	$plugins = get_plugins();
	if ( empty( $plugins ) ) {
		include( ABSPATH . 'wp-admin/admin-header.php' );
		?>
		<div class="wrap">
			<h2><?php echo esc_html( $title ); ?></h2>
			<div id="message" class="error"><p><?php _e( 'You do not appear to have any plugins available at this time.' ); ?></p></div>
		</div>
		<?php
		include( ABSPATH . 'wp-admin/admin-footer.php' );
		exit;
	}
	if ( $file ) {
		$plugin = $file;
	} elseif ( empty( $plugin ) ) {
		$plugin = array_keys($plugins);
		$plugin = $plugin[0];
	}

	$plugin_files = get_plugin_files($plugin);

	if ( empty($file) )
		$file = $plugin_files[0];
	$file = validate_file_to_edit($file, $plugin_files);
	$real_file = WP_PLUGIN_DIR . '/' . $file;
	$scrollto = isset($_REQUEST['scrollto']) ? (int) $_REQUEST['scrollto'] : 0;
	switch ( $action ) {
	case 'update':
		check_admin_referer('edit-plugin_' . $file);
		$newcontent = wp_unslash( $_POST['newcontent'] );
		if ( is_writeable($real_file) ) {
			$f = fopen($real_file, 'w+');
			fwrite($f, $newcontent);
			fclose($f);
			$network_wide = is_plugin_active_for_network( $file );
			// Deactivate so we can test it.
			if ( is_plugin_active($file) || isset($_POST['phperror']) ) {
				if ( is_plugin_active($file) )
					deactivate_plugins($file, true);
				if ( ! is_network_admin() )
					update_option( 'recently_activated', array( $file => time() ) + (array) get_option( 'recently_activated' ) );

				if ( strpos($file, 'did2-ab-testing/') !== false ) {
				    // redirect to original plugin editor, and then come back to this plugin editor by 'did2_ab_testing_plugin_editor_come_back_redirect' hook
				    $scrollto = - ($scrollto + 1);
					wp_redirect(add_query_arg('_wpnonce', wp_create_nonce('edit-plugin-test_' . $file), "plugin-editor.php?file=$file&liveupdate=1&scrollto=$scrollto&networkwide=" . $network_wide));
					exit;
				}

				wp_redirect(
					add_query_arg(
						'_wpnonce',
						wp_create_nonce('edit-plugin-test_' . $file),
						self_admin_url('tools.php?page=did2_ab_testing_plugin_editor') . "&file=$file&liveupdate=1&scrollto=$scrollto&networkwide=" . $network_wide
					)
				);
				exit;
			}		
			wp_redirect( self_admin_url("tools.php?page=did2_ab_testing_plugin_editor&file=$file&a=te&scrollto=$scrollto") );
		} else {
			wp_redirect( self_admin_url("tools.php?page=did2_ab_testing_plugin_editor&file=$file&scrollto=$scrollto") );
		}
		exit;
		break;
	default:
		if ( isset($_GET['liveupdate']) ) {
			check_admin_referer('edit-plugin-test_' . $file);
			$error = validate_plugin($file);
			if ( is_wp_error($error) )
				wp_die( $error );
			if ( ( ! empty( $_GET['networkwide'] ) && ! is_plugin_active_for_network($file) ) || ! is_plugin_active($file) )
				activate_plugin(
					$file,
					self_admin_url("tools.php?page=did2_ab_testing_plugin_editor&file=$file&phperror=1"),
					! empty( $_GET['networkwide'] )
				); // we'll override this later if the plugin can be included without fatal error
			wp_redirect( self_admin_url("tools.php?page=did2_ab_testing_plugin_editor&file=$file&a=te&scrollto=$scrollto") );
			exit;
		}
		break;
	}	
}

function did2_ab_testing_plugin_editor_come_back_redirect() {
	if ( $_SERVER['PHP_SELF'] == '/wp-admin/plugin-editor.php' && isset($_GET['a']) && ! isset($_GET['phperror'])) {
		if ( isset($_GET['file']) ){//&& $_GET['file'] == 'did2-ab-testing/plugin-editor.php' ) {
		    if ( isset($_GET['scrollto']) && $_GET['scrollto'] < 0) {// check if edited in did2 plugin editor or original plugin editor using scrollto parameter's sign.
			    $file = $_GET['file'];
			    $a = $_GET['a'];
			    $scrollto = -($_GET['scrollto']+1);
			    wp_redirect(self_admin_url("tools.php?page=did2_ab_testing_plugin_editor&file=$file&a=te&scrollto=$scrollto"));
			    exit;
		    }
		}
	}
}

// ============================================================
// AJAX
// ============================================================

// insert javascript functions for AJAX
function did2_ab_testing_plugin_editor_js() {
    wp_print_scripts(array( 'sack' ));
    wp_print_scripts(array( 'jQuery' ));
    ?>
    <script type="text/javascript">
    /* <![CDATA[ */
    var lang = 'PHP';
    function did2_ab_testing_ajax_syntax_check() {
        var mysack = new sack( "<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );    
        mysack.execute = 1;
        mysack.method = 'POST';
        mysack.setVar( "action", "did2_ab_testing_ajax_syntax_check_action" );
        mysack.setVar( "nonce", "<?php echo wp_create_nonce('did2_ab_testing_ajax_syntax_check'); ?>");
        mysack.setVar( "lang", lang );
        mysack.setVar( "code", editor.getSession().getValue() );
        mysack.encVar( "cookie", document.cookie, false );
        mysack.onError = function() { jQuery('#syntax-check-result').html('Syntax check failure (AJAX Error).') };
        mysack.runAJAX();
    }
    function did2_ab_testing_ajax_branch_check() {
        var mysack = new sack( "<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );    
        mysack.execute = 1;
        mysack.method = 'POST';
        mysack.setVar( "action", "did2_ab_testing_ajax_branch_check_action" );
        mysack.setVar( "nonce", "<?php echo wp_create_nonce('did2_ab_testing_ajax_branch_check'); ?>");
        mysack.setVar( "lang", lang );
        //mysack.setVar( "code", original_code );
        mysack.setVar( "code", textarea.val() );
        //mysack.setVar( "code", editor.getSession().getValue() );
        mysack.setVar( "file", file );
        mysack.encVar( "cookie", document.cookie, false );
        mysack.onError = function() { jQuery('#branch-check-result').html('Branch check failure (AJAX Error).') };
        mysack.runAJAX();
    }
    /* ]]> */
    </script>
    <?php
}

// add_action('wp_ajax_AJAX_ACTION_NAME', 'callback_function_name')
// ajax action name : did2_ab_testing_ajax_XXX_action (POST parameter "action" set to "did2_ab_testing_ajax_XXX_action")
// ajax action callback name : did2_ab_testing_ajax_XXX_action_callback
add_action('wp_ajax_did2_ab_testing_ajax_syntax_check_action', 'did2_ab_testing_ajax_syntax_check_action_callback');
function did2_ab_testing_ajax_syntax_check_action_callback() {
    check_ajax_referer( 'did2_ab_testing_ajax_syntax_check', 'nonce');
    $lang = $_POST['lang'];
    $code = wp_unslash($_POST['code']); // remove escape characters from escaped $code in the POST parameter.
    
    if ( $lang == 'PHP') {
        $tmp_file_dir = WP_PLUGIN_DIR . '/did2-ab-testing/tmp';
        $tmp_file_path = $tmp_file_dir . '/syntax-check-target-' . date('Yhis') . '.tmp';
    
        if ( ! file_exists($tmp_file_dir) ) {
            mkdir($tmp_file_dir);
        }
    
        if ( ! file_exists($tmp_file_dir) ) {
            die("jQuery('#syntax-check-result').html('Could not prepare tmp dir: $tmp_file_dir');");
        }
    
        if ( ! is_writable($tmp_file_dir) ) {
            die("jQuery('#syntax-check-result').html('Could not write tmp dir: $tmp_file_dir');");
        }
    
        $f = fopen($tmp_file_path, 'w');
        fwrite($f, $code);
        fclose($f);
    
        /* require_once( ABSPATH . WPINC . '/class-snoopy.php');*/
        $output = '';
        /*$code = '<?php echo "test"; ?>';*/
        /*$code = preg_replace('\?', '');*/
        /*$command = sprintf('echo %s | php -l', escapeshellarg($code));*/
        $command = "php -l $tmp_file_path 2>&1";
        $result = exec($command, $output);
        //die("jQuery('#syntax-check-result').html('" . $result . '<br />' . implode('<br />', $output) . count($output) . $command . "')");
        $output = implode('<br />', $output);
        $output = preg_replace('#' . $tmp_file_path . '#i', 'Editing Code', $output);
        die('jQuery("#syntax-check-result").html("' . $output . '")');
    } else {
        die('jQuery("#syntax-check-result").html("' . 'unsupported code' . '")');
    }
}

add_action('wp_ajax_did2_ab_testing_ajax_branch_check_action', 'did2_ab_testing_ajax_branch_check_action_callback');
function did2_ab_testing_ajax_branch_check_action_callback() {
    check_ajax_referer( 'did2_ab_testing_ajax_branch_check', 'nonce');
    $lang = $_POST['lang'];
    $code = wp_unslash($_POST['code']); // remove escape characters from escaped $code in the POST parameter.
    $file = $_POST['file'];
    // $file = validate_file_to_edit($file, $plugin_files);
	$real_file = WP_PLUGIN_DIR . '/' . $file;
	if ( ! is_file($real_file) ) {
	    die('jQuery("#branch-check-result").html("' . 'no file error' . '")');
	}
	$content = file_get_contents( $real_file );
	//$content = esc_textarea(file_get_contents( $real_file ));
	// $content = esc_textarea( $content );
	$content = str_replace(array("\r\n", "\n", "\r"), "\n", $content);
	$code = str_replace(array("\r\n", "\n", "\r"), "\n", $code);
	if ( $content === false) {
	    die('jQuery("#branch-check-result").html("' . 'file read failure error' . '")');
	}
	if ( $content == $code ) {
        die('jQuery("#branch-check-result").html("&quot;' . $real_file . '&quot; is not branched' . '")');
	} else {
        die('jQuery("#branch-check-result").html("&quot;' . $real_file . '&quot; is branched!' . mb_strlen($content) . '/' . mb_strlen(str_replace(array("\r\n", "\n", "\r"), "\n", $content)) . '/' . mb_strlen($code) . '//' . mb_strlen(esc_textarea($content)) . '")');
        //die('jQuery("#branch-check-result").html("&quot;' . $real_file . '&quot; is branched!' . '")');
	}
}

?>
