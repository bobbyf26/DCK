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
		if ( $location ) {
			$tax_query[] = array(
				'taxonomy'         => DCK_Post_Types::TAX_LOCATION,
				'field'            => 'slug',
				'terms'            => $location,
				'include_children' => true,
			);
		}

		$args = array(
			'post_type'      => DCK_Post_Types::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'paged'          => $paged,
			's'              => $keyword,
			// Default ranking: featured first, then premium tier, then average
			// review rating, then newest. Named meta_query clauses drive the
			// ordering. Every published listing is guaranteed to have these three
			// meta rows (DCK_Fields::ensure_defaults on save + a one-time
			// backfill), so requiring EXISTS here never drops a listing.
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation'        => 'AND',
				'featured_clause' => array( 'key' => '_dck_featured', 'compare' => 'EXISTS' ),
				'tier_clause'     => array( 'key' => '_dck_tier', 'compare' => 'EXISTS' ),
				// DECIMAL(10,2), not bare DECIMAL — WP casts bare DECIMAL as
				// DECIMAL(10,0), truncating 4.67/4.75 to 4 and killing the tiebreak.
				'rating_clause'   => array( 'key' => '_dck_rating_avg', 'type' => 'DECIMAL(10,2)', 'compare' => 'EXISTS' ),
			),
			'orderby'        => array(
				'featured_clause' => 'DESC',
				'tier_clause'     => 'DESC',
				'rating_clause'   => 'DESC',
				'date'            => 'DESC',
			),
		);
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$q       = new WP_Query( $args );
		$html    = '';
		$markers = array();
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$id    = get_the_ID();
				$html .= dck_render_card( $id );

				$lat = get_post_meta( $id, '_dck_lat', true );
				$lng = get_post_meta( $id, '_dck_lng', true );
				if ( '' !== (string) $lat && '' !== (string) $lng ) {
					$markers[] = array(
						'id'     => $id,
						'lat'    => (float) $lat,
						'lng'    => (float) $lng,
						'name'   => get_the_title( $id ),
						'url'    => get_permalink( $id ),
						'rating' => (float) get_post_meta( $id, '_dck_rating_avg', true ),
						'city'   => trim( get_post_meta( $id, '_dck_city', true ) . ', ' . get_post_meta( $id, '_dck_state', true ), ', ' ),
						'premium' => DCK_Fields::is_premium( $id ) ? 1 : 0,
					);
				}
			}
			wp_reset_postdata();
		}

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
