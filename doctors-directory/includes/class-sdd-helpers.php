<?php
defined( 'ABSPATH' ) || exit;

/**
 * Versioned cross-module contracts. File 07 owns only its public projection,
 * directory-specific governance and derivative operational records.
 */
final class DDD_Contracts {
	const IDENTITY_FILTER     = 'ddd_contract_identity_claims_v1';
	const VERIFICATION_FILTER = 'ddd_contract_verification_claims_v1';
	const PROFILE_FILTER      = 'ddd_contract_public_profile_v1';
	const CLINIC_FILTER       = 'ddd_contract_public_clinic_v1';
	const FOUNDER_FILTER      = 'ddd_contract_founder_v1';

	public static function dependency_health() {
		$mandatory = array(
			'file00' => self::identity_provider_available(),
			'file03' => self::profile_provider_available(),
			'file09' => self::verification_provider_available(),
		);
		$missing = array_keys( array_filter( $mandatory, static fn( $ready ) => ! $ready ) );
		$details = array(
			'file00' => $mandatory['file00'] ? 'available' : 'missing',
			'file03' => $mandatory['file03'] ? 'available' : 'missing',
			'file09' => $mandatory['file09'] ? 'available' : 'missing',
			'file08' => self::clinic_provider_available() ? 'available' : 'optional-unavailable',
		);

		if ( defined( 'SPD_VERSION' ) && version_compare( SPD_VERSION, DDD_MIN_FILE03_VERSION, '<' ) ) {
			return array(
				'ready'   => false,
				'code'    => 'file03_version_incompatible',
				'message' => sprintf( __( 'File 03 must be version %s or newer.', DDD_TEXT_DOMAIN ), DDD_MIN_FILE03_VERSION ),
				'details' => $details,
			);
		}

		if ( $missing ) {
			return array(
				'ready'   => false,
				'code'    => 'mandatory_contract_missing',
				'message' => sprintf(
					/* translators: %s: comma-separated file identifiers. */
					__( 'Mandatory owner contracts are unavailable (%s). Public eligibility fails closed until they are restored.', DDD_TEXT_DOMAIN ),
					implode( ', ', $missing )
				),
				'details' => $details,
			);
		}

		return array(
			'ready'   => true,
			'code'    => 'ok',
			'message' => __( 'Mandatory File 00, File 03 and File 09 contracts are available. File 08 clinic projection is optional.', DDD_TEXT_DOMAIN ),
			'details' => $details,
		);
	}

	private static function identity_provider_available() {
		return has_filter( self::IDENTITY_FILTER )
			|| defined( 'SMC_VERSION' )
			|| class_exists( 'SMC_Contracts' )
			|| false !== get_option( 'smc_db_version', false )
			|| false !== get_option( 'smc_version', false );
	}

	private static function verification_provider_available() {
		return has_filter( self::VERIFICATION_FILTER )
			|| defined( 'GDO_VERSION' )
			|| class_exists( 'GDO_Contracts' )
			|| class_exists( 'SPD_Helpers' )
			|| false !== get_option( 'gdo_db_version', false );
	}

	private static function profile_provider_available() {
		return has_filter( self::PROFILE_FILTER )
			|| class_exists( 'SPD_Helpers' )
			|| defined( 'SPD_VERSION' )
			|| false !== get_option( 'spd_db_version', false );
	}

	private static function clinic_provider_available() {
		return has_filter( self::CLINIC_FILTER )
			|| defined( 'WCA_VERSION' )
			|| class_exists( 'WCA_Contracts' )
			|| false !== get_option( 'wca_db_version', false );
	}

	public static function identity_claims( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'           => $user_id,
			'provider_available' => false,
			'account_active'    => false,
			'suspended'         => true,
			'risk_blocked'      => true,
			'age_eligible'      => false,
			'guardian_valid'    => false,
			'institutional'     => false,
			'claim_version'     => '',
			'source_updated_at' => '',
		);
		$claims = apply_filters( self::IDENTITY_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $claims ) ) {
			$claims = wp_parse_args( $claims, $defaults );
			$claims['provider_available'] = true;
			return self::normalize_identity( $claims );
		}

		if ( ! self::identity_provider_available() ) {
			return $defaults;
		}

		/*
		 * Compatibility adapter. It requires explicit positive canonical states;
		 * missing metadata never grants public eligibility.
		 */
		$status       = sanitize_key( (string) get_user_meta( $user_id, '_smc_membership_status', true ) );
		$suspension   = sanitize_key( (string) get_user_meta( $user_id, '_smc_suspension_status', true ) );
		$risk         = sanitize_key( (string) get_user_meta( $user_id, '_smc_risk_status', true ) );
		$age_status   = sanitize_key( (string) get_user_meta( $user_id, '_smc_age_eligibility', true ) );
		$guardian     = sanitize_key( (string) get_user_meta( $user_id, '_smc_guardian_status', true ) );
		$active       = in_array( $status, array( 'approved', 'active', 'verified', 'institutional' ), true );
		$not_suspended= in_array( $suspension, array( '', 'none', 'clear', 'active' ), true );
		$risk_clear   = in_array( $risk, array( '', 'none', 'clear', 'low', 'approved' ), true );
		$age_ok       = in_array( $age_status, array( 'eligible', 'adult', 'approved', 'verified' ), true );
		$guardian_ok  = in_array( $guardian, array( 'not-required', 'not_required', 'approved', 'verified', 'valid', 'adult' ), true );
		$institutional= DDD_Helpers::is_founder( $user_id ) || 'institutional' === $status;

		return self::normalize_identity(
			array_merge(
				$defaults,
				array(
					'provider_available' => true,
					'account_active'    => $active,
					'suspended'         => ! $not_suspended,
					'risk_blocked'      => ! $risk_clear,
					'age_eligible'      => $age_ok,
					'guardian_valid'    => $guardian_ok,
					'institutional'     => $institutional,
					'claim_version'     => 'file00-compat-v1',
				)
			)
		);
	}

	private static function normalize_identity( $claims ) {
		foreach ( array( 'provider_available', 'account_active', 'suspended', 'risk_blocked', 'age_eligible', 'guardian_valid', 'institutional' ) as $key ) {
			$claims[ $key ] = (bool) $claims[ $key ];
		}
		$claims['user_id'] = absint( $claims['user_id'] );
		$claims['claim_version'] = sanitize_text_field( (string) $claims['claim_version'] );
		$claims['source_updated_at'] = DDD_Helpers::mysql_datetime( $claims['source_updated_at'] );
		return $claims;
	}

	public static function verification_claims( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'           => $user_id,
			'provider_available' => false,
			'doctor'            => false,
			'verified'          => false,
			'status'            => 'unavailable',
			'effective_at'      => '',
			'expires_at'        => '',
			'decision_version'  => '',
			'source_updated_at' => '',
		);
		$claims = apply_filters( self::VERIFICATION_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $claims ) ) {
			$claims = wp_parse_args( $claims, $defaults );
			$claims['provider_available'] = true;
			return self::normalize_verification( $claims );
		}
		if ( ! self::verification_provider_available() ) {
			return $defaults;
		}

		$doctor = false;
		$status = '';
		if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'is_doctor' ) ) ) {
			$doctor = (bool) SPD_Helpers::is_doctor( $user_id );
		}
		if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'verification_status' ) ) ) {
			$status = sanitize_key( (string) SPD_Helpers::verification_status( $user_id ) );
		}
		if ( '' === $status ) {
			$status = sanitize_key( (string) get_user_meta( $user_id, '_gdo_verification_status', true ) );
		}
		if ( '' === $status ) {
			$status = sanitize_key( (string) get_user_meta( $user_id, '_spd_verification_status', true ) );
		}
		if ( ! $doctor ) {
			$doctor = 'verified' === $status && '1' === (string) get_user_meta( $user_id, '_spd_is_doctor', true );
		}
		$effective = (string) get_user_meta( $user_id, '_gdo_verified_at', true );
		if ( '' === $effective ) {
			$effective = (string) get_user_meta( $user_id, '_spd_verified_at', true );
		}
		return self::normalize_verification(
			array_merge(
				$defaults,
				array(
					'provider_available' => true,
					'doctor'            => $doctor,
					'verified'          => $doctor && 'verified' === $status,
					'status'            => $status ?: 'unverified',
					'effective_at'      => $effective,
					'expires_at'        => (string) get_user_meta( $user_id, '_gdo_verification_expires_at', true ),
					'decision_version'  => 'file09-compat-v1',
				)
			)
		);
	}

	private static function normalize_verification( $claims ) {
		$claims['user_id'] = absint( $claims['user_id'] );
		$claims['provider_available'] = (bool) $claims['provider_available'];
		$claims['doctor'] = (bool) $claims['doctor'];
		$claims['status'] = sanitize_key( (string) $claims['status'] );
		$claims['verified'] = (bool) $claims['verified'] && $claims['doctor'] && in_array( $claims['status'], array( 'verified', 'approved', 'active' ), true );
		$claims['effective_at'] = DDD_Helpers::mysql_datetime( $claims['effective_at'] );
		$claims['expires_at'] = DDD_Helpers::mysql_datetime( $claims['expires_at'] );
		$claims['decision_version'] = sanitize_text_field( (string) $claims['decision_version'] );
		$claims['source_updated_at'] = DDD_Helpers::mysql_datetime( $claims['source_updated_at'] );
		return $claims;
	}

	public static function public_profile( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'           => $user_id,
			'provider_available' => false,
			'public_id'         => '',
			'public'            => false,
			'discoverable'      => false,
			'display_name'      => '',
			'professional_title'=> '',
			'specialty'         => '',
			'country'           => '',
			'city'              => '',
			'languages'         => array(),
			'qualification'     => '',
			'experience_years'  => 0,
			'avatar_id'         => 0,
			'profile_url'       => '',
			'phone_public'      => false,
			'phone'             => '',
			'whatsapp_public'   => false,
			'whatsapp'          => '',
			'consent_version'   => '',
			'profile_version'   => '',
			'source_updated_at' => '',
		);
		$profile = apply_filters( self::PROFILE_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $profile ) ) {
			$profile = wp_parse_args( $profile, $defaults );
			$profile['provider_available'] = true;
			return self::normalize_profile( $profile );
		}
		if ( ! self::profile_provider_available() ) {
			return $defaults;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array_merge( $defaults, array( 'provider_available' => true ) );
		}
		$get = static function ( $key, $default = '' ) use ( $user_id ) {
			if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'get' ) ) ) {
				return SPD_Helpers::get( $user_id, $key, $default );
			}
			$value = get_user_meta( $user_id, '_spd_' . $key, true );
			return '' === $value ? $default : $value;
		};
		$discoverable = (string) DDD_Helpers::meta( $user_id, 'discoverable', '' );
		$public_state = sanitize_key( (string) $get( 'visibility', '' ) );
		$explicit_public = '1' === $discoverable && in_array( $public_state, array( '', 'public', 'published' ), true );
		$profile = array_merge(
			$defaults,
			array(
				'provider_available' => true,
				'public_id'         => (string) get_user_meta( $user_id, '_ddd_public_id', true ),
				'public'            => $explicit_public,
				'discoverable'      => $explicit_public,
				'display_name'      => (string) $user->display_name,
				'professional_title'=> (string) $get( 'headline', $get( 'specialty', '' ) ),
				'specialty'         => (string) $get( 'specialty', '' ),
				'country'           => (string) $get( 'country', '' ),
				'city'              => (string) $get( 'city', '' ),
				'languages'         => $get( 'languages', '' ),
				'qualification'     => (string) $get( 'qualification', '' ),
				'experience_years'  => absint( $get( 'experience_years', 0 ) ),
				'avatar_id'         => absint( $get( 'profile_photo_id', 0 ) ),
				'profile_url'       => class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'profile_url' ) ) ? (string) SPD_Helpers::profile_url( $user_id ) : '',
				'phone'             => (string) $get( 'phone', '' ),
				'whatsapp'          => (string) $get( 'whatsapp', '' ),
				'phone_public'      => '1' === (string) DDD_Helpers::meta( $user_id, 'public_phone', '0' ),
				'whatsapp_public'   => '1' === (string) DDD_Helpers::meta( $user_id, 'public_whatsapp', '0' ),
				'consent_version'   => sanitize_text_field( (string) DDD_Helpers::meta( $user_id, 'consent_version', '' ) ),
				'profile_version'   => 'file03-compat-v1',
			)
		);
		return self::normalize_profile( $profile );
	}

	private static function normalize_profile( $profile ) {
		$profile['user_id'] = absint( $profile['user_id'] );
		$profile['provider_available'] = (bool) $profile['provider_available'];
		$profile['public'] = (bool) $profile['public'];
		$profile['discoverable'] = (bool) $profile['discoverable'];
		$profile['phone_public'] = (bool) $profile['phone_public'];
		$profile['whatsapp_public'] = (bool) $profile['whatsapp_public'];
		$profile['languages'] = DDD_Helpers::list_value( $profile['languages'] );
		$profile['experience_years'] = min( 100, absint( $profile['experience_years'] ) );
		$profile['avatar_id'] = absint( $profile['avatar_id'] );
		foreach ( array( 'display_name', 'professional_title', 'specialty', 'country', 'city', 'qualification', 'consent_version', 'profile_version' ) as $key ) {
			$profile[ $key ] = sanitize_text_field( (string) $profile[ $key ] );
		}
		$profile['public_id'] = DDD_Helpers::valid_public_id( $profile['public_id'] ) ? strtolower( (string) $profile['public_id'] ) : '';
		$profile['profile_url'] = DDD_Helpers::same_origin_url( $profile['profile_url'] );
		$profile['phone'] = $profile['phone_public'] ? sanitize_text_field( (string) $profile['phone'] ) : '';
		$profile['whatsapp'] = $profile['whatsapp_public'] ? sanitize_text_field( (string) $profile['whatsapp'] ) : '';
		$profile['source_updated_at'] = DDD_Helpers::mysql_datetime( $profile['source_updated_at'] );
		return $profile;
	}

	public static function public_clinic( $user_id ) {
		$user_id = absint( $user_id );
		$defaults = array(
			'user_id'            => $user_id,
			'provider_available'  => false,
			'public'              => false,
			'clinic_name'         => '',
			'clinic_url'          => '',
			'appointment_url'     => '',
			'consultation_modes'  => array(),
			'accepting_patients'  => false,
			'fee_min'             => null,
			'fee_max'             => null,
			'currency'            => '',
			'availability_label'  => '',
			'clinic_version'      => '',
			'source_updated_at'   => '',
		);
		$clinic = apply_filters( self::CLINIC_FILTER, null, $user_id, DDD_CONTRACT_VERSION );
		if ( is_array( $clinic ) ) {
			$clinic = wp_parse_args( $clinic, $defaults );
			$clinic['provider_available'] = true;
			return self::normalize_clinic( $clinic );
		}
		/* File 08 is optional. No speculative URL or direct foreign-page query is created. */
		return $defaults;
	}

	private static function normalize_clinic( $clinic ) {
		$clinic['user_id'] = absint( $clinic['user_id'] );
		$clinic['provider_available'] = (bool) $clinic['provider_available'];
		$clinic['public'] = (bool) $clinic['public'];
		$clinic['accepting_patients'] = (bool) $clinic['accepting_patients'];
		$clinic['consultation_modes'] = DDD_Helpers::consultation_modes( $clinic['consultation_modes'] );
		$clinic['fee_min'] = DDD_Helpers::decimal_or_null( $clinic['fee_min'] );
		$clinic['fee_max'] = DDD_Helpers::decimal_or_null( $clinic['fee_max'] );
		if ( null !== $clinic['fee_min'] && null !== $clinic['fee_max'] && $clinic['fee_max'] < $clinic['fee_min'] ) {
			$clinic['fee_max'] = $clinic['fee_min'];
		}
		$currency = strtoupper( sanitize_text_field( (string) $clinic['currency'] ) );
		$clinic['currency'] = preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : '';
		$clinic['clinic_name'] = sanitize_text_field( (string) $clinic['clinic_name'] );
		$clinic['availability_label'] = sanitize_text_field( (string) $clinic['availability_label'] );
		$clinic['clinic_version'] = sanitize_text_field( (string) $clinic['clinic_version'] );
		$clinic['clinic_url'] = $clinic['public'] ? DDD_Helpers::same_origin_url( $clinic['clinic_url'] ) : '';
		$clinic['appointment_url'] = $clinic['public'] ? DDD_Helpers::same_origin_url( $clinic['appointment_url'] ) : '';
		$clinic['source_updated_at'] = DDD_Helpers::mysql_datetime( $clinic['source_updated_at'] );
		return $clinic;
	}

	public static function founder() {
		$founder = apply_filters( self::FOUNDER_FILTER, null, DDD_CONTRACT_VERSION );
		if ( is_array( $founder ) ) {
			$founder = self::normalize_profile( wp_parse_args( $founder, self::public_profile( absint( $founder['user_id'] ?? 0 ) ) ) );
			return ! empty( $founder['public'] ) ? $founder : array();
		}
		$user_id = DDD_Helpers::founder_id();
		if ( ! $user_id ) {
			return array();
		}
		$identity = self::identity_claims( $user_id );
		$profile = self::public_profile( $user_id );
		if ( empty( $identity['provider_available'] ) || empty( $identity['institutional'] ) || empty( $profile['public'] ) ) {
			return array();
		}
		$profile['institutional'] = true;
		return $profile;
	}

	public static function eligibility( $user_id ) {
		$identity = self::identity_claims( $user_id );
		$verification = self::verification_claims( $user_id );
		$profile = self::public_profile( $user_id );
		$clinic = self::public_clinic( $user_id );
		$reasons = array();
		if ( empty( $identity['provider_available'] ) ) { $reasons[] = 'identity_contract_unavailable'; }
		if ( empty( $verification['provider_available'] ) ) { $reasons[] = 'verification_contract_unavailable'; }
		if ( empty( $profile['provider_available'] ) ) { $reasons[] = 'profile_contract_unavailable'; }
		if ( empty( $identity['account_active'] ) ) { $reasons[] = 'account_inactive'; }
		if ( ! empty( $identity['suspended'] ) ) { $reasons[] = 'account_suspended'; }
		if ( ! empty( $identity['risk_blocked'] ) ) { $reasons[] = 'risk_blocked'; }
		if ( empty( $identity['age_eligible'] ) ) { $reasons[] = 'age_ineligible'; }
		if ( empty( $identity['guardian_valid'] ) ) { $reasons[] = 'guardian_required'; }
		if ( empty( $verification['doctor'] ) ) { $reasons[] = 'not_doctor'; }
		if ( empty( $verification['verified'] ) ) { $reasons[] = 'not_verified'; }
		if ( ! empty( $verification['expires_at'] ) && strtotime( $verification['expires_at'] . ' UTC' ) <= time() ) { $reasons[] = 'verification_expired'; }
		if ( empty( $profile['public'] ) || empty( $profile['discoverable'] ) ) { $reasons[] = 'profile_private'; }
		if ( empty( $profile['display_name'] ) || empty( $profile['specialty'] ) || empty( $profile['country'] ) ) { $reasons[] = 'public_fields_incomplete'; }
		if ( empty( $profile['profile_url'] ) && empty( $clinic['clinic_url'] ) ) { $reasons[] = 'public_destination_missing'; }
		if ( DDD_Helpers::is_founder( $user_id ) ) { $reasons[] = 'founder_separate'; }
		$status = empty( $reasons ) ? 'eligible' : 'limited';
		if ( in_array( 'account_suspended', $reasons, true ) || in_array( 'risk_blocked', $reasons, true ) ) {
			$status = 'suspended';
		} elseif ( in_array( 'identity_contract_unavailable', $reasons, true ) || in_array( 'verification_contract_unavailable', $reasons, true ) || in_array( 'profile_contract_unavailable', $reasons, true ) ) {
			$status = 'unavailable';
		}
		return array(
			'eligible'     => empty( $reasons ),
			'status'       => $status,
			'reasons'      => array_values( array_unique( $reasons ) ),
			'identity'     => $identity,
			'verification' => $verification,
			'profile'      => $profile,
			'clinic'       => $clinic,
		);
	}
}

final class DDD_Helpers {
	const META = '_ddd_';
	const LEGACY_META = '_sdd_';

	public static function meta( $user_id, $key, $default = '' ) {
		$value = get_user_meta( absint( $user_id ), self::META . $key, true );
		if ( '' === $value ) {
			$value = get_user_meta( absint( $user_id ), self::LEGACY_META . $key, true );
		}
		return '' === $value ? $default : $value;
	}

	public static function set_meta( $user_id, $key, $value ) {
		return update_user_meta( absint( $user_id ), self::META . $key, $value );
	}

	public static function founder_id() {
		$founder_id = absint( get_option( 'smc_founder_user_id', 0 ) );
		if ( ! $founder_id ) {
			$founder_id = absint( get_option( 'spf_founder_user_id', 0 ) );
		}
		return $founder_id;
	}

	public static function is_founder( $user_id ) {
		return absint( $user_id ) > 0 && absint( $user_id ) === self::founder_id();
	}

	public static function valid_public_id( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value );
	}

	public static function uuid_from_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}
		$existing = (string) get_user_meta( $user_id, '_ddd_public_id', true );
		if ( self::valid_public_id( $existing ) ) {
			return strtolower( $existing );
		}
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$uuid = strtolower( wp_generate_uuid4() );
			if ( add_user_meta( $user_id, '_ddd_public_id', $uuid, true ) ) {
				return $uuid;
			}
			$existing = (string) get_user_meta( $user_id, '_ddd_public_id', true );
			if ( self::valid_public_id( $existing ) ) {
				return strtolower( $existing );
			}
		}
		return '';
	}

	public static function list_value( $value ) {
		$list = is_array( $value ) ? $value : preg_split( '/[,;\n|]+/u', (string) $value );
		$list = array_map( 'sanitize_text_field', array_map( 'trim', (array) $list ) );
		return array_slice( array_values( array_unique( array_filter( $list ) ) ), 0, 30 );
	}

	public static function consultation_modes( $value ) {
		$aliases = array(
			'online' => 'online', 'virtual' => 'online',
			'in-person' => 'in-person', 'in person' => 'in-person', 'clinic' => 'in-person',
			'video' => 'video', 'video-call' => 'video', 'video call' => 'video',
			'phone' => 'phone', 'telephone' => 'phone', 'audio-call' => 'phone',
			'chat' => 'chat', 'messaging' => 'chat',
			'home-visit' => 'home-visit', 'home visit' => 'home-visit',
		);
		$out = array();
		foreach ( self::list_value( $value ) as $mode ) {
			$key = strtolower( trim( preg_replace( '/\s+/u', ' ', str_replace( '_', '-', (string) $mode ) ) ) );
			$key = $aliases[ $key ] ?? sanitize_key( $key );
			if ( in_array( $key, array( 'online', 'in-person', 'video', 'phone', 'chat', 'home-visit' ), true ) ) { $out[] = $key; }
		}
		return array_values( array_unique( $out ) );
	}

	public static function decimal_or_null( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$value = trim( (string) $value );
		if ( ! preg_match( '/^\d{1,12}(?:\.\d{1,2})?$/', $value ) ) {
			return null;
		}
		return round( (float) $value, 2 );
	}

	public static function mysql_datetime( $value ) {
		if ( ! $value ) {
			return '';
		}
		$timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
	}

	public static function normalize_token( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = remove_accents( $value );
		$variants = array( $value );
		if ( class_exists( 'Transliterator' ) ) {
			$converted = transliterator_transliterate( 'Any-Latin; Latin-ASCII; Lower()', $value );
			if ( is_string( $converted ) && '' !== $converted ) {
				$variants[] = $converted;
			}
		}
		$value = implode( ' ', array_unique( $variants ) );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}

	public static function public_profile_url( $public_id ) {
		return home_url( user_trailingslashit( 'doctors/' . rawurlencode( (string) $public_id ) ) );
	}

	/**
	 * Accept only canonical same-origin HTTP(S) destinations. Owner contracts may
	 * supply routes, but File 07 must not publish or redirect to arbitrary hosts.
	 */
	public static function same_origin_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			$url = home_url( $url );
		}
		$url = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}
		$home = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $url );
		if ( ! is_array( $home ) || ! is_array( $target ) || empty( $home['host'] ) || empty( $target['host'] ) ) {
			return '';
		}
		$home_host = strtolower( rtrim( (string) $home['host'], '.' ) );
		$target_host = strtolower( rtrim( (string) $target['host'], '.' ) );
		$home_scheme = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$target_scheme = strtolower( (string) ( $target['scheme'] ?? '' ) );
		$home_port = absint( $home['port'] ?? ( 'https' === $home_scheme ? 443 : 80 ) );
		$target_port = absint( $target['port'] ?? ( 'https' === $target_scheme ? 443 : 80 ) );
		if ( ! hash_equals( $home_host, $target_host ) || ! hash_equals( $home_scheme, $target_scheme ) || $home_port !== $target_port ) {
			return '';
		}
		return $url;
	}

	public static function trace_id() {
		try {
			return bin2hex( random_bytes( 8 ) );
		} catch ( Exception $e ) {
			return substr( wp_hash( microtime( true ) . '|' . wp_rand() ), 0, 16 );
		}
	}

	public static function safe_error( $code, $message, $status = 400, $extra = array() ) {
		$trace_id = self::trace_id();
		if ( class_exists( 'DDD_Observability' ) ) {
			DDD_Observability::log( 'warning', $code, array( 'trace_id' => $trace_id, 'status' => absint( $status ) ) );
		}
		return new WP_Error( sanitize_key( $code ), $message, array_merge( array( 'status' => absint( $status ), 'trace_id' => $trace_id ), $extra ) );
	}

	public static function rate_limit( $scope, $actor, $limit, $window ) {
		global $wpdb;
		$limit = max( 1, absint( $limit ) );
		$window = max( 60, absint( $window ) );
		$bucket = (int) floor( time() / $window );
		$scope_key = hash_hmac( 'sha256', sanitize_key( $scope ) . '|' . sanitize_text_field( (string) $actor ), wp_salt( 'auth' ) );
		$table = $wpdb->prefix . 'ddd_rate_limits';
		$expires = gmdate( 'Y-m-d H:i:s', ( $bucket + 1 ) * $window + 60 );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (scope_key,bucket,request_count,expires_at,updated_at) VALUES (%s,%d,1,%s,%s)", $scope_key, $bucket, $expires, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 === $inserted ) {
			return true;
		}
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET request_count=request_count+1,updated_at=%s WHERE scope_key=%s AND bucket=%d AND request_count<%d", current_time( 'mysql', true ), $scope_key, $bucket, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 === wp_rand( 1, 100 ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at<%s LIMIT 1000", current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return 1 === $updated;
	}

	public static function filter_hash( $args ) {
		$keys = array( 'q', 'country', 'city', 'specialty', 'language', 'qualification', 'min_experience', 'mode', 'accepting', 'currency', 'fee_min', 'fee_max', 'featured_only', 'recent_only' );
		$clean = array();
		foreach ( $keys as $key ) {
			$value = $args[ $key ] ?? '';
			if ( in_array( $key, array( 'q', 'country', 'city', 'specialty', 'language', 'qualification' ), true ) ) {
				$value = self::normalize_token( $value );
			} elseif ( in_array( $key, array( 'min_experience', 'accepting', 'featured_only', 'recent_only' ), true ) ) {
				$value = absint( $value );
			} elseif ( in_array( $key, array( 'fee_min', 'fee_max' ), true ) ) {
				$value = self::decimal_or_null( $value );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			$clean[ $key ] = $value;
		}
		return hash_hmac( 'sha256', wp_json_encode( $clean ), wp_salt( 'nonce' ) );
	}

	public static function cursor_encode( $payload ) {
		$payload['iat'] = time();
		$payload['exp'] = time() + HOUR_IN_SECONDS;
		$json = wp_json_encode( $payload );
		$body = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
		$sig = hash_hmac( 'sha256', $body, wp_salt( 'nonce' ) );
		return $body . '.' . $sig;
	}

	public static function cursor_decode( $cursor, $expected_filter_hash = '' ) {
		$parts = explode( '.', (string) $cursor, 2 );
		if ( 2 !== count( $parts ) || ! preg_match( '/^[A-Za-z0-9_-]+$/', $parts[0] ) || ! preg_match( '/^[a-f0-9]{64}$/', $parts[1] ) ) {
			return array();
		}
		if ( ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
			return array();
		}
		$padding = ( 4 - strlen( $parts[0] ) % 4 ) % 4;
		$decoded = base64_decode( strtr( $parts[0] . str_repeat( '=', $padding ), '-_', '+/' ), true );
		$data = json_decode( (string) $decoded, true );
		if ( ! is_array( $data ) || empty( $data['exp'] ) || absint( $data['exp'] ) < time() ) {
			return array();
		}
		if ( $expected_filter_hash && ( empty( $data['fh'] ) || ! hash_equals( $expected_filter_hash, (string) $data['fh'] ) ) ) {
			return array();
		}
		return $data;
	}

	public static function initials( $name ) {
		$words = preg_split( '/\s+/u', trim( (string) $name ) );
		$out = '';
		foreach ( array_slice( array_filter( $words ), 0, 2 ) as $word ) {
			$out .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1, 'UTF-8' ) : substr( $word, 0, 1 );
		}
		return $out ?: 'DR';
	}

	public static function current_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	public static function idempotency_key( $request = null ) {
		$key = '';
		if ( $request instanceof WP_REST_Request ) {
			$key = (string) $request->get_header( 'Idempotency-Key' );
		}
		if ( '' === $key && isset( $_SERVER['HTTP_IDEMPOTENCY_KEY'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_SERVER['HTTP_IDEMPOTENCY_KEY'] ) );
		}
		return preg_match( '/^[A-Za-z0-9._:-]{8,128}$/', $key ) ? $key : '';
	}
}

if ( ! class_exists( 'SDD_Helpers' ) ) {
	class_alias( 'DDD_Helpers', 'SDD_Helpers' );
}
