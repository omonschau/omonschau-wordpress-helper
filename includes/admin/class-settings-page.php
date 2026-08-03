<?php
/**
 * Tools submenu and settings UI (German labels).
 *
 * @package OmonschauWordPressHelper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Omonschau_WH_Settings_Page {

	const OPTION_GROUP = 'omonschau_wh_settings_group';
	const PAGE_SLUG    = 'omonschau-wordpress-helper';

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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	public function add_menu() {
		add_management_page(
			__( 'WordPress Helper', 'omonschau-wordpress-helper' ),
			__( 'WordPress Helper', 'omonschau-wordpress-helper' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			OMONSCHAU_WH_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(
					'enabled_features' => array(),
				),
			)
		);
	}

	/**
	 * @param mixed $value Raw option value.
	 * @return array<string, mixed>
	 */
	public function sanitize_options( $value ) {
		if ( ! is_array( $value ) ) {
			return array(
				'enabled_features' => array(),
			);
		}

		$allowed_keys = array(
			Omonschau_WH_Plugin::FEATURE_DISABLE_COMMENTS,
			Omonschau_WH_Plugin::FEATURE_REDUCE_ADMIN,
			Omonschau_WH_Plugin::FEATURE_UTM_PERSIST,
			Omonschau_WH_Plugin::FEATURE_AI_DISCLOSURE,
		);

		$enabled = array();
		if ( isset( $value['enabled_features'] ) && is_array( $value['enabled_features'] ) ) {
			foreach ( $value['enabled_features'] as $key ) {
				if ( is_string( $key ) && in_array( $key, $allowed_keys, true ) ) {
					$enabled[] = $key;
				}
			}
		}

		return array(
			'enabled_features' => array_values( array_unique( $enabled ) ),
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$opts           = Omonschau_WH_Plugin::get_options();
		$enabled        = isset( $opts['enabled_features'] ) && is_array( $opts['enabled_features'] )
			? $opts['enabled_features']
			: array();
		$form_enabled   = isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WordPress Helper', 'omonschau-wordpress-helper' ); ?></h1>
			<p><?php esc_html_e( 'Aktivieren Sie die gewünschten Funktionen für diese Website.', 'omonschau-wordpress-helper' ); ?></p>

			<?php if ( $form_enabled ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellungen gespeichert.', 'omonschau-wordpress-helper' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Funktionen', 'omonschau-wordpress-helper' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( OMONSCHAU_WH_OPTION_KEY ); ?>[enabled_features][]" value="<?php echo esc_attr( Omonschau_WH_Plugin::FEATURE_DISABLE_COMMENTS ); ?>"
											<?php checked( in_array( Omonschau_WH_Plugin::FEATURE_DISABLE_COMMENTS, $enabled, true ) ); ?> />
										<?php esc_html_e( 'Kommentare, Pings und Trackbacks deaktivieren', 'omonschau-wordpress-helper' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Schließt neue Kommentare und Pings aus, entfernt die Kommentar-Verwaltung im Backend und mindert Spam-Zugriffe.', 'omonschau-wordpress-helper' ); ?></p>

									<br />

									<label>
										<input type="checkbox" name="<?php echo esc_attr( OMONSCHAU_WH_OPTION_KEY ); ?>[enabled_features][]" value="<?php echo esc_attr( Omonschau_WH_Plugin::FEATURE_REDUCE_ADMIN ); ?>"
											<?php checked( in_array( Omonschau_WH_Plugin::FEATURE_REDUCE_ADMIN, $enabled, true ) ); ?> />
										<?php esc_html_e( 'Admin-Ansicht reduzieren', 'omonschau-wordpress-helper' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Blendet ausgewählte Dashboard-Widgets und Admin-Leisten-Einträge aus.', 'omonschau-wordpress-helper' ); ?></p>

									<br />

									<label>
										<input type="checkbox" name="<?php echo esc_attr( OMONSCHAU_WH_OPTION_KEY ); ?>[enabled_features][]" value="<?php echo esc_attr( Omonschau_WH_Plugin::FEATURE_UTM_PERSIST ); ?>"
											<?php checked( in_array( Omonschau_WH_Plugin::FEATURE_UTM_PERSIST, $enabled, true ) ); ?> />
										<?php esc_html_e( 'UTM-Parameter auf interne Links übernehmen (Frontend)', 'omonschau-wordpress-helper' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Speichert beim ersten Besuch mit UTM-Parametern in der URL deren Werte und hängt sie an interne Links an (nur öffentliche Seiten, nicht wp-admin).', 'omonschau-wordpress-helper' ); ?></p>

									<br />

									<label>
										<input type="checkbox" name="<?php echo esc_attr( OMONSCHAU_WH_OPTION_KEY ); ?>[enabled_features][]" value="<?php echo esc_attr( Omonschau_WH_Plugin::FEATURE_AI_DISCLOSURE ); ?>"
											<?php checked( in_array( Omonschau_WH_Plugin::FEATURE_AI_DISCLOSURE, $enabled, true ) ); ?> />
										<?php esc_html_e( 'KI-Kennzeichnung für Medien (EU AI Act)', 'omonschau-wordpress-helper' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Fügt in der Mediathek ein KI-Status-Feld hinzu und zeigt im Frontend ein Badge bei KI-generierten oder KI-modifizierten Bildern.', 'omonschau-wordpress-helper' ); ?></p>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Änderungen speichern', 'omonschau-wordpress-helper' ) ); ?>
			</form>
		</div>
		<?php
	}
}
