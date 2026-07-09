<?php
/**
 * Admin: meta boxes, tier/featured controls, list columns.
 *
 * @package DCK_Directory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCK_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_' . DCK_Post_Types::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		$pt = DCK_Post_Types::POST_TYPE;
		add_filter( "manage_{$pt}_posts_columns", array( $this, 'columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( $this, 'column_content' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'pending_notice' ) );

		// Leads list: show contact details as columns.
		add_filter( 'manage_dck_lead_posts_columns', array( $this, 'lead_columns' ) );
		add_action( 'manage_dck_lead_posts_custom_column', array( $this, 'lead_column_content' ), 10, 2 );
	}

	public function lead_columns( $cols ) {
		return array(
			'cb'         => isset( $cols['cb'] ) ? $cols['cb'] : '',
			'title'      => __( 'Lead', 'dck-directory' ),
			'dck_phone'  => __( 'Phone', 'dck-directory' ),
			'dck_email'  => __( 'Email', 'dck-directory' ),
			'dck_msg'    => __( 'Message', 'dck-directory' ),
			'dck_for'    => __( 'For listing', 'dck-directory' ),
			'date'       => __( 'Received', 'dck-directory' ),
		);
	}

	public function lead_column_content( $col, $post_id ) {
		switch ( $col ) {
			case 'dck_phone':
				echo esc_html( get_post_meta( $post_id, '_dck_lead_phone', true ) );
				break;
			case 'dck_email':
				echo esc_html( get_post_meta( $post_id, '_dck_lead_email', true ) );
				break;
			case 'dck_msg':
				echo esc_html( wp_trim_words( get_post_meta( $post_id, '_dck_lead_message', true ), 18 ) );
				break;
			case 'dck_for':
				$lid = (int) get_post_meta( $post_id, '_dck_lead_listing', true );
				if ( $lid ) {
					echo '<a href="' . esc_url( get_edit_post_link( $lid ) ) . '">' . esc_html( get_the_title( $lid ) ) . '</a>';
				}
				break;
		}
	}

	public function assets( $hook ) {
		global $post_type;
		if ( DCK_Post_Types::POST_TYPE !== $post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'dck-admin', DCK_DIR_URL . 'assets/js/dck-admin.js', array(), DCK_DIR_VERSION, true );
		wp_enqueue_style( 'dck-admin', DCK_DIR_URL . 'assets/css/dck-admin.css', array(), DCK_DIR_VERSION );
	}

	public function add_boxes() {
		add_meta_box( 'dck_tier', __( 'Listing Plan', 'dck-directory' ), array( $this, 'box_tier' ), DCK_Post_Types::POST_TYPE, 'side', 'high' );
		add_meta_box( 'dck_details', __( 'Contractor Details', 'dck-directory' ), array( $this, 'box_details' ), DCK_Post_Types::POST_TYPE, 'normal', 'high' );
	}

	/* ------------------------------------------------------------------ */

	public function box_tier( $post ) {
		wp_nonce_field( 'dck_save_' . $post->ID, 'dck_nonce' );
		$tier     = DCK_Fields::get_tier( $post->ID );
		$featured = get_post_meta( $post->ID, '_dck_featured', true );
		$owner    = get_userdata( $post->post_author );
		?>
		<p>
			<label for="dck_tier_select"><strong><?php esc_html_e( 'Plan', 'dck-directory' ); ?></strong></label><br>
			<select name="dck_tier" id="dck_tier_select" style="width:100%">
				<option value="free" <?php selected( $tier, 'free' ); ?>><?php esc_html_e( 'Free (name, address, phone)', 'dck-directory' ); ?></option>
				<option value="premium" <?php selected( $tier, 'premium' ); ?>><?php esc_html_e( 'Premium (all features)', 'dck-directory' ); ?></option>
			</select>
		</p>
		<p>
			<label><input type="checkbox" name="dck_featured" value="1" <?php checked( $featured, '1' ); ?>>
			<?php esc_html_e( 'Featured (top of search, premium only)', 'dck-directory' ); ?></label>
		</p>
		<p style="color:#5B6B7C;font-size:12px;margin-top:12px;border-top:1px solid #eee;padding-top:10px;">
			<?php esc_html_e( 'Owner:', 'dck-directory' ); ?>
			<strong><?php echo $owner ? esc_html( $owner->display_name ) : esc_html__( 'Unassigned', 'dck-directory' ); ?></strong><br>
			<?php esc_html_e( 'Set to Premium after payment is received to unlock all fields.', 'dck-directory' ); ?>
		</p>
		<?php
	}

	public function box_details( $post ) {
		$schema = DCK_Fields::schema();
		echo '<div class="dck-admin-fields">';

		echo '<h3 class="dck-group">' . esc_html__( 'Contact — included in free listings', 'dck-directory' ) . '</h3>';
		echo '<div class="dck-grid">';
		foreach ( array( 'address', 'city', 'state', 'zip', 'phone' ) as $key ) {
			$this->render_admin_field( $post->ID, $key, $schema[ $key ] );
		}
		echo '</div>';

		echo '<h3 class="dck-group">' . esc_html__( 'Premium fields', 'dck-directory' ) . ' <span class="dck-prem">PREMIUM</span></h3>';
		echo '<div class="dck-grid">';
		foreach ( array( 'website', 'email', 'facebook', 'instagram', 'youtube', 'response_time', 'service_area', 'year_founded', 'license', 'insurance', 'crew', 'payment', 'free_estimates', 'warranty' ) as $key ) {
			$this->render_admin_field( $post->ID, $key, $schema[ $key ] );
		}
		echo '</div>';

		$this->render_admin_field( $post->ID, 'services_list', $schema['services_list'] );
		$this->render_hours( $post->ID );
		$this->render_gallery( $post->ID );
		$this->render_faq( $post->ID );
		$this->render_reviews( $post->ID );

		echo '</div>';
	}

	private function render_admin_field( $post_id, $key, $def ) {
		$val = get_post_meta( $post_id, '_dck_' . $key, true );
		$id  = 'dck_' . $key;
		if ( 'textarea' === $def['type'] ) {
			echo '<p class="dck-full"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label>';
			echo '<textarea id="' . esc_attr( $id ) . '" name="dck[' . esc_attr( $key ) . ']" rows="5">' . esc_textarea( $val ) . '</textarea></p>';
		} else {
			$type = in_array( $def['type'], array( 'url', 'email' ), true ) ? $def['type'] : 'text';
			echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label>';
			echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="dck[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '"></p>';
		}
	}

	private function render_hours( $post_id ) {
		$hours = DCK_Fields::get_json( $post_id, 'hours', false );
		$days  = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
		echo '<h3 class="dck-group">' . esc_html__( 'Business hours', 'dck-directory' ) . ' <span class="dck-prem">PREMIUM</span></h3>';
		echo '<p class="dck-hint">' . esc_html__( 'Use 24-hour HH:MM (e.g. 07:00 and 18:00). Leave blank for closed.', 'dck-directory' ) . '</p>';
		echo '<table class="dck-hours">';
		foreach ( $days as $d => $label ) {
			$open  = isset( $hours[ $d ][0] ) ? $hours[ $d ][0] : '';
			$close = isset( $hours[ $d ][1] ) ? $hours[ $d ][1] : '';
			echo '<tr><td>' . esc_html( $label ) . '</td>';
			echo '<td><input type="time" name="dck[hours][' . $d . '][open]" value="' . esc_attr( $open ) . '"></td>';
			echo '<td><input type="time" name="dck[hours][' . $d . '][close]" value="' . esc_attr( $close ) . '"></td></tr>';
		}
		echo '</table>';
	}

	private function render_gallery( $post_id ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, '_dck_gallery', true ) ) ) );
		echo '<h3 class="dck-group">' . esc_html__( 'Photo gallery', 'dck-directory' ) . ' <span class="dck-prem">PREMIUM</span></h3>';
		echo '<div class="dck-gallery-field" data-dck-gallery>';
		echo '<input type="hidden" name="dck[gallery]" value="' . esc_attr( implode( ',', $ids ) ) . '" data-gallery-input>';
		echo '<div class="dck-gallery-preview" data-gallery-preview>';
		foreach ( $ids as $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $src ) {
				echo '<span data-id="' . esc_attr( $id ) . '"><img src="' . esc_url( $src ) . '" alt=""><button type="button" class="dck-remove" aria-label="Remove">&times;</button></span>';
			}
		}
		echo '</div>';
		echo '<button type="button" class="button" data-gallery-add>' . esc_html__( 'Add / manage photos', 'dck-directory' ) . '</button>';
		echo '</div>';
	}

	private function render_faq( $post_id ) {
		$faq = DCK_Fields::get_json( $post_id, 'faq', false );
		echo '<h3 class="dck-group">' . esc_html__( 'FAQ', 'dck-directory' ) . ' <span class="dck-prem">PREMIUM</span></h3>';
		echo '<div class="dck-repeater" data-repeater="faq">';
		echo '<div class="dck-repeater-items" data-items>';
		if ( empty( $faq ) ) {
			$faq = array( array( 'q' => '', 'a' => '' ) );
		}
		foreach ( $faq as $i => $row ) {
			$this->faq_row( $i, $row );
		}
		echo '</div>';
		echo '<button type="button" class="button" data-add>' . esc_html__( '+ Add question', 'dck-directory' ) . '</button>';
		echo '<template data-template>';
		$this->faq_row( '__i__', array( 'q' => '', 'a' => '' ) );
		echo '</template>';
		echo '</div>';
	}

	private function faq_row( $i, $row ) {
		echo '<div class="dck-repeater-item">';
		echo '<input type="text" placeholder="Question" name="dck[faq][' . esc_attr( $i ) . '][q]" value="' . esc_attr( $row['q'] ) . '">';
		echo '<textarea placeholder="Answer" name="dck[faq][' . esc_attr( $i ) . '][a]" rows="2">' . esc_textarea( $row['a'] ) . '</textarea>';
		echo '<button type="button" class="button-link dck-remove-row">' . esc_html__( 'Remove', 'dck-directory' ) . '</button>';
		echo '</div>';
	}

	private function render_reviews( $post_id ) {
		$reviews = DCK_Fields::get_json( $post_id, 'reviews', false );
		echo '<h3 class="dck-group">' . esc_html__( 'Reviews / testimonials', 'dck-directory' ) . ' <span class="dck-prem">PREMIUM</span></h3>';
		echo '<div class="dck-repeater" data-repeater="reviews">';
		echo '<div class="dck-repeater-items" data-items>';
		if ( empty( $reviews ) ) {
			$reviews = array( array( 'name' => '', 'location' => '', 'date' => '', 'rating' => 5, 'text' => '', 'tag' => '', 'reply' => '' ) );
		}
		foreach ( $reviews as $i => $row ) {
			$this->review_row( $i, $row );
		}
		echo '</div>';
		echo '<button type="button" class="button" data-add>' . esc_html__( '+ Add review', 'dck-directory' ) . '</button>';
		echo '<template data-template>';
		$this->review_row( '__i__', array( 'name' => '', 'location' => '', 'date' => '', 'rating' => 5, 'text' => '', 'tag' => '', 'reply' => '' ) );
		echo '</template>';
		echo '</div>';
	}

	private function review_row( $i, $row ) {
		echo '<div class="dck-repeater-item">';
		echo '<div class="dck-grid">';
		echo '<input type="text" placeholder="Reviewer name" name="dck[reviews][' . esc_attr( $i ) . '][name]" value="' . esc_attr( $row['name'] ) . '">';
		echo '<input type="text" placeholder="Location" name="dck[reviews][' . esc_attr( $i ) . '][location]" value="' . esc_attr( $row['location'] ) . '">';
		echo '<input type="text" placeholder="Date (e.g. May 2026)" name="dck[reviews][' . esc_attr( $i ) . '][date]" value="' . esc_attr( $row['date'] ) . '">';
		echo '<select name="dck[reviews][' . esc_attr( $i ) . '][rating]">';
		for ( $r = 5; $r >= 1; $r-- ) {
			echo '<option value="' . $r . '" ' . selected( (int) $row['rating'], $r, false ) . '>' . $r . ' stars</option>';
		}
		echo '</select>';
		echo '</div>';
		echo '<textarea placeholder="Review text" name="dck[reviews][' . esc_attr( $i ) . '][text]" rows="2">' . esc_textarea( $row['text'] ) . '</textarea>';
		echo '<input type="text" placeholder="Project tag (e.g. Stamped patio)" name="dck[reviews][' . esc_attr( $i ) . '][tag]" value="' . esc_attr( $row['tag'] ) . '">';
		echo '<textarea placeholder="Owner reply (optional)" name="dck[reviews][' . esc_attr( $i ) . '][reply]" rows="2">' . esc_textarea( $row['reply'] ) . '</textarea>';
		echo '<button type="button" class="button-link dck-remove-row">' . esc_html__( 'Remove', 'dck-directory' ) . '</button>';
		echo '</div>';
	}

	/* ------------------------------------------------------------------ */

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['dck_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dck_nonce'] ) ), 'dck_save_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Tier + featured (admin only).
		$tier = ( isset( $_POST['dck_tier'] ) && 'premium' === $_POST['dck_tier'] ) ? 'premium' : 'free';
		update_post_meta( $post_id, '_dck_tier', $tier );
		update_post_meta( $post_id, '_dck_featured', isset( $_POST['dck_featured'] ) ? '1' : '' );

		// All other fields. Admin may write premium fields regardless of tier.
		$input = isset( $_POST['dck'] ) ? wp_unslash( $_POST['dck'] ) : array();
		DCK_Fields::save( $post_id, $input, true );
	}

	/* ------------------------------------------------------------------ */

	public function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['dck_tier']  = __( 'Plan', 'dck-directory' );
				$new['dck_phone'] = __( 'Phone', 'dck-directory' );
			}
		}
		return $new;
	}

	public function column_content( $col, $post_id ) {
		if ( 'dck_tier' === $col ) {
			$tier = DCK_Fields::get_tier( $post_id );
			$star = DCK_Fields::is_featured( $post_id ) ? ' ★' : '';
			$style = 'premium' === $tier ? 'background:#E7EFF9;color:#14406F' : 'background:#eee;color:#555';
			echo '<span style="' . esc_attr( $style ) . ';padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600">' . esc_html( ucfirst( $tier ) ) . esc_html( $star ) . '</span>';
		}
		if ( 'dck_phone' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_dck_phone', true ) );
		}
	}

	public function pending_notice() {
		$screen = get_current_screen();
		if ( ! $screen || DCK_Post_Types::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$pending = wp_count_posts( DCK_Post_Types::POST_TYPE )->pending;
		if ( $pending > 0 ) {
			echo '<div class="notice notice-info"><p>' . sprintf(
				/* translators: %d = count */
				esc_html__( '%d contractor listing(s) are awaiting review.', 'dck-directory' ),
				(int) $pending
			) . ' <a href="' . esc_url( admin_url( 'edit.php?post_status=pending&post_type=' . DCK_Post_Types::POST_TYPE ) ) . '">' . esc_html__( 'Review now', 'dck-directory' ) . '</a></p></div>';
		}
	}
}
