<?php
/**
 * DCK site assets: page-specific CSS and JS for the directory pages.
 *
 * This code used to be pasted into the content of pages 38, 41, 44 and 118.
 * It now lives in assets/site/ and is loaded here, only on the page it belongs to.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page ids that have their own stylesheet and script in assets/site/.
 */
function dck_site_asset_pages() {
	return array( 38, 41, 44, 118 );
}

/**
 * Current page id, or 0 when this is not one of the directory pages.
 */
function dck_site_current_page() {
	if ( ! is_page() ) {
		return 0;
	}
	$id = (int) get_queried_object_id();
	return in_array( $id, dck_site_asset_pages(), true ) ? $id : 0;
}

/**
 * Enqueue the page stylesheet and script. Priority 999 keeps the stylesheet
 * after the plugin's own styles, matching the cascade the inline blocks had.
 */
function dck_site_enqueue_assets() {
	$id = dck_site_current_page();
	if ( ! $id ) {
		return;
	}

	$dir = plugin_dir_path( __FILE__ ) . 'assets/site/';
	$url = plugin_dir_url( __FILE__ ) . 'assets/site/';

	$css = 'page-' . $id . '.css';
	if ( file_exists( $dir . $css ) ) {
		wp_enqueue_style( 'dck-site-page-' . $id, $url . $css, array(), (string) filemtime( $dir . $css ) );
	}

	// Page 118 re-linked the directory stylesheet after its own styles, so the
	// plugin rules win ties there. Keep that order so the page looks the same.
	if ( 118 === $id ) {
		wp_enqueue_style(
			'dck-directory-after-118',
			plugin_dir_url( __FILE__ ) . 'assets/css/dck-directory.css',
			array( 'dck-site-page-118' ),
			'1.3.0'
		);
	}

	$js = 'page-' . $id . '.js';
	if ( file_exists( $dir . $js ) ) {
		wp_enqueue_script( 'dck-site-page-' . $id, $url . $js, array(), (string) filemtime( $dir . $js ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'dck_site_enqueue_assets', 999 );

/**
 * FAQ structured data for the directory page (38).
 */
function dck_site_faq_schema() {
	if ( 38 !== dck_site_current_page() ) {
		return;
	}
	$file = plugin_dir_path( __FILE__ ) . 'assets/site/faq-schema-38.json';
	if ( ! file_exists( $file ) ) {
		return;
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) {
		return;
	}
	echo '<script type="application/ld+json" id="dckx-faq-schema">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'dck_site_faq_schema', 20 );
