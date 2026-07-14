<?php
/**
 * Plugin Name:       DCK Directory
 * Plugin URI:        https://github.com/bobbyf26/DCK
 * Description:        Decorative concrete contractor directory — searchable landing page, contractor profiles, free front-end signup, and paid premium listings.
 * Version:           1.1.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Decorative Concrete Kingdom
 * License:           GPL-2.0-or-later
 * Text Domain:       dck-directory
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'DCK_DIR_VERSION', '1.1.2' );
define( 'DCK_DIR_FILE', __FILE__ );
define( 'DCK_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCK_DIR_URL', plugin_dir_url( __FILE__ ) );

require_once DCK_DIR_PATH . 'includes/template-functions.php';
require_once DCK_DIR_PATH . 'includes/class-dck-post-types.php';
require_once DCK_DIR_PATH . 'includes/class-dck-fields.php';
require_once DCK_DIR_PATH . 'includes/class-dck-admin.php';
require_once DCK_DIR_PATH . 'includes/class-dck-ajax.php';
require_once DCK_DIR_PATH . 'includes/class-dck-shortcodes.php';
require_once DCK_DIR_PATH . 'includes/class-dck-templates.php';

/**
 * Boot the plugin once WordPress has loaded.
 */
function dck_directory_init() {
	DCK_Post_Types::instance();
	DCK_Fields::instance();
	DCK_Admin::instance();
	DCK_Ajax::instance();
	DCK_Shortcodes::instance();
	DCK_Templates::instance();
}
add_action( 'plugins_loaded', 'dck_directory_init' );

/**
 * Front-end assets. Kept light — one stylesheet, one script.
 */
function dck_directory_assets() {
	wp_register_style( 'dck-directory', DCK_DIR_URL . 'assets/css/dck-directory.css', array(), DCK_DIR_VERSION );
	wp_register_script( 'dck-directory', DCK_DIR_URL . 'assets/js/dck-directory.js', array(), DCK_DIR_VERSION, true );
	wp_localize_script(
		'dck-directory',
		'DCK_DIR',
		array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'dck_dir_nonce' ),
		)
	);
	// Enqueued globally so shortcodes and single profiles are always styled.
	wp_enqueue_style( 'dck-directory' );
	wp_enqueue_script( 'dck-directory' );
}
add_action( 'wp_enqueue_scripts', 'dck_directory_assets' );

/**
 * Activation: register content types then flush rewrite rules so
 * /contractor/... and taxonomy archives resolve immediately.
 */
function dck_directory_activate() {
	require_once DCK_DIR_PATH . 'includes/template-functions.php';
	require_once DCK_DIR_PATH . 'includes/class-dck-post-types.php';
	DCK_Post_Types::instance()->register();
	DCK_Post_Types::instance()->seed_default_services();

	// A lightweight role for contractors who sign up on the front end.
	// Needs upload_files so the dashboard photo uploads work.
	if ( ! get_role( 'dck_contractor' ) ) {
		add_role(
			'dck_contractor',
			__( 'Contractor', 'dck-directory' ),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dck_directory_activate' );

/**
 * Deactivation: clean up rewrite rules.
 */
function dck_directory_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dck_directory_deactivate' );
