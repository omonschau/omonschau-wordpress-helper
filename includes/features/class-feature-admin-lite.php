<?php
/**
 * Reduce dashboard clutter and simplify the admin bar.
 *
 * @package OmonschauWordPressHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Omonschau_WH_Feature_Admin_Lite {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'wp_dashboard_setup', array( $this, 'strip_dashboard_widgets' ), 100 );
		add_action( 'admin_bar_menu', array( $this, 'trim_admin_bar' ), 999 );
	}

	public function strip_dashboard_widgets() {
		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		$boxes = apply_filters(
			'omonschau_wh_remove_dashboard_metaboxes',
			array(
				array(
					'id'     => 'dashboard_activity',
					'screen' => 'normal',
				),
				array(
					'id'     => 'dashboard_primary',
					'screen' => 'side',
				),
			)
		);

		foreach ( $boxes as $box ) {
			if ( ! empty( $box['id'] ) ) {
				remove_meta_box( $box['id'], 'dashboard', isset( $box['screen'] ) ? (string) $box['screen'] : 'normal' );
			}
		}
	}

	public function trim_admin_bar( $wp_admin_bar ) {
		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}

		$nodes = apply_filters(
			'omonschau_wh_remove_admin_bar_nodes',
			array(
				'comments',
				'new-content',
			)
		);

		foreach ( $nodes as $node ) {
			$wp_admin_bar->remove_node( (string) $node );
		}
	}
}
