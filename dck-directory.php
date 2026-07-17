<?php
/**
 * Plugin Name:       DCK Directory
 * Plugin URI:        https://github.com/bobbyf26/DCK
 * Description:        Decorative concrete contractor directory — searchable landing page, contractor profiles, free front-end signup, and paid premium listings.
 * Version:           1.5.0
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

define( 'DCK_DIR_VERSION', '1.5.0' );
define( 'DCK_DIR_FILE', __FILE__ );
define( 'DCK_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'DCK_DIR_URL', plugin_dir_url( __FILE__ ) );

require_once DCK_DIR_PATH . 'includes/template-functions.php';
require_once DCK_DIR_PATH . 'includes/class-dck-post-types.php';
require_once DCK_DIR_PATH . 'includes/class-dck-fields.php';
require_once DCK_DIR_PATH . 'includes/class-dck-settings.php';
require_once DCK_DIR_PATH . 'includes/class-dck-admin.php';
require_once DCK_DIR_PATH . 'includes/class-dck-ajax.php';
require_once DCK_DIR_PATH . 'includes/class-dck-shortcodes.php';
require_once DCK_DIR_PATH . 'includes/class-dck-templates.php';
require_once DCK_DIR_PATH . 'includes/class-dck-demo.php';

/**
 * Boot the plugin once WordPress has loaded.
 */
function dck_directory_init() {
	DCK_Post_Types::instance();
	DCK_Fields::instance();
	DCK_Settings::instance();
	DCK_Admin::instance();
	DCK_Ajax::instance();
	DCK_Shortcodes::instance();
	DCK_Templates::instance();
	DCK_Demo::instance();
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

	// Apply the admin-chosen brand color by overriding the CSS tokens.
	$brand = DCK_Settings::get( 'brand_color' );
	if ( $brand && '#1E62B4' !== strtoupper( $brand ) ) {
		$deep = dck_adjust_hex( $brand, -30 );
		$soft = dck_adjust_hex( $brand, 88 );
		$css  = sprintf(
			'.dck-profile,.dck-directory-page,.dck-form-page{--dck-brand:%1$s;--dck-brand-deep:%2$s;--dck-brand-soft:%3$s;}',
			esc_html( $brand ),
			esc_html( $deep ),
			esc_html( $soft )
		);
		wp_add_inline_style( 'dck-directory', $css );
	}

	// Admin/Cowork custom CSS (from DCK Directory → Settings → Custom CSS).
	// Stored in the DB, so it survives plugin updates and is REST-editable.
	// Printed last so it can override anything above, on every directory page.
	$custom_css = DCK_Settings::get( 'custom_css' );
	if ( is_string( $custom_css ) && '' !== trim( $custom_css ) ) {
		wp_add_inline_style( 'dck-directory', $custom_css );
	}
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
