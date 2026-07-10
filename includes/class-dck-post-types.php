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
	const TAX_SERVICE = 'dck_service';   // Coating systems (the finish/material).
	const TAX_AREA    = 'dck_area';      // Service areas / applications.
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

		// Coating systems (Epoxy, Metallic, Polished, Stamped, ...).
		register_taxonomy(
			self::TAX_SERVICE,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Coating Systems', 'dck-directory' ),
					'singular_name' => __( 'Coating System', 'dck-directory' ),
					'menu_name'     => __( 'Coating Systems', 'dck-directory' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'coating-system', 'with_front' => false ),
			)
		);

		// Service areas / applications (Garage Floors, Patios, Commercial, ...).
		register_taxonomy(
			self::TAX_AREA,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Service Areas', 'dck-directory' ),
					'singular_name' => __( 'Service Area', 'dck-directory' ),
					'menu_name'     => __( 'Service Areas', 'dck-directory' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'application-area', 'with_front' => false ),
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
	 * Seed default terms for both taxonomies on first activation.
	 * Only inserts terms that don't already exist, so it's safe to re-run.
	 */
	public function seed_default_services() {
		$coating_systems = array(
			'Epoxy Coatings',
			'Polyaspartic / Polyurea',
			'Flake / Chip System',
			'Quartz System',
			'Metallic Marble',
			'Polished Concrete',
			'Stained Concrete',
			'Stamped Concrete',
			'Concrete Overlays',
			'Concrete Wood',
			'Protect & Seal',
			'Basement Waterproofing',
		);
		foreach ( $coating_systems as $name ) {
			if ( ! term_exists( $name, self::TAX_SERVICE ) ) {
				wp_insert_term( $name, self::TAX_SERVICE );
			}
		}

		$service_areas = array(
			'Garage Floors',
			'Patios',
			'Pool Decks',
			'Driveways',
			'Walkways & Sidewalks',
			'Basements',
			'Interior Floors',
			'Commercial',
			'Industrial',
			'Warehouses',
			'Retail Spaces',
		);
		foreach ( $service_areas as $name ) {
			if ( ! term_exists( $name, self::TAX_AREA ) ) {
				wp_insert_term( $name, self::TAX_AREA );
			}
		}
	}
}
