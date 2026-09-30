<?php
/**
 * State and city directory pages: /contractors/{state}/ and
 * /contractors/{state}/{city}/. Inherits the theme header and footer.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$ctx     = DCK_SEO_Locations::context();
$term    = $ctx['term'];
$state   = $ctx['state'];
$count   = (int) $ctx['count'];
$place   = $ctx['place'];
$is_city = $ctx['is_city'];
$paged   = max( 1, (int) get_query_var( 'paged' ) );

if ( $is_city ) {
	$h1 = $count > 0
		? sprintf( __( 'Decorative Concrete Contractors in %1$s, %2$s', 'dck-directory' ), $term->name, $state->name )
		: sprintf( __( 'Decorative Concrete Contractors near %1$s, %2$s', 'dck-directory' ), $term->name, $state->name );
} else {
	$h1 = sprintf( __( 'Decorative Concrete Contractors in %s', 'dck-directory' ), $state->name );
}
?>
<div class="dck-directory-page dck-location-page">
	<div class="dck-wrap">
		<?php echo DCK_SEO_Locations::breadcrumbs_html( DCK_SEO_Locations::trail( $term ) ); // phpcs:ignore ?>

		<header class="dck-loc-head">
			<h1 class="dck-archive-title"><?php echo esc_html( $h1 ); ?></h1>
			<p class="dck-loc-intro">
				<?php
				if ( $is_city ) {
					if ( $count > 0 ) {
						printf(
							/* translators: 1: number of contractors, 2: City, ST */
							esc_html( _n( '%1$d decorative concrete, epoxy flooring and concrete coating contractor is listed in %2$s. Open a profile to call directly or request a free quote.', '%1$d decorative concrete, epoxy flooring and concrete coating contractors are listed in %2$s. Open a profile to call directly or request a free quote.', $count, 'dck-directory' ) ),
							(int) $count,
							esc_html( $place )
						);
					} else {
						printf( esc_html__( 'No contractors are listed in %s yet. Here are pros from nearby cities.', 'dck-directory' ), esc_html( $place ) );
					}
				} else {
					$n_cities = count( get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'parent' => $term->term_id, 'hide_empty' => true, 'fields' => 'ids' ) ) );
					printf(
						/* translators: 1: number of contractors, 2: number of cities, 3: state */
						esc_html__( '%1$d decorative concrete, epoxy flooring and concrete coating contractors across %2$d cities in %3$s. Pick your city below, or browse every listing.', 'dck-directory' ),
						(int) $count,
						(int) $n_cities,
						esc_html( $state->name )
					);
				}
				?>
			</p>
		</header>

		<?php if ( ! $is_city && 1 === $paged ) :
			$cities = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'parent' => $term->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
			if ( $cities && ! is_wp_error( $cities ) ) : ?>
			<section class="dck-loc-section" aria-labelledby="dck-cities-h">
				<h2 id="dck-cities-h"><?php printf( esc_html__( 'Cities in %s', 'dck-directory' ), esc_html( $state->name ) ); ?></h2>
				<div class="dck-states__grid dck-cities">
					<?php foreach ( $cities as $c ) : ?>
						<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?> <span>(<?php echo (int) $c->count; ?>)</span></a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; endif; ?>

		<?php if ( have_posts() ) : ?>
		<section class="dck-loc-section" aria-labelledby="dck-list-h">
			<h2 id="dck-list-h">
				<?php
				echo esc_html(
					$is_city
						? sprintf( __( 'Contractors in %s', 'dck-directory' ), $term->name )
						: sprintf( __( 'All %s contractors', 'dck-directory' ), $state->name )
				);
				?>
			</h2>
			<div class="dck-results">
				<?php
				while ( have_posts() ) {
					the_post();
					echo dck_render_card( get_the_ID() ); // phpcs:ignore
				}
				?>
			</div>
			<div class="dck-pagination"><?php echo wp_kses_post( paginate_links() ); ?></div>
		</section>
		<?php endif; ?>

		<?php if ( $is_city ) :
			$near_posts = $count < 10 ? DCK_SEO_Locations::nearby_listings( $term ) : array();
			if ( $near_posts ) : ?>
			<section class="dck-loc-section" aria-labelledby="dck-near-h">
				<h2 id="dck-near-h"><?php printf( esc_html__( 'More contractors near %s', 'dck-directory' ), esc_html( $term->name ) ); ?></h2>
				<p class="dck-muted"><?php printf( esc_html__( 'Within about %d miles.', 'dck-directory' ), (int) DCK_SEO_Locations::NEARBY_MILES ); ?></p>
				<div class="dck-results">
					<?php foreach ( $near_posts as $pid ) { echo dck_render_card( $pid ); } // phpcs:ignore ?>
				</div>
			</section>
			<?php endif;

			$near = DCK_SEO_Locations::nearby_cities( $term );
			if ( $near ) : ?>
			<section class="dck-loc-section" aria-labelledby="dck-nearc-h">
				<h2 id="dck-nearc-h"><?php printf( esc_html__( 'Cities near %s', 'dck-directory' ), esc_html( $term->name ) ); ?></h2>
				<div class="dck-states__grid dck-cities">
					<?php
					$pts = DCK_SEO_Locations::city_points();
					foreach ( $near as $tid => $mi ) :
						$c = get_term( $tid, DCK_Post_Types::TAX_LOCATION );
						if ( ! $c || is_wp_error( $c ) ) {
							continue;
						}
						$cs = get_term( (int) $c->parent, DCK_Post_Types::TAX_LOCATION );
						?>
						<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name . ( $cs && (int) $cs->term_id !== (int) $state->term_id ? ', ' . DCK_SEO_Locations::state_abbr( $cs->name ) : '' ) ); ?> <span>(<?php echo (int) $pts[ $tid ][2]; ?>)</span></a>
					<?php endforeach; ?>
				</div>
				<p><a href="<?php echo esc_url( get_term_link( $state ) ); ?>"><?php printf( esc_html__( 'All %s cities →', 'dck-directory' ), esc_html( $state->name ) ); ?></a></p>
			</section>
		<?php endif; endif; ?>

		<div class="dck-cta-strip">
			<div>
				<strong><?php esc_html_e( 'Do you install decorative concrete or coatings?', 'dck-directory' ); ?></strong>
				<span><?php esc_html_e( 'Add or claim your free listing in a few minutes.', 'dck-directory' ); ?></span>
			</div>
			<a class="dck-btn" href="<?php echo esc_url( dck_signup_url() ); ?>"><?php esc_html_e( 'List your business', 'dck-directory' ); ?></a>
		</div>
	</div>
</div>
<?php
get_footer();
