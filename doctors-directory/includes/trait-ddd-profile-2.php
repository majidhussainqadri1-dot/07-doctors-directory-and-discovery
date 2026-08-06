<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Profile_Trait_2 {
	public function update_consent() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Authentication is required.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		$user_id = get_current_user_id();
		check_admin_referer( 'ddd_update_directory_consent_' . $user_id );
		$claims = DDD_Contracts::verification_claims( $user_id );
		if ( ! $claims['doctor'] ) {
			wp_die( esc_html__( 'Only a doctor account may change directory consent.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		$old = (string) DDD_Helpers::meta( $user_id, 'discoverable', '1' );
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
			wp_die( esc_html( $id->get_error_message() ), '', array( 'response' => (int) $id->get_error_data()['status'] ) );
		}
		wp_safe_redirect( add_query_arg( 'reported', absint( $id ), wp_get_referer() ? wp_get_referer() : home_url( '/doctors/' ) ) );
		exit;
	}
	public function save_doctor() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Log in to save a doctor.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
		$public_id = isset( $_POST['public_id'] ) ? sanitize_text_field( wp_unslash( $_POST['public_id'] ) ) : '';
		check_admin_referer( 'ddd_save_doctor_' . $public_id );
		$doctor_id = DDD_Repository::resolve_doctor_id( $public_id );
		$result = DDD_Repository::save_reference( get_current_user_id(), $doctor_id, ! empty( $_POST['save'] ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) $result->get_error_data()['status'] ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url( '/doctors/' ) );
		exit;
	}
}
