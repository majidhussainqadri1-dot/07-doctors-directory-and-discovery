<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Contracts_Trait_2 {
	public static function public_profile( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'             => $user_id,
			'public_id'           => '',
			'public'              => false,
			'display_name'        => '',
			'professional_title'  => '',
			'specialty'           => '',
			'country'             => '',
			'city'                => '',
			'languages'           => array(),
			'qualification'       => '',
			'experience_years'    => 0,
			'avatar_id'           => 0,
			'profile_url'         => '',
			'phone_public'        => false,
			'phone'               => '',
			'whatsapp_public'     => false,
			'whatsapp'            => '',
			'discoverable'        => true,
			'consent_version'     => '',
			'profile_version'     => 'legacy-adapter',
			'source_updated_at'   => '',
		);
		$profile = apply_filters( self::PROFILE_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $profile ) ) {
			return self::normalize_profile( wp_parse_args( $profile, $defaults ) );
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $defaults;
		}
		$get = static function ( $key, $default = '' ) use ( $user_id ) {
			if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'get' ) ) ) {
				return SPD_Helpers::get( $user_id, $key, $default );
			}
			$value = get_user_meta( $user_id, '_spd_' . $key, true );
			return '' === $value ? $default : $value;
		};
		$defaults['display_name'] = (string) $user->display_name;
		$defaults['professional_title'] = (string) DDD_Helpers::meta( $user_id, 'headline', $get( 'specialty', 'Homeopathic practitioner' ) );
		$defaults['specialty'] = (string) $get( 'specialty', '' );
		$defaults['country'] = (string) $get( 'country', '' );
		$defaults['city'] = (string) $get( 'city', '' );
		$defaults['languages'] = DDD_Helpers::list_value( $get( 'languages', '' ) );
		$defaults['qualification'] = (string) $get( 'qualification', '' );
		$defaults['experience_years'] = absint( $get( 'experience_years', 0 ) );
		$defaults['avatar_id'] = absint( $get( 'profile_photo_id', 0 ) );
		$defaults['public_id'] = (string) get_user_meta( $user_id, '_ddd_public_id', true );
		if ( '' === $defaults['public_id'] ) {
			$defaults['public_id'] = DDD_Helpers::uuid_from_user( $user_id );
		}
		if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'profile_url' ) ) ) {
			$defaults['profile_url'] = (string) SPD_Helpers::profile_url( $user_id );
		}
		$defaults['phone'] = (string) $get( 'phone', '' );
		$defaults['whatsapp'] = (string) $get( 'whatsapp', '' );
		$defaults['phone_public'] = '1' === (string) DDD_Helpers::meta( $user_id, 'public_phone', $get( 'public_contact', '0' ) );
		$defaults['whatsapp_public'] = '1' === (string) DDD_Helpers::meta( $user_id, 'public_whatsapp', $get( 'public_contact', '0' ) );
		$defaults['discoverable'] = '0' !== (string) DDD_Helpers::meta( $user_id, 'discoverable', '1' );
		$defaults['public'] = $defaults['discoverable'];
		return self::normalize_profile( $defaults );
	}
	private static function normalize_profile( $profile ) {
		$profile['languages'] = DDD_Helpers::list_value( $profile['languages'] );
		$profile['experience_years'] = min( 100, absint( $profile['experience_years'] ) );
		$profile['avatar_id'] = absint( $profile['avatar_id'] );
		$profile['display_name'] = sanitize_text_field( (string) $profile['display_name'] );
		$profile['professional_title'] = sanitize_text_field( (string) $profile['professional_title'] );
		$profile['specialty'] = sanitize_text_field( (string) $profile['specialty'] );
		$profile['country'] = sanitize_text_field( (string) $profile['country'] );
		$profile['city'] = sanitize_text_field( (string) $profile['city'] );
		$profile['qualification'] = sanitize_text_field( (string) $profile['qualification'] );
		$profile['profile_url'] = esc_url_raw( (string) $profile['profile_url'] );
		return $profile;
	}
	public static function public_clinic( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'            => $user_id,
			'public'             => false,
			'clinic_name'        => '',
			'clinic_url'         => '',
			'appointment_url'    => '',
			'consultation_modes' => array(),
			'accepting_patients' => false,
			'fee_min'            => null,
			'fee_max'            => null,
			'currency'           => '',
			'availability_label' => '',
			'clinic_version'     => 'legacy-adapter',
			'source_updated_at'  => '',
		);
		$clinic = apply_filters( self::CLINIC_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $clinic ) ) {
			return self::normalize_clinic( wp_parse_args( $clinic, $defaults ) );
		}
		$defaults['consultation_modes'] = array_values( array_filter( array(
			'1' === (string) DDD_Helpers::meta( $user_id, 'online_available', '0' ) ? 'online' : '',
			'1' === (string) DDD_Helpers::meta( $user_id, 'in_person_available', '0' ) ? 'in-person' : '',
		) ) );
		$defaults['accepting_patients'] = '1' === (string) DDD_Helpers::meta( $user_id, 'accepting_patients', '0' );
		$defaults['fee_min'] = DDD_Helpers::decimal_or_null( DDD_Helpers::meta( $user_id, 'consultation_fee', null ) );
		$defaults['fee_max'] = $defaults['fee_min'];
		$defaults['currency'] = strtoupper( sanitize_text_field( (string) DDD_Helpers::meta( $user_id, 'fee_currency', '' ) ) );
		$defaults['availability_label'] = sanitize_text_field( (string) DDD_Helpers::meta( $user_id, 'consultation_timings', '' ) );
		$map = (array) get_option( 'swc_page_map', array() );
		if ( ! empty( $map['clinic'] ) && 'publish' === get_post_status( absint( $map['clinic'] ) ) ) {
			$defaults['clinic_url'] = add_query_arg( 'doctor_id', $user_id, get_permalink( absint( $map['clinic'] ) ) );
		}
		if ( ! empty( $map['request'] ) && 'publish' === get_post_status( absint( $map['request'] ) ) ) {
			$defaults['appointment_url'] = add_query_arg( 'doctor_id', $user_id, get_permalink( absint( $map['request'] ) ) );
		}
		$defaults['public'] = (bool) ( $defaults['clinic_url'] || $defaults['appointment_url'] || $defaults['consultation_modes'] );
		return self::normalize_clinic( $defaults );
	}
}
