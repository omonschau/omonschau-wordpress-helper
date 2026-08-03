<?php
/**
 * EU AI Act media disclosure: attachment meta field and frontend badges.
 *
 * @package OmonschauWordPressHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Omonschau_WH_Feature_Ai_Disclosure {

	const META_KEY_DEFAULT = '_omonschau_wh_ai_usage';

	const STATUS_NONE      = 'none';
	const STATUS_GENERATED = 'generated';
	const STATUS_MODIFIED  = 'modified';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var array<int, string>
	 */
	private static $usage_cache = array();

	/**
	 * @var array<string, bool>
	 */
	private static $bb_bg_badge_nodes = array();

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
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'attachment_fields_to_save' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_field' ) );

		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 20 );
		add_filter( 'wp_get_attachment_image', array( $this, 'filter_attachment_image' ), 20, 5 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_post_thumbnail_html' ), 20, 5 );

		if ( function_exists( 'wp_content_img_tag' ) ) {
			add_filter( 'wp_content_img_tag', array( $this, 'filter_content_img_tag' ), 20, 3 );
		} else {
			add_filter( 'the_content', array( $this, 'filter_content_images_legacy' ), 20 );
		}

		add_filter( 'the_content', array( $this, 'filter_builder_content_images' ), 999 );

		if ( class_exists( 'FLBuilder' ) ) {
			add_filter( 'fl_builder_render_module_html_content', array( $this, 'filter_bb_module_html' ), 20, 4 );
			add_filter( 'fl_builder_render_css', array( $this, 'filter_bb_css' ), 20, 4 );
		}
	}

	/**
	 * @return string
	 */
	private function meta_key() {
		return apply_filters( 'omonschau_wh_ai_disclosure_meta_key', self::META_KEY_DEFAULT );
	}

	/**
	 * @return array<string, string>
	 */
	private function labels() {
		$defaults = array(
			self::STATUS_NONE      => __( 'Kein KI-Einsatz', 'omonschau-wordpress-helper' ),
			self::STATUS_GENERATED => __( 'KI-generiert', 'omonschau-wordpress-helper' ),
			self::STATUS_MODIFIED  => __( 'KI-modifiziert', 'omonschau-wordpress-helper' ),
		);

		return apply_filters( 'omonschau_wh_ai_disclosure_labels', $defaults );
	}

	/**
	 * @return array<int, string>
	 */
	private function allowed_statuses() {
		return array(
			self::STATUS_NONE,
			self::STATUS_GENERATED,
			self::STATUS_MODIFIED,
		);
	}

	/**
	 * @param mixed $raw Raw status value.
	 */
	private function sanitize_status( $raw ) {
		if ( ! is_string( $raw ) ) {
			return self::STATUS_NONE;
		}
		$raw = sanitize_key( $raw );
		return in_array( $raw, $this->allowed_statuses(), true ) ? $raw : self::STATUS_NONE;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 */
	public function get_usage( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return self::STATUS_NONE;
		}

		if ( isset( self::$usage_cache[ $attachment_id ] ) ) {
			return self::$usage_cache[ $attachment_id ];
		}

		$stored = get_post_meta( $attachment_id, $this->meta_key(), true );
		if ( ! is_string( $stored ) || '' === $stored ) {
			$status = self::STATUS_NONE;
		} else {
			$status = $this->sanitize_status( $stored );
		}

		self::$usage_cache[ $attachment_id ] = $status;
		return $status;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 */
	private function is_image_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return false;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! is_string( $mime ) || '' === $mime ) {
			return false;
		}

		return 0 === strpos( $mime, 'image/' );
	}

	/**
	 * @param string $status AI usage status.
	 */
	private function is_badge_status( $status ) {
		return self::STATUS_GENERATED === $status || self::STATUS_MODIFIED === $status;
	}

	/**
	 * @param int    $attachment_id Attachment ID.
	 * @param string $status        AI usage status.
	 */
	private function should_show_badge( $attachment_id, $status ) {
		if ( ! $this->is_badge_status( $status ) ) {
			return false;
		}
		if ( ! $this->is_image_attachment( $attachment_id ) ) {
			return false;
		}

		return (bool) apply_filters( 'omonschau_wh_ai_disclosure_show_badge', true, $attachment_id, $status );
	}

	/**
	 * @param array<string, string> $fields Attachment form fields.
	 * @param WP_Post               $post   Attachment post.
	 * @return array<string, string>
	 */
	public function attachment_fields_to_edit( $fields, $post ) {
		$value   = $this->get_usage( $post->ID );
		$labels  = $this->labels();
		$options = '';

		foreach ( $this->allowed_statuses() as $status ) {
			$options .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $status ),
				selected( $value, $status, false ),
				esc_html( $labels[ $status ] )
			);
		}

		$fields['omonschau_wh_ai_usage'] = array(
			'label' => __( 'KI-Einsatz', 'omonschau-wordpress-helper' ),
			'input' => 'html',
			'html'  => '<select name="attachments[' . (int) $post->ID . '][omonschau_wh_ai_usage]" id="attachments-' . (int) $post->ID . '-omonschau-wh-ai-usage">' . $options . '</select>',
			'helps' => __( 'Kennzeichnung gemäß EU AI Act.', 'omonschau-wordpress-helper' ),
		);

		return $fields;
	}

	/**
	 * @param array<string, mixed> $post       Attachment post data.
	 * @param array<string, mixed> $attachment Submitted attachment fields.
	 * @return array<string, mixed>
	 */
	public function attachment_fields_to_save( $post, $attachment ) {
		if ( ! current_user_can( 'edit_post', $post['ID'] ) ) {
			return $post;
		}

		if ( ! isset( $attachment['omonschau_wh_ai_usage'] ) ) {
			return $post;
		}

		$status = $this->sanitize_status( $attachment['omonschau_wh_ai_usage'] );
		$id     = (int) $post['ID'];

		if ( self::STATUS_NONE === $status ) {
			delete_post_meta( $id, $this->meta_key() );
			self::$usage_cache[ $id ] = self::STATUS_NONE;
		} else {
			update_post_meta( $id, $this->meta_key(), $status );
			self::$usage_cache[ $id ] = $status;
		}

		return $post;
	}

	public function register_rest_field() {
		register_rest_field(
			'attachment',
			'omonschau_wh_ai_usage',
			array(
				'get_callback' => function ( $object ) {
					$id = isset( $object['id'] ) ? (int) $object['id'] : 0;
					return $this->get_usage( $id );
				},
				'update_callback' => function ( $value, $object ) {
					$id = isset( $object->ID ) ? (int) $object->ID : 0;
					if ( $id <= 0 || ! current_user_can( 'edit_post', $id ) ) {
						return new WP_Error(
							'omonschau_wh_ai_disclosure_forbidden',
							__( 'Keine Berechtigung.', 'omonschau-wordpress-helper' ),
							array( 'status' => 403 )
						);
					}

					$status = $this->sanitize_status( $value );
					if ( self::STATUS_NONE === $status ) {
						delete_post_meta( $id, $this->meta_key() );
					} else {
						update_post_meta( $id, $this->meta_key(), $status );
					}
					self::$usage_cache[ $id ] = $status;

					return true;
				},
				'schema' => array(
					'type'              => 'string',
					'enum'              => $this->allowed_statuses(),
					'context'           => array( 'view', 'edit' ),
					'description'       => __( 'KI-Einsatz-Kennzeichnung für Medien.', 'omonschau-wordpress-helper' ),
				),
			)
		);
	}

	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'omonschau-wh-ai-disclosure',
			OMONSCHAU_WH_PLUGIN_URL . 'assets/css/ai-disclosure-frontend.css',
			array(),
			OMONSCHAU_WH_VERSION
		);
	}

	/**
	 * @param string $status AI usage status.
	 */
	private function build_badge_html( $status ) {
		$labels = $this->labels();
		$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : '';

		return sprintf(
			'<span class="omonschau-wh-ai-badge omonschau-wh-ai-badge--%1$s" role="img" aria-label="%2$s">%3$s</span>',
			esc_attr( $status ),
			esc_attr( $label ),
			esc_html( $label )
		);
	}

	/**
	 * @param string $html          Image HTML.
	 * @param int    $attachment_id Attachment ID.
	 */
	private function maybe_wrap_image_html( $html, $attachment_id ) {
		if ( '' === $html || false !== strpos( $html, 'omonschau-wh-ai-badge-wrap' ) ) {
			return $html;
		}

		$status = $this->get_usage( $attachment_id );
		if ( ! $this->should_show_badge( $attachment_id, $status ) ) {
			return $html;
		}

		return '<span class="omonschau-wh-ai-badge-wrap">' . $html . $this->build_badge_html( $status ) . '</span>';
	}

	/**
	 * @param string       $html          Image HTML.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Image size.
	 * @param bool         $icon          Icon flag.
	 * @param string[]     $attr          Attributes.
	 */
	public function filter_attachment_image( $html, $attachment_id, $size, $icon, $attr ) {
		unset( $size, $icon, $attr );
		return $this->maybe_wrap_image_html( $html, (int) $attachment_id );
	}

	/**
	 * @param string       $html              Thumbnail HTML.
	 * @param int          $post_id           Post ID.
	 * @param int          $post_thumbnail_id Attachment ID.
	 * @param string|int[] $size              Image size.
	 * @param string[]     $attr              Attributes.
	 */
	public function filter_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		unset( $post_id, $size, $attr );
		return $this->maybe_wrap_image_html( $html, (int) $post_thumbnail_id );
	}

	/**
	 * @param string $filtered_image Image HTML.
	 * @param string $context        Context.
	 * @param int    $attachment_id  Attachment ID.
	 */
	public function filter_content_img_tag( $filtered_image, $context, $attachment_id ) {
		unset( $context );
		return $this->maybe_wrap_image_html( $filtered_image, (int) $attachment_id );
	}

	/**
	 * @param string $content Post content.
	 */
	public function filter_content_images_legacy( $content ) {
		if ( '' === $content || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/<img\b[^>]*\bclass=["\'][^"\']*\bwp-image-(\d+)\b[^"\']*["\'][^>]*>/i',
			function ( $matches ) {
				$attachment_id = (int) $matches[1];
				return $this->maybe_wrap_image_html( $matches[0], $attachment_id );
			},
			$content
		);
	}

	/**
	 * @param string $content Post content.
	 */
	public function filter_builder_content_images( $content ) {
		if ( '' === $content || false === strpos( $content, 'fl-builder-content' ) || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false !== strpos( $tag, 'omonschau-wh-ai-badge-wrap' ) ) {
					return $tag;
				}

				$attachment_id = $this->extract_attachment_id_from_img_tag( $tag );
				if ( $attachment_id <= 0 ) {
					return $tag;
				}

				return $this->maybe_wrap_image_html( $tag, $attachment_id );
			},
			$content
		);
	}

	/**
	 * @param string $type     Module type slug.
	 * @param object $settings Module settings.
	 * @param object $module   Module instance.
	 */
	public function filter_bb_module_html( $content, $type, $settings, $module ) {
		unset( $module );

		if ( '' === $content || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		$attachment_ids = $this->collect_bb_module_attachment_ids( $type, $settings );
		if ( empty( $attachment_ids ) ) {
			return $this->filter_builder_content_images( $content );
		}

		$index = 0;
		return (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			function ( $matches ) use ( &$index, $attachment_ids ) {
				$tag = $matches[0];
				if ( false !== strpos( $tag, 'omonschau-wh-ai-badge-wrap' ) ) {
					return $tag;
				}

				$attachment_id = 0;
				if ( isset( $attachment_ids[ $index ] ) ) {
					$attachment_id = (int) $attachment_ids[ $index ];
				} else {
					$attachment_id = $this->extract_attachment_id_from_img_tag( $tag );
				}
				++$index;

				if ( $attachment_id <= 0 ) {
					return $tag;
				}

				return $this->maybe_wrap_image_html( $tag, $attachment_id );
			},
			$content
		);
	}

	/**
	 * @param string $css             Compiled CSS.
	 * @param array  $nodes           Layout nodes.
	 * @param object $global_settings Global settings.
	 * @param bool   $include_global  Include global flag.
	 */
	public function filter_bb_css( $css, $nodes, $global_settings, $include_global ) {
		unset( $global_settings, $include_global );

		if ( ! is_array( $nodes ) ) {
			return $css;
		}

		$bg_fields = array( 'bg_image', 'bg_image_medium', 'bg_image_responsive' );

		foreach ( $nodes as $node ) {
			if ( ! is_object( $node ) || empty( $node->node ) || empty( $node->settings ) ) {
				continue;
			}

			$node_id       = sanitize_html_class( (string) $node->node );
			$attachment_id = 0;

			foreach ( $bg_fields as $field ) {
				if ( ! empty( $node->settings->$field ) ) {
					$attachment_id = $this->resolve_attachment_id( $node->settings->$field );
					if ( $attachment_id > 0 ) {
						break;
					}
				}
			}

			if ( $attachment_id <= 0 ) {
				continue;
			}

			$status = $this->get_usage( $attachment_id );
			if ( ! $this->should_show_badge( $attachment_id, $status ) ) {
				continue;
			}

			if ( isset( self::$bb_bg_badge_nodes[ $node_id ] ) ) {
				continue;
			}
			self::$bb_bg_badge_nodes[ $node_id ] = true;

			$labels = $this->labels();
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : '';
			$bg     = self::STATUS_GENERATED === $status ? 'rgba(30,30,30,0.78)' : 'rgba(45,45,55,0.78)';

			$css .= sprintf(
				'.fl-builder-content .fl-node-%1$s { position: relative; }' .
				'.fl-builder-content .fl-node-%1$s::after { content: %2$s; position: absolute; bottom: 6px; right: 6px; z-index: 10; display: inline-block; padding: 3px 7px; font-size: 11px; font-weight: 600; line-height: 1.3; color: #fff; background: %3$s; border: 1px solid rgba(255,255,255,0.25); border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.35); pointer-events: none; white-space: nowrap; }',
				esc_attr( $node_id ),
				$this->css_string( $label ),
				esc_attr( $bg )
			);
		}

		return $css;
	}

	/**
	 * @param string $type     Module type.
	 * @param object $settings Module settings.
	 * @return array<int, int>
	 */
	private function collect_bb_module_attachment_ids( $type, $settings ) {
		if ( ! is_object( $settings ) ) {
			return array();
		}

		if ( 'photo' === $type ) {
			if ( isset( $settings->photo_source ) && 'url' === $settings->photo_source ) {
				return array();
			}
			$id = $this->resolve_attachment_id( $settings->photo ?? null );
			return $id > 0 ? array( $id ) : array();
		}

		$list_types = array( 'gallery', 'slideshow', 'content-slider' );
		if ( in_array( $type, $list_types, true ) ) {
			$list_keys = array( 'photos', 'gallery', 'ids', 'gallery_ids', 'items' );
			foreach ( $list_keys as $key ) {
				if ( isset( $settings->$key ) ) {
					return $this->extract_attachment_ids_from_value( $settings->$key );
				}
			}
		}

		return array();
	}

	/**
	 * @param mixed $value Settings value or object.
	 * @return array<int, int>
	 */
	private function extract_attachment_ids_from_value( $value ) {
		$ids = array();

		if ( is_numeric( $value ) ) {
			$id = (int) $value;
			return $id > 0 ? array( $id ) : array();
		}

		if ( is_object( $value ) ) {
			if ( isset( $value->id ) && is_numeric( $value->id ) ) {
				$ids[] = (int) $value->id;
			}
			foreach ( get_object_vars( $value ) as $prop_value ) {
				$ids = array_merge( $ids, $this->extract_attachment_ids_from_value( $prop_value ) );
			}
			return array_values( array_unique( array_filter( $ids ) ) );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		foreach ( $value as $item ) {
			if ( is_numeric( $item ) ) {
				$id = (int) $item;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
				continue;
			}
			if ( is_object( $item ) && isset( $item->id ) && is_numeric( $item->id ) ) {
				$ids[] = (int) $item->id;
				continue;
			}
			if ( is_array( $item ) || is_object( $item ) ) {
				$ids = array_merge( $ids, $this->extract_attachment_ids_from_value( $item ) );
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param mixed $value Photo field value.
	 */
	private function resolve_attachment_id( $value ) {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		if ( is_object( $value ) && isset( $value->id ) && is_numeric( $value->id ) ) {
			return (int) $value->id;
		}
		if ( is_string( $value ) && is_numeric( $value ) ) {
			return (int) $value;
		}
		return 0;
	}

	/**
	 * @param string $tag Image HTML tag.
	 */
	private function extract_attachment_id_from_img_tag( $tag ) {
		if ( preg_match( '/\bwp-image-(\d+)\b/i', $tag, $matches ) ) {
			return (int) $matches[1];
		}

		if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $matches ) ) {
			$url = $matches[1];
			if ( is_string( $url ) && '' !== $url ) {
				$id = attachment_url_to_postid( $url );
				if ( $id > 0 ) {
					return (int) $id;
				}
			}
		}

		return 0;
	}

	/**
	 * @param string $value String for CSS content property.
	 */
	private function css_string( $value ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $value ) . '"';
	}
}
