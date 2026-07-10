<?php
/**
 * Archive / taxonomy listing: contractor cards in the directory style.
 * Inherits the active theme's header and footer.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$term  = get_queried_object();
$title = __( 'Contractors', 'dck-directory' );
if ( $term instanceof WP_Term ) {
	if ( DCK_Post_Types::TAX_LOCATION === $term->taxonomy ) {
		$title = sprintf( __( 'Decorative concrete contractors in %s', 'dck-directory' ), $term->name );
	} elseif ( DCK_Post_Types::TAX_SERVICE === $term->taxonomy || DCK_Post_Types::TAX_AREA === $term->taxonomy ) {
		$title = sprintf( __( '%s contractors', 'dck-directory' ), $term->name );
	}
}
?>
<div class="dck-directory-page">
	<div class="dck-wrap">
		<div class="dck-results-head">
			<h1 class="dck-archive-title"><?php echo esc_html( $title ); ?></h1>
			<a class="dck-back-link" href="<?php echo esc_url( dck_directory_url() ); ?>"><?php esc_html_e( '← All contractors', 'dck-directory' ); ?></a>
		</div>

		<?php if ( DCK_Post_Types::TAX_LOCATION === ( $term->taxonomy ?? '' ) && 0 === (int) $term->parent ) :
			$children = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'parent' => $term->term_id, 'hide_empty' => false ) );
			if ( ! empty( $children ) && ! is_wp_error( $children ) ) : ?>
			<div class="dck-states__grid dck-cities">
				<?php foreach ( $children as $c ) : ?>
					<a href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?> <span>(<?php echo (int) $c->count; ?>)</span></a>
				<?php endforeach; ?>
			</div>
		<?php endif; endif; ?>

		<div class="dck-results">
			<?php
			if ( have_posts() ) {
				while ( have_posts() ) {
					the_post();
					echo dck_render_card( get_the_ID() ); // phpcs:ignore
				}
			} else {
				echo '<div class="dck-results-empty">' . esc_html__( 'No contractors listed here yet.', 'dck-directory' ) . '</div>';
			}
			?>
		</div>

		<div class="dck-pagination">
			<?php echo wp_kses_post( paginate_links() ); ?>
		</div>
	</div>
</div>
<?php
get_footer();
