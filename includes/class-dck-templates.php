<?php
/**
 * Front-end template routing for single profiles and archives.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Templates {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'template_include', array( $this, 'route' ) );
		add_filter( 'the_content', array( $this, 'single_content' ) );
	}

	/**
	 * Use our archive template for the CPT archive and taxonomy pages so
	 * results render as directory cards. Single profiles keep the theme
	 * wrapper but swap in our profile via the_content (below).
	 */
	public function route( $template ) {
		if ( is_post_type_archive( DCK_Post_Types::POST_TYPE )
			|| is_tax( DCK_Post_Types::TAX_SERVICE )
			|| is_tax( DCK_Post_Types::TAX_AREA )
			|| is_tax( DCK_Post_Types::TAX_LOCATION ) ) {
			$custom = DCK_DIR_PATH . 'templates/archive-contractor.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	/**
	 * Replace the single-contractor content with the full profile render,
	 * so it inherits the active theme's header/footer automatically.
	 */
	public function single_content( $content ) {
		static $rendering = false;
		// Guard: dck_render_profile() runs the_content on the About text,
		// which would otherwise re-enter this filter forever.
		if ( $rendering ) {
			return $content;
		}
		if ( is_singular( DCK_Post_Types::POST_TYPE ) && in_the_loop() && is_main_query() ) {
			$rendering = true;
			$out       = dck_render_profile( get_the_ID() );
			$rendering = false;
			return $out;
		}
		return $content;
	}
}
