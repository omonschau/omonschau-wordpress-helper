<?php
/**
 * Persist first-touch UTM parameters (frontend only) and merge into internal links.
 *
 * @package OmonschauWordPressHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Omonschau_WH_Feature_Utm {

	const COOKIE_PREFIX = 'omonschau_wh_utm';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var array<string, string>|null
	 */
	private static $parsed_cookie = null;

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
		add_action( 'template_redirect', array( $this, 'maybe_store_utm_cookie' ), 1 );
		add_filter( 'the_content', array( $this, 'filter_html' ), 20 );
		add_filter( 'widget_text_content', array( $this, 'filter_html' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'filter_html' ), 20 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_footer_script' ), 20 );
	}

	/**
	 * @return array<int, string>
	 */
	private function allowed_keys() {
		return array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
	}

	private function cookie_name() {
		return self::COOKIE_PREFIX . '_' . md5( (string) home_url( '/' ) );
	}

	/**
	 * @return int
	 */
	private function cookie_lifetime() {
		return apply_filters( 'omonschau_wh_utm_cookie_lifetime', 30 * DAY_IN_SECONDS );
	}

	/**
	 * Parse GET for allowed UTM keys and return sanitized assoc array.
	 *
	 * @return array<string, string>
	 */
	private function parse_get_utms() {
		$out = array();
		foreach ( $this->allowed_keys() as $key ) {
			if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}
			$raw = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! is_string( $raw ) || '' === $raw ) {
				continue;
			}
			$clean = sanitize_text_field( $raw );
			if ( strlen( $clean ) > 200 ) {
				$clean = substr( $clean, 0, 200 );
			}
			$out[ $key ] = $clean;
		}
		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	private function parse_cookie_utms() {
		if ( null !== self::$parsed_cookie ) {
			return self::$parsed_cookie;
		}
		$name = $this->cookie_name();
		if ( empty( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			self::$parsed_cookie = array();
			return self::$parsed_cookie;
		}
		$decoded = json_decode( wp_unslash( $_COOKIE[ $name ] ), true );
		if ( ! is_array( $decoded ) ) {
			self::$parsed_cookie = array();
			return self::$parsed_cookie;
		}
		$clean = array();
		foreach ( $this->allowed_keys() as $key ) {
			if ( ! isset( $decoded[ $key ] ) || ! is_string( $decoded[ $key ] ) ) {
				continue;
			}
			$val = sanitize_text_field( $decoded[ $key ] );
			if ( '' !== $val && strlen( $val ) <= 200 ) {
				$clean[ $key ] = $val;
			}
		}
		self::$parsed_cookie = $clean;
		return self::$parsed_cookie;
	}

	public function maybe_store_utm_cookie() {
		if ( is_admin() || wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$get_utms = $this->parse_get_utms();
		if ( empty( $get_utms ) ) {
			return;
		}

		$name = $this->cookie_name();
		if ( ! empty( $_COOKIE[ $name ] ) ) {
			return;
		}

		$payload  = wp_json_encode( $get_utms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$expire   = time() + $this->cookie_lifetime();
		$path     = COOKIEPATH ? COOKIEPATH : '/';
		$domain   = COOKIE_DOMAIN;
		$secure   = is_ssl();
		$httponly = true;

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				$name,
				$payload,
				array(
					'expires'  => $expire,
					'path'     => $path,
					'domain'   => $domain,
					'secure'   => $secure,
					'httponly' => $httponly,
					'samesite' => 'Lax',
				)
			);
		} else {
			setcookie( $name, $payload, $expire, $path, $domain, $secure, $httponly );
		}

		$_COOKIE[ $name ] = $payload;
		self::$parsed_cookie = $get_utms;
	}

	/**
	 * @return array<string, string>
	 */
	public function get_active_utms() {
		$from_cookie = $this->parse_cookie_utms();
		if ( ! empty( $from_cookie ) ) {
			return $from_cookie;
		}
		$from_get = $this->parse_get_utms();
		return $from_get;
	}

	/**
	 * @param string $url URL (absolute or relative).
	 */
	private function is_internal_url( $url ) {
		if ( '' === $url ) {
			return false;
		}

		$trimmed = ltrim( $url );
		if ( '' === $trimmed || '#' === $trimmed[0] ) {
			return false;
		}

		$lower = strtolower( $trimmed );
		if ( 0 === strpos( $lower, 'mailto:' ) || 0 === strpos( $lower, 'tel:' ) || 0 === strpos( $lower, 'javascript:' ) ) {
			return false;
		}

		$parsed = wp_parse_url( $trimmed );
		if ( false === $parsed ) {
			return false;
		}

		if ( ! isset( $parsed['host'] ) || '' === $parsed['host'] ) {
			return true;
		}

		$home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $home ) || empty( $home['host'] ) ) {
			return false;
		}

		return strtolower( (string) $parsed['host'] ) === strtolower( (string) $home['host'] );
	}

	/**
	 * @param string $url Original URL.
	 */
	private function merge_utms_into_url( $url ) {
		$utms = $this->get_active_utms();
		if ( empty( $utms ) ) {
			return $url;
		}

		if ( ! $this->is_internal_url( $url ) ) {
			return $url;
		}

		$current = wp_parse_url( $url );
		if ( ! is_array( $current ) ) {
			return $url;
		}

		$query = array();
		if ( ! empty( $current['query'] ) ) {
			parse_str( $current['query'], $query );
		}

		foreach ( $utms as $k => $v ) {
			if ( ! isset( $query[ $k ] ) && '' !== $v ) {
				$query[ $k ] = $v;
			}
		}

		$scheme   = isset( $current['scheme'] ) ? $current['scheme'] . '://' : '';
		$host     = $current['host'] ?? '';
		$port     = isset( $current['port'] ) ? ':' . (int) $current['port'] : '';
		$user     = $current['user'] ?? '';
		$pass     = isset( $current['pass'] ) ? ':' . $current['pass'] : '';
		$auth     = '' !== $user ? $user . $pass . '@' : '';
		$path     = $current['path'] ?? '';
		$fragment = isset( $current['fragment'] ) ? '#' . $current['fragment'] : '';

		$new_query = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		$qs        = '' !== $new_query ? '?' . $new_query : '';

		if ( ( '' === $scheme && '' === $host ) ) {
			return $path . $qs . $fragment;
		}

		return $scheme . $auth . $host . $port . $path . $qs . $fragment;
	}

	/**
	 * @param string $content HTML fragment.
	 */
	public function filter_html( $content ) {
		if ( '' === $content || false === strpos( $content, '<a ' ) ) {
			return $content;
		}

		if ( empty( $this->get_active_utms() ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/<a\b([^>]*?)\bhref\s*=\s*([\'"])(.*?)\2([^>]*)>/is',
			function ( $m ) {
				$before = $m[1];
				$quote  = $m[2];
				$href   = $m[3];
				$after  = $m[4];

				$new_href = $this->merge_utms_into_url( $href );
				if ( $new_href === $href ) {
					return $m[0];
				}

				return '<a ' . $before . 'href=' . $quote . esc_attr( esc_url_raw( $new_href ) ) . $quote . $after . '>';
			},
			$content
		);
	}

	public function enqueue_footer_script() {
		if ( is_admin() ) {
			return;
		}

		$params = $this->get_active_utms();
		if ( empty( $params ) ) {
			return;
		}

		wp_register_script(
			'omonschau-wh-utm',
			false,
			array(),
			OMONSCHAU_WH_VERSION,
			true
		);
		wp_enqueue_script( 'omonschau-wh-utm' );
		wp_localize_script(
			'omonschau-wh-utm',
			'omonschauWhUtm',
			array(
				'origin' => home_url( '/' ),
				'params' => $params,
			)
		);

		$inline = <<<'JS'
(function(){
  var cfg = window.omonschauWhUtm;
  if (!cfg || !cfg.params) { return; }
  var params = cfg.params;
  function merge(linkHref){
    try {
      var absolute = new URL(linkHref, window.location.href);
      var home = new URL(cfg.origin);
      if (absolute.origin !== home.origin && absolute.origin !== window.location.origin) { return linkHref; }
      var u = new URL(absolute.toString());
      Object.keys(params).forEach(function(k){
        if (!u.searchParams.has(k)) { u.searchParams.set(k, params[k]); }
      });
      if (linkHref.charAt(0) === '/' && linkHref.charAt(1) !== '/') {
        return u.pathname + u.search + u.hash;
      }
      if (!/^https?:/i.test(linkHref)) {
        return u.pathname + u.search + u.hash;
      }
      return u.toString();
    } catch (e) { return linkHref; }
  }
  function patch(selector){
    var list = document.querySelectorAll(selector);
    for (var i = 0; i < list.length; i++) {
      var el = list[i];
      if (!el.getAttribute('href')) { continue; }
      var next = merge(el.getAttribute('href'));
      if (next && next !== el.getAttribute('href')) { el.setAttribute('href', next); }
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ patch('a[href]'); });
  } else {
    patch('a[href]');
  }
})();
JS;
		wp_add_inline_script( 'omonschau-wh-utm', $inline, 'after' );
	}
}
