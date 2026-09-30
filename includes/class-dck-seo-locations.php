<?php
/**
 * Location SEO: the /contractors/{state}/{city}/ hierarchy, titles, meta,
 * canonical URLs, robots rules, breadcrumbs, structured data and sitemaps.
 *
 * Rules this class enforces (so the directory reads as a real, browsable
 * directory rather than a set of doorway pages):
 * - A city page is indexed only when it has at least MIN_INDEX listings.
 *   Smaller towns still render (with nearby contractors) but are noindex,follow.
 * - Free profiles with no description, photo or logo are noindex,follow until
 *   they are filled out or upgraded.
 * - Sitemaps only list pages that are indexable.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_SEO_Locations {

	const BASE         = 'contractors';
	const MIN_INDEX    = 3;
	const NEARBY_MILES = 25;
	const CACHE_KEY    = 'dck_seo_city_points';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'register_taxonomy_args', array( $this, 'location_tax_args' ), 10, 2 );
		add_action( 'init', array( $this, 'rewrites' ), 11 );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'request', array( $this, 'resolve_request' ) );
		add_filter( 'term_link', array( $this, 'term_link' ), 10, 3 );
		add_filter( 'pre_handle_404', array( $this, 'hub_not_404' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirects' ), 1 );
		add_filter( 'redirect_canonical', array( $this, 'no_core_canonical' ) );
		add_action( 'pre_get_posts', array( $this, 'archive_query' ) );
		add_filter( 'template_include', array( $this, 'templates' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
		add_action( 'wp_head', array( $this, 'head_tags' ), 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'dck_profile_html', array( $this, 'profile_breadcrumbs' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'sitemap_taxonomies' ) );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( $this, 'sitemap_tax_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemap_post_args' ), 10, 2 );
		add_action( 'init', array( $this, 'sitemap_provider' ), 12 );
		add_action( 'save_post_' . DCK_Post_Types::POST_TYPE, array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'trashed_post', array( __CLASS__, 'flush_cache' ) );
	}

	/* ------------------------------------------------------------------ */
	/* URLs                                                                */
	/* ------------------------------------------------------------------ */

	public function location_tax_args( $args, $taxonomy ) {
		if ( DCK_Post_Types::TAX_LOCATION === $taxonomy ) {
			$args['rewrite'] = array( 'slug' => self::BASE, 'with_front' => false, 'hierarchical' => true );
		}
		return $args;
	}

	public function rewrites() {
		$b = self::BASE;
		add_rewrite_rule( '^' . $b . '/?$', 'index.php?dck_hub=1', 'top' );
		add_rewrite_rule( '^' . $b . '/([^/]+)/([^/]+)/page/([0-9]+)/?$', 'index.php?dck_state_slug=$matches[1]&dck_city_slug=$matches[2]&paged=$matches[3]', 'top' );
		add_rewrite_rule( '^' . $b . '/([^/]+)/page/([0-9]+)/?$', 'index.php?dck_state_slug=$matches[1]&paged=$matches[2]', 'top' );
		add_rewrite_rule( '^' . $b . '/([^/]+)/([^/]+)/?$', 'index.php?dck_state_slug=$matches[1]&dck_city_slug=$matches[2]', 'top' );
		add_rewrite_rule( '^' . $b . '/([^/]+)/?$', 'index.php?dck_state_slug=$matches[1]', 'top' );
		// Old flat location URLs (/location/dallas/) redirect to the new ones.
		add_rewrite_rule( '^location/([^/]+)/?$', 'index.php?dck_old_location=$matches[1]', 'top' );
	}

	public function maybe_flush() {
		if ( get_option( 'dck_seo_rewrite_ver' ) !== DCK_DIR_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'dck_seo_rewrite_ver', DCK_DIR_VERSION, false );
		}
	}

	public function query_vars( $vars ) {
		return array_merge( $vars, array( 'dck_hub', 'dck_state_slug', 'dck_city_slug', 'dck_old_location' ) );
	}

	/**
	 * Map /contractors/{state}/{city}/ to the real city term. City slugs that
	 * WordPress had to make unique (portland-oregon) are matched by their
	 * short form (portland) under the right state.
	 */
	public function resolve_request( $qv ) {
		if ( empty( $qv['dck_state_slug'] ) ) {
			return $qv;
		}
		$state = self::state_by_slug( $qv['dck_state_slug'] );
		$slug  = '__dck_not_found__';
		if ( $state ) {
			$slug = $state->slug;
			if ( ! empty( $qv['dck_city_slug'] ) ) {
				$city = self::city_by_short_slug( $state, $qv['dck_city_slug'] );
				$slug = $city ? $city->slug : '__dck_not_found__';
			}
		}
		unset( $qv['dck_state_slug'], $qv['dck_city_slug'] );
		$qv[ DCK_Post_Types::TAX_LOCATION ] = $slug;
		return $qv;
	}

	public static function state_by_slug( $slug ) {
		$t = get_term_by( 'slug', sanitize_title( $slug ), DCK_Post_Types::TAX_LOCATION );
		return ( $t && 0 === (int) $t->parent ) ? $t : null;
	}

	public static function city_by_short_slug( $state, $short ) {
		$short = sanitize_title( $short );
		foreach ( array( $short, $short . '-' . $state->slug ) as $try ) {
			$t = get_term_by( 'slug', $try, DCK_Post_Types::TAX_LOCATION );
			if ( $t && (int) $t->parent === (int) $state->term_id ) {
				return $t;
			}
		}
		return null;
	}

	public static function short_slug( $term, $state ) {
		$suffix = '-' . $state->slug;
		if ( strlen( $term->slug ) > strlen( $suffix ) && substr( $term->slug, -strlen( $suffix ) ) === $suffix ) {
			return substr( $term->slug, 0, -strlen( $suffix ) );
		}
		return $term->slug;
	}

	public static function hub_url() {
		return home_url( '/' . self::BASE . '/' );
	}

	public function term_link( $url, $term, $taxonomy ) {
		if ( DCK_Post_Types::TAX_LOCATION !== $taxonomy ) {
			return $url;
		}
		if ( 0 === (int) $term->parent ) {
			return home_url( '/' . self::BASE . '/' . $term->slug . '/' );
		}
		$state = get_term( (int) $term->parent, DCK_Post_Types::TAX_LOCATION );
		if ( ! $state || is_wp_error( $state ) ) {
			return $url;
		}
		return home_url( '/' . self::BASE . '/' . $state->slug . '/' . self::short_slug( $term, $state ) . '/' );
	}

	public function hub_not_404( $preempt, $wp_query ) {
		if ( get_query_var( 'dck_hub' ) ) {
			status_header( 200 );
			return true;
		}
		return $preempt;
	}

	/** Our pages handle their own canonical redirects. */
	public function no_core_canonical( $url ) {
		if ( get_query_var( 'dck_hub' ) || is_tax( DCK_Post_Types::TAX_LOCATION ) ) {
			return false;
		}
		return $url;
	}

	/**
	 * 301s: old /location/ URLs, the bare CPT archive, and any location URL
	 * that is not the canonical /contractors/{state}/{city}/ form.
	 */
	public function redirects() {
		$old = get_query_var( 'dck_old_location' );
		if ( $old ) {
			$t = get_term_by( 'slug', sanitize_title( $old ), DCK_Post_Types::TAX_LOCATION );
			if ( $t ) {
				wp_safe_redirect( get_term_link( $t ), 301 );
				exit;
			}
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		if ( is_post_type_archive( DCK_Post_Types::POST_TYPE ) ) {
			wp_safe_redirect( self::hub_url(), 301 );
			exit;
		}
		if ( is_tax( DCK_Post_Types::TAX_LOCATION ) && get_queried_object() instanceof WP_Term ) {
			$term      = get_queried_object();
			$canonical = get_term_link( $term );
			$paged     = max( 1, (int) get_query_var( 'paged' ) );
			if ( $paged > 1 ) {
				$canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
			}
			$req = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore
			$can = wp_parse_url( $canonical, PHP_URL_PATH );
			if ( $req && $can && untrailingslashit( $req ) !== untrailingslashit( $can ) ) {
				wp_safe_redirect( $canonical, 301 );
				exit;
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Queries + templates                                                 */
	/* ------------------------------------------------------------------ */

	public function archive_query( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_tax( DCK_Post_Types::TAX_LOCATION ) ) {
			return;
		}
		$term = $q->get_queried_object();
		$city = $term instanceof WP_Term && (int) $term->parent > 0;
		$q->set( 'posts_per_page', $city ? 100 : 24 );
		$q->set( 'orderby', 'title' );
		$q->set( 'order', 'ASC' );
		if ( $city ) {
			// City pages list that city only, not every descendant.
			$q->set( 'tax_query', array( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'terms' => (int) $term->term_id, 'include_children' => false ) ) );
		}
	}

	public function templates( $template ) {
		if ( get_query_var( 'dck_hub' ) ) {
			return DCK_DIR_PATH . 'templates/locations-hub.php';
		}
		if ( is_tax( DCK_Post_Types::TAX_LOCATION ) && ! is_404() && self::context() ) {
			return DCK_DIR_PATH . 'templates/archive-location.php';
		}
		return $template;
	}

	/* ------------------------------------------------------------------ */
	/* Data helpers                                                        */
	/* ------------------------------------------------------------------ */

	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Every city term with listings: [term_id => [lat, lng, count, parent]].
	 * Lat/lng is the average of its published listings' coordinates.
	 */
	public static function city_points() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id, tt.parent, AVG(CAST(la.meta_value AS DECIMAL(10,6))) AS lat, AVG(CAST(lo.meta_value AS DECIMAL(10,6))) AS lng, COUNT(DISTINCT p.ID) AS n
				FROM {$wpdb->term_taxonomy} tt
				JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
				JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = 'publish' AND p.post_type = %s
				LEFT JOIN {$wpdb->postmeta} la ON la.post_id = p.ID AND la.meta_key = '_dck_lat' AND la.meta_value <> ''
				LEFT JOIN {$wpdb->postmeta} lo ON lo.post_id = p.ID AND lo.meta_key = '_dck_lng' AND lo.meta_value <> ''
				WHERE tt.taxonomy = %s AND tt.parent > 0
				GROUP BY tt.term_id, tt.parent",
				DCK_Post_Types::POST_TYPE,
				DCK_Post_Types::TAX_LOCATION
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r->term_id ] = array( (float) $r->lat, (float) $r->lng, (int) $r->n, (int) $r->parent );
		}
		set_transient( self::CACHE_KEY, $out, 12 * HOUR_IN_SECONDS );
		return $out;
	}

	public static function city_count( $term ) {
		$pts = self::city_points();
		return isset( $pts[ $term->term_id ] ) ? $pts[ $term->term_id ][2] : (int) $term->count;
	}

	public static function is_indexable_city( $term ) {
		return self::city_count( $term ) >= self::MIN_INDEX;
	}

	/**
	 * Nearest other cities to a city, as [term_id => miles], closest first.
	 */
	public static function nearby_cities( $term, $limit = 8, $max_miles = 60 ) {
		$pts = self::city_points();
		if ( empty( $pts[ $term->term_id ] ) || ! $pts[ $term->term_id ][0] ) {
			return array();
		}
		list( $lat, $lng ) = $pts[ $term->term_id ];
		$d = array();
		foreach ( $pts as $id => $p ) {
			if ( $id === $term->term_id || ! $p[0] ) {
				continue;
			}
			$mi = dck_distance_mi( $lat, $lng, $p[0], $p[1] );
			if ( $mi <= $max_miles ) {
				$d[ $id ] = $mi;
			}
		}
		asort( $d );
		return array_slice( $d, 0, $limit, true );
	}

	/**
	 * Listings in other cities within $miles of a city, closest first.
	 */
	public static function nearby_listings( $term, $miles = self::NEARBY_MILES, $limit = 12 ) {
		$ids = array_keys( self::nearby_cities( $term, 40, $miles ) );
		if ( ! $ids ) {
			return array();
		}
		$pts   = self::city_points();
		$order = array_flip( $ids );
		$posts = get_posts(
			array(
				'post_type'      => DCK_Post_Types::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'terms' => $ids, 'include_children' => false ) ),
			)
		);
		$ranked = array();
		foreach ( $posts as $pid ) {
			$terms = wp_get_post_terms( $pid, DCK_Post_Types::TAX_LOCATION, array( 'fields' => 'ids' ) );
			$best  = 999;
			foreach ( (array) $terms as $tid ) {
				if ( isset( $order[ $tid ] ) ) {
					$best = min( $best, $order[ $tid ] );
				}
			}
			$ranked[ $pid ] = $best;
		}
		asort( $ranked );
		return array_slice( array_keys( $ranked ), 0, $limit );
	}

	public static function state_abbr( $name ) {
		static $map = array( 'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR', 'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE', 'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID', 'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS', 'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD', 'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS', 'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV', 'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM', 'New York' => 'NY', 'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK', 'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI', 'South Carolina' => 'SC', 'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT', 'Vermont' => 'VT', 'Virginia' => 'VA', 'Washington' => 'WA', 'West Virginia' => 'WV', 'Wisconsin' => 'WI', 'Wyoming' => 'WY' );
		return isset( $map[ $name ] ) ? $map[ $name ] : $name;
	}

	/** Context for the current location page: [term, state, is_city, count]. */
	public static function context( $term = null ) {
		$term = $term ? $term : get_queried_object();
		if ( ! $term instanceof WP_Term || DCK_Post_Types::TAX_LOCATION !== $term->taxonomy ) {
			return null;
		}
		$is_city = (int) $term->parent > 0;
		$state   = $is_city ? get_term( (int) $term->parent, DCK_Post_Types::TAX_LOCATION ) : $term;
		return array(
			'term'    => $term,
			'state'   => $state,
			'is_city' => $is_city,
			'count'   => $is_city ? self::city_count( $term ) : (int) $term->count,
			'place'   => $is_city ? $term->name . ', ' . self::state_abbr( $state->name ) : $state->name,
		);
	}

	/** Breadcrumb trail as [[name, url], ...]. */
	public static function trail( $term = null, $post_id = 0 ) {
		$trail = array( array( __( 'Home', 'dck-directory' ), home_url( '/' ) ), array( __( 'Contractors', 'dck-directory' ), self::hub_url() ) );
		if ( $post_id ) {
			$term = self::primary_city( $post_id );
		}
		if ( $term ) {
			$ctx = self::context( $term );
			if ( $ctx ) {
				$trail[] = array( $ctx['state']->name, get_term_link( $ctx['state'] ) );
				if ( $ctx['is_city'] ) {
					$trail[] = array( $term->name, get_term_link( $term ) );
				}
			}
		}
		if ( $post_id ) {
			$trail[] = array( get_the_title( $post_id ), get_permalink( $post_id ) );
		}
		return $trail;
	}

	public static function primary_city( $post_id ) {
		$terms = wp_get_post_terms( $post_id, DCK_Post_Types::TAX_LOCATION );
		$state = null;
		foreach ( (array) $terms as $t ) {
			if ( (int) $t->parent > 0 ) {
				return $t;
			}
			$state = $t;
		}
		return $state;
	}

	public static function breadcrumbs_html( $trail ) {
		$out  = '<nav class="dck-crumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'dck-directory' ) . '"><ol>';
		$last = count( $trail ) - 1;
		foreach ( $trail as $i => $c ) {
			$out .= $i === $last
				? '<li aria-current="page">' . esc_html( $c[0] ) . '</li>'
				: '<li><a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a></li>';
		}
		return $out . '</ol></nav>';
	}

	/* ------------------------------------------------------------------ */
	/* Head: title, description, canonical, schema, robots                 */
	/* ------------------------------------------------------------------ */

	public function title_parts( $parts ) {
		if ( get_query_var( 'dck_hub' ) ) {
			$parts['title'] = __( 'Decorative Concrete & Epoxy Floor Contractors by State', 'dck-directory' );
			return $parts;
		}
		$ctx = self::context();
		if ( ! $ctx ) {
			return $parts;
		}
		$parts['title'] = $ctx['is_city']
			? sprintf( __( '%1$d Decorative Concrete Contractors in %2$s', 'dck-directory' ), $ctx['count'], $ctx['place'] )
			: sprintf( __( 'Decorative Concrete & Epoxy Floor Contractors in %s', 'dck-directory' ), $ctx['place'] );
		if ( $ctx['is_city'] && $ctx['count'] < 1 ) {
			$parts['title'] = sprintf( __( 'Decorative Concrete Contractors near %s', 'dck-directory' ), $ctx['place'] );
		}
		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 ) {
			$parts['page'] = sprintf( __( 'Page %d', 'dck-directory' ), $paged );
		}
		return $parts;
	}

	public static function description() {
		if ( get_query_var( 'dck_hub' ) ) {
			$total = wp_count_posts( DCK_Post_Types::POST_TYPE )->publish;
			return sprintf( __( 'Browse %s decorative concrete, epoxy floor and concrete coating contractors in all 50 states. Pick your state and city to compare local pros and request free quotes.', 'dck-directory' ), number_format_i18n( $total ) );
		}
		$ctx = self::context();
		if ( ! $ctx ) {
			return '';
		}
		if ( $ctx['is_city'] ) {
			$names = get_posts( array( 'post_type' => DCK_Post_Types::POST_TYPE, 'posts_per_page' => 2, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true, 'tax_query' => array( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'terms' => $ctx['term']->term_id, 'include_children' => false ) ) ) );
			$names = array_map( 'get_the_title', $names );
			$incl  = $names ? ' ' . sprintf( __( 'including %s', 'dck-directory' ), implode( __( ' and ', 'dck-directory' ), $names ) ) : '';
			return sprintf( _n( 'Find %1$d decorative concrete and epoxy floor contractor in %2$s%3$s. Compare local concrete coating pros and request a free quote.', 'Find %1$d decorative concrete and epoxy floor contractors in %2$s%3$s. Compare local concrete coating pros and request free quotes.', $ctx['count'], 'dck-directory' ), $ctx['count'], $ctx['place'], $incl );
		}
		$cities = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'parent' => $ctx['term']->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 3, 'fields' => 'names' ) );
		$list   = is_array( $cities ) && $cities ? ' ' . sprintf( __( 'in cities like %s', 'dck-directory' ), implode( ', ', $cities ) ) : '';
		return sprintf( __( 'Browse %1$d decorative concrete, epoxy floor and concrete coating contractors across %2$s%3$s. Compare local pros and request free quotes.', 'dck-directory' ), $ctx['count'], $ctx['place'], $list );
	}

	public function head_tags() {
		$hub = (bool) get_query_var( 'dck_hub' );
		$ctx = $hub ? null : self::context();
		if ( ! $hub && ! $ctx ) {
			return;
		}
		$desc = self::description();
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $hub ) {
			$canonical = self::hub_url();
		} else {
			$canonical = get_term_link( $ctx['term'] );
			$paged     = (int) get_query_var( 'paged' );
			if ( $paged > 1 ) {
				$canonical = trailingslashit( $canonical ) . 'page/' . $paged . '/';
			}
		}
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";

		$graph   = array();
		$trail   = $hub ? array( array( __( 'Home', 'dck-directory' ), home_url( '/' ) ), array( __( 'Contractors', 'dck-directory' ), self::hub_url() ) ) : self::trail( $ctx['term'] );
		$graph[] = self::breadcrumb_schema( $trail );
		if ( $ctx && $ctx['is_city'] ) {
			global $wp_query;
			$items = array();
			foreach ( (array) $wp_query->posts as $i => $p ) {
				$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'url' => get_permalink( $p ), 'name' => get_the_title( $p ) );
			}
			if ( $items ) {
				$graph[] = array( '@type' => 'ItemList', 'name' => sprintf( __( 'Decorative concrete contractors in %s', 'dck-directory' ), $ctx['place'] ), 'numberOfItems' => count( $items ), 'itemListElement' => $items );
			}
		}
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}

	public static function breadcrumb_schema( $trail ) {
		$items = array();
		foreach ( $trail as $i => $c ) {
			$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] );
		}
		return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
	}

	/** Free profile with nothing but name/city/phone. */
	public static function is_thin_profile( $post_id ) {
		if ( DCK_Fields::is_premium( $post_id ) ) {
			return false;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return false;
		}
		return '' === trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) );
	}

	public function robots( $robots ) {
		$noindex = false;
		if ( is_tax( DCK_Post_Types::TAX_LOCATION ) ) {
			$ctx     = self::context();
			$noindex = $ctx && $ctx['is_city'] && ! self::is_indexable_city( $ctx['term'] );
		} elseif ( is_tax( DCK_Post_Types::TAX_SERVICE ) || is_tax( DCK_Post_Types::TAX_AREA ) ) {
			$t       = get_queried_object();
			$noindex = $t && (int) $t->count < self::MIN_INDEX;
		} elseif ( is_singular( DCK_Post_Types::POST_TYPE ) ) {
			$noindex = self::is_thin_profile( get_queried_object_id() );
		}
		if ( $noindex ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
			if ( empty( $robots['nofollow'] ) ) {
				$robots['follow'] = true;
			}
		}
		return $robots;
	}

	public function profile_breadcrumbs( $html, $post_id ) {
		return '<div class="dck-directory-page dck-crumbs-wrap"><div class="dck-wrap">' . self::breadcrumbs_html( self::trail( null, $post_id ) ) . '</div></div>' . $html;
	}

	/* ------------------------------------------------------------------ */
	/* Sitemaps                                                            */
	/* ------------------------------------------------------------------ */

	public function sitemap_taxonomies( $taxonomies ) {
		// Coating-system and service-area archives are thin; keep them out.
		unset( $taxonomies[ DCK_Post_Types::TAX_SERVICE ], $taxonomies[ DCK_Post_Types::TAX_AREA ] );
		return $taxonomies;
	}

	public function sitemap_tax_args( $args, $taxonomy ) {
		if ( DCK_Post_Types::TAX_LOCATION !== $taxonomy ) {
			return $args;
		}
		$pts     = self::city_points();
		$exclude = array();
		$parents = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'id=>parent' ) );
		foreach ( (array) $parents as $id => $parent ) {
			if ( (int) $parent > 0 && ( ! isset( $pts[ $id ] ) || $pts[ $id ][2] < self::MIN_INDEX ) ) {
				$exclude[] = (int) $id;
			}
		}
		$args['exclude']    = $exclude;
		$args['hide_empty'] = true;
		return $args;
	}

	public function sitemap_post_args( $args, $post_type ) {
		if ( DCK_Post_Types::POST_TYPE !== $post_type ) {
			return $args;
		}
		$ids = get_transient( 'dck_seo_indexable_profiles' );
		if ( ! is_array( $ids ) ) {
			global $wpdb;
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_dck_tier'
					LEFT JOIN {$wpdb->postmeta} th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
					WHERE p.post_type = %s AND p.post_status = 'publish'
					AND ( t.meta_value = 'premium' OR th.meta_value > 0 OR TRIM(p.post_content) <> '' )",
					DCK_Post_Types::POST_TYPE
				)
			);
			$ids = array_map( 'intval', (array) $ids );
			set_transient( 'dck_seo_indexable_profiles', $ids, 12 * HOUR_IN_SECONDS );
		}
		$args['post__in'] = $ids ? $ids : array( 0 );
		return $args;
	}

	public function sitemap_provider() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		require_once DCK_DIR_PATH . 'includes/class-dck-sitemap-hub.php';
		wp_register_sitemap_provider( 'dckhub', new DCK_Sitemap_Hub() );
	}
}

// Keep the indexable-profile cache fresh too.
add_action(
	'save_post_' . DCK_Post_Types::POST_TYPE,
	static function () {
		delete_transient( 'dck_seo_indexable_profiles' );
	}
);
