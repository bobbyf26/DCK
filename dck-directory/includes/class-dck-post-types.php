<?php
/**
 * Registers the contractor post type and its taxonomies.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Post_Types {

	const POST_TYPE   = 'dck_contractor';
	const TAX_SERVICE = 'dck_service';
	const TAX_LOCATION = 'dck_location';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the post type + taxonomies.
	 */
	public function register() {

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Contractors', 'dck-directory' ),
					'singular_name'      => __( 'Contractor', 'dck-directory' ),
					'add_new'            => __( 'Add Contractor', 'dck-directory' ),
					'add_new_item'       => __( 'Add New Contractor', 'dck-directory' ),
					'edit_item'          => __( 'Edit Contractor', 'dck-directory' ),
					'new_item'           => __( 'New Contractor', 'dck-directory' ),
					'view_item'          => __( 'View Contractor', 'dck-directory' ),
					'search_items'       => __( 'Search Contractors', 'dck-directory' ),
					'not_found'          => __( 'No contractors found', 'dck-directory' ),
					'menu_name'          => __( 'DCK Directory', 'dck-directory' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-building',
				'menu_position' => 25,
				'rewrite'      => array( 'slug' => 'contractor', 'with_front' => false ),
				'supports'     => array( 'title', 'editor', 'thumbnail', 'author' ),
				'show_in_rest' => true,
			)
		);

		// Service categories (Stamped, Stained, Garage Floors, ...).
		register_taxonomy(
			self::TAX_SERVICE,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Service Categories', 'dck-directory' ),
					'singular_name' => __( 'Service Category', 'dck-directory' ),
					'menu_name'     => __( 'Categories', 'dck-directory' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'concrete-service', 'with_front' => false ),
			)
		);

		// Leads captured from the quote forms (admin-only, private).
		register_post_type(
			'dck_lead',
			array(
				'labels'       => array(
					'name'          => __( 'Leads', 'dck-directory' ),
					'singular_name' => __( 'Lead', 'dck-directory' ),
					'menu_name'     => __( 'Leads', 'dck-directory' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . self::POST_TYPE,
				'capability_type' => 'post',
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
				'supports'     => array( 'title' ),
			)
		);

		// Locations (State -> City), hierarchical for drill-down.
		register_taxonomy(
			self::TAX_LOCATION,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Locations', 'dck-directory' ),
					'singular_name' => __( 'Location', 'dck-directory' ),
					'menu_name'     => __( 'Locations', 'dck-directory' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'location', 'with_front' => false ),
			)
		);
	}

	/**
	 * Seed the 9 service categories from the live site on first activation.
	 */
	public function seed_default_services() {
		$defaults = array(
			'Basement Waterproofing',
			'Concrete Wood',
			'Garage Floors',
			'Metallic Marble',
			'Patios & Pool Decks',
			'Polished Concrete',
			'Protect & Seal',
			'Stained Concrete',
			'Stamped Concrete',
		);
		foreach ( $defaults as $name ) {
			if ( ! term_exists( $name, self::TAX_SERVICE ) ) {
				wp_insert_term( $name, self::TAX_SERVICE );
			}
		}
	}
}
