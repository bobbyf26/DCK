<?php
/**
 * Adds the /contractors/ state index to the core WordPress sitemap.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Sitemap_Hub extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'dckhub';
		$this->object_type = 'dckhub';
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		return 1 === (int) $page_num ? array( array( 'loc' => DCK_SEO_Locations::hub_url() ) ) : array();
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
