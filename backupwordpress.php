<?php
/*
Plugin Name: BackUpWordPress
Plugin URI: http://bwp.hmn.md/
Description: Simple automated backups of your WordPress powered website. Once activated you'll find me under <strong>Tools &rarr; Backups</strong>. On multisite, you'll find me under the Network Settings menu.
Version: 3.0.4
Author: Human Made Limited
Author URI: http://hmn.md/
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: backupwordpress
Domain Path: /languages
Network: true
*/

/*
Copyright 2011 - 2014 Human Made Limited  (email : support@hmn.md)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

include_once( dirname( __FILE__ ) . '/classes/class-setup.php' );

register_activation_hook( __FILE__, array( 'BackUpWordPress_Setup', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BackUpWordPress_Setup', 'deactivate' ) );

/**
 * Class BackUpWordPress_Plugin
 */
class BackUpWordPress_Plugin {

	const PLUGIN_VERSION = '3.0.4';

	/**
	 * @var BackUpWordPress_Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Instantiates a new BackUpWordPress_Plugin object.
	 */
	private function __construct() {

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Insures we always return the same object.
	 *
	 * @return BackUpWordPress_Plugin
	 */
	public static function get_instance() {

		if ( ! ( self::$instance instanceof BackUpWordPress_Plugin ) ) {
			self::$instance = new BackUpWordPress_Plugin();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 */
	public function plugins_loaded() {

		if ( false !== $this->maybe_self_deactivate() ) {

			$this->constants();

			$this->includes();

			$this->hooks();

			$this->text_domain();

			// If we get here, then BWP is loaded
			do_action( 'backupwordpress_loaded' );

		}

	}

	public function maybe_self_deactivate() {

		if ( ! BackUpWordPress_Setup::meets_requirements() ) {

			add_action( 'admin_init', array( 'BackUpWordPress_Setup', 'self_deactivate' ) );

			add_action( 'admin_notices', array( 'BackUpWordPress_Setup', 'display_admin_notices' ) );
		}

	}

	/**
	 * Define all the constants.
	 */
	public function constants() {

		if ( ! defined( 'HMBKP_PLUGIN_SLUG' ) ) {
			define( 'HMBKP_PLUGIN_SLUG', dirname( plugin_basename( __FILE__ ) ) );
		}

		if ( ! defined( 'HMBKP_PLUGIN_PATH' ) ) {
			define( 'HMBKP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
		}

		if ( ! defined( 'HMBKP_PLUGIN_URL' ) ) {
			define( 'HMBKP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		if ( ! defined( 'HMBKP_PLUGIN_LANG_DIR' ) ) {
			define( 'HMBKP_PLUGIN_LANG_DIR', apply_filters( 'hmbkp_filter_lang_dir', HMBKP_PLUGIN_SLUG . '/languages/' ) );
		}

		if ( ! defined( 'HMBKP_ADMIN_URL' ) ) {

			$page = is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'tools.php' );

			define( 'HMBKP_ADMIN_URL', add_query_arg( 'page', HMBKP_PLUGIN_SLUG, $page ) );

		}

		if ( ! defined( 'HMBKP_ADMIN_PAGE' ) ) {

			$prefix = is_multisite() ? 'settings_page_' : 'tools_page_';

			define( 'HMBKP_ADMIN_PAGE', $prefix . HMBKP_PLUGIN_SLUG );
		}

		define( 'HMBKP_SECURE_KEY', $this->generate_key() );

	}

	/**
	 * Load all BackUpWordPress functions.
	 */
	protected function includes() {

		require_once( HMBKP_PLUGIN_PATH . 'classes/class-notices.php' );

		// Load the admin menu
		require_once( HMBKP_PLUGIN_PATH . 'admin/menu.php' );
		require_once( HMBKP_PLUGIN_PATH . 'admin/actions.php' );

		// Load hm-backup
		if ( ! class_exists( 'HM_Backup' ) ) {
			require_once( HMBKP_PLUGIN_PATH . 'hm-backup/hm-backup.php' );
		}

		// Load Backdrop if necessary.
		if ( ! class_exists( 'HM_Backdrop_Task' ) ) {
			require_once( HMBKP_PLUGIN_PATH . 'backdrop/hm-backdrop.php' );
		}

		require_once( HMBKP_PLUGIN_PATH . 'classes/class-requirements.php' );

		require_once( HMBKP_PLUGIN_PATH . 'classes/class-hmbkp-path.php' );

		// Load the schedules
		require_once( HMBKP_PLUGIN_PATH . 'classes/class-schedule.php' );
		require_once( HMBKP_PLUGIN_PATH . 'classes/class-schedules.php' );

		// Load the core functions
		require_once( HMBKP_PLUGIN_PATH . 'functions/core.php' );
		require_once( HMBKP_PLUGIN_PATH . 'functions/interface.php' );

		// Load Services
		require_once( HMBKP_PLUGIN_PATH . 'classes/class-services.php' );

		// Load the email service
		require_once( HMBKP_PLUGIN_PATH . 'classes/class-email.php' );

		// Load the webhook services
		require_once( HMBKP_PLUGIN_PATH . 'classes/class-webhooks.php' );
		require_once( HMBKP_PLUGIN_PATH . 'classes/class-webhook-wpremote.php' );

		// Load the wp cli command
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			include( HMBKP_PLUGIN_PATH . 'classes/wp-cli.php' );
		}

	}

	/**
	 * Hook into WordPress page lifecycle and execute BackUpWordPress functions.
	 */
	public function hooks() {

		add_action( 'activated_plugin', array( $this, 'load_first' ) );

		add_action( 'admin_init', array( $this, 'upgrade' ) );

		add_action( 'admin_init', array( $this, 'init' ) );

		add_action( 'hmbkp_schedule_hook', array( $this, 'schedule_hook_run' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );

	}

	/**
	 * Load the Javascript in the admin.
	 *
	 * @param $hook The name of the admin page hook.
	 */
	public function scripts( $hook ) {

		if ( HMBKP_ADMIN_PAGE !== $hook ) {
			return;
		}

		$js_file = HMBKP_PLUGIN_URL . 'assets/hmbkp.min.js';

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$js_file = HMBKP_PLUGIN_URL . 'assets/hmbkp.js';
		}

		wp_enqueue_script( 'hmbkp', $js_file, array( 'heartbeat' ), sanitize_key( self::PLUGIN_VERSION ) );

		wp_localize_script(
			'hmbkp',
			'hmbkp',
			array(
				'page_slug'                => HMBKP_PLUGIN_SLUG,
				'nonce'                    => wp_create_nonce( 'hmbkp_nonce' ),
				'hmbkp_run_schedule_nonce' => wp_create_nonce( 'hmbkp_run_schedule' ),
				'update'                   => __( 'Update', 'backupwordpress' ),
				'cancel'                   => __( 'Cancel', 'backupwordpress' ),
				'delete_schedule'          => __( 'Are you sure you want to delete this schedule? All of it\'s backups will also be deleted.', 'backupwordpress' ) . "\n\n" . __( '\'Cancel\' to go back, \'OK\' to delete.', 'backupwordpress' ) . "\n",
				'delete_backup'            => __( 'Are you sure you want to delete this backup?', 'backupwordpress' ) . "\n\n" . __( '\'Cancel\' to go back, \'OK\' to delete.', 'backupwordpress' ) . "\n",
				'remove_exclude_rule'      => __( 'Are you sure you want to remove this exclude rule?', 'backupwordpress' ) . "\n\n" . __( '\'Cancel\' to go back, \'OK\' to delete.', 'backupwordpress' ) . "\n",
				'remove_old_backups'       => __( 'Reducing the number of backups that are stored on this server will cause some of your existing backups to be deleted, are you sure that\'s what you want?', 'backupwordpress' ) . "\n\n" . __( '\'Cancel\' to go back, \'OK\' to delete.', 'backupwordpress' ) . "\n"
			)
		);

	}

	/**
	 * Loads the plugin text domain for translation.
	 * This setup allows a user to just drop his custom translation files into the WordPress language directory
	 * Files will need to be in a subdirectory with the name of the textdomain 'backupwordpress'
	 */
	public function text_domain() {

		// Set unique textdomain string
		$textdomain = 'backupwordpress';

		// The 'plugin_locale' filter is also used by default in load_plugin_textdomain()
		$locale = apply_filters( 'plugin_locale', get_locale(), $textdomain );

		// Set filter for WordPress languages directory
		$hmbkp_wp_lang_dir = apply_filters( 'hmbkp_do_filter_wp_lang_dir', trailingslashit( WP_LANG_DIR ) . trailingslashit( $textdomain ) . $textdomain . '-' . $locale . '.mo' );

		// Translations: First, look in WordPress' "languages" folder = custom & update-secure!
		load_textdomain( $textdomain, $hmbkp_wp_lang_dir );

		// Translations: Secondly, look in plugin's "languages" folder = default
		load_plugin_textdomain( $textdomain, false, HMBKP_PLUGIN_LANG_DIR );

	}

	/**
	 * Determine if we need to run an upgrade routine.
	 */
	public function upgrade() {

		// Fire the update action
		if ( self::PLUGIN_VERSION != get_option( 'hmbkp_plugin_version' ) ) {
			hmbkp_update();
		}

	}

	/**
	 * Runs on every admin page load
	 */
	public function init() {

		// If we have multiple paths for some reason then clean them up
		HMBKP_Path::get_instance()->merge_existing_paths();
	}

	/**
	 * Generate a unique key.
	 *
	 * @return string
	 */
	protected function generate_key() {

		$key = array( ABSPATH, time() );

		foreach (
			array(
				'AUTH_KEY',
				'SECURE_AUTH_KEY',
				'LOGGED_IN_KEY',
				'NONCE_KEY',
				'AUTH_SALT',
				'SECURE_AUTH_SALT',
				'LOGGED_IN_SALT',
				'NONCE_SALT',
				'SECRET_KEY'
			) as $constant
		) {

			if ( defined( $constant ) ) {
				$key[] = constant( $constant );
			}

		}

		shuffle( $key );

		return md5( serialize( $key ) );

	}

	/**
	 * Ensure BackUpWordPress is loaded before add-ons, changes the order of the serialized values in the DB field.
	 */
	public function load_first() {

		$active_plugins = get_option( 'active_plugins' );

		$plugin_path = plugin_basename( __FILE__ );

		$key = array_search( $plugin_path, $active_plugins );

		if ( $key > 0 ) {

			array_splice( $active_plugins, $key, 1 );

			array_unshift( $active_plugins, $plugin_path );

			update_option( 'active_plugins', $active_plugins );

		}

	}

	/**
	 * Function to run when the schedule cron fires.
	 *
	 * @param $schedule_id
	 */
	public function schedule_hook_run( $schedule_id ) {

		if ( ! hmbkp_possible() ) {
			return;
		}

		$schedules = HMBKP_Schedules::get_instance();
		$schedule  = $schedules->get_schedule( $schedule_id );

		if ( ! $schedule ) {
			return;
		}

		$schedule->run();

	}

	/**
	 * Enqueue the plugin styles.
	 *
	 * @param $hook
	 */
	public function styles( $hook ) {

		if ( HMBKP_ADMIN_PAGE !== $hook ) {
			return;
		}

		$css_file = HMBKP_PLUGIN_URL . 'assets/hmbkp.min.css';

		if ( WP_DEBUG ) {
			$css_file = HMBKP_PLUGIN_URL . 'assets/hmbkp.css';
		}

		wp_enqueue_style( 'backupwordpress', $css_file, false, sanitize_key( self::PLUGIN_VERSION ) );

	}

	/**
	 * Load Intercom and send across user information and server info. Only loaded if the user has opted in.
	 *
	 * @param $hook
	 */
	public function load_intercom_script( $hook ) {

		if ( HMBKP_ADMIN_PAGE !== $hook ) {
			return;
		}

		if ( ! get_option( 'hmbkp_enable_support' ) ) {
			return;
		}

		foreach ( HMBKP_Requirements::get_requirement_groups() as $group ) {

			foreach ( HMBKP_Requirements::get_requirements( $group ) as $requirement ) {

				$info[ $requirement->name() ] = $requirement->result();

			}

		}

		foreach ( HMBKP_Services::get_services() as $file => $service ) {
			array_merge( $info, call_user_func( array( $service, 'intercom_data' ) ) );
		}

		$current_user = wp_get_current_user();

		$info['user_hash']  = hash_hmac( 'sha256', $current_user->user_email, 'fcUEt7Vi4ym5PXdcr2UNpGdgZTEvxX9NJl8YBTxK' );
		$info['email']      = $current_user->user_email;
		$info['created_at'] = strtotime( $current_user->user_registered );
		$info['app_id']     = '7f1l4qyq';
		$info['name']       = $current_user->display_name;
		$info['widget']     = array( 'activator' => '#intercom' ); ?>

		<script id="IntercomSettingsScriptTag">
			window.intercomSettings = <?php echo json_encode( $info ); ?>;
		</script>
		<script>!function(){function e(){var a=c.createElement("script");a.type="text/javascript",a.async=!0,a.src="https://static.intercomcdn.com/intercom.v1.js";var b=c.getElementsByTagName("script")[0];b.parentNode.insertBefore(a,b)}var a=window,b=a.Intercom;if("function"==typeof b)b("reattach_activator"),b("update",intercomSettings);else{var c=document,d=function(){d.c(arguments)};d.q=[],d.c=function(a){d.q.push(a)},a.Intercom=d,a.attachEvent?a.attachEvent("onload",e):a.addEventListener("load",e,!1)}}();</script>

	<?php }

}

BackUpWordPress_Plugin::get_instance();
