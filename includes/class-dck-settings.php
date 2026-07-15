<?php
/**
 * Editable settings — lets the page text, form labels, optional fields, and
 * brand color be changed from wp-admin without touching code.
 *
 * All values live in a single option array ('dck_settings'). Templates read
 * them through dck_setting(); the current hardcoded strings are the defaults.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Settings {

	const OPTION = 'dck_settings';

	private static $instance = null;
	private static $cache    = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest' ) );
	}

	/**
	 * Expose the settings option over the REST API so it can be read/written
	 * at /wp/v2/settings (requires manage_options — i.e. an admin app password).
	 * Partial updates merge over existing values so one key doesn't wipe the rest.
	 */
	public function register_rest() {
		register_setting(
			'options',
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( __CLASS__, 'sanitize_rest' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * Sanitize + merge an incoming (possibly partial) settings object from REST.
	 */
	public static function sanitize_rest( $value ) {
		$in       = is_array( $value ) ? $value : array();
		$existing = get_option( self::OPTION, array() );
		$existing = is_array( $existing ) ? $existing : array();
		$flat     = array();
		foreach ( self::schema() as $group ) {
			foreach ( $group['fields'] as $key => $def ) {
				$flat[ $key ] = $def;
			}
		}
		foreach ( $in as $key => $val ) {
			if ( ! isset( $flat[ $key ] ) ) {
				continue; // ignore unknown keys
			}
			$existing[ $key ] = self::sanitize_one( $flat[ $key ]['type'], $val );
		}
		self::$cache = $existing;
		return $existing;
	}

	/**
	 * Sanitize a single value by field type. Shared by the admin form + REST.
	 */
	public static function sanitize_one( $type, $val ) {
		switch ( $type ) {
			case 'checkbox':
				return ( '1' === (string) $val || 1 === $val || true === $val ) ? '1' : '0';
			case 'textarea':
				return sanitize_textarea_field( (string) $val );
			case 'color':
				return (string) sanitize_hex_color( (string) $val );
			case 'css':
				// Allow CSS (including `>` child combinators) but strip any
				// <style>/<script> tags so the value can't break out of <style>.
				return trim( preg_replace( '#</?\s*(script|style)[^>]*>#i', '', (string) $val ) );
			default:
				return sanitize_text_field( (string) $val );
		}
	}

	/**
	 * Field definitions, grouped into tabs. type: text | textarea | checkbox | color.
	 */
	public static function schema() {
		return array(
			'branding' => array(
				'title'  => __( 'Branding', 'dck-directory' ),
				'fields' => array(
					'brand_color' => array( 'label' => __( 'Brand color', 'dck-directory' ), 'type' => 'color', 'default' => '#1E62B4', 'help' => __( 'Buttons, links, chips, and accents. Deep/soft shades are derived automatically.', 'dck-directory' ) ),
				),
			),
			'landing' => array(
				'title'  => __( 'Directory / search page', 'dck-directory' ),
				'fields' => array(
					'hero_title'      => array( 'label' => __( 'Hero heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Find a decorative concrete pro near you' ),
					'hero_subtitle'   => array( 'label' => __( 'Hero subtext', 'dck-directory' ), 'type' => 'text', 'default' => 'Browse verified contractors for stamped, stained, epoxy, and polished concrete.' ),
					'search_button'   => array( 'label' => __( 'Search button label', 'dck-directory' ), 'type' => 'text', 'default' => 'Search' ),
					'systems_heading' => array( 'label' => __( '"Browse by system" heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Browse by coating system' ),
					'states_heading'  => array( 'label' => __( '"Browse by state" heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Browse by state' ),
					'results_heading' => array( 'label' => __( 'Results heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Contractors' ),
					'cta_title'       => array( 'label' => __( 'Contractor CTA heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Are you a contractor?' ),
					'cta_subtitle'    => array( 'label' => __( 'Contractor CTA subtext', 'dck-directory' ), 'type' => 'text', 'default' => 'List your business free in minutes.' ),
					'cta_button'      => array( 'label' => __( 'Contractor CTA button', 'dck-directory' ), 'type' => 'text', 'default' => 'Add your free listing' ),
				),
			),
			'signup' => array(
				'title'  => __( 'Signup form', 'dck-directory' ),
				'fields' => array(
					'signup_title'        => array( 'label' => __( 'Page heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Add your free listing' ),
					'signup_intro'        => array( 'label' => __( 'Intro text', 'dck-directory' ), 'type' => 'textarea', 'default' => 'Free listings include your business name, address, phone, and category. Upgrade anytime for photos, reviews, and more.' ),
					'signup_systems_label' => array( 'label' => __( 'Coating systems section label', 'dck-directory' ), 'type' => 'text', 'default' => 'Coating systems you offer (select all that apply)' ),
					'signup_areas_label'  => array( 'label' => __( 'Service areas section label', 'dck-directory' ), 'type' => 'text', 'default' => 'Service areas / applications (select all that apply)' ),
					'label_business'      => array( 'label' => __( 'Field label: Business name', 'dck-directory' ), 'type' => 'text', 'default' => 'Business name' ),
					'label_fullname'      => array( 'label' => __( 'Field label: Your name', 'dck-directory' ), 'type' => 'text', 'default' => 'Your name' ),
					'label_email'         => array( 'label' => __( 'Field label: Email', 'dck-directory' ), 'type' => 'text', 'default' => 'Email (your login)' ),
					'label_password'      => array( 'label' => __( 'Field label: Password', 'dck-directory' ), 'type' => 'text', 'default' => 'Password' ),
					'label_phone'         => array( 'label' => __( 'Field label: Phone', 'dck-directory' ), 'type' => 'text', 'default' => 'Phone' ),
					'label_address'       => array( 'label' => __( 'Field label: Street address', 'dck-directory' ), 'type' => 'text', 'default' => 'Street address' ),
					'label_city'          => array( 'label' => __( 'Field label: City', 'dck-directory' ), 'type' => 'text', 'default' => 'City' ),
					'label_state'         => array( 'label' => __( 'Field label: State', 'dck-directory' ), 'type' => 'text', 'default' => 'State' ),
					'label_zip'           => array( 'label' => __( 'Field label: ZIP', 'dck-directory' ), 'type' => 'text', 'default' => 'ZIP' ),
					'show_address'        => array( 'label' => __( 'Show the Street address field', 'dck-directory' ), 'type' => 'checkbox', 'default' => '1' ),
					'show_zip'            => array( 'label' => __( 'Show the ZIP field', 'dck-directory' ), 'type' => 'checkbox', 'default' => '1' ),
					'terms_text'          => array( 'label' => __( 'Consent checkbox text', 'dck-directory' ), 'type' => 'text', 'default' => 'I confirm I represent this business.' ),
					'signup_button'       => array( 'label' => __( 'Submit button label', 'dck-directory' ), 'type' => 'text', 'default' => 'Create free listing' ),
				),
			),
			'dashboard' => array(
				'title'  => __( 'Owner dashboard', 'dck-directory' ),
				'fields' => array(
					'dash_title'        => array( 'label' => __( 'Page heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Manage your listing' ),
					'dash_upgrade_text' => array( 'label' => __( 'Upgrade banner text', 'dck-directory' ), 'type' => 'textarea', 'default' => 'Premium unlocks photos, reviews, hours, website & social links, and featured placement.' ),
					'dash_save_button'  => array( 'label' => __( 'Save button label', 'dck-directory' ), 'type' => 'text', 'default' => 'Save changes' ),
				),
			),
			'profile' => array(
				'title'  => __( 'Contractor profile', 'dck-directory' ),
				'fields' => array(
					'profile_about_heading'       => array( 'label' => __( 'About heading', 'dck-directory' ), 'type' => 'text', 'default' => 'About this contractor' ),
					'profile_services_heading'    => array( 'label' => __( 'Services heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Services offered' ),
					'profile_reviews_heading'     => array( 'label' => __( 'Reviews heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Reviews' ),
					'profile_area_heading'        => array( 'label' => __( 'Service area heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Service area' ),
					'profile_credentials_heading' => array( 'label' => __( 'Credentials heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Credentials & business details' ),
					'profile_faq_heading'         => array( 'label' => __( 'FAQ heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Frequently asked questions' ),
					'profile_quote_heading'       => array( 'label' => __( 'Quote form heading', 'dck-directory' ), 'type' => 'text', 'default' => 'Request a free quote' ),
					'profile_quote_button'        => array( 'label' => __( 'Quote submit button', 'dck-directory' ), 'type' => 'text', 'default' => 'Get my free quote' ),
				),
			),
			'advanced' => array(
				'title'  => __( 'Custom CSS', 'dck-directory' ),
				'fields' => array(
					'custom_css' => array( 'label' => __( 'Custom CSS', 'dck-directory' ), 'type' => 'css', 'default' => '', 'help' => __( 'Applied on every directory page (including contractor profiles and archives). Stored in the database, so it survives plugin updates and is editable over the REST API.', 'dck-directory' ) ),
				),
			),
		);
	}

	/**
	 * Flat map of key => default.
	 */
	public static function defaults() {
		$out = array();
		foreach ( self::schema() as $group ) {
			foreach ( $group['fields'] as $key => $def ) {
				$out[ $key ] = $def['default'];
			}
		}
		return $out;
	}

	/**
	 * Read one setting, falling back to its default.
	 */
	public static function get( $key ) {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = is_array( $saved ) ? $saved : array();
		}
		if ( array_key_exists( $key, self::$cache ) && '' !== self::$cache[ $key ] ) {
			return self::$cache[ $key ];
		}
		$defaults = self::defaults();
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	/**
	 * Boolean helper for checkbox settings.
	 */
	public static function is_on( $key ) {
		return '1' === (string) self::get( $key );
	}

	/* ------------------------------------------------------------------ */

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . DCK_Post_Types::POST_TYPE,
			__( 'DCK Directory Settings', 'dck-directory' ),
			__( 'Settings', 'dck-directory' ),
			'manage_options',
			'dck-settings',
			array( $this, 'render_page' )
		);
	}

	public function assets( $hook ) {
		if ( strpos( (string) $hook, 'dck-settings' ) === false ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".dck-color-field").wpColorPicker();});' );
	}

	public function maybe_save() {
		if ( ! isset( $_POST['dck_settings_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dck_settings_nonce'] ) ), 'dck_save_settings' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$in  = isset( $_POST['dck_settings'] ) ? (array) wp_unslash( $_POST['dck_settings'] ) : array();
		$out = array();
		foreach ( self::schema() as $group ) {
			foreach ( $group['fields'] as $key => $def ) {
				if ( 'checkbox' === $def['type'] ) {
					$out[ $key ] = isset( $in[ $key ] ) ? '1' : '0';
				} else {
					$out[ $key ] = isset( $in[ $key ] ) ? self::sanitize_one( $def['type'], $in[ $key ] ) : '';
				}
			}
		}
		update_option( self::OPTION, $out );
		self::$cache = $out;
		add_settings_error( 'dck_settings', 'saved', __( 'Settings saved.', 'dck-directory' ), 'updated' );
		set_transient( 'dck_settings_saved', 1, 30 );
	}

	public function render_page() {
		$schema = self::schema();
		?>
		<div class="wrap dck-settings-wrap">
			<h1><?php esc_html_e( 'DCK Directory Settings', 'dck-directory' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Edit the wording, form labels, optional fields, and brand color for your directory pages. Categories are managed under Coating Systems and Service Areas.', 'dck-directory' ); ?></p>
			<?php if ( get_transient( 'dck_settings_saved' ) ) : delete_transient( 'dck_settings_saved' ); ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'dck-directory' ); ?></p></div>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( 'dck_save_settings', 'dck_settings_nonce' ); ?>
				<?php foreach ( $schema as $group_key => $group ) : ?>
					<h2 class="title"><?php echo esc_html( $group['title'] ); ?></h2>
					<table class="form-table" role="presentation"><tbody>
						<?php foreach ( $group['fields'] as $key => $def ) :
							$val = self::get( $key ); ?>
							<tr>
								<th scope="row"><label for="dck_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></label></th>
								<td>
									<?php if ( 'css' === $def['type'] ) : ?>
										<textarea id="dck_<?php echo esc_attr( $key ); ?>" name="dck_settings[<?php echo esc_attr( $key ); ?>]" rows="10" class="large-text code" spellcheck="false" style="font-family:Menlo,Consolas,monospace;"><?php echo esc_textarea( $val ); ?></textarea>
									<?php elseif ( 'textarea' === $def['type'] ) : ?>
										<textarea id="dck_<?php echo esc_attr( $key ); ?>" name="dck_settings[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $val ); ?></textarea>
									<?php elseif ( 'checkbox' === $def['type'] ) : ?>
										<label><input type="checkbox" id="dck_<?php echo esc_attr( $key ); ?>" name="dck_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( '1', (string) $val ); ?>> <?php esc_html_e( 'Enabled', 'dck-directory' ); ?></label>
									<?php elseif ( 'color' === $def['type'] ) : ?>
										<input type="text" id="dck_<?php echo esc_attr( $key ); ?>" name="dck_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="dck-color-field" data-default-color="<?php echo esc_attr( $def['default'] ); ?>">
									<?php else : ?>
										<input type="text" id="dck_<?php echo esc_attr( $key ); ?>" name="dck_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="regular-text">
									<?php endif; ?>
									<?php if ( ! empty( $def['help'] ) ) : ?><p class="description"><?php echo esc_html( $def['help'] ); ?></p><?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>
				<?php endforeach; ?>
				<?php submit_button( __( 'Save settings', 'dck-directory' ) ); ?>
			</form>
		</div>
		<?php
	}
}

/**
 * Convenience accessor used throughout the templates.
 */
function dck_setting( $key ) {
	return DCK_Settings::get( $key );
}
function dck_setting_on( $key ) {
	return DCK_Settings::is_on( $key );
}
