<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Profile {
	public function hooks() {
		add_shortcode( 'ddd_directory_status', array( $this, 'status' ) );
		add_shortcode( 'sdd_profile_settings', array( $this, 'status' ) );
		add_action( 'admin_post_ddd_update_directory_consent', array( $this, 'update_consent' ) );
		add_action( 'admin_post_ddd_report_listing', array( $this, 'report' ) );
		add_action( 'admin_post_ddd_save_doctor', array( $this, 'save_doctor' ) );
	}

	public function status() {
		if ( ! is_user_logged_in() ) {
			return '<div class="ddd-notice"><p>' . esc_html__( 'Log in to view your doctor-directory status.', DDD_TEXT_DOMAIN ) . '</p><a class="ddd-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', DDD_TEXT_DOMAIN ) . '</a></div>';
		}
		$user_id = get_current_user_id();
		$verification = DDD_Contracts::verification_claims( $user_id );
		if ( ! $verification['doctor'] ) {
			return '<div class="ddd-notice"><p>' . esc_html__( 'Directory status is available to doctor accounts. Doctor onboarding and verification are owned by File 09 and membership authority by File 00.', DDD_TEXT_DOMAIN ) . '</p></div>';
		}
		$status = DDD_Repository::get_live_status( $user_id );
		$reason_labels = $this->reason_labels();
		$profile = DDD_Contracts::public_profile( $user_id );
		ob_start();
		?>
		<main class="ddd-shell ddd-status-page">
			<header class="ddd-page-head"><span><?php esc_html_e( 'Professional Directory', DDD_TEXT_DOMAIN ); ?></span><h1><?php esc_html_e( 'Directory Status', DDD_TEXT_DOMAIN ); ?></h1><p><?php esc_html_e( 'This page shows the exact owner-sourced conditions used by File 07. It does not alter identity, verification, profile or clinic truth.', DDD_TEXT_DOMAIN ); ?></p></header>
			<section class="ddd-status-summary" aria-live="polite">
				<h2><?php echo esc_html( $status['eligible'] ? __( 'Publicly eligible', DDD_TEXT_DOMAIN ) : __( 'Not publicly eligible', DDD_TEXT_DOMAIN ) ); ?></h2>
				<p><?php echo esc_html( sprintf( __( 'Current owner status: %1$s · Projection version %2$d · Last reconciled %3$s UTC', DDD_TEXT_DOMAIN ), $status['status'], $status['version'], $status['updated_at'] ) ); ?></p>
				<?php if ( $status['eligible'] ) : ?><p><a class="ddd-button" href="<?php echo esc_url( $status['public_url'] ); ?>"><?php esc_html_e( 'Open public directory route', DDD_TEXT_DOMAIN ); ?></a></p><?php endif; ?>
			</section>
			<section class="ddd-panel"><h2><?php esc_html_e( 'Eligibility conditions', DDD_TEXT_DOMAIN ); ?></h2><?php if ( $status['reasons'] ) : ?><ul class="ddd-reasons"><?php foreach ( $status['reasons'] as $reason ) : ?><li><strong><?php echo esc_html( isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : $reason ); ?></strong><?php echo $this->reason_action( $reason, $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e( 'All mandatory eligibility conditions currently pass.', DDD_TEXT_DOMAIN ); ?></p><?php endif; ?></section>
			<section class="ddd-panel"><h2><?php esc_html_e( 'Public discoverability consent', DDD_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'You may withdraw public directory discoverability without deleting your canonical professional profile. Withdrawal is propagated to the projection, cache and sitemap.', DDD_TEXT_DOMAIN ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_update_directory_consent"><?php wp_nonce_field( 'ddd_update_directory_consent_' . $user_id ); ?><label class="ddd-check"><input type="checkbox" name="discoverable" value="1" <?php checked( $profile['discoverable'] ); ?>> <?php esc_html_e( 'Include my eligible professional profile in public directory discovery', DDD_TEXT_DOMAIN ); ?></label><button class="ddd-button" type="submit"><?php esc_html_e( 'Save directory consent', DDD_TEXT_DOMAIN ); ?></button></form></section>
		</main>
		<?php
		return ob_get_clean();
	}

	private function reason_labels() {
		return array(
			'identity_contract_unavailable' => __( 'Membership identity contract is unavailable; eligibility fails closed', DDD_TEXT_DOMAIN ),
			'verification_contract_unavailable' => __( 'Doctor verification contract is unavailable; eligibility fails closed', DDD_TEXT_DOMAIN ),
			'profile_contract_unavailable' => __( 'Public profile contract is unavailable; eligibility fails closed', DDD_TEXT_DOMAIN ),
			'public_fields_incomplete' => __( 'Mandatory public professional fields are incomplete', DDD_TEXT_DOMAIN ),
			'public_destination_missing' => __( 'No canonical public profile or clinic destination is available', DDD_TEXT_DOMAIN ),
			'account_inactive'      => __( 'Membership account is inactive', DDD_TEXT_DOMAIN ),
			'account_suspended'     => __( 'Membership or professional access is suspended', DDD_TEXT_DOMAIN ),
			'risk_blocked'          => __( 'A current platform risk restriction blocks public listing', DDD_TEXT_DOMAIN ),
			'age_ineligible'        => __( 'Age policy does not permit public professional listing', DDD_TEXT_DOMAIN ),
			'guardian_required'     => __( 'Required guardian authorization is absent or invalid', DDD_TEXT_DOMAIN ),
			'not_doctor'            => __( 'No authoritative doctor claim is present', DDD_TEXT_DOMAIN ),
			'not_verified'          => __( 'Doctor verification is incomplete', DDD_TEXT_DOMAIN ),
			'verification_expired'  => __( 'Doctor verification has expired', DDD_TEXT_DOMAIN ),
			'profile_private'       => __( 'The public profile or directory consent is private', DDD_TEXT_DOMAIN ),
			'founder_separate'      => __( 'Founder identity is presented in the separate institutional Founder section', DDD_TEXT_DOMAIN ),
			'projection_unavailable'=> __( 'Directory projection is temporarily unavailable or awaiting reconciliation', DDD_TEXT_DOMAIN ),
				'projection_stale' => __( 'A previously public projection is awaiting removal after an owner-state change', DDD_TEXT_DOMAIN ),
		);
	}

	private function reason_action( $reason, $status ) {
		$url = '';
		$label = '';
		if ( in_array( $reason, array( 'account_inactive','account_suspended','risk_blocked','age_ineligible','guardian_required' ), true ) ) {
			$map = (array) get_option( 'smc_page_map', array() );
			$url = ! empty( $map['status'] ) && 'publish' === get_post_status( absint( $map['status'] ) ) ? get_permalink( absint( $map['status'] ) ) : '';
			$label = __( 'Open membership status', DDD_TEXT_DOMAIN );
		} elseif ( in_array( $reason, array( 'not_doctor','not_verified','verification_expired' ), true ) ) {
			$map = (array) get_option( 'gdo_page_map', array() );
			$url = ! empty( $map['status'] ) && 'publish' === get_post_status( absint( $map['status'] ) ) ? get_permalink( absint( $map['status'] ) ) : '';
			$label = __( 'Open verification status', DDD_TEXT_DOMAIN );
		} elseif ( 'profile_private' === $reason && ! empty( $status['profile_url'] ) ) {
			$url = $status['profile_url'];
			$label = __( 'Open canonical profile', DDD_TEXT_DOMAIN );
		}
		return $url ? ' <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>' : '';
	}

	public function update_consent() {
		if ( DDD_Observability::safe_mode() ) { wp_die( esc_html__( 'Directory changes are temporarily unavailable while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) ); }
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Authentication is required.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		$user_id = get_current_user_id();
		check_admin_referer( 'ddd_update_directory_consent_' . $user_id );
		$claims = DDD_Contracts::verification_claims( $user_id );
		if ( ! $claims['doctor'] ) {
			wp_die( esc_html__( 'Only a doctor account may change directory consent.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		$old = (string) DDD_Helpers::meta( $user_id, 'discoverable', '0' );
		$new = isset( $_POST['discoverable'] ) ? '1' : '0';
		DDD_Helpers::set_meta( $user_id, 'discoverable', $new );
		$rebuilt = DDD_Repository::rebuild_doctor( $user_id, 'doctor_consent_change' );
		if ( is_wp_error( $rebuilt ) ) {
			DDD_Helpers::set_meta( $user_id, 'discoverable', $old );
			wp_die( esc_html( $rebuilt->get_error_message() ), '', array( 'response' => 500 ) );
		}
		do_action( 'ddd_directory_consent_changed', $user_id, $old, $new );
		wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ? wp_get_referer() : home_url( '/account/directory-status/' ) ) );
		exit;
	}

	public function report() {
		if ( DDD_Observability::safe_mode() ) { wp_die( esc_html__( 'Reports are temporarily unavailable while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) ); }
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in to report a public listing.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		$public_id = isset( $_POST['public_id'] ) ? sanitize_text_field( wp_unslash( $_POST['public_id'] ) ) : '';
		check_admin_referer( 'ddd_report_listing_' . $public_id );
		$doctor_id = DDD_Repository::resolve_doctor_id( $public_id );
		$id = DDD_Repository::report(
			$doctor_id,
			get_current_user_id(),
			isset( $_POST['reason'] ) ? wp_unslash( $_POST['reason'] ) : '',
			isset( $_POST['details'] ) ? wp_unslash( $_POST['details'] ) : '',
			isset( $_POST['evidence_url'] ) ? wp_unslash( $_POST['evidence_url'] ) : '',
			isset( $_POST['idempotency_key'] ) ? wp_unslash( $_POST['idempotency_key'] ) : ''
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ), '', array( 'response' => (int) ( is_array( $id->get_error_data() ) && isset( $id->get_error_data()['status'] ) ? $id->get_error_data()['status'] : 400 ) ) );
		}
		wp_safe_redirect( add_query_arg( 'reported', absint( $id ), wp_get_referer() ? wp_get_referer() : home_url( '/doctors/' ) ) );
		exit;
	}

	public function save_doctor() {
		if ( DDD_Observability::safe_mode() && ! empty( $_POST['save'] ) ) { wp_die( esc_html__( 'Saving doctors is temporarily unavailable while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) ); }
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in to save a doctor.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		$public_id = isset( $_POST['public_id'] ) ? sanitize_text_field( wp_unslash( $_POST['public_id'] ) ) : '';
		check_admin_referer( 'ddd_save_doctor_' . $public_id );
		$doctor_id = DDD_Repository::resolve_doctor_id( $public_id );
		$result = DDD_Repository::save_reference( get_current_user_id(), $doctor_id, ! empty( $_POST['save'] ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) ( is_array( $result->get_error_data() ) && isset( $result->get_error_data()['status'] ) ? $result->get_error_data()['status'] : 400 ) ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/doctors/' ) );
		exit;
	}
}

if ( ! class_exists( 'SDD_Profile' ) ) {
	class_alias( 'DDD_Profile', 'SDD_Profile' );
}
