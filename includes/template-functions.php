<?php
/**
 * Shared render helpers. These produce the approved contractor design
 * from listing data and are used by the single template + search cards.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lighten or darken a hex color. Positive percent lightens toward white,
 * negative darkens toward black. Used to derive brand shades from one color.
 *
 * @param string $hex     e.g. "#1E62B4".
 * @param int    $percent -100..100.
 * @return string Hex color.
 */
function dck_adjust_hex( $hex, $percent ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '#' . $hex;
	}
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	$adjust = function ( $c ) use ( $percent ) {
		if ( $percent < 0 ) {
			return max( 0, (int) round( $c * ( 1 + $percent / 100 ) ) );
		}
		return min( 255, (int) round( $c + ( 255 - $c ) * ( $percent / 100 ) ) );
	};
	return sprintf( '#%02x%02x%02x', $adjust( $r ), $adjust( $g ), $adjust( $b ) );
}

/**
 * SVG star row for a given rating (0–5, halves rounded to nearest).
 */
function dck_stars_html( $rating, $size = 17 ) {
	$rating = (float) $rating;
	$full   = (int) round( $rating );
	$out     = '<span class="dck-stars" aria-label="' . esc_attr( sprintf( '%s out of 5 stars', $rating ) ) . '">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$fill = $i <= $full ? 'var(--dck-star)' : 'var(--dck-line)';
		$out .= '<svg viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" style="fill:' . $fill . '"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>';
	}
	$out .= '</span>';
	return $out;
}

/**
 * Initials for the logo placeholder.
 */
function dck_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$a     = isset( $parts[0][0] ) ? $parts[0][0] : '';
	$b     = isset( $parts[1][0] ) ? $parts[1][0] : ( isset( $parts[0][1] ) ? $parts[0][1] : '' );
	return strtoupper( $a . $b );
}

/**
 * Record which page a shortcode lives on, so internal links resolve reliably.
 * Called by each shortcode when it renders on a singular page.
 */
function dck_remember_page( $key ) {
	if ( ! is_page() && ! is_singular() ) {
		return;
	}
	$id = get_queried_object_id();
	if ( $id && (int) get_option( 'dck_page_' . $key ) !== (int) $id ) {
		update_option( 'dck_page_' . $key, (int) $id, false );
	}
}

/**
 * Resolve the URL of the page holding a given shortcode.
 * Uses the remembered page id; falls back to a content scan, then home.
 */
function dck_page_url_for( $key, $shortcode ) {
	$id = (int) get_option( 'dck_page_' . $key );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	// Fallback: scan published pages for the shortcode once.
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
		)
	);
	foreach ( $pages as $pid ) {
		if ( has_shortcode( get_post_field( 'post_content', $pid ), $shortcode ) ) {
			update_option( 'dck_page_' . $key, (int) $pid, false );
			return get_permalink( $pid );
		}
	}
	return home_url( '/' );
}

function dck_directory_url() {
	return dck_page_url_for( 'directory', 'dck_directory' );
}
function dck_signup_url() {
	return dck_page_url_for( 'signup', 'dck_signup' );
}
function dck_dashboard_url() {
	return dck_page_url_for( 'dashboard', 'dck_dashboard' );
}

/**
 * Compute the human service-area string for a listing.
 */
function dck_service_area_text( $post_id ) {
	$area = DCK_Fields::get( $post_id, 'service_area' );
	if ( $area ) {
		// Show first couple cities.
		$cities = array_filter( array_map( 'trim', explode( ',', $area ) ) );
		if ( count( $cities ) > 2 ) {
			return $cities[0] . ' – ' . end( $cities ) . ' area';
		}
		return implode( ', ', $cities );
	}
	$city  = get_post_meta( $post_id, '_dck_city', true );
	$state = get_post_meta( $post_id, '_dck_state', true );
	return trim( $city . ( $city && $state ? ', ' : '' ) . $state );
}

/**
 * Render a compact result card (used by the AJAX search grid).
 */
function dck_render_card( $post_id ) {
	$name     = get_the_title( $post_id );
	$premium  = DCK_Fields::is_premium( $post_id );
	$featured = DCK_Fields::is_featured( $post_id );
	$reviews  = DCK_Fields::get_json( $post_id, 'reviews' );
	$count    = count( $reviews );
	$avg      = 0;
	foreach ( $reviews as $r ) {
		$avg += isset( $r['rating'] ) ? (int) $r['rating'] : 0;
	}
	$avg      = $count ? round( $avg / $count, 1 ) : 0;
	$svc_names  = wp_get_post_terms( $post_id, DCK_Post_Types::TAX_SERVICE, array( 'fields' => 'names' ) );
	$area_names = wp_get_post_terms( $post_id, DCK_Post_Types::TAX_AREA, array( 'fields' => 'names' ) );
	$cats     = array_merge( is_array( $svc_names ) ? $svc_names : array(), is_array( $area_names ) ? $area_names : array() );
	$area     = dck_service_area_text( $post_id );
	$thumb    = get_the_post_thumbnail_url( $post_id, 'medium' );

	ob_start();
	?>
	<a class="dck-card<?php echo $featured ? ' dck-card--featured' : ''; ?>" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<div class="dck-card__media">
			<?php if ( $thumb ) : ?>
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy">
			<?php else : ?>
				<span class="dck-card__ph"><?php echo esc_html( dck_initials( $name ) ); ?></span>
			<?php endif; ?>
			<?php if ( $featured ) : ?><span class="dck-card__badge"><?php esc_html_e( 'Featured', 'dck-directory' ); ?></span><?php endif; ?>
		</div>
		<div class="dck-card__body">
			<h3><?php echo esc_html( $name ); ?><?php if ( $premium ) : ?> <span class="dck-verify-dot" title="Verified Pro">✓</span><?php endif; ?></h3>
			<?php if ( $count ) : ?>
				<div class="dck-card__rating"><?php echo dck_stars_html( $avg, 14 ); // phpcs:ignore ?> <b><?php echo esc_html( $avg ); ?></b> <span>(<?php echo (int) $count; ?>)</span></div>
			<?php endif; ?>
			<?php if ( $area ) : ?><p class="dck-card__area"><?php echo esc_html( $area ); ?></p><?php endif; ?>
			<?php if ( $cats ) : ?>
				<div class="dck-card__chips">
					<?php foreach ( array_slice( $cats, 0, 3 ) as $c ) : ?><span><?php echo esc_html( $c ); ?></span><?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * Full contractor profile — the approved design, driven by data + tier.
 */
function dck_render_profile( $post_id ) {
	$name    = get_the_title( $post_id );
	$premium = DCK_Fields::is_premium( $post_id );
	$about   = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
	$phone   = get_post_meta( $post_id, '_dck_phone', true );
	$address = get_post_meta( $post_id, '_dck_address', true );
	$city    = get_post_meta( $post_id, '_dck_city', true );
	$state   = get_post_meta( $post_id, '_dck_state', true );
	$zip     = get_post_meta( $post_id, '_dck_zip', true );
	$area    = dck_service_area_text( $post_id );
	$systems = wp_get_post_terms( $post_id, DCK_Post_Types::TAX_SERVICE, array( 'fields' => 'names' ) );
	$systems = is_array( $systems ) ? $systems : array();
	$app_areas = wp_get_post_terms( $post_id, DCK_Post_Types::TAX_AREA, array( 'fields' => 'names' ) );
	$app_areas = is_array( $app_areas ) ? $app_areas : array();
	$logo    = get_the_post_thumbnail_url( $post_id, 'thumbnail' );

	// Premium-gated data.
	$gallery = $premium ? array_filter( array_map( 'absint', explode( ',', (string) DCK_Fields::get( $post_id, 'gallery' ) ) ) ) : array();
	$reviews = $premium ? DCK_Fields::get_json( $post_id, 'reviews' ) : array();
	$faq     = $premium ? DCK_Fields::get_json( $post_id, 'faq' ) : array();
	$hours   = $premium ? DCK_Fields::get_json( $post_id, 'hours' ) : array();
	$services = $premium ? array_filter( array_map( 'trim', explode( "\n", (string) DCK_Fields::get( $post_id, 'services_list' ) ) ) ) : array();

	$count = count( $reviews );
	$avg   = 0;
	foreach ( $reviews as $r ) {
		$avg += isset( $r['rating'] ) ? (int) $r['rating'] : 0;
	}
	$avg = $count ? round( $avg / $count, 1 ) : 0;

	// Rating distribution.
	$dist = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
	foreach ( $reviews as $r ) {
		$rt = isset( $r['rating'] ) ? (int) $r['rating'] : 0;
		if ( isset( $dist[ $rt ] ) ) {
			$dist[ $rt ]++;
		}
	}

	ob_start();
	?>
	<div class="dck-profile">
		<div class="dck-wrap">

			<?php if ( $premium && $gallery ) : ?>
			<section class="dck-mosaic" aria-label="<?php esc_attr_e( 'Project photos', 'dck-directory' ); ?>">
				<?php
				$shown = array_slice( $gallery, 0, 5 );
				foreach ( $shown as $gi => $gid ) {
					$src = wp_get_attachment_image_url( $gid, 'large' );
					if ( $src ) {
						echo '<div class="dck-photo"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $name ) . '"></div>';
					}
				}
				if ( count( $gallery ) > 5 ) {
					echo '<button class="dck-all-photos" type="button">' . esc_html( sprintf( __( 'View all %d photos', 'dck-directory' ), count( $gallery ) ) ) . '</button>';
				}
				?>
			</section>
			<?php endif; ?>

			<header class="dck-head">
				<div class="dck-avatar" aria-hidden="true">
					<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt=""><?php else : ?><?php echo esc_html( dck_initials( $name ) ); ?><?php endif; ?>
				</div>
				<div>
					<h1><?php echo esc_html( $name ); ?>
						<?php if ( $premium ) : ?>
						<span class="dck-verified">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10.6 16.2l-3.3-3.3 1.4-1.4 1.9 1.9 4.7-4.7 1.4 1.4z"/></svg>
							<?php esc_html_e( 'Verified Pro', 'dck-directory' ); ?>
						</span>
						<?php endif; ?>
					</h1>
					<div class="dck-rating-line">
						<?php if ( $count ) : ?>
							<?php echo dck_stars_html( $avg ); // phpcs:ignore ?>
							<span class="dck-rating-num"><?php echo esc_html( $avg ); ?></span>
							<a href="#dck-reviews">(<?php echo esc_html( sprintf( _n( '%d review', '%d reviews', $count, 'dck-directory' ), $count ) ); ?>)</a>
							<span class="dck-dot">•</span>
						<?php endif; ?>
						<?php if ( $area ) : ?><span class="dck-muted"><?php echo esc_html( sprintf( __( 'Serving %s', 'dck-directory' ), $area ) ); ?></span><?php endif; ?>
						<?php if ( $premium && $hours ) : ?>
							<span class="dck-dot">•</span>
							<span class="dck-open-status" data-dck-hours='<?php echo esc_attr( wp_json_encode( $hours ) ); ?>'><b><?php esc_html_e( 'Hours', 'dck-directory' ); ?></b></span>
						<?php endif; ?>
					</div>
					<?php if ( $systems || $app_areas ) : ?>
					<div class="dck-chips">
						<?php foreach ( $systems as $c ) : ?><span class="dck-chip"><?php echo esc_html( $c ); ?></span><?php endforeach; ?>
						<?php foreach ( $app_areas as $c ) : ?><span class="dck-chip dck-chip--plain"><?php echo esc_html( $c ); ?></span><?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>
			</header>

			<div class="dck-cols">
				<div class="dck-main">

					<?php if ( trim( wp_strip_all_tags( $about ) ) ) : ?>
					<section class="dck-card">
						<h2><?php echo esc_html( dck_setting( 'profile_about_heading' ) ); ?></h2>
						<?php echo wp_kses_post( $about ); ?>
					</section>
					<?php endif; ?>

					<?php if ( $premium && $services ) : ?>
					<section class="dck-card">
						<h2><?php echo esc_html( dck_setting( 'profile_services_heading' ) ); ?></h2>
						<ul class="dck-svc-grid">
							<?php foreach ( $services as $s ) : ?>
							<li><svg viewBox="0 0 24 24" width="16" height="16"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg><?php echo esc_html( $s ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
					<?php endif; ?>

					<?php if ( $premium && $count ) : ?>
					<section class="dck-card" id="dck-reviews">
						<h2><?php echo esc_html( dck_setting( 'profile_reviews_heading' ) ); ?></h2>
						<div class="dck-rev-summary">
							<div class="dck-rev-big">
								<b><?php echo esc_html( $avg ); ?></b>
								<?php echo dck_stars_html( $avg ); // phpcs:ignore ?>
								<small><?php echo esc_html( sprintf( _n( '%d review', '%d reviews', $count, 'dck-directory' ), $count ) ); ?></small>
							</div>
							<div class="dck-bars">
								<?php foreach ( array( 5, 4, 3, 2, 1 ) as $stars ) :
									$pct = $count ? round( ( $dist[ $stars ] / $count ) * 100 ) : 0; ?>
									<div class="dck-bar-row"><span><?php echo (int) $stars; ?></span><div class="dck-bar"><i style="width:<?php echo (int) $pct; ?>%"></i></div><span><?php echo (int) $dist[ $stars ]; ?></span></div>
								<?php endforeach; ?>
							</div>
						</div>
						<?php foreach ( $reviews as $r ) : ?>
						<article class="dck-review">
							<div class="dck-rev-head">
								<div class="dck-rev-avatar"><?php echo esc_html( dck_initials( $r['name'] ) ); ?></div>
								<div><b><?php echo esc_html( $r['name'] ); ?></b><small><?php echo esc_html( trim( $r['location'] . ( $r['location'] && $r['date'] ? ' — ' : '' ) . $r['date'] ) ); ?></small></div>
								<?php echo dck_stars_html( isset( $r['rating'] ) ? $r['rating'] : 5, 14 ); // phpcs:ignore ?>
							</div>
							<?php if ( ! empty( $r['text'] ) ) : ?><p><?php echo esc_html( $r['text'] ); ?></p><?php endif; ?>
							<?php if ( ! empty( $r['tag'] ) ) : ?><span class="dck-rev-tag"><?php echo esc_html( $r['tag'] ); ?></span><?php endif; ?>
							<?php if ( ! empty( $r['reply'] ) ) : ?>
							<div class="dck-owner-reply"><b><?php esc_html_e( 'Response from the owner', 'dck-directory' ); ?></b><?php echo esc_html( $r['reply'] ); ?></div>
							<?php endif; ?>
						</article>
						<?php endforeach; ?>
					</section>
					<?php endif; ?>

					<?php
					$area_cities = $premium ? array_filter( array_map( 'trim', explode( ',', (string) DCK_Fields::get( $post_id, 'service_area' ) ) ) ) : array();
					if ( $premium && $area_cities ) : ?>
					<section class="dck-card">
						<h2><?php echo esc_html( dck_setting( 'profile_area_heading' ) ); ?></h2>
						<div class="dck-area-cities">
							<?php foreach ( $area_cities as $c ) : ?><span class="dck-chip dck-chip--plain"><?php echo esc_html( $c ); ?></span><?php endforeach; ?>
						</div>
					</section>
					<?php endif; ?>

					<?php
					$details = array(
						'license'        => __( 'License', 'dck-directory' ),
						'insurance'      => __( 'Insurance', 'dck-directory' ),
						'year_founded'   => __( 'Year founded', 'dck-directory' ),
						'crew'           => __( 'Crew size', 'dck-directory' ),
						'payment'        => __( 'Payment', 'dck-directory' ),
						'free_estimates' => __( 'Free estimates', 'dck-directory' ),
						'warranty'       => __( 'Warranty', 'dck-directory' ),
					);
					$has_details = false;
					foreach ( $details as $k => $l ) {
						if ( $premium && DCK_Fields::get( $post_id, $k ) ) { $has_details = true; break; }
					}
					if ( $has_details ) : ?>
					<section class="dck-card">
						<h2><?php echo esc_html( dck_setting( 'profile_credentials_heading' ) ); ?></h2>
						<div class="dck-details">
							<?php foreach ( $details as $k => $l ) :
								$v = DCK_Fields::get( $post_id, $k );
								if ( ! $v ) { continue; } ?>
								<div><small><?php echo esc_html( $l ); ?></small><b><?php echo esc_html( $v ); ?></b></div>
							<?php endforeach; ?>
						</div>
					</section>
					<?php endif; ?>

					<?php if ( $premium && $faq ) : ?>
					<section class="dck-card">
						<h2><?php echo esc_html( dck_setting( 'profile_faq_heading' ) ); ?></h2>
						<?php foreach ( $faq as $f ) : ?>
						<details class="dck-faq">
							<summary><?php echo esc_html( $f['q'] ); ?></summary>
							<p><?php echo esc_html( $f['a'] ); ?></p>
						</details>
						<?php endforeach; ?>
					</section>
					<?php endif; ?>

				</div>

				<aside class="dck-side">
					<section class="dck-card dck-cta-card">
						<h2><?php echo esc_html( dck_setting( 'profile_quote_heading' ) ); ?></h2>
						<?php $resp = $premium ? DCK_Fields::get( $post_id, 'response_time' ) : ''; ?>
						<?php if ( $resp ) : ?><div class="dck-responds"><span class="dck-pulse"></span><?php echo esc_html( sprintf( __( 'Typically responds within %s', 'dck-directory' ), $resp ) ); ?></div><?php endif; ?>
						<form class="dck-quote" data-dck-lead>
							<input type="hidden" name="listing" value="<?php echo (int) $post_id; ?>">
							<div><label><?php esc_html_e( 'Your name', 'dck-directory' ); ?></label><input type="text" name="name" required></div>
							<div><label><?php esc_html_e( 'Phone', 'dck-directory' ); ?></label><input type="tel" name="phone" required></div>
							<div><label><?php esc_html_e( 'Email', 'dck-directory' ); ?></label><input type="email" name="email"></div>
							<div><label><?php esc_html_e( 'Project details', 'dck-directory' ); ?></label><textarea name="message" placeholder="<?php esc_attr_e( 'Approx. size, timeline, anything else…', 'dck-directory' ); ?>"></textarea></div>
							<button class="dck-btn" type="submit"><?php echo esc_html( dck_setting( 'profile_quote_button' ) ); ?></button>
							<p class="dck-form-msg" role="status" aria-live="polite"></p>
						</form>
						<p class="dck-fine"><?php esc_html_e( 'Free • No obligation', 'dck-directory' ); ?></p>
					</section>

					<section class="dck-card">
						<?php if ( $phone ) : ?>
						<a class="dck-btn dck-btn--ghost" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( sprintf( __( 'Call %s', 'dck-directory' ), $phone ) ); ?></a>
						<?php endif; ?>
						<?php if ( $address || $city ) : ?>
						<div class="dck-contact-row">
							<svg viewBox="0 0 24 24" width="17" height="17"><path d="M12 2C7.6 2 4 5.6 4 10c0 5.3 6.4 11.4 7.2 12.1.4.4 1.2.4 1.6 0C13.6 21.4 20 15.3 20 10c0-4.4-3.6-8-8-8zm0 10.5A2.5 2.5 0 1 1 12 7a2.5 2.5 0 0 1 0 5.5z"/></svg>
							<span><b><?php echo esc_html( $address ); ?></b><br><span class="dck-muted"><?php echo esc_html( trim( $city . ( $city && $state ? ', ' : '' ) . $state . ' ' . $zip ) ); ?></span></span>
						</div>
						<?php endif; ?>
						<?php $website = $premium ? DCK_Fields::get( $post_id, 'website' ) : ''; ?>
						<?php if ( $website ) : ?>
						<div class="dck-contact-row">
							<svg viewBox="0 0 24 24" width="17" height="17"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.9 9h-3a15.6 15.6 0 0 0-1.8-6.3A8 8 0 0 1 19.9 11zM12 4c.9 1.2 2 3.6 2.4 7H9.6C10 7.6 11.1 5.2 12 4zM4.1 13h3a15.6 15.6 0 0 0 1.8 6.3A8 8 0 0 1 4.1 13zm3-2h-3a8 8 0 0 1 4.8-6.3A15.6 15.6 0 0 0 7.1 11zM12 20c-.9-1.2-2-3.6-2.4-7h4.8c-.4 3.4-1.5 5.8-2.4 7z"/></svg>
							<a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="nofollow noopener"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', $website ) ); ?></a>
						</div>
						<?php endif; ?>
						<?php
						$socials = array();
						foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube' ) as $sk => $sl ) {
							$sv = $premium ? DCK_Fields::get( $post_id, $sk ) : '';
							if ( $sv ) {
								$socials[] = '<a href="' . esc_url( $sv ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $sl ) . '</a>';
							}
						}
						if ( $socials ) {
							echo '<div class="dck-socials">' . implode( '', $socials ) . '</div>'; // phpcs:ignore
						}
						?>
						<?php if ( $premium && $hours ) : ?>
						<div class="dck-contact-row dck-hours-row">
							<svg viewBox="0 0 24 24" width="17" height="17"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 10.6l4.2 2.5-.8 1.3L11 13.5V7h2v5.6z"/></svg>
							<div style="flex:1">
								<b><?php esc_html_e( 'Hours', 'dck-directory' ); ?> <span class="dck-open-pill" data-dck-hours-pill='<?php echo esc_attr( wp_json_encode( $hours ) ); ?>'></span></b>
								<table class="dck-hours-table">
									<?php
									$days = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
									foreach ( $days as $d => $label ) :
										$slot = isset( $hours[ $d ] ) ? $hours[ $d ] : null;
										$txt  = $slot ? dck_fmt_time( $slot[0] ) . ' – ' . dck_fmt_time( $slot[1] ) : __( 'Closed', 'dck-directory' ); ?>
										<tr><td><?php echo esc_html( $label ); ?></td><td><?php echo esc_html( $txt ); ?></td></tr>
									<?php endforeach; ?>
								</table>
							</div>
						</div>
						<?php endif; ?>
					</section>

					<?php if ( ! $premium ) : ?>
					<section class="dck-card dck-upsell">
						<p><strong><?php esc_html_e( 'Are you the owner?', 'dck-directory' ); ?></strong><br><?php esc_html_e( 'Upgrade to a premium listing to add photos, reviews, hours, your website, and more.', 'dck-directory' ); ?></p>
						<a class="dck-btn dck-btn--ghost" href="<?php echo esc_url( dck_dashboard_url() ); ?>"><?php esc_html_e( 'Manage this listing', 'dck-directory' ); ?></a>
					</section>
					<?php endif; ?>
				</aside>
			</div>
		</div>

		<div class="dck-callbar">
			<?php if ( $phone ) : ?><a class="dck-btn dck-btn--ghost" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" style="flex:1"><?php esc_html_e( 'Call', 'dck-directory' ); ?></a><?php endif; ?>
			<a class="dck-btn" href="#dck-reviews" style="flex:2"><?php esc_html_e( 'Get a free quote', 'dck-directory' ); ?></a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Format a 24h HH:MM string to a friendly 12h time.
 */
function dck_fmt_time( $hhmm ) {
	$parts = explode( ':', $hhmm );
	if ( count( $parts ) < 2 ) {
		return $hhmm;
	}
	$h  = (int) $parts[0];
	$m  = (int) $parts[1];
	$ap = $h >= 12 ? 'PM' : 'AM';
	$h  = $h % 12;
	if ( 0 === $h ) {
		$h = 12;
	}
	return $h . ( $m ? ':' . str_pad( $m, 2, '0', STR_PAD_LEFT ) : '' ) . ' ' . $ap;
}

/**
 * Build a schema.org LocalBusiness (GeneralContractor) structured-data array
 * from everything the contractor has filled in. Premium-only fields are read
 * through DCK_Fields::get(), which returns '' when the tier hasn't unlocked
 * them — so a free listing only emits the data it actually has (NAP, categories,
 * logo, description), while a premium listing adds hours, reviews, website,
 * socials, gallery, and services.
 *
 * @param int $post_id Contractor listing ID.
 * @return array JSON-LD-ready associative array.
 */
function dck_build_local_business_schema( $post_id ) {
	$premium = DCK_Fields::is_premium( $post_id );
	$name    = get_the_title( $post_id );
	$permalink = get_permalink( $post_id );

	$phone   = get_post_meta( $post_id, '_dck_phone', true );
	$addr    = get_post_meta( $post_id, '_dck_address', true );
	$city    = get_post_meta( $post_id, '_dck_city', true );
	$state   = get_post_meta( $post_id, '_dck_state', true );
	$zip     = get_post_meta( $post_id, '_dck_zip', true );
	$website = DCK_Fields::get( $post_id, 'website' );
	$email   = DCK_Fields::get( $post_id, 'email' );

	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => 'GeneralContractor',
		'@id'      => $permalink . '#business',
		'name'     => $name,
		'url'      => $website ? $website : $permalink,
	);

	$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) ) );
	if ( '' !== $desc ) {
		$schema['description'] = ( function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, 500 ) : substr( $desc, 0, 500 ) );
	}

	if ( $phone ) {
		$schema['telephone'] = $phone;
	}
	if ( $email ) {
		$schema['email'] = $email;
	}

	if ( $addr || $city || $state || $zip ) {
		$schema['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $addr,
				'addressLocality' => $city,
				'addressRegion'   => $state,
				'postalCode'      => $zip,
				'addressCountry'  => 'US',
			),
			static function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);
	}

	// Images: logo/featured first, then up to 6 gallery photos (premium).
	$images = array();
	$thumb  = get_the_post_thumbnail_url( $post_id, 'large' );
	if ( $thumb ) {
		$images[] = $thumb;
	}
	if ( $premium ) {
		$gallery = array_filter( array_map( 'absint', explode( ',', (string) DCK_Fields::get( $post_id, 'gallery' ) ) ) );
		foreach ( array_slice( $gallery, 0, 6 ) as $gid ) {
			$u = wp_get_attachment_image_url( $gid, 'large' );
			if ( $u ) {
				$images[] = $u;
			}
		}
	}
	if ( $images ) {
		$schema['image'] = array_values( array_unique( $images ) );
	}

	// Areas served: the premium "cities served" list, else the base city/state.
	$areas_served = array();
	$sa           = DCK_Fields::get( $post_id, 'service_area' );
	if ( $sa ) {
		$areas_served = array_values( array_filter( array_map( 'trim', explode( ',', $sa ) ) ) );
	} elseif ( $city || $state ) {
		$areas_served[] = trim( $city . ( $city && $state ? ', ' : '' ) . $state );
	}
	if ( $areas_served ) {
		$schema['areaServed'] = $areas_served;
	}

	// Social profiles (premium).
	$same_as = array();
	foreach ( array( 'facebook', 'instagram', 'youtube' ) as $sk ) {
		$sv = DCK_Fields::get( $post_id, $sk );
		if ( $sv ) {
			$same_as[] = $sv;
		}
	}
	if ( $same_as ) {
		$schema['sameAs'] = $same_as;
	}

	// Business hours (premium) → OpeningHoursSpecification.
	$hours = DCK_Fields::get_json( $post_id, 'hours' );
	if ( $hours ) {
		$days = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		$spec = array();
		foreach ( $days as $i => $dname ) {
			$slot = isset( $hours[ $i ] ) ? $hours[ $i ] : null;
			if ( is_array( $slot ) && ! empty( $slot[0] ) && ! empty( $slot[1] ) ) {
				$spec[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $dname,
					'opens'     => $slot[0],
					'closes'    => $slot[1],
				);
			}
		}
		if ( $spec ) {
			$schema['openingHoursSpecification'] = $spec;
		}
	}

	// Services offered → makesOffer (coating systems + service areas + list).
	$offer_names = array();
	foreach ( array( DCK_Post_Types::TAX_SERVICE, DCK_Post_Types::TAX_AREA ) as $tax ) {
		$terms = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'names' ) );
		if ( is_array( $terms ) ) {
			$offer_names = array_merge( $offer_names, $terms );
		}
	}
	$slist = DCK_Fields::get( $post_id, 'services_list' );
	if ( $slist ) {
		$offer_names = array_merge( $offer_names, array_map( 'trim', explode( "\n", $slist ) ) );
	}
	$offer_names = array_values( array_unique( array_filter( $offer_names ) ) );
	if ( $offer_names ) {
		$offers = array();
		foreach ( $offer_names as $oname ) {
			$offers[] = array(
				'@type'       => 'Offer',
				'itemOffered' => array( '@type' => 'Service', 'name' => $oname ),
			);
		}
		$schema['makesOffer'] = $offers;
	}

	// Ratings + reviews (premium).
	$reviews = DCK_Fields::get_json( $post_id, 'reviews' );
	if ( $reviews ) {
		$count = 0;
		$sum   = 0;
		$out   = array();
		foreach ( $reviews as $r ) {
			$rt = isset( $r['rating'] ) ? (int) $r['rating'] : 0;
			if ( $rt < 1 ) {
				continue;
			}
			$count++;
			$sum += $rt;
			$rev = array(
				'@type'        => 'Review',
				'reviewRating' => array( '@type' => 'Rating', 'ratingValue' => $rt, 'bestRating' => 5 ),
			);
			if ( ! empty( $r['name'] ) ) {
				$rev['author'] = array( '@type' => 'Person', 'name' => $r['name'] );
			}
			if ( ! empty( $r['text'] ) ) {
				$rev['reviewBody'] = $r['text'];
			}
			if ( ! empty( $r['date'] ) ) {
				$ts = strtotime( $r['date'] );
				if ( $ts ) {
					$rev['datePublished'] = gmdate( 'Y-m-d', $ts );
				}
			}
			$out[] = $rev;
		}
		if ( $count ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $sum / $count, 1 ),
				'reviewCount' => $count,
				'bestRating'  => 5,
			);
			$schema['review'] = array_slice( $out, 0, 10 );
		}
	}

	/**
	 * Filter the generated LocalBusiness schema before output.
	 *
	 * @param array $schema  The schema array.
	 * @param int   $post_id Listing ID.
	 */
	return apply_filters( 'dck_local_business_schema', $schema, $post_id );
}

/**
 * Print the LocalBusiness JSON-LD in <head> on a single contractor profile.
 * Output-only in the head, so it never touches the visible profile DOM.
 */
function dck_print_profile_schema() {
	if ( ! is_singular( DCK_Post_Types::POST_TYPE ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}
	$schema = dck_build_local_business_schema( $post_id );
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
