<?php
/**
 * Demo / proof-of-concept seeder. Creates 5 fully-populated premium
 * contractor listings so the directory can be previewed with realistic data,
 * plus a matching teardown. All seeded content is marked with _dck_demo = '1'
 * and any city terms it creates are tracked so teardown removes them cleanly.
 *
 * Trigger from DCK Directory → Demo Data (admin), or WP-CLI:
 *   wp dck seed-demo
 *   wp dck remove-demo
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Demo {

	const DEMO_META  = '_dck_demo';
	const TERMS_OPT  = 'dck_demo_terms';
	/** Reusable decorative-concrete photos already in the media library. */
	const GALLERY_POOL = array( 152, 154, 157, 159, 161, 163 );

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_dck_seed_demo', array( $this, 'handle_seed' ) );
		add_action( 'admin_post_dck_remove_demo', array( $this, 'handle_remove' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'dck seed-demo', array( $this, 'cli_seed' ) );
			WP_CLI::add_command( 'dck remove-demo', array( $this, 'cli_remove' ) );
		}
	}

	/* ================================================================
	 * Admin UI
	 * ================================================================ */

	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . DCK_Post_Types::POST_TYPE,
			__( 'Demo Data', 'dck-directory' ),
			__( 'Demo Data', 'dck-directory' ),
			'manage_options',
			'dck-demo',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		$count = count( $this->existing_demo_ids() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DCK Directory — Demo Data', 'dck-directory' ); ?></h1>
			<?php if ( isset( $_GET['dck_seeded'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'Seeded %d demo contractor listings.', 'dck-directory' ), (int) $_GET['dck_seeded'] ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['dck_removed'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'Removed %d demo contractor listings.', 'dck-directory' ), (int) $_GET['dck_removed'] ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Seed 5 fully-filled premium contractor listings (one featured) across TX, MI, FL, CO, and NC to preview the directory. This is temporary demo content — remove it anytime.', 'dck-directory' ); ?></p>
			<p><strong><?php printf( esc_html__( 'Demo listings currently present: %d', 'dck-directory' ), (int) $count ); ?></strong></p>
			<p style="display:flex;gap:12px;align-items:center;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'dck_seed_demo' ); ?>
					<input type="hidden" name="action" value="dck_seed_demo">
					<?php submit_button( __( 'Seed 5 demo listings', 'dck-directory' ), 'primary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete all demo listings and the demo city terms?', 'dck-directory' ) ); ?>');">
					<?php wp_nonce_field( 'dck_remove_demo' ); ?>
					<input type="hidden" name="action" value="dck_remove_demo">
					<?php submit_button( __( 'Remove demo listings', 'dck-directory' ), 'delete', 'submit', false ); ?>
				</form>
			</p>
			<p class="description"><?php esc_html_e( 'Re-seeding first clears any existing demo listings, so it is safe to run repeatedly. Featured image + gallery reuse existing media (IDs 152–163); if those attachments are absent, listings are still created without photos.', 'dck-directory' ); ?></p>
		</div>
		<?php
	}

	public function handle_seed() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'dck_seed_demo' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'dck-directory' ) );
		}
		$n = $this->seed();
		wp_safe_redirect( add_query_arg( array( 'post_type' => DCK_Post_Types::POST_TYPE, 'page' => 'dck-demo', 'dck_seeded' => $n ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function handle_remove() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'dck_remove_demo' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'dck-directory' ) );
		}
		$n = $this->teardown();
		wp_safe_redirect( add_query_arg( array( 'post_type' => DCK_Post_Types::POST_TYPE, 'page' => 'dck-demo', 'dck_removed' => $n ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function cli_seed() {
		$n = $this->seed();
		WP_CLI::success( "Seeded {$n} demo contractor listings." );
	}

	public function cli_remove() {
		$n = $this->teardown();
		WP_CLI::success( "Removed {$n} demo contractor listings." );
	}

	/* ================================================================
	 * Seed / teardown
	 * ================================================================ */

	private function existing_demo_ids() {
		return get_posts(
			array(
				'post_type'      => DCK_Post_Types::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	/**
	 * Remove all demo listings and any city terms the seeder created.
	 *
	 * @return int Number of listings removed.
	 */
	public function teardown() {
		$ids = $this->existing_demo_ids();
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		$terms = (array) get_option( self::TERMS_OPT, array() );
		foreach ( $terms as $term_id ) {
			if ( get_term( (int) $term_id, DCK_Post_Types::TAX_LOCATION ) ) {
				wp_delete_term( (int) $term_id, DCK_Post_Types::TAX_LOCATION );
			}
		}
		delete_option( self::TERMS_OPT );
		return count( $ids );
	}

	/**
	 * Create the 5 demo listings. Clears any prior demo content first, so it is
	 * safe to run repeatedly.
	 *
	 * @return int Number of listings created.
	 */
	public function seed() {
		$this->teardown();
		$created_terms = array();
		$pool          = array_values( array_filter( self::GALLERY_POOL, static function ( $id ) {
			return (bool) wp_get_attachment_image_url( $id, 'thumbnail' );
		} ) );

		$i = 0;
		foreach ( self::datasets() as $d ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => DCK_Post_Types::POST_TYPE,
					'post_status'  => 'publish',
					'post_author'  => 1,
					'post_title'   => $d['name'],
					'post_content' => $d['about'],
				)
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, self::DEMO_META, '1' );
			update_post_meta( $post_id, '_dck_tier', 'premium' );
			update_post_meta( $post_id, '_dck_featured', ! empty( $d['featured'] ) ? '1' : '' );

			// Scalar fields.
			foreach ( $d['meta'] as $key => $val ) {
				update_post_meta( $post_id, '_dck_' . $key, $val );
			}
			// JSON fields.
			update_post_meta( $post_id, '_dck_hours', wp_json_encode( $d['hours'] ) );
			update_post_meta( $post_id, '_dck_faq', wp_json_encode( $d['faq'] ) );
			update_post_meta( $post_id, '_dck_reviews', wp_json_encode( $d['reviews'] ) );

			// Taxonomies by slug.
			$this->assign_terms_by_slug( $post_id, DCK_Post_Types::TAX_SERVICE, $d['systems'] );
			$this->assign_terms_by_slug( $post_id, DCK_Post_Types::TAX_AREA, $d['areas'] );

			// Location: state (existing) + city child (create + track).
			$this->assign_location( $post_id, $d['state'], $d['city'], $created_terms );

			// Photos: rotate the pool so cards differ; featured image = first.
			if ( $pool ) {
				$rot     = array_merge( array_slice( $pool, $i % count( $pool ) ), array_slice( $pool, 0, $i % count( $pool ) ) );
				$gallery = array_slice( $rot, 0, min( 6, max( 4, count( $rot ) ) ) );
				update_post_meta( $post_id, '_dck_gallery', implode( ',', $gallery ) );
				set_post_thumbnail( $post_id, $gallery[0] );
			}

			$i++;
		}

		update_option( self::TERMS_OPT, array_values( array_unique( $created_terms ) ), false );
		return $i;
	}

	private function assign_terms_by_slug( $post_id, $taxonomy, $slugs ) {
		$ids = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		if ( $ids ) {
			wp_set_post_terms( $post_id, $ids, $taxonomy );
		}
	}

	private function assign_location( $post_id, $state_name, $city_name, &$created_terms ) {
		$state = get_term_by( 'name', $state_name, DCK_Post_Types::TAX_LOCATION );
		if ( $state ) {
			$state_id = (int) $state->term_id;
		} else {
			$r = wp_insert_term( $state_name, DCK_Post_Types::TAX_LOCATION );
			if ( is_wp_error( $r ) ) {
				return;
			}
			$state_id        = (int) $r['term_id'];
			$created_terms[] = $state_id;
		}

		$loc_ids = array( $state_id );
		$exists  = term_exists( $city_name, DCK_Post_Types::TAX_LOCATION, $state_id );
		if ( $exists ) {
			$city_id = (int) $exists['term_id'];
		} else {
			$c = wp_insert_term( $city_name, DCK_Post_Types::TAX_LOCATION, array( 'parent' => $state_id ) );
			$city_id = is_wp_error( $c ) ? 0 : (int) $c['term_id'];
			if ( $city_id ) {
				$created_terms[] = $city_id;
			}
		}
		if ( $city_id ) {
			$loc_ids[] = $city_id;
		}
		wp_set_post_terms( $post_id, $loc_ids, DCK_Post_Types::TAX_LOCATION );
	}

	/* ================================================================
	 * Data
	 * ================================================================ */

	public static function datasets() {
		return array(
			// 1 — FEATURED.
			array(
				'name'     => 'Lone Star Garage Coatings',
				'featured' => true,
				'state'    => 'Texas',
				'city'     => 'Austin',
				'about'    => 'Lone Star Garage Coatings has been transforming Austin-area garages since 2012. Our crews install full-broadcast flake and polyaspartic systems engineered for Texas heat, with one-day installs and industry-leading adhesion. From two-car garages to commercial showrooms, every floor is prepped with diamond grinding and finished with a UV-stable topcoat.',
				'systems'  => array( 'epoxy-coatings', 'flake-chip-system', 'polyaspartic-polyurea' ),
				'areas'    => array( 'garage-floors', 'driveways', 'commercial' ),
				'meta'     => array(
					'phone'          => '(512) 555-0142',
					'address'        => '2810 Cedar Bend Dr',
					'city'           => 'Austin',
					'state'          => 'TX',
					'zip'            => '78758',
					'website'        => 'https://lonestargarage.example.com',
					'email'          => 'quotes@lonestargarage.example.com',
					'facebook'       => 'https://facebook.com/lonestargaragedemo',
					'instagram'      => 'https://instagram.com/lonestargaragedemo',
					'response_time'  => '1 hour',
					'service_area'   => 'Austin, Round Rock, Cedar Park, Pflugerville, Georgetown',
					'license'        => 'TX-C 148217',
					'insurance'      => 'Fully insured — $2M general liability',
					'year_founded'   => '2012',
					'crew'           => '3 crews / 11 installers',
					'payment'        => 'Card, check, financing available',
					'free_estimates' => 'Yes',
					'warranty'       => '15-year adhesion warranty',
					'services_list'  => "Full-broadcast flake garage floors\nOne-day polyaspartic systems\nCommercial epoxy flooring\nConcrete grinding & prep\nDriveway resurfacing\nMoisture mitigation",
				),
				'hours'    => array( null, array( '07:00', '18:00' ), array( '07:00', '18:00' ), array( '07:00', '18:00' ), array( '07:00', '18:00' ), array( '07:00', '18:00' ), array( '08:00', '14:00' ) ),
				'faq'      => array(
					array( 'q' => 'How long does a garage floor install take?', 'a' => 'Most residential garages are done in a single day with our polyaspartic system; you can park on it in 24 hours.' ),
					array( 'q' => 'Will the coating handle hot tires?', 'a' => 'Yes — hot-tire pickup is exactly what polyaspartic topcoats are engineered to resist, and it is covered by our 15-year warranty.' ),
					array( 'q' => 'Do you work outside Austin?', 'a' => 'We cover the whole metro from Georgetown to Buda; travel fees may apply beyond 40 miles.' ),
				),
				'reviews'  => array(
					array( 'name' => 'Marcus T.', 'location' => 'Round Rock, TX', 'date' => 'March 2026', 'rating' => 5, 'text' => 'Crew showed up at 7am, floor was done by 4. Looks like a showroom.', 'tag' => 'Garage floor', 'reply' => 'Thanks Marcus — enjoy the new floor!' ),
					array( 'name' => 'Dana W.', 'location' => 'Austin, TX', 'date' => 'January 2026', 'rating' => 5, 'text' => 'Got three bids and Lone Star was the most thorough on prep. You can tell it matters.', 'tag' => 'Flake system', 'reply' => '' ),
					array( 'name' => 'Priya K.', 'location' => 'Cedar Park, TX', 'date' => 'November 2025', 'rating' => 4, 'text' => 'Great result. Scheduling took a bit longer than quoted but the work is flawless.', 'tag' => 'Polyaspartic', 'reply' => '' ),
					array( 'name' => 'James R.', 'location' => 'Georgetown, TX', 'date' => 'August 2025', 'rating' => 5, 'text' => "Second house we've used them on. Consistent both times.", 'tag' => 'Garage floor', 'reply' => '' ),
				),
			),
			// 2.
			array(
				'name'     => 'Great Lakes Epoxy Pros',
				'featured' => false,
				'state'    => 'Michigan',
				'city'     => 'Grand Rapids',
				'about'    => 'Great Lakes Epoxy Pros serves West Michigan with moisture-tolerant epoxy systems and basement waterproofing built for lakeside freeze-thaw cycles. We specialize in metallic marble floors for kitchens and rec rooms, plus vapor-barrier coatings that keep Michigan basements dry year round.',
				'systems'  => array( 'epoxy-coatings', 'metallic-marble', 'basement-waterproofing' ),
				'areas'    => array( 'basements', 'garage-floors', 'interior-floors' ),
				'meta'     => array(
					'phone'          => '(616) 555-0127',
					'address'        => '1440 Plainfield Ave NE',
					'city'           => 'Grand Rapids',
					'state'          => 'MI',
					'zip'            => '49505',
					'website'        => 'https://greatlakesepoxy.example.com',
					'email'          => 'hello@greatlakesepoxy.example.com',
					'facebook'       => 'https://facebook.com/greatlakesepoxydemo',
					'response_time'  => '2 hours',
					'service_area'   => 'Grand Rapids, Wyoming, Kentwood, Holland, Muskegon',
					'license'        => 'MI 2101-88342',
					'insurance'      => 'Insured & bonded',
					'year_founded'   => '2016',
					'crew'           => '2 crews / 7 installers',
					'payment'        => 'Card, check, cash',
					'free_estimates' => 'Yes',
					'warranty'       => '10-year system warranty',
					'services_list'  => "Metallic marble epoxy floors\nBasement waterproofing & vapor barriers\nGarage epoxy systems\nFreeze-thaw resistant coatings\nRec room & kitchen floors\nConcrete crack repair",
				),
				'hours'    => array( null, array( '08:00', '17:00' ), array( '08:00', '17:00' ), array( '08:00', '17:00' ), array( '08:00', '17:00' ), array( '08:00', '17:00' ), array( '09:00', '13:00' ) ),
				'faq'      => array(
					array( 'q' => 'My basement gets damp — can you still coat it?', 'a' => 'Yes. We start with a moisture test and install a vapor-barrier epoxy base rated for hydrostatic pressure before any decorative coat.' ),
					array( 'q' => "What's the difference between epoxy and metallic marble?", 'a' => 'Metallic marble is an epoxy system with metallic pigments manipulated for a marbled, one-of-a-kind finish — same durability, showpiece look.' ),
					array( 'q' => 'Do you install in winter?', 'a' => 'Yes, interior work runs all winter; we heat and dehumidify the space as needed.' ),
				),
				'reviews'  => array(
					array( 'name' => 'Karen V.', 'location' => 'Holland, MI', 'date' => 'February 2026', 'rating' => 5, 'text' => "Our basement floods every spring — not anymore. Waterproofing plus metallic floor and it's now the nicest room in the house.", 'tag' => 'Basement waterproofing', 'reply' => 'That project was a fun one — thanks Karen!' ),
					array( 'name' => 'Tom B.', 'location' => 'Grand Rapids, MI', 'date' => 'December 2025', 'rating' => 5, 'text' => 'Metallic marble in copper. Every guest asks about it.', 'tag' => 'Metallic marble', 'reply' => '' ),
					array( 'name' => 'Ashley D.', 'location' => 'Kentwood, MI', 'date' => 'October 2025', 'rating' => 4, 'text' => "Solid work, fair price. Dust control could've been better but they cleaned up well.", 'tag' => 'Garage floor', 'reply' => '' ),
				),
			),
			// 3.
			array(
				'name'     => 'Sunshine State Coatings',
				'featured' => false,
				'state'    => 'Florida',
				'city'     => 'Tampa',
				'about'    => 'Sunshine State Coatings installs slip-resistant quartz and flake systems across Tampa Bay pool decks, lanais, and patios. Our Protect and Seal packages guard against salt air, UV fade, and hurricane-season moisture, keeping outdoor concrete looking new in the Florida sun.',
				'systems'  => array( 'quartz-system', 'flake-chip-system', 'protect-seal' ),
				'areas'    => array( 'pool-decks', 'patios', 'walkways-sidewalks' ),
				'meta'     => array(
					'phone'          => '(813) 555-0168',
					'address'        => '4205 W Gandy Blvd',
					'city'           => 'Tampa',
					'state'          => 'FL',
					'zip'            => '33611',
					'website'        => 'https://sunshinecoatings.example.com',
					'email'          => 'info@sunshinecoatings.example.com',
					'instagram'      => 'https://instagram.com/sunshinecoatingsdemo',
					'response_time'  => 'Same day',
					'service_area'   => 'Tampa, St. Petersburg, Clearwater, Brandon, Sarasota',
					'license'        => 'FL CGC-1534988',
					'insurance'      => '$1M liability + workers comp',
					'year_founded'   => '2018',
					'crew'           => '2 crews / 6 installers',
					'payment'        => 'Card, financing available',
					'free_estimates' => 'Yes',
					'warranty'       => '12-year UV-fade & adhesion warranty',
					'services_list'  => "Slip-resistant quartz pool decks\nLanai & patio flake systems\nProtect and Seal exterior packages\nUV-stable topcoats\nCool-touch deck coatings\nPressure washing & resealing",
				),
				'hours'    => array( null, array( '07:00', '19:00' ), array( '07:00', '19:00' ), array( '07:00', '19:00' ), array( '07:00', '19:00' ), array( '07:00', '19:00' ), array( '07:00', '19:00' ) ),
				'faq'      => array(
					array( 'q' => 'Will the deck get too hot to walk on?', 'a' => 'Our cool-touch quartz systems reflect heat and stay noticeably cooler than bare concrete or pavers.' ),
					array( 'q' => 'Is it slippery when wet?', 'a' => 'No — quartz broadcast gives a textured, barefoot-friendly surface that meets slip-resistance standards for pool surrounds.' ),
					array( 'q' => 'How does it hold up to hurricanes and salt air?', 'a' => "The Protect and Seal topcoat is rated for salt spray and standing water; we've had zero coating failures through two hurricane seasons." ),
				),
				'reviews'  => array(
					array( 'name' => 'Luis M.', 'location' => 'St. Petersburg, FL', 'date' => 'April 2026', 'rating' => 5, 'text' => "Pool deck went from cracked and ugly to resort-quality. Kids love that it's not slippery.", 'tag' => 'Pool deck', 'reply' => 'Enjoy the pool season, Luis!' ),
					array( 'name' => 'Barb H.', 'location' => 'Clearwater, FL', 'date' => 'February 2026', 'rating' => 5, 'text' => 'They resealed our lanai in one day. Looks brand new.', 'tag' => 'Patio', 'reply' => '' ),
					array( 'name' => 'Greg S.', 'location' => 'Brandon, FL', 'date' => 'January 2026', 'rating' => 4, 'text' => "Good crew, great result. Took an extra day due to rain, but that's Florida.", 'tag' => 'Quartz system', 'reply' => '' ),
					array( 'name' => 'Nicole P.', 'location' => 'Tampa, FL', 'date' => 'September 2025', 'rating' => 5, 'text' => "Best contractor experience we've had, start to finish.", 'tag' => 'Walkway', 'reply' => '' ),
				),
			),
			// 4.
			array(
				'name'     => 'Rocky Mountain Concrete Co.',
				'featured' => false,
				'state'    => 'Colorado',
				'city'     => 'Denver',
				'about'    => "Rocky Mountain Concrete Co. brings warm Rustic Concrete Wood finishes and polished concrete to homes and businesses across the Front Range. Our high-altitude installation process accounts for Denver's dry climate and rapid temperature swings, delivering floors that stay beautiful at 5,280 feet.",
				'systems'  => array( 'concrete-wood', 'polished-concrete', 'epoxy-coatings' ),
				'areas'    => array( 'interior-floors', 'commercial', 'patios' ),
				'meta'     => array(
					'phone'          => '(303) 555-0135',
					'address'        => '3901 Brighton Blvd',
					'city'           => 'Denver',
					'state'          => 'CO',
					'zip'            => '80216',
					'website'        => 'https://rockymtnconcrete.example.com',
					'email'          => 'projects@rockymtnconcrete.example.com',
					'youtube'        => 'https://youtube.com/@rockymtnconcretedemo',
					'response_time'  => '4 hours',
					'service_area'   => 'Denver, Aurora, Lakewood, Boulder, Castle Rock',
					'license'        => 'CO 2019-114576',
					'insurance'      => 'Fully insured',
					'year_founded'   => '2010',
					'crew'           => '4 crews / 14 installers',
					'payment'        => 'Card, check, net-30 commercial',
					'free_estimates' => 'Yes',
					'warranty'       => 'Lifetime delamination warranty',
					'services_list'  => "Rustic Concrete Wood plank floors\nPolished concrete (residential & commercial)\nCommercial epoxy systems\nStained & sealed patios\nConcrete overlays\nMaintenance & repolishing programs",
				),
				'hours'    => array( null, array( '07:00', '17:00' ), array( '07:00', '17:00' ), array( '07:00', '17:00' ), array( '07:00', '17:00' ), array( '07:00', '17:00' ), null ),
				'faq'      => array(
					array( 'q' => 'What is Rustic Concrete Wood?', 'a' => 'A hand-carved overlay that gives you the look of hardwood planks with the durability of concrete — no water damage, no refinishing.' ),
					array( 'q' => 'Does altitude really affect coatings?', 'a' => 'Yes — cure times and off-gassing change above 5,000 ft. Our mix schedules are dialed for the Front Range.' ),
					array( 'q' => 'Do you do commercial projects?', 'a' => 'About half our work is commercial — breweries, offices, and retail. Net-30 terms available.' ),
				),
				'reviews'  => array(
					array( 'name' => 'Sarah J.', 'location' => 'Boulder, CO', 'date' => 'March 2026', 'rating' => 5, 'text' => "Concrete wood throughout our walkout basement. Everyone thinks it's real hardwood until they touch it.", 'tag' => 'Concrete wood', 'reply' => 'It fooled our own estimator in photos. Thanks Sarah!' ),
					array( 'name' => 'Mike D.', 'location' => 'Denver, CO', 'date' => 'January 2026', 'rating' => 5, 'text' => 'They polished 8,000 sq ft for our taproom over one weekend. Zero downtime.', 'tag' => 'Polished concrete', 'reply' => '' ),
					array( 'name' => 'Elena R.', 'location' => 'Castle Rock, CO', 'date' => 'November 2025', 'rating' => 4, 'text' => 'Beautiful patio finish. Scheduling was tight in fall but worth the wait.', 'tag' => 'Patio', 'reply' => '' ),
				),
			),
			// 5.
			array(
				'name'     => 'Blue Ridge Floor Systems',
				'featured' => false,
				'state'    => 'North Carolina',
				'city'     => 'Asheville',
				'about'    => "Blue Ridge Floor Systems is Asheville's decorative concrete specialist, known for one-of-a-kind metallic marble installations in mountain homes and breweries. We pair fast-curing polyaspartic topcoats with mountain-grade sealing so floors handle mud season and four true seasons of wear.",
				'systems'  => array( 'metallic-marble', 'polyaspartic-polyurea', 'protect-seal' ),
				'areas'    => array( 'garage-floors', 'interior-floors', 'commercial' ),
				'meta'     => array(
					'phone'          => '(828) 555-0119',
					'address'        => '802 Riverside Dr',
					'city'           => 'Asheville',
					'state'          => 'NC',
					'zip'            => '28801',
					'website'        => 'https://blueridgefloors.example.com',
					'email'          => 'team@blueridgefloors.example.com',
					'facebook'       => 'https://facebook.com/blueridgefloorsdemo',
					'instagram'      => 'https://instagram.com/blueridgefloorsdemo',
					'response_time'  => '1 business day',
					'service_area'   => 'Asheville, Hendersonville, Waynesville, Black Mountain',
					'license'        => 'NC 77031-U',
					'insurance'      => 'Insured & bonded',
					'year_founded'   => '2014',
					'crew'           => '1 crew / 4 installers',
					'payment'        => 'Card, check, cash',
					'free_estimates' => 'Yes',
					'warranty'       => '10-year workmanship warranty',
					'services_list'  => "Custom metallic marble floors\nPolyaspartic garage systems\nBrewery & taproom flooring\nProtect and Seal packages\nMountain-home mudroom floors\nColor consultations",
				),
				'hours'    => array( null, array( '08:00', '18:00' ), array( '08:00', '18:00' ), array( '08:00', '18:00' ), array( '08:00', '18:00' ), array( '08:00', '18:00' ), array( '09:00', '15:00' ) ),
				'faq'      => array(
					array( 'q' => 'Can I pick my metallic colors?', 'a' => 'Absolutely — we do an in-person color consultation and pour a sample board before every metallic job.' ),
					array( 'q' => 'How do floors handle mud season?', 'a' => 'Our topcoats are sealed against grit and moisture; a hose-off or mop brings them right back.' ),
					array( 'q' => 'Do you travel outside Asheville?', 'a' => 'We regularly work from Waynesville to Black Mountain; ask about anything further out.' ),
				),
				'reviews'  => array(
					array( 'name' => 'Hannah C.', 'location' => 'Asheville, NC', 'date' => 'April 2026', 'rating' => 5, 'text' => 'The metallic floor in our tasting room is a literal conversation piece. Patrons photograph it.', 'tag' => 'Metallic marble', 'reply' => 'Cheers, Hannah — see you at the taproom.' ),
					array( 'name' => 'Robert L.', 'location' => 'Hendersonville, NC', 'date' => 'February 2026', 'rating' => 5, 'text' => 'Garage floor handled a whole winter of salt and mud. Wipes clean.', 'tag' => 'Garage floor', 'reply' => '' ),
					array( 'name' => 'Diane F.', 'location' => 'Black Mountain, NC', 'date' => 'December 2025', 'rating' => 4, 'text' => 'Lovely work on our mudroom and entry. A little pricey, but quality shows.', 'tag' => 'Interior floor', 'reply' => '' ),
				),
			),
		);
	}
}
