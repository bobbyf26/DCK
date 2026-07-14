<?php
/**
 * Front-end shortcodes: directory landing/search, signup, owner dashboard.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Shortcodes {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'dck_directory', array( $this, 'directory' ) );
		add_shortcode( 'dck_signup', array( $this, 'signup' ) );
		add_shortcode( 'dck_dashboard', array( $this, 'dashboard' ) );
		// Handle front-end form posts early.
		add_action( 'template_redirect', array( $this, 'handle_posts' ) );
	}

	/* ==================================================================
	 * LANDING / SEARCH
	 * ================================================================== */

	public function directory( $atts ) {
		dck_remember_page( 'directory' );
		$services = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_SERVICE, 'hide_empty' => false ) );
		$areas    = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_AREA, 'hide_empty' => false ) );
		$states   = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_LOCATION, 'parent' => 0, 'hide_empty' => false ) );

		// Pre-selected from query string (e.g. category tile links).
		$sel_service  = isset( $_GET['service'] ) ? sanitize_title( wp_unslash( $_GET['service'] ) ) : '';
		$sel_area     = isset( $_GET['area'] ) ? sanitize_title( wp_unslash( $_GET['area'] ) ) : '';
		$sel_location = isset( $_GET['location'] ) ? sanitize_title( wp_unslash( $_GET['location'] ) ) : '';

		ob_start();
		?>
		<div class="dck-directory-page" data-dck-directory>
			<div class="dck-wrap">
				<div class="dck-hero">
					<h1><?php echo esc_html( dck_setting( 'hero_title' ) ); ?></h1>
					<p><?php echo esc_html( dck_setting( 'hero_subtitle' ) ); ?></p>
					<form class="dck-searchbar" data-dck-search>
						<div class="dck-field">
							<label><?php esc_html_e( 'Coating system', 'dck-directory' ); ?></label>
							<select name="service" data-search-service>
								<option value=""><?php esc_html_e( 'All systems', 'dck-directory' ); ?></option>
								<?php foreach ( $services as $t ) : ?>
									<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $sel_service, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="dck-field">
							<label><?php esc_html_e( 'Area', 'dck-directory' ); ?></label>
							<select name="area" data-search-area>
								<option value=""><?php esc_html_e( 'All areas', 'dck-directory' ); ?></option>
								<?php foreach ( $areas as $t ) : ?>
									<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $sel_area, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="dck-field">
							<label><?php esc_html_e( 'Where', 'dck-directory' ); ?></label>
							<select name="location" data-search-location>
								<option value=""><?php esc_html_e( 'All states', 'dck-directory' ); ?></option>
								<?php foreach ( $states as $t ) : ?>
									<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $sel_location, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="dck-field dck-field--grow">
							<label><?php esc_html_e( 'Keyword / city', 'dck-directory' ); ?></label>
							<input type="text" name="keyword" data-search-keyword placeholder="<?php esc_attr_e( 'e.g. patio, city name…', 'dck-directory' ); ?>">
						</div>
						<button type="submit" class="dck-btn"><?php echo esc_html( dck_setting( 'search_button' ) ); ?></button>
					</form>
				</div>

				<?php if ( ! empty( $services ) && ! is_wp_error( $services ) ) : ?>
				<section class="dck-tiles" aria-label="<?php echo esc_attr( dck_setting( 'systems_heading' ) ); ?>">
					<h2><?php echo esc_html( dck_setting( 'systems_heading' ) ); ?></h2>
					<div class="dck-tiles__grid">
						<?php foreach ( $services as $t ) : ?>
							<button type="button" class="dck-tile" data-service="<?php echo esc_attr( $t->slug ); ?>">
								<span class="dck-tile__name"><?php echo esc_html( $t->name ); ?></span>
								<span class="dck-tile__count"><?php echo esc_html( sprintf( _n( '%d pro', '%d pros', $t->count, 'dck-directory' ), $t->count ) ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

				<section class="dck-results-wrap">
					<div class="dck-results-head">
						<h2 data-results-title><?php echo esc_html( dck_setting( 'results_heading' ) ); ?></h2>
						<span data-results-count></span>
					</div>
					<div class="dck-results" data-results aria-live="polite"></div>
					<div class="dck-results-empty" data-results-empty hidden><?php esc_html_e( 'No contractors match your search yet. Try widening your filters.', 'dck-directory' ); ?></div>
					<div class="dck-loadmore-wrap"><button type="button" class="dck-btn dck-btn--ghost" data-loadmore hidden><?php esc_html_e( 'Load more', 'dck-directory' ); ?></button></div>
				</section>

				<?php if ( ! empty( $states ) && ! is_wp_error( $states ) ) : ?>
				<section class="dck-states">
					<h2><?php echo esc_html( dck_setting( 'states_heading' ) ); ?></h2>
					<div class="dck-states__grid">
						<?php foreach ( $states as $t ) : ?>
							<a href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?> <span>(<?php echo (int) $t->count; ?>)</span></a>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

				<div class="dck-cta-strip">
					<div>
						<strong><?php echo esc_html( dck_setting( 'cta_title' ) ); ?></strong>
						<span><?php echo esc_html( dck_setting( 'cta_subtitle' ) ); ?></span>
					</div>
					<a class="dck-btn" href="<?php echo esc_url( dck_signup_url() ); ?>"><?php echo esc_html( dck_setting( 'cta_button' ) ); ?></a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ==================================================================
	 * SIGNUP  (creates user + a pending free listing)
	 * ================================================================== */

	public function signup( $atts ) {
		dck_remember_page( 'signup' );
		if ( is_user_logged_in() ) {
			return '<div class="dck-notice">' . sprintf(
				/* translators: %s dashboard url */
				wp_kses_post( __( 'You are logged in. <a href="%s">Manage your listing</a>.', 'dck-directory' ) ),
				esc_url( dck_dashboard_url() )
			) . '</div>';
		}

		$services = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_SERVICE, 'hide_empty' => false ) );
		$areas    = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_AREA, 'hide_empty' => false ) );
		$notice   = $this->flash();

		ob_start();
		?>
		<div class="dck-form-page">
			<div class="dck-wrap dck-narrow">
				<h1><?php echo esc_html( dck_setting( 'signup_title' ) ); ?></h1>
				<p class="dck-muted"><?php echo esc_html( dck_setting( 'signup_intro' ) ); ?></p>
				<?php echo $notice; // phpcs:ignore ?>
				<form method="post" class="dck-stack">
					<?php wp_nonce_field( 'dck_signup', 'dck_signup_nonce' ); ?>
					<input type="hidden" name="dck_action" value="signup">
					<div class="dck-grid2">
						<label><?php echo esc_html( dck_setting( 'label_business' ) ); ?><input type="text" name="business" required></label>
						<label><?php echo esc_html( dck_setting( 'label_fullname' ) ); ?><input type="text" name="fullname" required></label>
						<label><?php echo esc_html( dck_setting( 'label_email' ) ); ?><input type="email" name="email" required></label>
						<label><?php echo esc_html( dck_setting( 'label_password' ) ); ?><input type="password" name="password" required minlength="8"></label>
						<label><?php echo esc_html( dck_setting( 'label_phone' ) ); ?><input type="tel" name="phone" required></label>
						<?php if ( dck_setting_on( 'show_address' ) ) : ?><label><?php echo esc_html( dck_setting( 'label_address' ) ); ?><input type="text" name="address"></label><?php endif; ?>
						<label><?php echo esc_html( dck_setting( 'label_city' ) ); ?><input type="text" name="city" required></label>
						<label><?php echo esc_html( dck_setting( 'label_state' ) ); ?><input type="text" name="state" required></label>
						<?php if ( dck_setting_on( 'show_zip' ) ) : ?><label><?php echo esc_html( dck_setting( 'label_zip' ) ); ?><input type="text" name="zip"></label><?php endif; ?>
					</div>
					<label><?php echo esc_html( dck_setting( 'signup_systems_label' ) ); ?></label>
					<div class="dck-checks">
						<?php foreach ( $services as $t ) : ?>
							<label class="dck-check"><input type="checkbox" name="services[]" value="<?php echo (int) $t->term_id; ?>"> <?php echo esc_html( $t->name ); ?></label>
						<?php endforeach; ?>
					</div>
					<label><?php echo esc_html( dck_setting( 'signup_areas_label' ) ); ?></label>
					<div class="dck-checks">
						<?php foreach ( $areas as $t ) : ?>
							<label class="dck-check"><input type="checkbox" name="areas[]" value="<?php echo (int) $t->term_id; ?>"> <?php echo esc_html( $t->name ); ?></label>
						<?php endforeach; ?>
					</div>
					<label class="dck-check"><input type="checkbox" name="terms" required> <?php echo esc_html( dck_setting( 'terms_text' ) ); ?></label>
					<button class="dck-btn" type="submit"><?php echo esc_html( dck_setting( 'signup_button' ) ); ?></button>
					<p class="dck-muted dck-small"><?php printf( wp_kses_post( __( 'Already registered? <a href="%s">Log in</a>.', 'dck-directory' ) ), esc_url( wp_login_url( dck_dashboard_url() ) ) ); ?></p>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ==================================================================
	 * DASHBOARD  (owner edits their listing)
	 * ================================================================== */

	public function dashboard( $atts ) {
		dck_remember_page( 'dashboard' );
		if ( ! is_user_logged_in() ) {
			return '<div class="dck-form-page"><div class="dck-wrap dck-narrow"><h1>' . esc_html__( 'Manage your listing', 'dck-directory' ) . '</h1><p>' . sprintf(
				wp_kses_post( __( 'Please <a href="%1$s">log in</a> or <a href="%2$s">create a free listing</a>.', 'dck-directory' ) ),
				esc_url( wp_login_url( dck_dashboard_url() ) ),
				esc_url( dck_signup_url() )
			) . '</p></div></div>';
		}

		$user     = wp_get_current_user();
		$listings = get_posts(
			array(
				'post_type'      => DCK_Post_Types::POST_TYPE,
				'author'         => $user->ID,
				'post_status'    => array( 'publish', 'pending', 'draft' ),
				'posts_per_page' => -1,
			)
		);

		if ( empty( $listings ) ) {
			return '<div class="dck-form-page"><div class="dck-wrap dck-narrow"><h1>' . esc_html__( 'No listing yet', 'dck-directory' ) . '</h1><p>' . sprintf(
				wp_kses_post( __( '<a href="%s">Create your free listing</a> to get started.', 'dck-directory' ) ),
				esc_url( dck_signup_url() )
			) . '</p></div></div>';
		}

		$current_id = isset( $_GET['listing'] ) ? absint( $_GET['listing'] ) : $listings[0]->ID;
		$listing    = get_post( $current_id );
		if ( ! $listing || (int) $listing->post_author !== (int) $user->ID ) {
			$listing = $listings[0];
		}
		$post_id = $listing->ID;
		$premium = DCK_Fields::is_premium( $post_id );
		$schema  = DCK_Fields::schema();
		$notice  = $this->flash();

		ob_start();
		?>
		<div class="dck-form-page dck-dashboard">
			<div class="dck-wrap">
				<div class="dck-dash-head">
					<h1><?php echo esc_html( dck_setting( 'dash_title' ) ); ?></h1>
					<div class="dck-dash-status">
						<?php if ( 'publish' === $listing->post_status ) : ?>
							<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank"><?php esc_html_e( 'View live profile ↗', 'dck-directory' ); ?></a>
						<?php else : ?>
							<span class="dck-pill-pending"><?php esc_html_e( 'Pending review', 'dck-directory' ); ?></span>
						<?php endif; ?>
						<span class="dck-plan-badge dck-plan-<?php echo esc_attr( $premium ? 'premium' : 'free' ); ?>"><?php echo esc_html( $premium ? __( 'Premium', 'dck-directory' ) : __( 'Free plan', 'dck-directory' ) ); ?></span>
					</div>
				</div>

				<?php if ( count( $listings ) > 1 ) : ?>
				<div class="dck-listing-switch">
					<?php foreach ( $listings as $l ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'listing', $l->ID ) ); ?>" class="<?php echo $l->ID === $post_id ? 'active' : ''; ?>"><?php echo esc_html( $l->post_title ); ?></a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php echo $notice; // phpcs:ignore ?>

				<?php if ( ! $premium ) : ?>
				<div class="dck-upgrade-banner">
					<div><strong><?php esc_html_e( 'You are on the free plan.', 'dck-directory' ); ?></strong> <?php echo esc_html( dck_setting( 'dash_upgrade_text' ) ); ?></div>
					<form method="post" style="margin:0">
						<?php wp_nonce_field( 'dck_upgrade', 'dck_upgrade_nonce' ); ?>
						<input type="hidden" name="dck_action" value="request_upgrade">
						<input type="hidden" name="listing" value="<?php echo (int) $post_id; ?>">
						<button class="dck-btn" type="submit"><?php esc_html_e( 'Request upgrade', 'dck-directory' ); ?></button>
					</form>
				</div>
				<?php endif; ?>

				<form method="post" enctype="multipart/form-data" class="dck-stack">
					<?php wp_nonce_field( 'dck_save_listing_' . $post_id, 'dck_dash_nonce' ); ?>
					<input type="hidden" name="dck_action" value="save_listing">
					<input type="hidden" name="listing" value="<?php echo (int) $post_id; ?>">

					<section class="dck-card">
						<h2><?php esc_html_e( 'Basics', 'dck-directory' ); ?></h2>
						<div class="dck-grid2">
							<label><?php esc_html_e( 'Business name', 'dck-directory' ); ?><input type="text" name="business" value="<?php echo esc_attr( $listing->post_title ); ?>" required></label>
							<label><?php esc_html_e( 'Phone', 'dck-directory' ); ?><input type="text" name="dck[phone]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_phone', true ) ); ?>"></label>
							<label><?php esc_html_e( 'Street address', 'dck-directory' ); ?><input type="text" name="dck[address]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_address', true ) ); ?>"></label>
							<label><?php esc_html_e( 'City', 'dck-directory' ); ?><input type="text" name="dck[city]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_city', true ) ); ?>"></label>
							<label><?php esc_html_e( 'State', 'dck-directory' ); ?><input type="text" name="dck[state]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_state', true ) ); ?>"></label>
							<label><?php esc_html_e( 'ZIP', 'dck-directory' ); ?><input type="text" name="dck[zip]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_zip', true ) ); ?>"></label>
						</div>
						<label><?php esc_html_e( 'About your business', 'dck-directory' ); ?>
							<textarea name="about" rows="5"><?php echo esc_textarea( $listing->post_content ); ?></textarea>
						</label>
						<label><?php esc_html_e( 'Coating systems you offer', 'dck-directory' ); ?></label>
						<div class="dck-checks">
							<?php
							$assigned_svc = wp_get_post_terms( $post_id, DCK_Post_Types::TAX_SERVICE, array( 'fields' => 'ids' ) );
							$all_svc      = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_SERVICE, 'hide_empty' => false ) );
							foreach ( $all_svc as $t ) : ?>
								<label class="dck-check"><input type="checkbox" name="services[]" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( $t->term_id, $assigned_svc, true ) ); ?>> <?php echo esc_html( $t->name ); ?></label>
							<?php endforeach; ?>
						</div>
						<label><?php esc_html_e( 'Service areas / applications', 'dck-directory' ); ?></label>
						<div class="dck-checks">
							<?php
							$assigned_area = wp_get_post_terms( $post_id, DCK_Post_Types::TAX_AREA, array( 'fields' => 'ids' ) );
							$all_area      = get_terms( array( 'taxonomy' => DCK_Post_Types::TAX_AREA, 'hide_empty' => false ) );
							foreach ( $all_area as $t ) : ?>
								<label class="dck-check"><input type="checkbox" name="areas[]" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( $t->term_id, $assigned_area, true ) ); ?>> <?php echo esc_html( $t->name ); ?></label>
							<?php endforeach; ?>
						</div>
						<label><?php esc_html_e( 'Logo / cover image', 'dck-directory' ); ?><input type="file" name="logo" accept="image/*"></label>
						<?php if ( has_post_thumbnail( $post_id ) ) : ?><div class="dck-current-logo"><?php echo get_the_post_thumbnail( $post_id, 'thumbnail' ); // phpcs:ignore ?></div><?php endif; ?>
					</section>

					<section class="dck-card dck-premium-section<?php echo $premium ? '' : ' dck-locked'; ?>">
						<h2><?php esc_html_e( 'Premium details', 'dck-directory' ); ?> <?php if ( ! $premium ) : ?><span class="dck-lock-tag">🔒 <?php esc_html_e( 'Premium', 'dck-directory' ); ?></span><?php endif; ?></h2>
						<?php if ( ! $premium ) : ?><p class="dck-locked-note"><?php esc_html_e( 'Upgrade to edit these fields. They are shown here so you can see what premium includes.', 'dck-directory' ); ?></p><?php endif; ?>
						<fieldset <?php disabled( ! $premium ); ?>>
							<div class="dck-grid2">
								<label><?php esc_html_e( 'Website URL', 'dck-directory' ); ?><input type="url" name="dck[website]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_website', true ) ); ?>"></label>
								<label><?php esc_html_e( 'Public email', 'dck-directory' ); ?><input type="email" name="dck[email]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_email', true ) ); ?>"></label>
								<label><?php esc_html_e( 'Facebook', 'dck-directory' ); ?><input type="url" name="dck[facebook]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_facebook', true ) ); ?>"></label>
								<label><?php esc_html_e( 'Instagram', 'dck-directory' ); ?><input type="url" name="dck[instagram]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_instagram', true ) ); ?>"></label>
								<label><?php esc_html_e( 'Response time', 'dck-directory' ); ?><input type="text" name="dck[response_time]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_response_time', true ) ); ?>" placeholder="1 hour"></label>
								<label><?php esc_html_e( 'Cities served (comma separated)', 'dck-directory' ); ?><input type="text" name="dck[service_area]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_service_area', true ) ); ?>"></label>
								<label><?php esc_html_e( 'License #', 'dck-directory' ); ?><input type="text" name="dck[license]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_license', true ) ); ?>"></label>
								<label><?php esc_html_e( 'Insurance', 'dck-directory' ); ?><input type="text" name="dck[insurance]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_insurance', true ) ); ?>"></label>
								<label><?php esc_html_e( 'Year founded', 'dck-directory' ); ?><input type="text" name="dck[year_founded]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_year_founded', true ) ); ?>"></label>
								<label><?php esc_html_e( 'Warranty', 'dck-directory' ); ?><input type="text" name="dck[warranty]" value="<?php echo esc_attr( get_post_meta( $post_id, '_dck_warranty', true ) ); ?>"></label>
							</div>
							<label><?php esc_html_e( 'Services offered (one per line)', 'dck-directory' ); ?><textarea name="dck[services_list]" rows="4"><?php echo esc_textarea( get_post_meta( $post_id, '_dck_services_list', true ) ); ?></textarea></label>
							<label><?php esc_html_e( 'Add photos to gallery', 'dck-directory' ); ?><input type="file" name="gallery[]" accept="image/*" multiple></label>
						</fieldset>
					</section>

					<div class="dck-save-bar">
						<button class="dck-btn" type="submit"><?php echo esc_html( dck_setting( 'dash_save_button' ) ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ==================================================================
	 * POST HANDLERS
	 * ================================================================== */

	public function handle_posts() {
		if ( ! isset( $_POST['dck_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['dck_action'] ) );

		if ( 'signup' === $action ) {
			$this->do_signup();
		} elseif ( 'save_listing' === $action ) {
			$this->do_save_listing();
		} elseif ( 'request_upgrade' === $action ) {
			$this->do_request_upgrade();
		}
	}

	private function do_signup() {
		if ( ! isset( $_POST['dck_signup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dck_signup_nonce'] ) ), 'dck_signup' ) ) {
			return;
		}
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$pass     = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$business = isset( $_POST['business'] ) ? sanitize_text_field( wp_unslash( $_POST['business'] ) ) : '';

		if ( ! is_email( $email ) || strlen( $pass ) < 8 || ! $business ) {
			$this->set_flash( __( 'Please complete all required fields with a valid email and 8+ character password.', 'dck-directory' ), 'error' );
			return;
		}
		if ( email_exists( $email ) ) {
			$this->set_flash( sprintf( __( 'That email is already registered. Please %s log in.', 'dck-directory' ), '' ), 'error' );
			return;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => isset( $_POST['fullname'] ) ? sanitize_text_field( wp_unslash( $_POST['fullname'] ) ) : $business,
				'role'         => 'dck_contractor',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			$this->set_flash( $user_id->get_error_message(), 'error' );
			return;
		}

		// Create the pending free listing.
		$post_id = wp_insert_post(
			array(
				'post_type'    => DCK_Post_Types::POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => $business,
				'post_author'  => $user_id,
			)
		);
		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_dck_tier', 'free' );
			update_post_meta( $post_id, '_dck_featured', '' );
			$fields = array( 'phone', 'address', 'city', 'state', 'zip' );
			foreach ( $fields as $f ) {
				if ( isset( $_POST[ $f ] ) ) {
					update_post_meta( $post_id, '_dck_' . $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
				}
			}
			if ( ! empty( $_POST['services'] ) && is_array( $_POST['services'] ) ) {
				wp_set_post_terms( $post_id, array_map( 'absint', wp_unslash( $_POST['services'] ) ), DCK_Post_Types::TAX_SERVICE );
			} elseif ( ! empty( $_POST['service'] ) ) {
				// Back-compat with the old single-select field.
				wp_set_post_terms( $post_id, array( absint( $_POST['service'] ) ), DCK_Post_Types::TAX_SERVICE );
			}
			if ( ! empty( $_POST['areas'] ) && is_array( $_POST['areas'] ) ) {
				wp_set_post_terms( $post_id, array_map( 'absint', wp_unslash( $_POST['areas'] ) ), DCK_Post_Types::TAX_AREA );
			}
			$this->assign_location( $post_id, isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '', isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '' );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( add_query_arg( 'dck_msg', 'created', dck_dashboard_url() ) );
		exit;
	}

	private function do_save_listing() {
		$post_id = isset( $_POST['listing'] ) ? absint( $_POST['listing'] ) : 0;
		if ( ! $post_id || ! isset( $_POST['dck_dash_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dck_dash_nonce'] ) ), 'dck_save_listing_' . $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || (int) $post->post_author !== get_current_user_id() ) {
			$this->set_flash( __( 'You do not have permission to edit this listing.', 'dck-directory' ), 'error' );
			return;
		}

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => isset( $_POST['business'] ) ? sanitize_text_field( wp_unslash( $_POST['business'] ) ) : $post->post_title,
				'post_content' => isset( $_POST['about'] ) ? wp_kses_post( wp_unslash( $_POST['about'] ) ) : $post->post_content,
			)
		);

		// Field values (premium fields only saved when the listing is premium).
		$input = isset( $_POST['dck'] ) ? wp_unslash( $_POST['dck'] ) : array();
		DCK_Fields::save( $post_id, $input );

		// Coating systems + service areas. Send empty array to clear if none checked.
		$svc = ( isset( $_POST['services'] ) && is_array( $_POST['services'] ) ) ? array_map( 'absint', wp_unslash( $_POST['services'] ) ) : array();
		wp_set_post_terms( $post_id, $svc, DCK_Post_Types::TAX_SERVICE );
		$area = ( isset( $_POST['areas'] ) && is_array( $_POST['areas'] ) ) ? array_map( 'absint', wp_unslash( $_POST['areas'] ) ) : array();
		wp_set_post_terms( $post_id, $area, DCK_Post_Types::TAX_AREA );

		// Location from city/state.
		$this->assign_location(
			$post_id,
			isset( $_POST['dck']['state'] ) ? sanitize_text_field( wp_unslash( $_POST['dck']['state'] ) ) : '',
			isset( $_POST['dck']['city'] ) ? sanitize_text_field( wp_unslash( $_POST['dck']['city'] ) ) : ''
		);

		// Uploads.
		$this->handle_logo_upload( $post_id );
		if ( DCK_Fields::is_premium( $post_id ) ) {
			$this->handle_gallery_upload( $post_id );
		}

		$this->set_flash( __( 'Your listing has been saved.', 'dck-directory' ), 'success' );
		wp_safe_redirect( add_query_arg( array( 'listing' => $post_id, 'dck_msg' => 'saved' ), dck_dashboard_url() ) );
		exit;
	}

	private function do_request_upgrade() {
		$post_id = isset( $_POST['listing'] ) ? absint( $_POST['listing'] ) : 0;
		if ( ! $post_id || ! isset( $_POST['dck_upgrade_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dck_upgrade_nonce'] ) ), 'dck_upgrade' ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || (int) $post->post_author !== get_current_user_id() ) {
			return;
		}
		update_post_meta( $post_id, '_dck_upgrade_requested', current_time( 'mysql' ) );
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( __( 'Premium upgrade requested: %s', 'dck-directory' ), $post->post_title ),
			sprintf( "%s requested a premium upgrade.\n\nEdit: %s", $post->post_title, admin_url( 'post.php?action=edit&post=' . $post_id ) )
		);
		$this->set_flash( __( 'Thanks! We\'ll be in touch about upgrading your listing.', 'dck-directory' ), 'success' );
		wp_safe_redirect( add_query_arg( 'listing', $post_id, dck_dashboard_url() ) );
		exit;
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Create/assign a State (parent) -> City (child) location term pair.
	 */
	private function assign_location( $post_id, $state, $city ) {
		if ( ! $state ) {
			return;
		}
		$state_term = term_exists( $state, DCK_Post_Types::TAX_LOCATION, 0 );
		if ( ! $state_term ) {
			$state_term = wp_insert_term( $state, DCK_Post_Types::TAX_LOCATION );
		}
		if ( is_wp_error( $state_term ) ) {
			return;
		}
		$state_id = (int) $state_term['term_id'];
		$ids      = array( $state_id );

		if ( $city ) {
			$city_term = term_exists( $city, DCK_Post_Types::TAX_LOCATION, $state_id );
			if ( ! $city_term ) {
				$city_term = wp_insert_term( $city, DCK_Post_Types::TAX_LOCATION, array( 'parent' => $state_id ) );
			}
			if ( ! is_wp_error( $city_term ) ) {
				$ids[] = (int) $city_term['term_id'];
			}
		}
		wp_set_post_terms( $post_id, $ids, DCK_Post_Types::TAX_LOCATION );
	}

	private function require_media_deps() {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	private function handle_logo_upload( $post_id ) {
		if ( empty( $_FILES['logo']['name'] ) ) {
			return;
		}
		$this->require_media_deps();
		$attach_id = media_handle_upload( 'logo', $post_id );
		if ( ! is_wp_error( $attach_id ) ) {
			set_post_thumbnail( $post_id, $attach_id );
		}
	}

	private function handle_gallery_upload( $post_id ) {
		if ( empty( $_FILES['gallery']['name'][0] ) ) {
			return;
		}
		$this->require_media_deps();
		$existing = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, '_dck_gallery', true ) ) ) );
		$files    = $_FILES['gallery'];
		$count    = count( $files['name'] );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( empty( $files['name'][ $i ] ) ) {
				continue;
			}
			$_FILES['dck_single'] = array(
				'name'     => $files['name'][ $i ],
				'type'     => $files['type'][ $i ],
				'tmp_name' => $files['tmp_name'][ $i ],
				'error'    => $files['error'][ $i ],
				'size'     => $files['size'][ $i ],
			);
			$attach_id = media_handle_upload( 'dck_single', $post_id );
			if ( ! is_wp_error( $attach_id ) ) {
				$existing[] = $attach_id;
			}
		}
		update_post_meta( $post_id, '_dck_gallery', implode( ',', array_unique( $existing ) ) );
	}

	/* ------------------------------------------------------------------ */

	private function set_flash( $message, $type = 'success' ) {
		set_transient( 'dck_flash_' . get_current_user_id() . '_' . md5( $message ), array( 'm' => $message, 't' => $type ), 60 );
		// Also stash a simple key so flash() can find the latest.
		set_transient( 'dck_flash_latest_' . get_current_user_id(), array( 'm' => $message, 't' => $type ), 60 );
	}

	private function flash() {
		$data = get_transient( 'dck_flash_latest_' . get_current_user_id() );
		if ( ! $data ) {
			// Fallback: query-string message from redirects.
			if ( isset( $_GET['dck_msg'] ) ) {
				$map = array(
					'created' => __( 'Listing created and submitted for review!', 'dck-directory' ),
					'saved'   => __( 'Your listing has been saved.', 'dck-directory' ),
				);
				$key = sanitize_key( wp_unslash( $_GET['dck_msg'] ) );
				if ( isset( $map[ $key ] ) ) {
					return '<div class="dck-notice dck-notice--success">' . esc_html( $map[ $key ] ) . '</div>';
				}
			}
			return '';
		}
		delete_transient( 'dck_flash_latest_' . get_current_user_id() );
		$class = 'error' === $data['t'] ? 'dck-notice--error' : 'dck-notice--success';
		return '<div class="dck-notice ' . esc_attr( $class ) . '">' . esc_html( $data['m'] ) . '</div>';
	}
}
