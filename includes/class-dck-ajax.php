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
		check_ajax_referer( 'dck_dir_nonce', 'nonce' );

		$service  = isset( $_POST['service'] ) ? sanitize_title( wp_unslash( $_POST['service'] ) ) : '';
		$location = isset( $_POST['location'] ) ? sanitize_title( wp_unslash( $_POST['location'] ) ) : '';
		$keyword  = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

		$tax_query = array( 'relation' => 'AND' );
		if ( $service ) {
			$tax_query[] = array(
				'taxonomy' => DCK_Post_Types::TAX_SERVICE,
				'field'    => 'slug',
				'terms'    => $service,
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
			// Featured listings float to the top, then newest. The OR meta
			// query keeps listings that have no _dck_featured meta from being
			// dropped by the meta ordering join.
			'meta_key'       => '_dck_featured', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => array( 'meta_value' => 'DESC', 'date' => 'DESC' ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => '_dck_featured', 'compare' => 'EXISTS' ),
				array( 'key' => '_dck_featured', 'compare' => 'NOT EXISTS' ),
			),
		);
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}

		$q    = new WP_Query( $args );
		$html = '';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$html .= dck_render_card( get_the_ID() );
			}
			wp_reset_postdata();
		}

		wp_send_json_success(
			array(
				'html'  => $html,
				'found' => (int) $q->found_posts,
				'pages' => (int) $q->max_num_pages,
				'paged' => $paged,
			)
		);
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
