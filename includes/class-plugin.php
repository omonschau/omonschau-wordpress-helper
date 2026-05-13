<?php
/**
 * Main plugin bootstrap.
 *
 * @package OmonschauWordPressHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Omonschau_WH_Plugin {

	const FEATURE_DISABLE_COMMENTS = 'disable_comments';
	const FEATURE_REDUCE_ADMIN     = 'reduce_admin';
	const FEATURE_UTM_PERSIST      = 'utm_persist';

	/**
	 * @var Omonschau_WH_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return Omonschau_WH_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Bootstrap hooks.
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_features' ), 5 );

		if ( is_admin() ) {
			require_once OMONSCHAU_WH_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
			Omonschau_WH_Settings_Page::instance()->register();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'omonschau-wordpress-helper',
			false,
			dirname( plugin_basename( OMONSCHAU_WH_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_options() {
		$defaults = array(
			'enabled_features' => array(),
		);
		$stored = get_option( OMONSCHAU_WH_OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * @param string $feature One of FEATURE_* constants.
	 */
	public static function is_feature_enabled( $feature ) {
		$opts    = self::get_options();
		$enabled = isset( $opts['enabled_features'] ) && is_array( $opts['enabled_features'] )
			? $opts['enabled_features']
			: array();
		return in_array( $feature, $enabled, true );
	}

	public function register_features() {
		if ( self::is_feature_enabled( self::FEATURE_DISABLE_COMMENTS ) ) {
			require_once OMONSCHAU_WH_PLUGIN_DIR . 'includes/features/class-feature-comments.php';
			Omonschau_WH_Feature_Comments::instance()->register();
		}

		if ( self::is_feature_enabled( self::FEATURE_REDUCE_ADMIN ) ) {
			require_once OMONSCHAU_WH_PLUGIN_DIR . 'includes/features/class-feature-admin-lite.php';
			Omonschau_WH_Feature_Admin_Lite::instance()->register();
		}

		if ( self::is_feature_enabled( self::FEATURE_UTM_PERSIST ) ) {
			require_once OMONSCHAU_WH_PLUGIN_DIR . 'includes/features/class-feature-utm.php';
			Omonschau_WH_Feature_Utm::instance()->register();
		}
	}
}
