<?php
/**
 * Central definition of listing fields, tiers, and premium gating.
 *
 * Everything that decides "what is free vs premium" lives here so the
 * admin box, dashboard, and profile template all agree.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Fields {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Field schema. `tier` is 'free' or 'premium'. `type` drives how the
	 * dashboard/admin render and sanitize each field.
	 *
	 * @return array
	 */
	public static function schema() {
		return array(
			// --- Free tier: name, address, phone, one category, logo ---
			'address'        => array( 'label' => 'Street address', 'type' => 'text', 'tier' => 'free' ),
			'city'           => array( 'label' => 'City', 'type' => 'text', 'tier' => 'free' ),
			'state'          => array( 'label' => 'State', 'type' => 'text', 'tier' => 'free' ),
			'zip'            => array( 'label' => 'ZIP', 'type' => 'text', 'tier' => 'free' ),
			'phone'          => array( 'label' => 'Phone', 'type' => 'text', 'tier' => 'free' ),

			// --- Premium tier ---
			'website'        => array( 'label' => 'Website URL', 'type' => 'url', 'tier' => 'premium' ),
			'email'          => array( 'label' => 'Public email', 'type' => 'email', 'tier' => 'premium' ),
			'facebook'       => array( 'label' => 'Facebook URL', 'type' => 'url', 'tier' => 'premium' ),
			'instagram'      => array( 'label' => 'Instagram URL', 'type' => 'url', 'tier' => 'premium' ),
			'youtube'        => array( 'label' => 'YouTube URL', 'type' => 'url', 'tier' => 'premium' ),

			'service_area'   => array( 'label' => 'Cities served (comma separated)', 'type' => 'text', 'tier' => 'premium' ),
			'services_list'  => array( 'label' => 'Services offered (one per line)', 'type' => 'textarea', 'tier' => 'premium' ),
			'response_time'  => array( 'label' => 'Typical response time', 'type' => 'text', 'tier' => 'premium' ),

			'year_founded'   => array( 'label' => 'Year founded', 'type' => 'text', 'tier' => 'premium' ),
			'license'        => array( 'label' => 'License #', 'type' => 'text', 'tier' => 'premium' ),
			'insurance'      => array( 'label' => 'Insurance', 'type' => 'text', 'tier' => 'premium' ),
			'crew'           => array( 'label' => 'Crew size', 'type' => 'text', 'tier' => 'premium' ),
			'payment'        => array( 'label' => 'Payment methods', 'type' => 'text', 'tier' => 'premium' ),
			'free_estimates' => array( 'label' => 'Free estimates?', 'type' => 'text', 'tier' => 'premium' ),
			'warranty'       => array( 'label' => 'Warranty', 'type' => 'text', 'tier' => 'premium' ),

			// Structured, stored as JSON. Edited with dedicated UI.
			'hours'          => array( 'label' => 'Business hours', 'type' => 'hours', 'tier' => 'premium' ),
			'faq'            => array( 'label' => 'FAQ', 'type' => 'faq', 'tier' => 'premium' ),
			'reviews'        => array( 'label' => 'Reviews / testimonials', 'type' => 'reviews', 'tier' => 'premium' ),
			'gallery'        => array( 'label' => 'Photo gallery', 'type' => 'gallery', 'tier' => 'premium' ),
		);
	}

	/**
	 * Register post meta so the fields are exposed to REST and sanitized.
	 */
	public function register_meta() {
		foreach ( self::schema() as $key => $def ) {
			register_post_meta(
				DCK_Post_Types::POST_TYPE,
				'_dck_' . $key,
				array(
					'single'        => true,
					'type'          => 'string',
					'show_in_rest'  => false,
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
		register_post_meta( DCK_Post_Types::POST_TYPE, '_dck_tier', array( 'single' => true, 'type' => 'string' ) );
		register_post_meta( DCK_Post_Types::POST_TYPE, '_dck_featured', array( 'single' => true, 'type' => 'string' ) );
	}

	/* ---------------------------------------------------------------------
	 * Tier helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Get a listing's tier. Defaults to 'free'.
	 */
	public static function get_tier( $post_id ) {
		$tier = get_post_meta( $post_id, '_dck_tier', true );
		return ( 'premium' === $tier ) ? 'premium' : 'free';
	}

	public static function is_premium( $post_id ) {
		return 'premium' === self::get_tier( $post_id );
	}

	public static function is_featured( $post_id ) {
		return self::is_premium( $post_id ) && '1' === get_post_meta( $post_id, '_dck_featured', true );
	}

	/**
	 * True when a given field key is available for this listing's tier.
	 */
	public static function field_unlocked( $post_id, $key ) {
		$schema = self::schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return false;
		}
		if ( 'free' === $schema[ $key ]['tier'] ) {
			return true;
		}
		return self::is_premium( $post_id );
	}

	/**
	 * Read a listing field, honoring tier gating on the front end.
	 */
	public static function get( $post_id, $key, $gated = true ) {
		if ( $gated && ! self::field_unlocked( $post_id, $key ) ) {
			return '';
		}
		return get_post_meta( $post_id, '_dck_' . $key, true );
	}

	/**
	 * Decode a JSON field to array.
	 */
	public static function get_json( $post_id, $key, $gated = true ) {
		$raw = self::get( $post_id, $key, $gated );
		if ( empty( $raw ) ) {
			return array();
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	/* ---------------------------------------------------------------------
	 * Saving / sanitizing (shared by admin box + front-end dashboard)
	 * ------------------------------------------------------------------- */

	/**
	 * Sanitize a single incoming value according to its field type.
	 *
	 * @param array $def   Field definition from schema().
	 * @param mixed $value Raw submitted value.
	 * @return string Storage-ready string (JSON for structured types).
	 */
	public static function sanitize_value( $def, $value ) {
		switch ( $def['type'] ) {
			case 'url':
				return esc_url_raw( trim( (string) $value ) );

			case 'email':
				return sanitize_email( (string) $value );

			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'hours':
				// Expect array of [open, close] pairs or empty for closed. 7 rows, index 0 = Sunday.
				$out = array();
				$value = is_array( $value ) ? $value : array();
				for ( $d = 0; $d < 7; $d++ ) {
					$open  = isset( $value[ $d ]['open'] ) ? sanitize_text_field( $value[ $d ]['open'] ) : '';
					$close = isset( $value[ $d ]['close'] ) ? sanitize_text_field( $value[ $d ]['close'] ) : '';
					$out[ $d ] = ( $open && $close ) ? array( $open, $close ) : null;
				}
				return wp_json_encode( $out );

			case 'faq':
				$out = array();
				$value = is_array( $value ) ? $value : array();
				foreach ( $value as $row ) {
					$q = isset( $row['q'] ) ? sanitize_text_field( $row['q'] ) : '';
					$a = isset( $row['a'] ) ? sanitize_textarea_field( $row['a'] ) : '';
					if ( $q ) {
						$out[] = array( 'q' => $q, 'a' => $a );
					}
				}
				return wp_json_encode( $out );

			case 'reviews':
				$out = array();
				$value = is_array( $value ) ? $value : array();
				foreach ( $value as $row ) {
					$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
					if ( ! $name ) {
						continue;
					}
					$out[] = array(
						'name'     => $name,
						'location' => isset( $row['location'] ) ? sanitize_text_field( $row['location'] ) : '',
						'date'     => isset( $row['date'] ) ? sanitize_text_field( $row['date'] ) : '',
						'rating'   => isset( $row['rating'] ) ? max( 1, min( 5, (int) $row['rating'] ) ) : 5,
						'text'     => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
						'tag'      => isset( $row['tag'] ) ? sanitize_text_field( $row['tag'] ) : '',
						'reply'    => isset( $row['reply'] ) ? sanitize_textarea_field( $row['reply'] ) : '',
					);
				}
				return wp_json_encode( $out );

			case 'gallery':
				// Comma-separated attachment IDs.
				$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
				return implode( ',', $ids );

			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Persist a set of listing fields from a submitted array.
	 *
	 * Premium fields are only written when the listing is premium (unless
	 * $allow_premium is forced true, e.g. from wp-admin where you set tiers).
	 *
	 * @param int   $post_id       Listing ID.
	 * @param array $input         Raw input keyed by field key (no _dck_ prefix).
	 * @param bool  $allow_premium Whether premium fields may be written.
	 */
	public static function save( $post_id, $input, $allow_premium = null ) {
		if ( null === $allow_premium ) {
			$allow_premium = self::is_premium( $post_id );
		}
		foreach ( self::schema() as $key => $def ) {
			if ( 'premium' === $def['tier'] && ! $allow_premium ) {
				continue; // Do not let free listings write premium data.
			}
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$clean = self::sanitize_value( $def, $input[ $key ] );
			update_post_meta( $post_id, '_dck_' . $key, $clean );
		}
	}
}
