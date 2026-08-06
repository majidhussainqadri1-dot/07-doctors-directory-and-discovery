<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Contracts_Trait_1 {
	public static function dependency_health() {
		$health = array(
			'ready'   => true,
			'code'    => 'ok',
			'message' => __( 'Required contracts are available.', DDD_TEXT_DOMAIN ),
			'details' => array(),
		);

		$profile_ready = class_exists( 'SPD_Helpers' ) || has_filter( self::PROFILE_FILTER );
		if ( ! $profile_ready ) {
			$health['ready'] = false;
			$health['code'] = 'file03_contract_missing';
			$health['message'] = __( 'File 03 public profile contract is unavailable; public eligibility will fail closed.', DDD_TEXT_DOMAIN );
			$health['details'][] = 'File 03';
		}
		if ( defined( 'SPD_VERSION' ) && version_compare( SPD_VERSION, DDD_MIN_FILE03_VERSION, '<' ) ) {
			$health['ready'] = false;
			$health['code'] = 'file03_version_incompatible';
			$health['message'] = sprintf( __( 'File 03 must be version %s or newer.', DDD_TEXT_DOMAIN ), DDD_MIN_FILE03_VERSION );
			$health['details'][] = SPD_VERSION;
		}
		return $health;
	}
	public static function identity_claims( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'          => $user_id,
			'account_active'   => true,
			'suspended'        => false,
			'risk_blocked'     => false,
			'age_eligible'     => true,
			'guardian_valid'   => true,
			'institutional'    => false,
			'claim_version'    => 'legacy-adapter',
			'source_updated_at'=> '',
		);
		$claims = apply_filters( self::IDENTITY_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $claims ) ) {
			return wp_parse_args( $claims, $defaults );
		}

		/* Compatibility adapter. It is deliberately conservative and can only remove eligibility. */
		$status = (string) get_user_meta( $user_id, '_smc_membership_status', true );
		$suspension = (string) get_user_meta( $user_id, '_smc_suspension_status', true );
		$risk = (string) get_user_meta( $user_id, '_smc_risk_status', true );
		$age_status = (string) get_user_meta( $user_id, '_smc_age_eligibility', true );
		$guardian = (string) get_user_meta( $user_id, '_smc_guardian_status', true );
		$defaults['account_active'] = ! in_array( $status, array( 'disabled', 'rejected', 'deleted', 'closed' ), true );
		$defaults['suspended'] = in_array( $suspension, array( 'suspended', 'blocked', 'revoked' ), true );
		$defaults['risk_blocked'] = in_array( $risk, array( 'blocked', 'critical', 'denied' ), true );
		$defaults['age_eligible'] = ! in_array( $age_status, array( 'ineligible', 'blocked' ), true );
		$defaults['guardian_valid'] = ! in_array( $guardian, array( 'required', 'expired', 'revoked', 'invalid' ), true );
		$defaults['institutional'] = DDD_Helpers::is_founder( $user_id ) || user_can( $user_id, 'manage_options' );
		return $defaults;
	}
	public static function verification_claims( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'          => $user_id,
			'doctor'           => false,
			'verified'         => false,
			'status'           => 'unverified',
			'effective_at'     => '',
			'expires_at'       => '',
			'decision_version' => 'legacy-adapter',
			'source_updated_at'=> '',
		);
		$claims = apply_filters( self::VERIFICATION_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $claims ) ) {
			return wp_parse_args( $claims, $defaults );
		}
		if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'is_doctor' ) ) ) {
			$defaults['doctor'] = (bool) SPD_Helpers::is_doctor( $user_id );
		}
		if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'verification_status' ) ) ) {
			$defaults['status'] = sanitize_key( (string) SPD_Helpers::verification_status( $user_id ) );
		} else {
			$defaults['status'] = sanitize_key( (string) get_user_meta( $user_id, '_spd_verification_status', true ) );
		}
		$defaults['verified'] = $defaults['doctor'] && 'verified' === $defaults['status'];
		$effective = (string) get_user_meta( $user_id, '_gdo_verified_at', true );
		if ( '' === $effective ) {
			$effective = (string) get_user_meta( $user_id, '_spd_verified_at', true );
		}
		$defaults['effective_at'] = DDD_Helpers::mysql_datetime( $effective );
		$defaults['expires_at'] = DDD_Helpers::mysql_datetime( (string) get_user_meta( $user_id, '_gdo_verification_expires_at', true ) );
		return $defaults;
	}
}
