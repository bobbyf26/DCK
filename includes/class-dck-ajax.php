<?php
/**
 * AJAX endpoints: directory search + lead capture.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_dck_search', array( $this, 'search' ) );
		add_action( 'wp_ajax_nopriv_dck_search', array( $this, 'search' ) );
		add_action( 'wp_ajax_dck_lead', array( $this, 'lead' ) );
		add_action( 'wp_ajax_nopriv_dck_lead', array( $this, 'lead' ) );
		add_action( 'wp_ajax_dck_geo_suggest', array( $this, 'geo_suggest' ) );
		add_action( 'wp_ajax_nopriv_dck_geo_suggest', array( $this, 'geo_suggest' ) );
	}

	/**
	 * Location typeahead: suggest US places for the "Where" box via OpenStreetMap
	 * Nominatim. Cached per query. Each item carries lat/lng and, when the place's
	 * state matches one of our location terms, that state's slug ('loc') so a pick
	 * filters straight to it.
	 */
	public function geo_suggest() {
		check_ajax_referer( 'dck_dir_nonce', 'nonce', false );
		$q = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
		if ( strlen( $q ) < 3 ) {
			wp_send_json_success( array( 'items' => array() ) );
		}
		$key    = 'dck_sg_' . md5( strtolower( $q ) );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			wp_send_json_success( array( 'items' => $cached ) );
		}
		$url  = add_query_arg(
			array( 'q' => rawurlencode( $q ), 'format' => 'json', 'addressdetails' => 1, 'limit' => 6, 'countrycodes' => 'us', 'dedupe' => 1 ),
			'https://nominatim.openstreetmap.org/search'
		);
		$resp = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array( 'User-Agent' => 'DCK-Directory/1.8 (' . home_url( '/' ) . '; ' . get_option( 'admin_email' ) . ')' ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			wp_send_json_success( array( 'items' => array() ) );
		}
		$body  = json_decode( wp_remote_retrieve_body( $resp ), true );
		$items = array();
		if ( is_array( $body ) ) {
			// Map state name → our location-term slug (so a pick can filter).
			$state_slug  = array();
			$state_terms = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'parent' => 0, 'hide_empty' => false ) );
			if ( ! is_wp_error( $state_terms ) ) {
				foreach ( $state_terms as $st ) {
					$state_slug[ strtolower( $st->name ) ] = $st->slug;
				}
			}
			foreach ( $body as $r ) {
				if ( empty( $r['lat'] ) || empty( $r['lon'] ) ) {
					continue;
				}
				$addr = isset( $r['address'] ) && is_array( $r['address'] ) ? $r['address'] : array();
				$city = '';
				foreach ( array( 'city', 'town', 'village', 'hamlet', 'county' ) as $ck ) {
					if ( ! empty( $addr[ $ck ] ) ) {
						$city = $addr[ $ck ];
						break;
					}
				}
				$state_name = ! empty( $addr['state'] ) ? $addr['state'] : '';
				$label      = trim( ( $city ? $city : ( isset( $r['name'] ) ? $r['name'] : '' ) ) . ( $state_name ? ', ' . $state_name : '' ) );
				if ( '' === $label ) {
					$label = isset( $r['display_name'] ) ? $r['display_name'] : $q;
				}
				$items[] = array(
					'label' => $label,
					'lat'   => (string) $r['lat'],
					'lng'   => (string) $r['lon'],
					'loc'   => ( $state_name && isset( $state_slug[ strtolower( $state_name ) ] ) ) ? $state_slug[ strtolower( $state_name ) ] : '',
				);
				if ( count( $items ) >= 6 ) {
					break;
				}
			}
		}
		set_transient( $key, $items, DAY_IN_SECONDS );
		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * Search contractors. Filters: service (term slug), location (term slug),
	 * keyword. Featured premium listings float to the top.
	 */
	public function search() {
		// Read-only, public data. Tolerate an expired/invalid nonce so a visitor
		// served a stale (LiteSpeed-cached) page still gets results instead of a
		// silent -1. The nonce is still checked (best effort) but never fatal.
		check_ajax_referer( 'dck_dir_nonce', 'nonce', false );

		// Filters are multi-select (services[]/areas[]); keep back-compat with the
		// old singular service/area params.
		$services = $this->slug_list( 'services' );
		if ( ! $services && isset( $_POST['service'] ) ) {
			$services = array_filter( array( sanitize_title( wp_unslash( $_POST['service'] ) ) ) );
		}
		$areas = $this->slug_list( 'areas' );
		if ( ! $areas && isset( $_POST['area'] ) ) {
			$areas = array_filter( array( sanitize_title( wp_unslash( $_POST['area'] ) ) ) );
		}
		$location = isset( $_POST['location'] ) ? sanitize_title( wp_unslash( $_POST['location'] ) ) : '';
		$keyword  = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

		$near_lat  = ( isset( $_POST['near_lat'] ) && '' !== $_POST['near_lat'] ) ? (float) $_POST['near_lat'] : null;
		$near_lng  = ( isset( $_POST['near_lng'] ) && '' !== $_POST['near_lng'] ) ? (float) $_POST['near_lng'] : null;
		$proximity = ( null !== $near_lat && null !== $near_lng && abs( $near_lat ) <= 90 && abs( $near_lng ) <= 180 );

		$tax_query = array( 'relation' => 'AND' );
		if ( $services ) {
			$tax_query[] = array(
				'taxonomy' => DCK_Post_Types::TAX_SERVICE,
				'field'    => 'slug',
				'terms'    => $services,
				'operator' => 'IN',
			);
		}
		if ( $areas ) {
			$tax_query[] = array(
				'taxonomy' => DCK_Post_Types::TAX_AREA,
				'field'    => 'slug',
				'terms'    => $areas,
				'operator' => 'IN',
			);
		}
		// In proximity mode we do NOT constrain by the location term — the whole
		// point is to show the nearest contractors even if none are in that place.
		if ( $location && ! $proximity ) {
			$tax_query[] = array(
				'taxonomy'         => DCK_Post_Types::TAX_LOCATION,
				'field'            => 'slug',
				'terms'            => $location,
				'include_children' => true,
			);
		}

		$per  = 10;
		$base = array(
			'post_type'   => DCK_Post_Types::POST_TYPE,
			'post_status' => 'publish',
			's'           => $proximity ? '' : $keyword,
		);
		if ( count( $tax_query ) > 1 ) {
			$base['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		// --- Proximity mode: rank every match by distance, nearest first, so a
		// searched area always returns the closest contractors (paged in PHP). ---
		if ( $proximity ) {
			$ids = get_posts( array_merge( $base, array( 'posts_per_page' => 300, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) ) );
			$with = array();
			$without = array();
			foreach ( $ids as $id ) {
				$lat = get_post_meta( $id, '_dck_lat', true );
				$lng = get_post_meta( $id, '_dck_lng', true );
				if ( '' !== (string) $lat && '' !== (string) $lng ) {
					$with[] = array( 'id' => $id, 'd' => dck_distance_mi( $near_lat, $near_lng, (float) $lat, (float) $lng ) );
				} else {
					$without[] = $id;
				}
			}
			usort( $with, function ( $a, $b ) { return $a['d'] <=> $b['d']; } );
			$ordered = array_map( function ( $x ) { return $x['id']; }, $with );
			$ordered = array_merge( $ordered, $without ); // listings without coords go last
			$total   = count( $ordered );
			$pages   = (int) max( 1, ceil( $total / $per ) );
			$slice   = array_slice( $ordered, ( $paged - 1 ) * $per, $per );
			list( $html, $markers ) = $this->cards_and_markers( $slice );
			wp_send_json_success(
				array(
					'html'    => $html,
					'found'   => $total,
					'pages'   => $pages,
					'paged'   => $paged,
					'markers' => $markers,
					'center'  => array( 'lat' => $near_lat, 'lng' => $near_lng ),
				)
			);
		}

		// --- Default ranked mode: featured → premium → rating → newest. ---
		$args = array_merge(
			$base,
			array(
				'posts_per_page' => $per,
				'paged'          => $paged,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation'        => 'AND',
					'featured_clause' => array( 'key' => '_dck_featured', 'compare' => 'EXISTS' ),
					'tier_clause'     => array( 'key' => '_dck_tier', 'compare' => 'EXISTS' ),
					'rating_clause'   => array( 'key' => '_dck_rating_avg', 'type' => 'DECIMAL(10,2)', 'compare' => 'EXISTS' ),
				),
				'orderby'        => array(
					'featured_clause' => 'DESC',
					'tier_clause'     => 'DESC',
					'rating_clause'   => 'DESC',
					'date'            => 'DESC',
				),
			)
		);

		$q = new WP_Query( $args );
		list( $html, $markers ) = $this->cards_and_markers( wp_list_pluck( $q->posts, 'ID' ) );
		wp_send_json_success(
			array(
				'html'    => $html,
				'found'   => (int) $q->found_posts,
				'pages'   => (int) $q->max_num_pages,
				'paged'   => $paged,
				'markers' => $markers,
			)
		);
	}

	/**
	 * Build result-card HTML + map markers for a list of listing IDs.
	 *
	 * @return array [ html, markers ]
	 */
	private function cards_and_markers( $ids ) {
		$html    = '';
		$markers = array();
		foreach ( (array) $ids as $id ) {
			$html .= dck_render_card( $id );
			$lat = get_post_meta( $id, '_dck_lat', true );
			$lng = get_post_meta( $id, '_dck_lng', true );
			if ( '' !== (string) $lat && '' !== (string) $lng ) {
				$markers[] = array(
					'id'      => $id,
					'lat'     => (float) $lat,
					'lng'     => (float) $lng,
					'name'    => get_the_title( $id ),
					'url'     => get_permalink( $id ),
					'rating'  => (float) get_post_meta( $id, '_dck_rating_avg', true ),
					'city'    => trim( get_post_meta( $id, '_dck_city', true ) . ', ' . get_post_meta( $id, '_dck_state', true ), ', ' ),
					'premium' => DCK_Fields::is_premium( $id ) ? 1 : 0,
				);
			}
		}
		return array( $html, $markers );
	}

	/**
	 * Read a POSTed array of slugs (e.g. services[]) as a sanitized list.
	 */
	private function slug_list( $key ) {
		if ( empty( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return array();
		}
		$out = array_map( 'sanitize_title', array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) ) );
		return array_values( array_filter( array_unique( $out ) ) );
	}

	/**
	 * Store a quote lead + notify the contractor / site admin.
	 */
	public function lead() {
		check_ajax_referer( 'dck_dir_nonce', 'nonce' );

		$listing = isset( $_POST['listing'] ) ? absint( $_POST['listing'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $listing || ! $name || ! $phone ) {
			wp_send_json_error( array( 'message' => __( 'Please add your name and phone number.', 'dck-directory' ) ) );
		}

		$listing_name = get_the_title( $listing );

		$lead_id = wp_insert_post(
			array(
				'post_type'   => 'dck_lead',
				'post_status' => 'private',
				'post_title'  => sprintf( '%s → %s', $name, $listing_name ),
				'post_parent' => $listing,
			)
		);
		if ( $lead_id && ! is_wp_error( $lead_id ) ) {
			update_post_meta( $lead_id, '_dck_lead_name', $name );
			update_post_meta( $lead_id, '_dck_lead_phone', $phone );
			update_post_meta( $lead_id, '_dck_lead_email', $email );
			update_post_meta( $lead_id, '_dck_lead_message', $message );
			update_post_meta( $lead_id, '_dck_lead_listing', $listing );
		}

		// Notify: contractor's email if premium, otherwise the site admin.
		$to = DCK_Fields::is_premium( $listing ) ? DCK_Fields::get( $listing, 'email' ) : '';
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}
		$subject = sprintf( __( 'New quote request for %s', 'dck-directory' ), $listing_name );
		$body    = sprintf(
			"Name: %s\nPhone: %s\nEmail: %s\n\n%s\n\nListing: %s",
			$name,
			$phone,
			$email,
			$message,
			get_permalink( $listing )
		);
		wp_mail( $to, $subject, $body );

		wp_send_json_success( array( 'message' => __( 'Thanks! Your request has been sent.', 'dck-directory' ) ) );
	}
}
