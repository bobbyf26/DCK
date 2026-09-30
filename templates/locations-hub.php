<?php
/**
 * /contractors/ — every state, plus the largest cities.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$states = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'parent' => 0, 'hide_empty' => true, 'orderby' => 'name' ) );
$pts    = DCK_SEO_Locations::city_points();
uasort(
	$pts,
	static function ( $a, $b ) {
		return $b[2] <=> $a[2];
	}
);
$top   = array_slice( $pts, 0, 30, true );
$total = wp_count_posts( DCK_Post_Types::POST_TYPE )->publish;
$trail = array( array( __( 'Home', 'dck-directory' ), home_url( '/' ) ), array( __( 'Contractors', 'dck-directory' ), DCK_SEO_Locations::hub_url() ) );
?>
<div class="dck-directory-page dck-location-page">
	<div class="dck-wrap">
		<?php echo DCK_SEO_Locations::breadcrumbs_html( $trail ); // phpcs:ignore ?>
		<header class="dck-loc-head">
			<h1 class="dck-archive-title"><?php esc_html_e( 'Decorative Concrete Contractors by State', 'dck-directory' ); ?></h1>
			<p class="dck-loc-intro"><?php printf( esc_html__( 'Browse %s decorative concrete, epoxy flooring and concrete coating contractors. Choose your state, then your city.', 'dck-directory' ), esc_html( number_format_i18n( $total ) ) ); ?></p>
		</header>

		<?php if ( $states && ! is_wp_error( $states ) ) : ?>
		<section class="dck-loc-section" aria-labelledby="dck-states-h">
			<h2 id="dck-states-h"><?php esc_html_e( 'States', 'dck-directory' ); ?></h2>
			<div class="dck-states__grid">
				<?php foreach ( $states as $s ) : ?>
					<a href="<?php echo esc_url( get_term_link( $s ) ); ?>"><?php echo esc_html( $s->name ); ?> <span>(<?php echo (int) $s->count; ?>)</span></a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( $top ) : ?>
		<section class="dck-loc-section" aria-labelledby="dck-top-h">
			<h2 id="dck-top-h"><?php esc_html_e( 'Largest cities', 'dck-directory' ); ?></h2>
			<div class="dck-states__grid dck-cities">
				<?php
				foreach ( $top as $tid => $p ) :
					$c  = get_term( $tid, DCK_Post_Types::TAX_LOCATION );
					$cs = $c ? get_term( (int) $c->parent, DCK_Post_Types::TAX_LOCATION ) : null;
					if ( ! $c || ! $cs || is_wp_error( $c ) || is_wp_error( $cs ) ) {
						continue;
					}
					?>
					<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name . ', ' . DCK_SEO_Locations::state_abbr( $cs->name ) ); ?> <span>(<?php echo (int) $p[2]; ?>)</span></a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
