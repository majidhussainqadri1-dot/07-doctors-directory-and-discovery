<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Contracts_Trait_3 {
	private static function normalize_clinic( $clinic ) {
		$clinic['consultation_modes'] = DDD_Helpers::list_value( $clinic['consultation_modes'] );
		$clinic['fee_min'] = DDD_Helpers::decimal_or_null( $clinic['fee_min'] );
		$clinic['fee_max'] = DDD_Helpers::decimal_or_null( $clinic['fee_max'] );
		$clinic['currency'] = preg_match( '/^[A-Z]{3}$/', strtoupper( (string) $clinic['currency'] ) ) ? strtoupper( (string) $clinic['currency'] ) : '';
		$clinic['clinic_url'] = esc_url_raw( (string) $clinic['clinic_url'] );
		$clinic['appointment_url'] = esc_url_raw( (string) $clinic['appointment_url'] );
		return $clinic;
	}
	public static function founder() {
		$founder = apply_filters( self::FOUNDER_FILTER, null, DDD_CONTRACT_VERSION );
		if ( is_array( $founder ) ) {
			return $founder;
		}
		$user_id = DDD_Helpers::founder_id();
		if ( ! $user_id ) {
			return array();
		}
		$profile = self::public_profile( $user_id );
		$profile['user_id'] = $user_id;
		$profile['institutional'] = true;
		return $profile;
	}
	public static function eligibility( $user_id ) {
		$identity = self::identity_claims( $user_id );
		$verification = self::verification_claims( $user_id );
		$profile = self::public_profile( $user_id );
		$clinic = self::public_clinic( $user_id );
		$reasons = array();
		if ( ! $identity['account_active'] ) { $reasons[] = 'account_inactive'; }
		if ( $identity['suspended'] ) { $reasons[] = 'account_suspended'; }
		if ( $identity['risk_blocked'] ) { $reasons[] = 'risk_blocked'; }
		if ( ! $identity['age_eligible'] ) { $reasons[] = 'age_ineligible'; }
		if ( ! $identity['guardian_valid'] ) { $reasons[] = 'guardian_required'; }
		if ( ! $verification['doctor'] ) { $reasons[] = 'not_doctor'; }
		if ( ! $verification['verified'] ) { $reasons[] = 'not_verified'; }
		if ( $verification['expires_at'] && strtotime( $verification['expires_at'] . ' UTC' ) <= time() ) { $reasons[] = 'verification_expired'; }
		if ( ! $profile['public'] || ! $profile['discoverable'] ) { $reasons[] = 'profile_private'; }
		if ( DDD_Helpers::is_founder( $user_id ) ) { $reasons[] = 'founder_separate'; }
		return array(
			'eligible'     => empty( $reasons ),
			'status'       => empty( $reasons ) ? 'eligible' : ( in_array( 'account_suspended', $reasons, true ) ? 'suspended' : 'limited' ),
			'reasons'      => array_values( array_unique( $reasons ) ),
			'identity'     => $identity,
			'verification' => $verification,
			'profile'      => $profile,
			'clinic'       => $clinic,
		);
	}
}
