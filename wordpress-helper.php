<?php
/**
 * Plugin Name: WordPress Helper
 * Description: Optional utilities: disable comments, simplify admin, persist UTM parameters on internal links.
 * Version: 1.0.0
 * Author: Oliver Monschau
 * Author URI: https://omonschau.de
 * Text Domain: omonschau-wordpress-helper
 * Domain Path: /languages
 *
 * @package OmonschauWordPressHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OMONSCHAU_WH_VERSION', '1.0.0' );
define( 'OMONSCHAU_WH_PLUGIN_FILE', __FILE__ );
define( 'OMONSCHAU_WH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OMONSCHAU_WH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OMONSCHAU_WH_OPTION_KEY', 'omonschau_wh_options' );

require_once OMONSCHAU_WH_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Returns the main plugin instance.
 *
 * @return Omonschau_WH_Plugin
 */
function omonschau_wh() {
	return Omonschau_WH_Plugin::instance();
}

Omonschau_WH_Plugin::instance()->init();
