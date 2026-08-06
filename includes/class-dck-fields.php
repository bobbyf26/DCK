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
		// Ensure every contractor has _dck_tier, _dck_featured and _dck_rating_avg
		// meta, so the ordering search query never excludes a listing (and REST-
		// created listings behave consistently).
		add_action( 'save_post_' . DCK_Post_Types::POST_TYPE, array( $this, 'ensure_defaults' ), 20, 1 );
		// Geocode the address → lat/lng on save (single listing only — never in
		// the batch backfill, which would fire an HTTP request per listing).
		add_action( 'save_post_' . DCK_Post_Types::POST_TYPE, array( $this, 'geocode_on_save' ), 30, 1 );
		// One-time backfill of those meta on existing listings after an update.
		add_action( 'init', array( $this, 'maybe_backfill' ), 21 );
	}

	/**
	 * Geocode a single listing on save (guards autosave/revisions).
	 */
	public function geocode_on_save( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || get_post_type( $post_id ) !== DCK_Post_Types::POST_TYPE ) {
			return;
		}
		dck_maybe_geocode_listing( $post_id );
	}

	/**
	 * Guarantee the tier / featured / rating-average meta rows exist on a
	 * listing, and keep the rating average in sync with the reviews.
	 */
	public function ensure_defaults( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( get_post_type( $post_id ) !== DCK_Post_Types::POST_TYPE ) {
			return;
		}
		if ( '' === get_post_meta( $post_id, '_dck_tier', true ) ) {
			update_post_meta( $post_id, '_dck_tier', 'free' );
		}
		if ( ! metadata_exists( 'post', $post_id, '_dck_featured' ) ) {
			update_post_meta( $post_id, '_dck_featured', '' );
		}
		update_post_meta( $post_id, '_dck_rating_avg', self::compute_rating_avg( $post_id ) );
	}

	/**
	 * Average review rating (0.0–5.0) from the _dck_reviews JSON, as a string.
	 */
	public static function compute_rating_avg( $post_id ) {
		$reviews = self::get_json( $post_id, 'reviews', false );
		$count   = 0;
		$sum     = 0;
		foreach ( (array) $reviews as $r ) {
			$rt = isset( $r['rating'] ) ? (int) $r['rating'] : 0;
			if ( $rt > 0 ) {
				$count++;
				$sum += $rt;
			}
		}
		return $count ? (string) round( $sum / $count, 2 ) : '0';
	}

	/**
	 * Backfill tier/featured/rating meta across all existing listings once per
	 * version, so pre-existing posts sort correctly without a manual re-save.
	 */
	public function maybe_backfill() {
		if ( get_option( 'dck_backfill_ver' ) === DCK_DIR_VERSION ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => DCK_Post_Types::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$this->ensure_defaults( $id );
		}
		update_option( 'dck_backfill_ver', DCK_DIR_VERSION, false );
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
			// Map coordinates. Auto-filled by geocoding the address; can be set
			// manually (admin / REST), which locks out auto-geocoding.
			'lat'            => array( 'label' => 'Latitude', 'type' => 'text', 'tier' => 'free' ),
			'lng'            => array( 'label' => 'Longitude', 'type' => 'text', 'tier' => 'free' ),

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
					'show_in_rest'  => true,
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
		// Plan controls: readable over REST, writable only by admins.
		$admin_auth = function() {
			return current_user_can( 'manage_options' );
		};
		register_post_meta( DCK_Post_Types::POST_TYPE, '_dck_tier', array( 'single' => true, 'type' => 'string', 'show_in_rest' => true, 'auth_callback' => $admin_auth ) );
		register_post_meta( DCK_Post_Types::POST_TYPE, '_dck_featured', array( 'single' => true, 'type' => 'string', 'show_in_rest' => true, 'auth_callback' => $admin_auth ) );
		register_post_meta( DCK_Post_Types::POST_TYPE, '_dck_rating_avg', array( 'single' => true, 'type' => 'string', 'show_in_rest' => true, 'auth_callback' => $admin_auth ) );

		// Photo for each coating system (attachment ID) — shown on the homepage
		// "Browse by system" tiles. Editable on the term screen + over REST.
		register_term_meta(
			DCK_Post_Types::TAX_SERVICE,
			'_dck_term_image',
			array(
				'single'        => true,
				'type'          => 'integer',
				'show_in_rest'  => true,
				'auth_callback' => function() {
					return current_user_can( 'manage_categories' );
				},
			)
		);
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
