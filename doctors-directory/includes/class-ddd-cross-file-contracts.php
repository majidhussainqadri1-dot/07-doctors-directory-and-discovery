<?php
defined( 'ABSPATH' ) || exit;

/**
 * Current owner-contract adapters for File 07.
 *
 * File 07 owns only the public searchable doctor projection. Identity, profile,
 * verification, clinic, ranking and notification truth remain in their
 * canonical owner files and are consumed here through published contracts.
 */
final class DDD_Cross_File_Contracts {
	const FILE19_PRODUCER = 'file07-doctor-discovery';
	const FILE19_EVENT    = 'DoctorDiscovery.SavedSearchMatched';

	/** @var callable|null */
	private static $file03_profile_provider = null;

	public static function register() {
		add_action( 'sabri_file07_register_profile_provider', array( __CLASS__, 'register_file03_profile_provider' ), 10, 2 );
		add_filter( DDD_Contracts::IDENTITY_FILTER, array( __CLASS__, 'identity_claims' ), 5, 3 );
		add_filter( DDD_Contracts::VERIFICATION_FILTER, array( __CLASS__, 'verification_claims' ), 5, 3 );
		add_filter( DDD_Contracts::PROFILE_FILTER, array( __CLASS__, 'profile_claims' ), 5, 3 );
		add_filter( DDD_Contracts::CLINIC_FILTER, array( __CLASS__, 'clinic_claims' ), 5, 3 );
		add_action( 'init', array( __CLASS__, 'register_notification_producer' ), 90 );
	}

	public static function identity_provider_available() {
		return function_exists( 'smc_membership_assertions' )
			&& defined( 'SMC_CONTRACT_VERSION' )
			&& version_compare( (string) SMC_CONTRACT_VERSION, DDD_MIN_FILE00_CONTRACT_VERSION, '>=' );
	}

	public static function verification_provider_available() {
		return class_exists( 'GDO_Integration_Contracts' )
			&& is_callable( array( 'GDO_Integration_Contracts', 'projection' ) )
			&& defined( 'GDO_Integration_Contracts::VERSION' )
			&& version_compare( (string) constant( 'GDO_Integration_Contracts::VERSION' ), DDD_MIN_FILE09_CONTRACT_VERSION, '>=' );
	}

	public static function profile_provider_available() {
		if ( ! defined( 'SPD_VERSION' ) || ! defined( 'SPD_CONTRACT_VERSION' )
			|| version_compare( (string) SPD_VERSION, DDD_MIN_FILE03_VERSION, '<' )
			|| version_compare( (string) SPD_CONTRACT_VERSION, DDD_MIN_FILE03_CONTRACT_VERSION, '<' )
		) {
			return false;
		}
		return is_callable( self::$file03_profile_provider ) || function_exists( 'spd_get_personal_site_profile' );
	}

	public static function clinic_provider_available() {
		return (bool) has_filter( 'sabri_file08_public_clinic_projection_v1' );
	}

	public static function register_file03_profile_provider( $owner, $callback ) {
		if ( 'file03' !== sanitize_key( (string) $owner ) || ! is_callable( $callback ) ) {
			return;
		}
		self::$file03_profile_provider = $callback;
	}

	private static function provider_failure( $provider, $surface, $exception = null ) {
		if ( class_exists( 'DDD_Observability' ) ) {
			DDD_Observability::record_health(
				sanitize_key( (string) $provider ),
				'degraded',
				'owner_contract_unavailable',
				array(
					'surface' => sanitize_key( (string) $surface ),
					'exception_class' => is_object( $exception ) ? sanitize_key( get_class( $exception ) ) : '',
				)
			);
		}
		do_action(
			'sabri_file24_directory_provider_failure',
			array(
				'owner' => 'file07',
				'provider' => sanitize_key( (string) $provider ),
				'surface' => sanitize_key( (string) $surface ),
				'exception_class' => is_object( $exception ) ? sanitize_key( get_class( $exception ) ) : '',
				'at' => gmdate( 'c' ),
			)
		);
	}

	private static function call( $provider, $surface, $callback, $fallback = null ) {
		try {
			return call_user_func( $callback );
		} catch ( Throwable $exception ) {
			self::provider_failure( $provider, $surface, $exception );
			return $fallback;
		}
	}

	public static function identity_claims( $current, $user_id, $contract_version ) {
		if ( is_array( $current ) ) {
			return $current;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id
			|| ! function_exists( 'smc_membership_assertions' )
			|| ! defined( 'SMC_CONTRACT_VERSION' )
			|| version_compare( (string) SMC_CONTRACT_VERSION, DDD_MIN_FILE00_CONTRACT_VERSION, '<' )
		) {
			return $current;
		}
		$raw = self::call( 'file00', 'directory_identity', static function () use ( $user_id ) {
			return smc_membership_assertions( $user_id );
		}, null );
		if ( ! is_array( $raw ) || absint( $raw['user_id'] ?? 0 ) !== $user_id ) {
			return array( 'user_id' => $user_id, 'provider_available' => false );
		}
		$status = sanitize_key( (string) ( $raw['status'] ?? '' ) );
		$blocked = in_array( $status, array( 'suspended', 'rejected', 'expired', 'appeal_review', 'erasure_pending', 'invalid_application', 'effects_reconciliation' ), true );
		$age = is_array( $raw['age_context'] ?? null ) ? $raw['age_context'] : array();
		$age_known = ! empty( $age['known'] );
		$minor = ! empty( $age['minor'] );
		$approved = ! empty( $raw['approved'] );
		$eligible = ! empty( $raw['eligible'] );
		return array(
			'user_id' => $user_id,
			'provider_available' => true,
			'account_active' => $approved && $eligible && ! $blocked,
			'suspended' => ! empty( $raw['suspended'] ) || $blocked,
			'risk_blocked' => $blocked,
			'age_eligible' => $age_known && ! $minor,
			'guardian_valid' => ! $minor || ! empty( $raw['guardian_verified'] ),
			'institutional' => ! empty( $raw['institutional_account'] ),
			'claim_version' => sanitize_text_field( (string) ( $raw['contract_version'] ?? $contract_version ) ),
			'source_updated_at' => '',
		);
	}

	public static function verification_claims( $current, $user_id, $contract_version ) {
		if ( is_array( $current ) ) {
			return $current;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id
			|| ! class_exists( 'GDO_Integration_Contracts' )
			|| ! is_callable( array( 'GDO_Integration_Contracts', 'projection' ) )
			|| ! defined( 'GDO_Integration_Contracts::VERSION' )
			|| version_compare( (string) constant( 'GDO_Integration_Contracts::VERSION' ), DDD_MIN_FILE09_CONTRACT_VERSION, '<' )
		) {
			return $current;
		}
		$raw = self::call( 'file09', 'directory_verification', static function () use ( $user_id ) {
			return GDO_Integration_Contracts::projection( $user_id, 'file07' );
		}, null );
		if ( is_wp_error( $raw ) || ! is_array( $raw ) || absint( $raw['user_id'] ?? 0 ) !== $user_id ) {
			return array( 'user_id' => $user_id, 'provider_available' => false );
		}
		$verified = ! empty( $raw['verified'] ) && ! empty( $raw['eligible'] ) && ! empty( $raw['authorization_rechecked'] );
		$status = sanitize_key( (string) ( $raw['state'] ?? ( $verified ? 'verified' : 'unverified' ) ) );
		return array(
			'user_id' => $user_id,
			'provider_available' => true,
			'doctor' => $verified,
			'verified' => $verified,
			'status' => $verified ? 'verified' : $status,
			'effective_at' => '',
			'expires_at' => sanitize_text_field( (string) ( $raw['verified_until'] ?? '' ) ),
			'decision_version' => sanitize_text_field( (string) ( $raw['version'] ?? '' ) ) . ':' . absint( $raw['application_version'] ?? 0 ) . ':' . absint( $raw['claim_version'] ?? 0 ),
			'source_updated_at' => sanitize_text_field( (string) ( $raw['checked_at'] ?? '' ) ),
		);
	}

	private static function file03_profile( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! defined( 'SPD_VERSION' )
			|| ! defined( 'SPD_CONTRACT_VERSION' )
			|| version_compare( (string) SPD_VERSION, DDD_MIN_FILE03_VERSION, '<' )
			|| version_compare( (string) SPD_CONTRACT_VERSION, DDD_MIN_FILE03_CONTRACT_VERSION, '<' )
		) {
			return null;
		}
		$provider = self::$file03_profile_provider;
		if ( ! is_callable( $provider ) && function_exists( 'spd_get_personal_site_profile' ) ) {
			$provider = 'spd_get_personal_site_profile';
		}
		if ( ! $user_id || ! is_callable( $provider ) ) {
			return null;
		}
		return self::call( 'file03', 'directory_profile', static function () use ( $provider, $user_id ) {
			return call_user_func( $provider, $user_id, 0 );
		}, null );
	}

	private static function first( $array, $keys, $default = '' ) {
		if ( ! is_array( $array ) ) {
			return $default;
		}
		foreach ( (array) $keys as $key ) {
			if ( array_key_exists( $key, $array ) && null !== $array[ $key ] && '' !== $array[ $key ] ) {
				return $array[ $key ];
			}
		}
		return $default;
	}

	public static function profile_claims( $current, $user_id, $contract_version ) {
		if ( is_array( $current ) ) {
			return $current;
		}
		$user_id = absint( $user_id );
		$dto = self::file03_profile( $user_id );
		if ( is_wp_error( $dto ) || ! is_array( $dto ) ) {
			return array( 'user_id' => $user_id, 'provider_available' => false );
		}
		$public_id = strtolower( sanitize_text_field( (string) ( $dto['public_id'] ?? '' ) ) );
		if ( ! DDD_Helpers::valid_public_id( $public_id ) ) {
			return array( 'user_id' => $user_id, 'provider_available' => true, 'public_id' => '', 'public' => false, 'discoverable' => false );
		}
		$professional = is_array( $dto['professional'] ?? null ) ? $dto['professional'] : array();
		$fields = is_array( $dto['fields'] ?? null ) ? $dto['fields'] : array();
		$contacts = is_array( $dto['contacts'] ?? null ) ? $dto['contacts'] : array();
		$media = is_array( $dto['media'] ?? null ) ? $dto['media'] : array();
		$avatar = is_array( $media['avatar'] ?? null ) ? $media['avatar'] : array();
		$state = sanitize_key( (string) ( $dto['state'] ?? 'published' ) );
		$public = ! in_array( $state, array( 'private', 'hidden', 'suspended', 'deleted', 'tombstoned', 'restricted' ), true );
		$discoverable = $public && '1' === (string) DDD_Helpers::meta( $user_id, 'discoverable', '0' );
		$phone = sanitize_text_field( (string) ( $contacts['phone'] ?? '' ) );
		$whatsapp = sanitize_text_field( (string) ( $contacts['whatsapp'] ?? '' ) );
		return array(
			'user_id' => $user_id,
			'provider_available' => true,
			'public_id' => $public_id,
			'public' => $public,
			'discoverable' => $discoverable,
			'display_name' => sanitize_text_field( (string) ( $dto['display_name'] ?? '' ) ),
			'professional_title' => sanitize_text_field( (string) self::first( $professional, array( 'professional_title', 'headline', 'title' ), '' ) ),
			'specialty' => sanitize_text_field( (string) self::first( $professional, array( 'specialty', 'specialization' ), '' ) ),
			'country' => sanitize_text_field( (string) self::first( $professional, array( 'country' ), self::first( $fields, array( 'country' ), '' ) ) ),
			'city' => sanitize_text_field( (string) self::first( $professional, array( 'city' ), self::first( $fields, array( 'city' ), '' ) ) ),
			'languages' => self::first( $professional, array( 'languages' ), self::first( $fields, array( 'languages' ), array() ) ),
			'qualification' => sanitize_text_field( (string) self::first( $professional, array( 'qualification', 'qualifications' ), '' ) ),
			'experience_years' => absint( self::first( $professional, array( 'experience_years', 'years_experience' ), 0 ) ),
			'avatar_id' => 0,
			'avatar_url' => DDD_Helpers::same_origin_url( (string) ( $avatar['url'] ?? '' ) ),
			'profile_url' => DDD_Helpers::same_origin_url( (string) ( $dto['canonical_url'] ?? '' ) ),
			'phone_public' => '' !== $phone,
			'phone' => $phone,
			'whatsapp_public' => '' !== $whatsapp,
			'whatsapp' => $whatsapp,
			'consent_version' => $discoverable ? 'file07-directory-consent-v1' : '',
			'profile_version' => 'file03:' . sanitize_text_field( (string) ( $dto['contract_version'] ?? $contract_version ) ) . ':' . absint( $dto['version'] ?? 0 ),
			'source_updated_at' => '',
		);
	}

	public static function clinic_claims( $current, $user_id, $contract_version ) {
		if ( is_array( $current ) ) {
			return $current;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id || ! has_filter( 'sabri_file08_public_clinic_projection_v1' ) ) {
			return $current;
		}

		$raw = self::call( 'file08', 'directory_clinic', static function () use ( $user_id, $contract_version ) {
			return apply_filters( 'sabri_file08_public_clinic_projection_v1', null, $user_id, 0, $contract_version );
		}, null );
		if ( is_wp_error( $raw ) || ! is_array( $raw ) || empty( $raw ) ) {
			return array( 'user_id' => $user_id, 'provider_available' => false );
		}
		if ( absint( $raw['doctor_user_id'] ?? 0 ) !== $user_id
			|| 'active' !== sanitize_key( (string) ( $raw['status'] ?? '' ) )
			|| 'public' !== sanitize_key( (string) ( $raw['visibility'] ?? '' ) )
		) {
			return array( 'user_id' => $user_id, 'provider_available' => true, 'public' => false );
		}

		return array(
			'user_id' => $user_id,
			'provider_available' => true,
			'public' => true,
			'clinic_name' => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
			'clinic_url' => DDD_Helpers::same_origin_url( (string) ( $raw['url'] ?? '' ) ),
			'appointment_url' => DDD_Helpers::same_origin_url( (string) ( $raw['appointment_url'] ?? '' ) ),
			'consultation_modes' => DDD_Helpers::consultation_modes( $raw['consultation_modes'] ?? array() ),
			'accepting_patients' => ! empty( $raw['accepting_patients'] ),
			'fee_min' => $raw['fee_min'] ?? null,
			'fee_max' => $raw['fee_max'] ?? null,
			'currency' => sanitize_text_field( (string) ( $raw['currency'] ?? '' ) ),
			'availability_label' => sanitize_text_field( (string) ( $raw['availability_label'] ?? '' ) ),
			'clinic_version' => sanitize_text_field( (string) ( $raw['owner_version'] ?? $raw['contract_version'] ?? $contract_version ) ),
			'source_updated_at' => sanitize_text_field( (string) ( $raw['generated_at'] ?? '' ) ),
		);
	}

	public static function register_notification_producer() {
		if ( ! function_exists( 'sun_register_notification_producer' ) ) {
			return false;
		}
		return (bool) self::call( 'file19', 'notification_registration', static function () {
			return sun_register_notification_producer(
				self::FILE19_PRODUCER,
				array(
					'owner' => 'File 07',
					'event_types' => array( self::FILE19_EVENT ),
					'schema_versions' => array( '1.0' ),
					'allowed_data_fields' => array( 'action_name', 'summary', 'object_name', 'search_id' ),
					'internal' => true,
				)
			);
		}, false );
	}

	public static function notify_saved_search_match( $recipient_user_id, $doctor, $search, $fingerprint ) {
		$recipient_user_id = absint( $recipient_user_id );
		if ( ! $recipient_user_id || ! is_array( $doctor ) || ! function_exists( 'sun_ingest_domain_event' ) ) {
			return new WP_Error( 'file19_notification_provider_unavailable', __( 'Notification delivery is temporarily unavailable.', DDD_TEXT_DOMAIN ) );
		}
		self::register_notification_producer();
		$public_id = sanitize_text_field( (string) ( $doctor['public_id'] ?? '' ) );
		$search_id = sanitize_key( (string) ( $search['id'] ?? '' ) );
		$label = sanitize_text_field( (string) ( $search['label'] ?? __( 'Saved doctor search', DDD_TEXT_DOMAIN ) ) );
		$event_id = 'file07.saved-search.' . $recipient_user_id . '.' . $search_id . '.' . sanitize_key( (string) $fingerprint );
		$event = array(
			'producer' => self::FILE19_PRODUCER,
			'owner' => 'File 07',
			'event_id' => $event_id,
			'event_type' => self::FILE19_EVENT,
			'schema_version' => '1.0',
			'occurred_at' => gmdate( 'c' ),
			'recipients' => array( array( 'user_id' => $recipient_user_id ) ),
			'subject' => array( 'type' => 'doctor', 'public_id' => $public_id ),
			'category' => 'search',
			'priority' => 'normal',
			'sensitivity' => 'standard',
			'deep_link' => home_url( '/doctors/' ),
			'data' => array(
				'action_name' => __( 'Saved doctor search match', DDD_TEXT_DOMAIN ),
				'summary' => sprintf( __( 'A verified doctor now matches “%s”.', DDD_TEXT_DOMAIN ), $label ),
				'object_name' => sanitize_text_field( (string) ( $doctor['display_name'] ?? '' ) ),
				'search_id' => $search_id,
			),
			'idempotency_key' => $event_id,
			'source_version' => DDD_VERSION,
		);
		return self::call( 'file19', 'saved_search_notification', static function () use ( $event ) {
			return sun_ingest_domain_event( $event );
		}, new WP_Error( 'file19_notification_failed', __( 'Notification delivery failed safely.', DDD_TEXT_DOMAIN ) ) );
	}
}
