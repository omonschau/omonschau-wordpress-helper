<?php
/**
 * Disable comments, pings, trackbacks; hide admin UI; restrict REST/XML-RPC.
 *
 * @package OmonschauWordPressHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Omonschau_WH_Feature_Comments {

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
		add_action( 'init', array( $this, 'on_init' ), 100 );
		add_action( 'admin_init', array( $this, 'redirect_comment_admin' ) );
		add_action( 'admin_menu', array( $this, 'remove_admin_menus' ), 999 );
		add_action( 'admin_bar_menu', array( $this, 'remove_admin_bar_nodes' ), 999 );
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_admin_bar_nodes_legacy' ), 999 );

		add_filter( 'comments_open', array( $this, 'force_closed' ), 99, 2 );
		add_filter( 'pings_open', array( $this, 'force_closed' ), 99, 2 );
		add_filter( 'get_comments_number', array( $this, 'comments_number_zero' ), 10, 2 );

		add_filter( 'comments_array', array( $this, 'empty_comments_array' ), 10, 2 );
		add_filter( 'pre_comment_approved', array( $this, 'block_comment_submission' ), 99, 2 );
		add_action( 'pre_ping', array( $this, 'cancel_outbound_pings' ), 10, 3 );

		add_filter( 'rest_endpoints', array( $this, 'remove_comment_rest_endpoints' ) );
		add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_comment_pingback' ) );
		add_filter( 'wp_headers', array( $this, 'maybe_remove_pingback_header' ) );

		add_filter( 'comment_feed_links_show', '__return_false' );
	}

	/**
	 * Block comment inserts while keeping validation intact.
	 *
	 * @param int|string|WP_Error $approved    Approval status.
	 * @param array<string, mixed> $commentdata Comment data.
	 * @return WP_Error|int|string
	 */
	public function block_comment_submission( $approved, $commentdata ) {
		if ( is_wp_error( $approved ) ) {
			return $approved;
		}
		return new WP_Error(
			'omonschau_wh_comments_disabled',
			__( 'Kommentare sind deaktiviert.', 'omonschau-wordpress-helper' )
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function get_post_types() {
		return apply_filters(
			'omonschau_wh_disable_comments_post_types',
			array( 'post', 'page' )
		);
	}

	public function on_init() {
		foreach ( $this->get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
			}
			if ( post_type_supports( $post_type, 'trackbacks' ) ) {
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	public function redirect_comment_admin() {
		global $pagenow;
		$blocked = array( 'edit-comments.php', 'comment.php' );
		if ( in_array( $pagenow, $blocked, true ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	public function remove_admin_menus() {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function remove_admin_bar_nodes( $wp_admin_bar ) {
		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}
		$wp_admin_bar->remove_node( 'comments' );
	}

	public function remove_admin_bar_nodes_legacy() {
		global $wp_admin_bar;
		if ( is_object( $wp_admin_bar ) ) {
			$wp_admin_bar->remove_node( 'comments' );
		}
	}

	/**
	 * @param bool        $open    Whether open.
	 * @param int|string $post_id Post ID.
	 */
	public function force_closed( $open, $post_id ) {
		return false;
	}

	/**
	 * @param int|string $count Count.
	 * @param int        $post_id Post ID.
	 */
	public function comments_number_zero( $count, $post_id ) {
		return 0;
	}

	/**
	 * @param array<int, WP_Comment> $comments Comments.
	 * @param int                    $post_id Post ID.
	 * @return array<int, WP_Comment>
	 */
	public function empty_comments_array( $comments, $post_id ) {
		return array();
	}

	/**
	 * @param array<int, string> $post_links Outbound links.
	 * @param array<int, string> $pung     Already pinged.
	 * @param int|string         $post_id  Post ID.
	 */
	public function cancel_outbound_pings( &$post_links, $pung, $post_id ) {
		$post_links = array();
	}

	/**
	 * @param array<string, array<int, array<string, mixed>>> $endpoints Endpoints.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function remove_comment_rest_endpoints( $endpoints ) {
		if ( ! is_array( $endpoints ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( is_string( $route ) && preg_match( '#^/wp/v2/comments#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * @param array<string, string> $methods Methods.
	 * @return array<string, string>
	 */
	public function disable_xmlrpc_comment_pingback( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'], $methods['wp.newComment'] );
		return $methods;
	}

	/**
	 * @param array<string, string> $headers Headers.
	 * @return array<string, string>
	 */
	public function maybe_remove_pingback_header( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}
}
