<?php

function did2_ab_testing_admin_menu_hook_diff_themes() {
	add_management_page('Diff themes - Did2 AB Testing', 'Diff Themes', 'edit_themes', __FILE__, 'did2_ab_testing_create_diff_themes_page');
}

function did2_ab_testing_create_diff_themes_page() {
	?>
<h2>Diff Themes</h2>
	<?php
	did2_ab_testing_creage_diff_themes_form();
}

function did2_ab_testing_creage_diff_themes_form() {
	?>
<form name="did_ab_testing_diff_template_form" method="get" action="tools.php">
	<input type="hidden" name="page" value="did2-ab-testing/diff-themes.php">
	<input type="hidden" name="actioon" value="diff">
			<select name="theme_a">
				<?php
				$themes = wp_get_themes();
				foreach( $themes as $theme_dir_name => $theme ) {
				?>

					<option
						value="<?php echo $theme_dir_name; ?>"
						<?php if ( isset($_GET['theme_a']) && $_GET['theme_a'] == $theme_dir_name ) echo 'selected'; ?>
					>
						<?php echo $theme->get( 'Name' ) . ' (' . $theme_dir_name . ')' ; ?>
					</option>	
				<?php
				}
				?>
			</select>

			<select name="theme_b">
				<?php
				$themes = wp_get_themes();
				foreach( $themes as $theme_dir_name => $theme ) {
				?>
					<option
						value="<?php echo $theme_dir_name; ?>"
						<?php if ( isset($_GET['theme_b']) && $_GET['theme_b'] == $theme_dir_name ) echo 'selected'; ?>
					>
						<?php echo $theme->get( 'Name' ) . ' (' . $theme_dir_name . ')' ; ?>
					</option>
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
	if ( isset($_GET['theme_a']) && isset($_GET['theme_b']) ) :
	?>

	<?php
	//echo 'diff:';
	diff_themes( $_GET['theme_a'], $_GET['theme_b'] );
	// echo $diff;
	?>

	<?php
	endif;
}

function diff_themes( $theme_a_dir_name, $theme_b_dir_name) {
	global $wp_filesystem;
	//require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once dirname(__FILE__) . '/php-diff/lib/Diff.php';
	require_once dirname(__FILE__) . '/php-diff/lib/Diff/Renderer/Html/SideBySide.php';
	require_once dirname(__FILE__) . '/php-diff/lib/Diff/Renderer/Html/Inline.php';
	require_once dirname(__FILE__) . '/finediff/finediff.php';

	$theme_a = wp_get_theme( $theme_a_dir_name );
	if ( ! $theme_a->exists() )
		wp_die( 'Theme A (' . $theme_a_dir_name . ') does not exist.' );

	$theme_b = wp_get_theme( $theme_b_dir_name );
	if ( ! $theme_b->exists() )
		wp_die( 'Theme B (' . $theme_b_dir_name . ') does not exist.' );

	$redirect = wp_nonce_url( plugin_dir_path( __FILE__ ) . 'diff_themes.php?theme_a=' . $theme_a_dir_name . '&theme_b=' . $theme_b_dir_name, '', false, false);
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
	$theme_a_dir_path = $themes_dir . $theme_a_dir_name;
	$theme_b_dir_path = $themes_dir . $theme_b_dir_name;

	$theme_a_file_list = $wp_filesystem->dirlist( $theme_a_dir_path, false, false /* true */ );
	$theme_b_file_list = $wp_filesystem->dirlist( $theme_a_dir_path, false, false /* true */  );

	// echo var_dump( $theme_a_file_list );
	// echo '<br />';
	// echo var_dump( $theme_b_file_list );

	$status_not_changed = 0;
	$status_added = 0;
	$status_deleted = 0;
	$status_modified = 0;

	$filetable = '';
	$filetable .= "
		<table class='filelist'>
		<thead>
		<tr>
		<td>$theme_a_dir_name</td>
		<td>$theme_b_dir_name</td>
		<td>status</td>
		</tr>
		</thead>
		<tbody>
		";
	$html = '';
//	$html .= "<script src='" . .  "' type='text/javascript' charset='utf-8'></script>";

	// $theme_file_name_list = array_merge( array_keys($theme_a_file_list), array_keys($theme_b_file_list) );
	$theme_file_name_list = array_keys( array_merge( $theme_a_file_list, $theme_b_file_list ) );
	foreach( $theme_file_name_list as $name) {
		if( ! isset( $theme_a_file_list[ $name ] ) && isset( $theme_b_file_list[ $name ] ) ) {
			$status_added += 1;
			$filetable .= '<tr class="added">';
			$filetable .= '<td>&nbsp;</td>';
			$filetable .= "<td>$name</td>";
			$filetable .= '<td>Added</td>';
			$filetable .= '</tr>';
			continue;
		} elseif ( isset( $theme_a_file_list[ $name ] ) && ! isset( $theme_b_file_list[ $name ] ) ) {
			$status_deleted += 1;
			$filetable .= '<tr class="deleted">';
			$filetable .= "<td>$name</td>";
			$filetable .= '<td>&nbsp;</td>';
			$filetable .= '<td>Deleted</td>';
			$filetable .= '</tr>';
			continue;
		} else {

		}

		$a_path = trailingslashit($theme_a_dir_path) . $name;
		$b_path = trailingslashit($theme_b_dir_path) . $name;
		// echo $a_path;
		// echo $b_path;
		$a = $wp_filesystem->get_contents( trailingslashit($theme_a_dir_path) . $name);
		$b = $wp_filesystem->get_contents( trailingslashit($theme_b_dir_path) . $name);

		if( $a == $b ) {
			$status_not_changed += 1;
			$filetable .= '<tr class="same">';
			$filetable .= "<td>$name</td>";
			$filetable .= "<td>$name</td>";
			$filetable .= '<td>Not Changed</td>';
			$filetable .= '</tr>';
			continue;
		} else {
			$status_modified += 1;
			$filetable .= '<tr class="modified">';
			$filetable .= "<td>$name</td>";
			$filetable .= "<td>$name</td>";
			$filetable .= '<td>Modified (';
			$filetable .= "<a href='#$name'>Diff</a>";
			$filetable .= ')</td>';
			$filetable .= '</tr>';

			$html .= "<h3 name='$name' id='$name'>" . $name . '</h3>';
		}

		//$opcodes = FineDiff::getDiffOpcodes($a, $b, FineDiff::$paragraphGranularity);
		$opcodes = FineDiff::getDiffOpcodes(mb_convert_encoding($a, 'HTML-ENTITIES', 'UTF-8'), mb_convert_encoding($b, 'HTML-ENTITIES', 'UTF-8'), FineDiff::$paragraphGranularity);
		//echo $opcodes;
		$html .= "<pre id='editor-$name' class='diff'>";
		//$html .= FineDiff::renderDiffToHTMLFromOpcodes($a, $opcodes);
		//$html .= FineDiff::renderDiffToHTMLFromOpcodes(mb_convert_encoding($a, 'HTML-ENTITIES', 'UTF-8'), $opcodes);
		//$html .= mb_convert_encoding(FineDiff::renderDiffToHTMLFromOpcodes(mb_convert_encoding($a, 'HTML-ENTITIES', 'UTF-8'), $opcodes), 'UTF-8', 'HTML-ENTITIES');
		//$html .= mb_convert_encoding(FineDiff::renderDiffToHTMLFromOpcodes(mb_convert_encoding($a, 'HTML-ENTITIES', 'UTF-8'), $opcodes));
		$diff_with_entities = FineDiff::renderDiffToHTMLFromOpcodes(mb_convert_encoding($a, 'HTML-ENTITIES', 'UTF-8'), $opcodes);
		$diff_without_entities = preg_replace_callback("/&amp;#[a-z0-9]{2,8};/i", 'did2_ab_testing_convert_from_html_entities_to_utf_8', $diff_with_entities);
		$diff_without_entities = preg_replace("#\n</ins>#i", "</ins>\n", $diff_without_entities);
		$diff_without_entities = preg_replace("#\n</del>#i", "</del>\n", $diff_without_entities);
		$lines = explode("\n", $diff_without_entities);
		$line_num_ins = array();
		$line_num_del = array();
		$ins_del_mode = '';
		foreach( $lines as $line_num => $line ) {
			if ( strpos( $line, '<ins>' ) === 0) {
				$ins_del_mode = 'ins';
				$line_num_ins[] = $line_num;
			} elseif ( strpos( $line, '<del>' ) === 0) {
				$ins_del_mode = 'del';
				$line_num_del[] = $line_num;
			} elseif ( $ins_del_mode === 'ins' ) {
				$line_num_ins[] = $line_num;
			} elseif ( $ins_del_mode === 'del' ) {
				$line_num_del[] = $line_num;
			}

			if ( $ins_del_mode === 'ins' && substr( $line, -strlen('</ins>') ) === '</ins>' ) {
				$ins_del_mode = '';
			} elseif ( $ins_del_mode === 'del' && substr( $line, -strlen('</del>') ) === '</del>' ) {
				$ins_del_mode = '';
			}
		}
		$ins_del_mode = '';
		$diff_without_entities = preg_replace("#<ins>#i", '', $diff_without_entities);
		$diff_without_entities = preg_replace("#</ins>#i", '', $diff_without_entities);
		$diff_without_entities = preg_replace("#<del>#i", '', $diff_without_entities);

		$diff_without_entities = preg_replace("#</del>#i", '', $diff_without_entities);
		$html .= $diff_without_entities;
		$html .= '</pre>';

		$html .= "
			<script>
				var editor = ace.edit('editor-$name');
				editor.getSession().setUseWorker(false);
				editor.setTheme('ace/theme/github');
				editor.getSession().setMode('ace/mode/php');
				editor.setReadOnly(true);

				var Range = ace.require('ace/range').Range;
			";
		foreach( $line_num_ins as $line_num ) {
			$html .= "editor.session.addMarker(new Range($line_num,0,$line_num,200), 'did2_ace_ins-line', 'fullLine', false);\n";
		}

		foreach( $line_num_del as $line_num ) {
			$html .= "editor.session.addMarker(new Range($line_num,0,$line_num,200), 'did2_ace_del-line', 'fullLine', false);\n";
		}

		$html .= "</script>";

		//echo '<br />begin';
		//$options = array();
		// $diff = new Diff(esc_html($a), esc_html($b), $options);
		//$diff = new Diff($a, $b, $options);
		//$diff = new Diff('aa', 'bbaa', $options);
		//echo '<br />end';
		//echo '<pre>' . esc_html(var_dump($diff)) . '</pre>';

		//$renderer = new Diff_Renderer_Html_SideBySide;
		//echo '<br />begin render<br />';
		//echo var_dump($renderer);
		//echo $diff->render($renderer);	
		//echo '<br />end render';
	}

	$filetable .= '</tbody></table>';

	echo '<h2>Summary</h2>';
	echo "<table>
		<thead><tr><td>Not Changed</td><td>Added</td><td>Deleted</td><td>Modified</td></tr></thead>
		<tbody><tr><td>$status_not_changed</td><td>$status_added</td><td>$status_deleted</td><td>$status_modified</td></tr></tbody></table>";
	echo $filetable;

	echo '<h2>Details</h2>';

		$html .= "<style type='text/css' media='screen'>
    #editor { 
        /* position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0; */
	height: 275px;
    }
</style>
			<div id='editor'>function</div>
			<script>
				var editor = ace.edit('editor');
//				editor.getSession().setUseWorker(false);
				editor.setTheme('ace/theme/monokai');
//				editor.getSession().setMode('ace/mode/javascript');
			</script>
			";

	echo $html;
}

function did2_ab_testing_convert_from_html_entities_to_utf_8 ( $matches ) {
	return mb_convert_encoding( $matches[0], 'UTF-8', 'HTML-ENTITIES');
}

?>