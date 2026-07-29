<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Profile {
	public function hooks() {
		add_shortcode( 'sdd_doctor_profile', array( $this, 'profile' ) );
		add_shortcode( 'sdd_profile_settings', array( $this, 'settings' ) );
		add_action( 'admin_post_sdd_save_settings', array( $this, 'save' ) );
		add_action( 'admin_post_sdd_report_doctor', array( $this, 'report' ) );
	}

	private function requested_user() {
		$value = isset( $_GET['user'] ) ? sanitize_user( wp_unslash( $_GET['user'] ), true ) : '';
		if ( ! $value ) {
			return is_user_logged_in() ? wp_get_current_user() : false;
		}
		return ctype_digit( $value ) ? get_userdata( absint( $value ) ) : get_user_by( 'slug', $value );
	}

	public function profile() {
		$user = $this->requested_user();
		if ( ! $user ) {
			return '<div class="sdd-notice">Profile not found.</div>';
		}
		if ( ! SPD_Helpers::is_doctor( $user->ID ) ) {
			return shortcode_exists( 'sabri_member_profile' ) ? do_shortcode( '[sabri_member_profile]' ) : '<div class="sdd-notice">Member profile is unavailable.</div>';
		}
		$can_review = current_user_can( 'manage_doctors_directory' ) || current_user_can( 'manage_sabri_doctors' );
		$is_owner   = get_current_user_id() === absint( $user->ID );
		$is_public  = SDD_Helpers::is_public( $user->ID );
		if ( ! $is_public && ! $is_owner && ! $can_review ) {
			return '<div class="sdd-notice">This professional profile is not publicly listed.</div>';
		}
		$photo      = absint( SDD_Helpers::spd( $user->ID, 'profile_photo_id', 0 ) );
		$cover      = absint( SDD_Helpers::spd( $user->ID, 'cover_photo_id', 0 ) );
		$completion = SDD_Helpers::completion( $user->ID );
		$status     = SDD_Helpers::verification_status( $user->ID );
		$directory  = new SDD_Directory();
		ob_start();
		?>
		<main class="sdd-shell sdd-profile" data-doctor-profile="<?php echo absint( $user->ID ); ?>">
			<?php do_action( 'sdd_before_doctor_profile', $user->ID ); ?>
			<?php if ( ! $is_public ) : ?><div class="sdd-status-notice" role="status">Private preview — verification status: <strong><?php echo esc_html( SDD_Helpers::status_label( $user->ID ) ); ?></strong>.</div><?php endif; ?>
			<section class="sdd-profile-hero"<?php echo $cover ? ' style="background-image:linear-gradient(90deg,rgba(15,23,42,.92),rgba(15,23,42,.58)),url(' . esc_url( wp_get_attachment_image_url( $cover, 'large' ) ) . ')"' : ''; ?>>
				<div class="sdd-avatar sdd-avatar-large"><?php echo $photo ? wp_get_attachment_image( $photo, 'medium', false, array( 'alt' => $user->display_name ) ) : esc_html( SDD_Helpers::initials( $user->display_name ) ); ?></div>
				<div><span class="sdd-badge is-<?php echo esc_attr( $status ); ?>"><?php echo 'verified' === $status ? '✓ ' : ''; ?><?php echo esc_html( SDD_Helpers::status_label( $user->ID ) ); ?> Doctor</span><h1><?php echo esc_html( $user->display_name ); ?></h1><p><?php echo esc_html( SDD_Helpers::get( $user->ID, 'headline', SDD_Helpers::spd( $user->ID, 'specialty', 'Homeopathic practitioner' ) ) ); ?></p><p><?php echo esc_html( trim( SDD_Helpers::spd( $user->ID, 'city' ) . ', ' . SDD_Helpers::spd( $user->ID, 'country' ), ', ' ) ); ?></p></div>
			</section>
			<section class="sdd-profile-summary"><div><strong><?php echo absint( $completion ); ?>%</strong><span>Profile completion</span></div><div><strong><?php echo absint( SDD_Helpers::spd( $user->ID, 'experience_years', 0 ) ); ?></strong><span>Years of experience</span></div><div><strong><?php echo absint( SDD_Helpers::contributions( $user->ID ) ); ?></strong><span>Platform contributions</span></div></section>
			<?php echo $directory->contacts( $user->ID, SDD_Helpers::spd( $user->ID, 'phone' ), SDD_Helpers::spd( $user->ID, 'whatsapp' ), SDD_Helpers::contact_is_public( $user->ID, 'phone' ), SDD_Helpers::contact_is_public( $user->ID, 'whatsapp' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $this->professional_actions( $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $is_owner ) : echo $this->owner_actions(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
			<div class="sdd-profile-grid">
				<?php $this->panel( 'Professional Biography', SDD_Helpers::spd( $user->ID, 'bio' ) ); ?>
				<?php $this->panel( 'Specialty', SDD_Helpers::spd( $user->ID, 'specialty' ) ); ?>
				<?php $this->panel( 'Qualifications', SDD_Helpers::spd( $user->ID, 'qualification' ) ); ?>
				<?php $this->panel( 'License / Registration Number', SDD_Helpers::spd( $user->ID, 'licence_number' ) ); ?>
				<?php $this->panel( 'Licensing Authority', SDD_Helpers::get( $user->ID, 'licensing_authority' ) ); ?>
				<?php $this->panel( 'Professional Address', SDD_Helpers::get( $user->ID, 'professional_address' ) ); ?>
				<?php $this->panel( 'Professional Experience', SDD_Helpers::spd( $user->ID, 'experience_years' ) ? SDD_Helpers::spd( $user->ID, 'experience_years' ) . ' years' : '' ); ?>
				<?php $this->panel( 'Clinic Information', SDD_Helpers::spd( $user->ID, 'clinic' ) ); ?>
				<?php $this->panel( 'Consultation Fee', $this->fee_text( $user->ID ) ); ?>
				<?php $this->panel( 'Consultation Timings', $this->timings_text( $user->ID ) ); ?>
				<?php $this->panel( 'Consultation Methods', SDD_Helpers::spd( $user->ID, 'consultation_modes' ) ); ?>
				<?php $this->panel( 'Languages', SDD_Helpers::spd( $user->ID, 'languages' ) ); ?>
				<?php $this->panel( 'Classical Books Studied', SDD_Helpers::spd( $user->ID, 'studied_books' ) ); ?>
				<?php $this->links_panel( $user->ID ); ?>
			</div>
			<?php if ( $is_public ) : echo $this->report_form( $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
			<p class="sdd-disclaimer">This profile is for professional discovery and education. Verification is not an endorsement, does not guarantee outcomes, and does not replace emergency or locally licensed medical care.</p>
			<?php do_action( 'sdd_after_doctor_profile', $user->ID ); ?>
		</main>
		<?php
		return ob_get_clean();
	}

	private function fee_text( $user_id ) {
		$fee      = trim( (string) SDD_Helpers::get( $user_id, 'consultation_fee' ) );
		$currency = strtoupper( trim( (string) SDD_Helpers::get( $user_id, 'fee_currency' ) ) );
		return $fee ? trim( $currency . ' ' . $fee ) : '';
	}

	private function timings_text( $user_id ) {
		$timings = trim( (string) SDD_Helpers::get( $user_id, 'consultation_timings' ) );
		$zone    = trim( (string) SDD_Helpers::get( $user_id, 'timezone' ) );
		return $timings ? $timings . ( $zone ? "\nTime zone: " . $zone : '' ) : '';
	}

	private function professional_actions( $user_id ) {
		$links = array(
			'clinic'      => array( 'View Clinic', SDD_Helpers::integration_url( 'clinic', $user_id ) ),
			'appointment' => array( 'Request Appointment', SDD_Helpers::integration_url( 'appointment', $user_id ) ),
		);
		$out = '';
		foreach ( $links as $item ) {
			if ( $item[1] ) {
				$out .= '<a class="sdd-button sdd-button-light" href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a>';
			}
		}
		return $out ? '<nav class="sdd-owner-actions" aria-label="Doctor services">' . $out . '</nav>' : '';
	}

	private function owner_actions() {
		$pages = (array) get_option( 'sdd_page_map', array() );
		$spd   = (array) get_option( 'spd_page_map', array() );
		$links = array(
			array( 'Directory Settings', SDD_Helpers::safe_page_url( $pages, 'settings' ), 'sdd-button' ),
			array( 'Edit Core Profile', SDD_Helpers::safe_page_url( $spd, 'edit' ), 'sdd-button sdd-button-light' ),
		);
		$out = '';
		foreach ( $links as $link ) {
			if ( $link[1] ) {
				$out .= '<a class="' . esc_attr( $link[2] ) . '" href="' . esc_url( $link[1] ) . '">' . esc_html( $link[0] ) . '</a>';
			}
		}
		return $out ? '<nav class="sdd-owner-actions" aria-label="Profile owner actions">' . $out . '</nav>' : '';
	}

	private function panel( $title, $value ) {
		if ( ! trim( (string) $value ) ) {
			return;
		}
		echo '<section class="sdd-panel"><h2>' . esc_html( $title ) . '</h2><p>' . nl2br( esc_html( $value ) ) . '</p></section>';
	}

	private function links_panel( $user_id ) {
		$links = array( 'website' => 'Website', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook' );
		$out   = '';
		foreach ( $links as $key => $label ) {
			$url = esc_url( SDD_Helpers::get( $user_id, $key ) );
			if ( $url ) {
				$out .= '<li><a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . esc_html( $label ) . '</a></li>';
			}
		}
		if ( $out ) {
			echo '<section class="sdd-panel"><h2>Professional Links</h2><ul>' . $out . '</ul></section>';
		}
	}

	public function settings() {
		if ( ! is_user_logged_in() ) {
			return '<div class="sdd-notice"><p>Log in to manage your directory settings.</p></div>';
		}
		$user = wp_get_current_user();
		if ( ! SPD_Helpers::is_doctor( $user->ID ) ) {
			return '<div class="sdd-notice">Doctor directory settings are available to doctor accounts.</div>';
		}
		$completion = SDD_Helpers::completion( $user->ID );
		$timezones  = timezone_identifiers_list();
		ob_start();
		?>
		<main class="sdd-shell"><header class="sdd-page-head"><span>Professional Directory</span><h1>Doctor Directory Settings</h1><p>Control professional availability, contact visibility, and the additional information shown in public discovery.</p></header>
		<section class="sdd-completion"><div><strong><?php echo absint( $completion ); ?>%</strong><span>Profile complete</span></div><div class="sdd-meter" role="progressbar" aria-label="Profile completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo absint( $completion ); ?>"><span style="width:<?php echo absint( $completion ); ?>%"></span></div><p>Complete your photograph, biography, qualification, license, licensing authority, professional address, experience, location, languages, contacts, clinic, consultation methods, and timings.</p></section>
		<form class="sdd-settings" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="sdd_save_settings"><?php wp_nonce_field( 'sdd_save_settings', 'sdd_nonce' ); ?>
			<label>Professional headline<input name="headline" maxlength="120" value="<?php echo esc_attr( SDD_Helpers::get( $user->ID, 'headline' ) ); ?>" placeholder="Classical homeopathic practitioner"></label>
			<label>Licensing authority<input name="licensing_authority" maxlength="160" value="<?php echo esc_attr( SDD_Helpers::get( $user->ID, 'licensing_authority' ) ); ?>"></label>
			<label class="sdd-wide">Professional / clinic address<textarea name="professional_address" maxlength="500"><?php echo esc_textarea( SDD_Helpers::get( $user->ID, 'professional_address' ) ); ?></textarea></label>
			<label>Consultation fee<input name="consultation_fee" maxlength="40" value="<?php echo esc_attr( SDD_Helpers::get( $user->ID, 'consultation_fee' ) ); ?>" inputmode="decimal"></label>
			<label>Currency code<input name="fee_currency" maxlength="3" pattern="[A-Za-z]{3}" value="<?php echo esc_attr( SDD_Helpers::get( $user->ID, 'fee_currency' ) ); ?>" placeholder="PKR"></label>
			<label class="sdd-wide">Consultation timings<textarea name="consultation_timings" maxlength="500" placeholder="Monday–Friday, 10:00 AM–6:00 PM"><?php echo esc_textarea( SDD_Helpers::get( $user->ID, 'consultation_timings' ) ); ?></textarea></label>
			<label>Time zone<select name="timezone"><option value="">Select a time zone</option><?php foreach ( $timezones as $zone ) : ?><option value="<?php echo esc_attr( $zone ); ?>" <?php selected( SDD_Helpers::get( $user->ID, 'timezone' ), $zone ); ?>><?php echo esc_html( $zone ); ?></option><?php endforeach; ?></select></label>
			<label>Professional website<input type="url" name="website" value="<?php echo esc_attr( SDD_Helpers::get( $user->ID, 'website' ) ); ?>"></label>
			<label>LinkedIn profile<input type="url" name="linkedin" value="<?php echo esc_attr( SDD_Helpers::get( $user->ID, 'linkedin' ) ); ?>"></label>
			<label>Facebook profile<input type="url" name="facebook" value="<?php echo esc_attr( SDD_Helpers::get( $user->ID, 'facebook' ) ); ?>"></label>
			<div class="sdd-checks"><label><input type="checkbox" name="online_available" value="1" <?php checked( SDD_Helpers::get( $user->ID, 'online_available', '0' ), '1' ); ?>> Online consultation available</label><label><input type="checkbox" name="in_person_available" value="1" <?php checked( SDD_Helpers::get( $user->ID, 'in_person_available', '0' ), '1' ); ?>> In-person consultation available</label><label><input type="checkbox" name="accepting_patients" value="1" <?php checked( SDD_Helpers::get( $user->ID, 'accepting_patients', '0' ), '1' ); ?>> Currently accepting new patients</label><label><input type="checkbox" name="public_phone" value="1" <?php checked( SDD_Helpers::contact_is_public( $user->ID, 'phone' ) ); ?>> Show my professional phone button publicly</label><label><input type="checkbox" name="public_whatsapp" value="1" <?php checked( SDD_Helpers::contact_is_public( $user->ID, 'whatsapp' ) ); ?>> Show my professional WhatsApp button publicly</label><label><input type="checkbox" name="discoverable" value="1" <?php checked( SDD_Helpers::get( $user->ID, 'discoverable', '1' ), '1' ); ?>> Include my verified profile in public search</label><p class="description">A verified doctor must keep at least one available professional contact method public.</p></div>
			<button class="sdd-button" type="submit">Save Directory Settings</button>
		</form></main>
		<?php
		return ob_get_clean();
	}

	public function save() {
		if ( ! is_user_logged_in() || ! SPD_Helpers::is_doctor( get_current_user_id() ) ) {
			wp_die( esc_html__( 'You are not allowed to update these settings.', 'doctors-directory' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sdd_save_settings', 'sdd_nonce' );
		$user_id = get_current_user_id();
		$text_fields = array( 'headline' => 120, 'licensing_authority' => 160, 'consultation_fee' => 40, 'fee_currency' => 3, 'timezone' => 80 );
		foreach ( $text_fields as $key => $max ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
			if ( 'fee_currency' === $key ) {
				$value = preg_match( '/^[A-Za-z]{3}$/', $value ) ? strtoupper( $value ) : '';
			}
			if ( 'timezone' === $key && $value && ! in_array( $value, timezone_identifiers_list(), true ) ) {
				$value = '';
			}
			update_user_meta( $user_id, '_sdd_' . $key, $value );
		}
		foreach ( array( 'professional_address' => 500, 'consultation_timings' => 500 ) as $key => $max ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
			update_user_meta( $user_id, '_sdd_' . $key, $value );
		}
		foreach ( array( 'website', 'linkedin', 'facebook' ) as $key ) {
			update_user_meta( $user_id, '_sdd_' . $key, isset( $_POST[ $key ] ) ? esc_url_raw( wp_unslash( $_POST[ $key ] ) ) : '' );
		}
		$old_discoverable = SDD_Helpers::get( $user_id, 'discoverable', '1' );
		$old_phone        = SDD_Helpers::contact_is_public( $user_id, 'phone' ) ? '1' : '0';
		$old_whatsapp     = SDD_Helpers::contact_is_public( $user_id, 'whatsapp' ) ? '1' : '0';
		$flags = array();
		foreach ( array( 'online_available', 'in_person_available', 'accepting_patients', 'public_phone', 'public_whatsapp', 'discoverable' ) as $key ) {
			$flags[ $key ] = isset( $_POST[ $key ] ) ? '1' : '0';
		}
		if ( SDD_Helpers::is_verified( $user_id ) && '0' === $flags['public_phone'] && '0' === $flags['public_whatsapp'] ) {
			if ( SDD_Helpers::spd( $user_id, 'phone' ) ) {
				$flags['public_phone'] = '1';
			} elseif ( SDD_Helpers::spd( $user_id, 'whatsapp' ) ) {
				$flags['public_whatsapp'] = '1';
			}
		}
		foreach ( $flags as $key => $value ) {
			update_user_meta( $user_id, '_sdd_' . $key, $value );
		}
		SDD_Helpers::maybe_audit( $user_id, 'discoverable_' . $old_discoverable, 'discoverable_' . $flags['discoverable'], 'Doctor updated directory discoverability.' );
		SDD_Helpers::maybe_audit( $user_id, 'phone_public_' . $old_phone, 'phone_public_' . $flags['public_phone'], 'Doctor updated professional phone visibility.' );
		SDD_Helpers::maybe_audit( $user_id, 'whatsapp_public_' . $old_whatsapp, 'whatsapp_public_' . $flags['public_whatsapp'], 'Doctor updated professional WhatsApp visibility.' );
		$map = (array) get_option( 'sdd_page_map', array() );
		$url = SDD_Helpers::safe_page_url( $map, 'settings' );
		wp_safe_redirect( add_query_arg( 'updated', '1', $url ? $url : home_url( '/' ) ) );
		exit;
	}

	private function report_form( $doctor_id ) {
		if ( ! is_user_logged_in() ) {
			return '<p class="sdd-report-login"><a href="' . esc_url( wp_login_url( SDD_Helpers::profile_url( $doctor_id ) ) ) . '">Log in to report a profile concern</a></p>';
		}
		ob_start();
		?><details class="sdd-report"><summary>Report a Profile Concern</summary><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="sdd_report_doctor"><input type="hidden" name="doctor_id" value="<?php echo absint( $doctor_id ); ?>"><?php wp_nonce_field( 'sdd_report_' . $doctor_id ); ?><label>Reason<select name="reason" required><option value="">Choose a reason</option><option value="credentials">Credentials concern</option><option value="incorrect-information">Incorrect information</option><option value="medical-safety">Medical safety concern</option><option value="spam">Spam or impersonation</option><option value="harassment">Harassment</option><option value="copyright">Copyright concern</option><option value="other">Other</option></select></label><label>Details<textarea name="details" maxlength="1500" required></textarea></label><button class="sdd-button" type="submit">Send Report</button></form></details><?php
		return ob_get_clean();
	}

	public function report() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in to submit a report.', 'doctors-directory' ), '', array( 'response' => 403 ) );
		}
		$rate_key = 'sdd_report_' . get_current_user_id();
		if ( absint( get_transient( $rate_key ) ) >= 5 ) {
			wp_die( esc_html__( 'Please wait before submitting another report.', 'doctors-directory' ), '', array( 'response' => 429 ) );
		}
		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		check_admin_referer( 'sdd_report_' . $doctor_id );
		$allowed = array( 'credentials', 'incorrect-information', 'medical-safety', 'spam', 'harassment', 'copyright', 'other' );
		$reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		if ( ! SDD_Helpers::is_public( $doctor_id ) || ! in_array( $reason, $allowed, true ) || ! trim( $details ) ) {
			wp_die( esc_html__( 'Invalid report.', 'doctors-directory' ), '', array( 'response' => 400 ) );
		}
		$details = function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 1500 ) : substr( $details, 0, 1500 );
		$now     = current_time( 'mysql', true );
		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'sdd_reports',
			array( 'doctor_id' => $doctor_id, 'reporter_id' => get_current_user_id(), 'reason' => $reason, 'details' => $details, 'status' => 'open', 'reviewer_id' => 0, 'review_note' => '', 'created_at' => $now, 'updated_at' => $now ),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			wp_die( esc_html__( 'The report could not be saved. Please try again.', 'doctors-directory' ), '', array( 'response' => 500 ) );
		}
		set_transient( $rate_key, absint( get_transient( $rate_key ) ) + 1, HOUR_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'reported', '1', SDD_Helpers::profile_url( $doctor_id ) ) );
		exit;
	}
}
