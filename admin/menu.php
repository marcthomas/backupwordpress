<?php

/**
 * Add the backups menu item
 * to the tools menu
 *
 * @return null
 */
function hmbkp_admin_menu() {

	if ( is_multisite() )
		add_submenu_page( 'settings.php', __( 'Manage Backups','backupwordpress' ), __( 'Backups','backupwordpress' ), ( defined( 'HMBKP_CAPABILITY' ) && HMBKP_CAPABILITY ) ? HMBKP_CAPABILITY : 'manage_options', HMBKP_PLUGIN_SLUG, 'hmbkp_manage_backups' );
	else
		add_management_page( __( 'Manage Backups','backupwordpress' ), __( 'Backups','backupwordpress' ), ( defined( 'HMBKP_CAPABILITY' ) && HMBKP_CAPABILITY ) ? HMBKP_CAPABILITY : 'manage_options', HMBKP_PLUGIN_SLUG, 'hmbkp_manage_backups' );
}
add_action( 'network_admin_menu', 'hmbkp_admin_menu' );
add_action( 'admin_menu', 'hmbkp_admin_menu' );

/**
 * Load the backups admin page
 * when the menu option is clicked
 *
 * @return null
 */
function hmbkp_manage_backups() {
	require_once( HMBKP_PLUGIN_PATH . 'admin/page.php' );
}

/**
 * Add a link to the backups page to the plugin action links.
 *
 * @param array $links
 * @param string $file
 * @return array $links
 */
function hmbkp_plugin_action_link( $links, $file ) {

	if ( strpos( $file, HMBKP_PLUGIN_SLUG ) !== false )
		array_push( $links, '<a href="' . esc_url( HMBKP_ADMIN_URL ) . '">' . __( 'Backups', 'backupwordpress' ) . '</a>' );

	return $links;

}
add_filter( 'plugin_action_links', 'hmbkp_plugin_action_link', 10, 2 );

/**
 * Add Contextual Help to Backups tools page.
 *
 * Help is pulled from the readme FAQ.
 *
 * @return null
 */
function hmbkp_contextual_help() {

	// Pre WordPress 3.3 compat
	if ( ! method_exists( get_current_screen(), 'add_help_tab' ) )
		return;

	ob_start();
	require_once( HMBKP_PLUGIN_PATH . 'admin/constants.php' );
	$constants = ob_get_clean();

	ob_start();
	include_once( HMBKP_PLUGIN_PATH . 'admin/faq.php' );
	$faq = ob_get_clean();

	get_current_screen()->add_help_tab( array( 'title' => __( 'FAQ', 'backupwordpress' ), 'id' => 'hmbkp_faq', 'content' => wp_kses_post( $faq ) ) );

	get_current_screen()->add_help_tab( array( 'title' => __( 'Constants', 'backupwordpress' ), 'id' => 'hmbkp_constants', 'content' => wp_kses_post( $constants ) ) );

	require_once( HMBKP_PLUGIN_PATH . 'classes/class-requirements.php' );

	ob_start();
	require_once( HMBKP_PLUGIN_PATH . 'admin/server-info.php' );
	$info = ob_get_clean();

	get_current_screen()->add_help_tab(
		array(
			'title' => __( 'Server Info', 'backupwordpress' ),
			'id' => 'hmbkp_server',
			'content' => $info,
		)
	);

	get_current_screen()->set_help_sidebar(
		'<p><strong>' . __( 'For more information:', 'backupwordpress' ) . '</strong></p>' .
		'<p><a href="https://github.com/humanmade/backupwordpress" target="_blank">GitHub</a></p>' .
		'<p><a href="http://wordpress.org/tags/backupwordpress?forum_id=10" target="_blank">' . __( 'Support Forums', 'backupwordpress' ) .'</a></p>' .
		'<p><a href="http://translate.hmn.md/" target="_blank">' . __( 'Help with translation', 'backupwordpress' ) .'</a></p>'
	);

}
add_action( 'load-' . HMBKP_ADMIN_PAGE, 'hmbkp_contextual_help' );