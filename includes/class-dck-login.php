<?php
/**
 * Sitewide login modal. Renders a styled login popup in the footer on every
 * front-end page; the JS (in dck-directory.js) intercepts links to
 * wp-login.php (except action= links) and opens it in place, and auto-opens
 * it when the URL has ?login=1.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Login {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_footer', array( $this, 'render' ) );
	}

	public function render() {
		if ( is_admin() ) {
			return;
		}
		// Logged-in users don't need the login popup.
		if ( is_user_logged_in() ) {
			return;
		}

		// After login, send contractors to their dashboard.
		$redirect = dck_dashboard_url();
		?>
		<div class="dck-login-modal" id="dck-login" aria-hidden="true">
			<div class="dck-login-card" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Log in', 'dck-directory' ); ?>">
				<button type="button" class="dck-login-close" aria-label="<?php esc_attr_e( 'Close', 'dck-directory' ); ?>">&times;</button>
				<h2><?php esc_html_e( 'Log in', 'dck-directory' ); ?></h2>
				<form method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" class="dck-login-form">
					<label><?php esc_html_e( 'Email or username', 'dck-directory' ); ?>
						<input type="text" name="log" autocomplete="username" required>
					</label>
					<label><?php esc_html_e( 'Password', 'dck-directory' ); ?>
						<input type="password" name="pwd" autocomplete="current-password" required>
					</label>
					<label class="dck-login-remember"><input type="checkbox" name="rememberme" value="forever"> <?php esc_html_e( 'Remember me', 'dck-directory' ); ?></label>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect ); ?>">
					<button class="dck-btn" type="submit"><?php esc_html_e( 'Log in', 'dck-directory' ); ?></button>
				</form>
				<p class="dck-login-links">
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'dck-directory' ); ?></a>
					<span class="dck-dot">&bull;</span>
					<a href="<?php echo esc_url( dck_signup_url() ); ?>"><?php esc_html_e( 'Add your listing', 'dck-directory' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}
}
