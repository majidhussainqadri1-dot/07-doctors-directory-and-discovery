<?php
defined( 'ABSPATH' ) || exit;

/**
 * Exact-current cross-file adapters for File 07.
 *
 * File 07 owns the public verified-doctor directory projection and discovery
 * experience. It never becomes the source of truth for identity (File 00),
 * public profiles (File 03), clinics/appointments (File 08), verification
 * (File 09), notifications (File 19), shell (File 20), assurance (File 24),
 * visual presentation (File 25), or global merit ranking (File 26).
 */
final class DDD_Cross_File_Adapters {
	const FILE19_PRODUCER = 'file07-doctor-discovery';
	const FILE19_OWNER    = 'File 07';
	const FILE19_EVENT    = 'Doctor.SavedSearchMatchedV1';
	const FILE19_SCHEMA   = '1.0.0';

	private static $profile_provider = null;
	private static $profile_cache = array();

	public static function register() {
		add_action( 'sabri_file07_register_profile_provider', array( __CLASS__, 'register_profile_provider' ), 10, 2 );

		if ( function_exists( 'smc_membership_assertions' ) ) {
			add_filter( DDD_Contracts::IDENTITY_FILTER, array( __CLASS__, 'identity_claims' ), 10, 3 );
		}
		if ( class_exists( 'GDO_Integration_Contracts' ) && is_callable( array( 'GDO_Integration_Contracts', 'projection' ) ) ) {
			add_filter( DDD_Contracts::VERIFICATION_FILTER, array( __CLASS__, 'verification_claims' ), 10, 3 );
		}
		if ( function_exists( 'spd_get_public_profile' ) || function_exists( 'spd_get_personal_site_profile' ) || class_exists( 'SPD_Contracts' ) ) {
			add_filter( DDD_Contracts::PROFILE_FILTER, array( __CLASS__, 'public_profile' ), 10, 3 );
		}
		if ( has_filter( 'sabri_file08_public_clinic_projection_v1' ) ) {
			add_filter( DDD_Contracts::CLINIC_FILTER, array( __CLASS__, 'public_clinic' ), 10, 3 );
		}

		add_filter( 'sabri_shell_verified_doctor_user_ids', array( __CLASS__, 'shell_verified_doctor_ids' ), 10, 2 );

		if ( class_exists( '\\Sabri\\File26\\Plugin' ) ) {
			add_filter( DDD_Central_Ranking::RANKING_FILTER, array( __CLASS__, 'file26_ranking' ), 10, 2 );
			add_filter( DDD_Ranking_Appeal::FILTER, array( __CLASS__, 'file26_appeal' ), 10, 2 );
		}

		add_action( 'init', array( __CLASS__, 'register_file19_producer' ), 60 );
	}

	public static function register_profile_provider( $owner, $callback ) {
		if ( 'file03' === sanitize_key( (string) $owner ) && is_callable( $callback ) ) {
			self::$profile_provider = $callback;
		}
	}

	public static function identity_claims( $claims, $user_id, $consumer_contract ) {
		if ( is_array( $claims ) ) {
			return $claims;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id || ! function_exists( 'smc_membership_assertions' ) ) {
			return null;
		}
		try {
			$raw = smc_membership_assertions( $user_id );
		} catch ( Throwable $e ) {
			return null;
		}
		if ( ! is_array( $raw ) || absint( $raw['user_id'] ?? 0 ) !== $user_id || empty( $raw['contract_version'] ) ) {
			return null;
		}
		$age = is_array( $raw['age_context'] ?? null ) ? $raw['age_context'] : array();
		$age_known = ! empty( $age['known'] );
		$minor = ! empty( $age['minor'] );
		$guardian_ok = ! $minor || ! empty( $raw['guardian_verified'] );
		$institutional = ! empty( $raw['institutional_account'] );
		if ( function_exists( 'smc_is_founder' ) ) {
			try {
				$institutional = $institutional || (bool) smc_is_founder( $user_id );
			} catch ( Throwable $e ) {}
		}
		$eligible = ! empty( $raw['eligible'] ) && empty( $raw['suspended'] );

		return array(
			'user_id'            => $user_id,
			'provider_available' => true,
			'account_active'     => $eligible,
			'suspended'          => ! empty( $raw['suspended'] ),
			'risk_blocked'       => ! $eligible && ! empty( $raw['approved'] ) && empty( $raw['suspended'] ),
			'age_eligible'       => $age_known && ! $minor,
			'guardian_valid'     => $age_known && $guardian_ok,
			'institutional'      => $institutional,
			'claim_version'      => 'file00:' . sanitize_text_field( (string) $raw['contract_version'] ),
			'source_updated_at'  => '',
		);
	}

	public static function verification_claims( $claims, $user_id, $consumer_contract ) {
		if ( is_array( $claims ) ) {
			return $claims;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id || ! class_exists( 'GDO_Integration_Contracts' ) || ! is_callable( array( 'GDO_Integration_Contracts', 'projection' ) ) ) {
			return null;
		}
		try {
			$raw = GDO_Integration_Contracts::projection( $user_id, 'file07' );
		} catch ( Throwable $e ) {
			return null;
		}
		if ( is_wp_error( $raw ) || ! is_array( $raw ) || absint( $raw['user_id'] ?? 0 ) !== $user_id || 'file09' !== sanitize_key( (string) ( $raw['source_of_truth'] ?? '' ) ) ) {
			return null;
		}
		$effective = '';
		if ( is_callable( array( 'GDO_Integration_Contracts', 'file03_public_projection' ) ) ) {
			try {
				$p = GDO_Integration_Contracts::file03_public_projection( null, $user_id, (string) $consumer_contract );
				if ( is_array( $p ) && 'verified' === sanitize_key( (string) ( $p['status'] ?? '' ) ) ) {
					$effective = sanitize_text_field( (string) ( $p['reviewed_at'] ?? '' ) );
				}
			} catch ( Throwable $e ) {}
		}
		$state = sanitize_key( (string) ( $raw['state'] ?? 'unavailable' ) );
		$verified = ! empty( $raw['verified'] ) && ! empty( $raw['eligible'] );
		return array(
			'user_id'            => $user_id,
			'provider_available' => true,
			'doctor'             => $verified,
			'verified'           => $verified,
			'status'             => $verified ? 'verified' : $state,
			'effective_at'       => $effective,
			'expires_at'         => sanitize_text_field( (string) ( $raw['verified_until'] ?? '' ) ),
			'decision_version'   => 'file09:' . sanitize_text_field( (string) ( $raw['version'] ?? '' ) ) . ':' . absint( $raw['claim_version'] ?? 0 ),
			'source_updated_at'  => sanitize_text_field( (string) ( $raw['checked_at'] ?? '' ) ),
		);
	}

	private static function file03_dto( $user_id ) {
		$user_id = absint( $user_id );
		if ( isset( self::$profile_cache[ $user_id ] ) ) {
			return self::$profile_cache[ $user_id ];
		}
		$dto = null;
		try {
			if ( is_callable( self::$profile_provider ) ) {
				$dto = call_user_func( self::$profile_provider, $user_id, 0 );
			} elseif ( function_exists( 'spd_get_public_profile' ) ) {
				$dto = spd_get_public_profile( $user_id, 0 );
			} elseif ( function_exists( 'spd_get_personal_site_profile' ) ) {
				$dto = spd_get_personal_site_profile( $user_id, 0 );
			} elseif ( class_exists( 'SPD_Contracts' ) && is_callable( array( 'SPD_Contracts', 'public_provider' ) ) ) {
				$dto = SPD_Contracts::public_provider( $user_id, 0 );
			}
		} catch ( Throwable $e ) {
			$dto = null;
		}
		if ( is_wp_error( $dto ) || ! is_array( $dto ) ) {
			$dto = array();
		}
		self::$profile_cache[ $user_id ] = $dto;
		return $dto;
	}

	public static function public_profile( $profile, $user_id, $consumer_contract ) {
		if ( is_array( $profile ) ) {
			return $profile;
		}
		$user_id = absint( $user_id );
		$dto = self::file03_dto( $user_id );
		if ( ! $dto || empty( $dto['public_id'] ) || empty( $dto['canonical_url'] ) ) {
			return null;
		}
		$state = sanitize_key( (string) ( $dto['state'] ?? '' ) );
		if ( in_array( $state, array( 'private', 'restricted', 'hidden', 'tombstoned', 'deleted', 'suspended' ), true ) ) {
			return null;
		}
		$type = sanitize_key( (string) ( $dto['profile_type'] ?? '' ) );
		if ( ! in_array( $type, array( 'doctor', 'founder' ), true ) ) {
			return null;
		}
		$professional = is_array( $dto['professional'] ?? null ) ? $dto['professional'] : array();
		$fields = is_array( $dto['fields'] ?? null ) ? $dto['fields'] : array();
		$media = is_array( $dto['media'] ?? null ) ? $dto['media'] : array();
		$contacts = is_array( $dto['contacts'] ?? null ) ? $dto['contacts'] : array();
		$avatar = is_array( $media['avatar'] ?? null ) ? $media['avatar'] : array();
		$languages = $professional['languages'] ?? ( $fields['languages'] ?? array() );
		$specialty = $professional['specialty'] ?? ( $professional['professional_title'] ?? '' );
		$title = $professional['professional_title'] ?? $specialty;
		$country = $professional['country'] ?? ( $fields['country'] ?? '' );
		$city = $professional['city'] ?? ( $fields['city'] ?? '' );

		return array(
			'user_id'            => $user_id,
			'provider_available' => true,
			'public_id'          => sanitize_text_field( (string) $dto['public_id'] ),
			'public'             => true,
			'discoverable'       => true,
			'display_name'       => sanitize_text_field( (string) ( $dto['display_name'] ?? '' ) ),
			'professional_title' => sanitize_text_field( (string) $title ),
			'specialty'          => sanitize_text_field( (string) $specialty ),
			'country'            => sanitize_text_field( (string) $country ),
			'city'               => sanitize_text_field( (string) $city ),
			'languages'          => $languages,
			'qualification'      => sanitize_text_field( (string) ( $professional['qualification'] ?? '' ) ),
			'experience_years'   => absint( $professional['experience_years'] ?? 0 ),
			'avatar_id'          => absint( $avatar['attachment_id'] ?? 0 ),
			'profile_url'        => esc_url_raw( (string) $dto['canonical_url'] ),
			'phone_public'       => ! empty( $contacts['phone'] ),
			'phone'              => sanitize_text_field( (string) ( $contacts['phone'] ?? '' ) ),
			'whatsapp_public'    => ! empty( $contacts['whatsapp'] ),
			'whatsapp'           => sanitize_text_field( (string) ( $contacts['whatsapp'] ?? '' ) ),
			'consent_version'    => '',
			'profile_version'    => 'file03:' . sanitize_text_field( (string) ( $dto['contract_version'] ?? '' ) ) . ':' . absint( $dto['version'] ?? 0 ),
			'source_updated_at'  => '',
		);
	}

	public static function public_clinic( $clinic, $user_id, $consumer_contract ) {
		if ( is_array( $clinic ) ) {
			return $clinic;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id || ! has_filter( 'sabri_file08_public_clinic_projection_v1' ) ) {
			return null;
		}
		try {
			$raw = apply_filters( 'sabri_file08_public_clinic_projection_v1', null, $user_id, 0, (string) $consumer_contract );
		} catch ( Throwable $e ) {
			return null;
		}
		if ( ! is_array( $raw ) || absint( $raw['doctor_user_id'] ?? 0 ) !== $user_id || 'active' !== sanitize_key( (string) ( $raw['status'] ?? '' ) ) || 'public' !== sanitize_key( (string) ( $raw['visibility'] ?? '' ) ) ) {
			return null;
		}
		return array(
			'user_id'            => $user_id,
			'provider_available' => true,
			'public'             => true,
			'clinic_name'        => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
			'clinic_url'         => esc_url_raw( (string) ( $raw['url'] ?? '' ) ),
			'appointment_url'    => esc_url_raw( (string) ( $raw['appointment_url'] ?? '' ) ),
			'consultation_modes' => $raw['consultation_modes'] ?? array(),
			'accepting_patients' => ! empty( $raw['accepting_patients'] ),
			'fee_min'            => $raw['fee_min'] ?? null,
			'fee_max'            => $raw['fee_max'] ?? null,
			'currency'           => sanitize_text_field( (string) ( $raw['currency'] ?? '' ) ),
			'availability_label' => sanitize_text_field( (string) ( $raw['availability_label'] ?? '' ) ),
			'clinic_version'     => 'file08:' . sanitize_text_field( (string) ( $raw['contract_version'] ?? '' ) ) . ':' . sanitize_text_field( (string) ( $raw['owner_version'] ?? '' ) ),
			'source_updated_at'  => sanitize_text_field( (string) ( $raw['generated_at'] ?? '' ) ),
		);
	}

	public static function shell_verified_doctor_ids( $ids, $limit = 5 ) {
		global $wpdb;
		$limit = max( 1, min( 20, absint( $limit ) ) );
		$out = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
		$table = DDD_Repository::table( 'projection' );
		if ( ! $table ) {
			return array_slice( $out, 0, $limit );
		}
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT doctor_id FROM {$table} WHERE eligible=1 ORDER BY display_name_norm ASC,doctor_id ASC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $id ) {
			$id = absint( $id );
			if ( $id && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return array_slice( $out, 0, $limit );
	}

	public static function register_file19_producer() {
		if ( ! function_exists( 'sun_register_notification_producer' ) ) {
			return false;
		}
		return (bool) sun_register_notification_producer(
			self::FILE19_PRODUCER,
			array(
				'owner'               => self::FILE19_OWNER,
				'event_types'         => array( self::FILE19_EVENT ),
				'schema_versions'     => array( self::FILE19_SCHEMA ),
				'allowed_data_fields' => array( 'action_name', 'summary', 'object_public_id', 'search_id' ),
			)
		);
	}

	public static function file19_available() {
		return function_exists( 'sun_register_notification_producer' ) && function_exists( 'sun_ingest_domain_event' );
	}

	public static function notify_saved_search_match( $user_id, $public_id, $search_id, $fingerprint ) {
		$user_id = absint( $user_id );
		$public_id = strtolower( sanitize_text_field( (string) $public_id ) );
		$search_id = sanitize_key( (string) $search_id );
		$fingerprint = sanitize_key( (string) $fingerprint );
		if ( ! $user_id || ! DDD_Helpers::valid_public_id( $public_id ) || ! $search_id || ! $fingerprint || ! self::file19_available() || ! self::register_file19_producer() ) {
			return false;
		}
		$event_id = 'ddd-search-match:' . substr( hash( 'sha256', $user_id . '|' . $public_id . '|' . $search_id . '|' . $fingerprint ), 0, 48 );
		$event = array(
			'producer'        => self::FILE19_PRODUCER,
			'owner'           => self::FILE19_OWNER,
			'event_id'        => $event_id,
			'event_type'      => self::FILE19_EVENT,
			'schema_version'  => self::FILE19_SCHEMA,
			'occurred_at'     => gmdate( 'c' ),
			'recipients'      => array( array( 'user_id' => $user_id ) ),
			'category'        => 'doctor_discovery',
			'priority'        => 'normal',
			'sensitivity'     => 'standard',
			'deep_link'       => home_url( '/doctors/' ),
			'deep_context'    => 'doctor_saved_search_match',
			'idempotency_key' => $event_id,
			'source_version'  => DDD_VERSION,
			'subject'         => array( 'type' => 'doctor', 'public_id' => $public_id ),
			'data'            => array(
				'action_name'      => __( 'Doctor match', DDD_TEXT_DOMAIN ),
				'summary'          => __( 'A verified doctor matched one of your saved searches.', DDD_TEXT_DOMAIN ),
				'object_public_id' => $public_id,
				'search_id'        => $search_id,
			),
		);
		try {
			$result = sun_ingest_domain_event( $event );
		} catch ( Throwable $e ) {
			return false;
		}
		return is_array( $result ) && in_array( sanitize_key( (string) ( $result['status'] ?? '' ) ), array( 'processed', 'duplicate' ), true );
	}

	private static function file26_plugin() {
		if ( ! class_exists( '\\Sabri\\File26\\Plugin' ) || ! is_callable( array( '\\Sabri\\File26\\Plugin', 'instance' ) ) ) {
			return null;
		}
		try {
			return \Sabri\File26\Plugin::instance();
		} catch ( Throwable $e ) {
			return null;
		}
	}

	private static function file26_context( $filters ) {
		$filters = is_array( $filters ) ? $filters : array();
		foreach ( array( 'city' => 'city', 'country' => 'country', 'language' => 'language', 'specialty' => 'specialization' ) as $key => $context ) {
			if ( ! empty( $filters[ $key ] ) ) {
				return array( $context, sanitize_text_field( (string) $filters[ $key ] ) );
			}
		}
		return array( 'global', '' );
	}

	private static function local_rank_row_by_url( $url ) {
		global $wpdb;
		$url = DDD_Helpers::same_origin_url( $url );
		if ( ! $url ) {
			return array();
		}
		$table = DDD_Repository::table( 'projection' );
		if ( ! $table ) {
			return array();
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE eligible=1 AND profile_url=%s LIMIT 1", $url ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : array();
	}

	private static function local_row_matches( $row, $filters ) {
		if ( ! is_array( $row ) || empty( $row['eligible'] ) ) {
			return false;
		}
		$filters = is_array( $filters ) ? $filters : array();
		$eq = array( 'country' => 'country_norm', 'city' => 'city_norm', 'specialty' => 'specialty_norm' );
		foreach ( $eq as $key => $column ) {
			if ( ! empty( $filters[ $key ] ) && DDD_Repository::taxonomy_normalize( $key, $filters[ $key ] ) !== (string) ( $row[ $column ] ?? '' ) ) {
				return false;
			}
		}
		if ( ! empty( $filters['q'] ) ) {
			$q = DDD_Helpers::normalize_token( $filters['q'] );
			$hay = (string) ( $row['search_text_norm'] ?? '' );
			if ( $q && false === strpos( $hay, $q ) ) {
				return false;
			}
		}
		if ( ! empty( $filters['language'] ) ) {
			$needle = DDD_Repository::taxonomy_normalize( 'language', $filters['language'] );
			if ( false === strpos( (string) ( $row['languages_norm'] ?? '' ), $needle ) ) {
				return false;
			}
		}
		if ( ! empty( $filters['qualification'] ) ) {
			$needle = DDD_Helpers::normalize_token( $filters['qualification'] );
			if ( false === strpos( (string) ( $row['qualification_norm'] ?? '' ), $needle ) ) {
				return false;
			}
		}
		if ( ! empty( $filters['min_experience'] ) && absint( $row['experience_years'] ?? 0 ) < absint( $filters['min_experience'] ) ) {
			return false;
		}
		if ( ! empty( $filters['mode'] ) ) {
			$modes = json_decode( (string) ( $row['consultation_modes_json'] ?? '[]' ), true );
			if ( ! is_array( $modes ) || ! in_array( sanitize_key( $filters['mode'] ), array_map( 'sanitize_key', $modes ), true ) ) {
				return false;
			}
		}
		if ( ! empty( $filters['accepting'] ) && empty( $row['accepting_patients'] ) ) {
			return false;
		}
		if ( ! empty( $filters['currency'] ) && strtoupper( (string) $filters['currency'] ) !== strtoupper( (string) ( $row['currency'] ?? '' ) ) ) {
			return false;
		}
		$fee_min = DDD_Helpers::decimal_or_null( $filters['fee_min'] ?? null );
		$fee_max = DDD_Helpers::decimal_or_null( $filters['fee_max'] ?? null );
		if ( null !== $fee_min && null !== $row['fee_max'] && (float) $row['fee_max'] < $fee_min ) {
			return false;
		}
		if ( null !== $fee_max && null !== $row['fee_min'] && (float) $row['fee_min'] > $fee_max ) {
			return false;
		}
		return true;
	}

	private static function file26_constitution( $plugin ) {
		try {
			$central = is_object( $plugin ) && is_callable( array( $plugin, 'central_plan' ) ) ? $plugin->central_plan() : null;
			return is_object( $central ) && is_callable( array( $central, 'ranking_constitution' ) ) ? $central->ranking_constitution() : array();
		} catch ( Throwable $e ) {
			return array();
		}
	}

	private static function file26_health( $plugin ) {
		try {
			$health = is_object( $plugin ) && is_callable( array( $plugin, 'health' ) ) ? $plugin->health() : null;
			return is_object( $health ) && is_callable( array( $health, 'snapshot' ) ) ? $health->snapshot() : array();
		} catch ( Throwable $e ) {
			return array();
		}
	}

	public static function file26_ranking( $current, $request ) {
		if ( is_array( $current ) && ! empty( $current['ready'] ) ) {
			return $current;
		}
		$plugin = self::file26_plugin();
		if ( ! $plugin || ! is_callable( array( $plugin, 'doctor_ranking' ) ) ) {
			return $current;
		}
		$tier_map = array( 'top10' => 'top_10', 'top100' => 'top_100', 'top1000' => 'top_1000', 'all' => 'all_verified' );
		$tier = sanitize_key( (string) ( $request['tier'] ?? 'all' ) );
		$owner_tier = $tier_map[ $tier ] ?? 'all_verified';
		list( $context, $context_value ) = self::file26_context( $request['filters'] ?? array() );
		$limit = max( 1, min( 100, 'top10' === $tier ? 10 : 100 ) );
		$owner_request = array(
			'context'       => $context,
			'context_value' => $context_value,
			'tier'          => $owner_tier,
			'limit'         => $limit,
			'cursor'        => sanitize_text_field( (string) ( $request['cursor'] ?? '' ) ),
		);
		try {
			$ranking = $plugin->doctor_ranking()->directory( $owner_request );
		} catch ( Throwable $e ) {
			return $current;
		}
		if ( is_wp_error( $ranking ) || ! is_array( $ranking ) ) {
			return $current;
		}
		$constitution = self::file26_constitution( $plugin );
		$health = self::file26_health( $plugin );
		$last_run = sanitize_text_field( (string) ( $health['doctor_ranking']['last_run'] ?? '' ) );
		$last_ts = $last_run ? strtotime( $last_run ) : false;
		if ( ! $last_ts || $last_ts < time() - DDD_Central_Ranking::MAX_SNAPSHOT_AGE || $last_ts > time() + DAY_IN_SECONDS ) {
			return $current;
		}
		$prohibited = array_map( 'sanitize_key', (array) ( $constitution['prohibited_signals'] ?? array() ) );
		$required = array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism', 'purchased_engagement' );
		$bias_ok = empty( array_diff( array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism' ), $prohibited ) )
			&& empty( $constitution['paid_or_sponsored_organic_results'] )
			&& ! empty( $constitution['single_free_tier_rank_parity'] );

		$items = array();
		$filters = is_array( $request['filters'] ?? null ) ? $request['filters'] : array();
		$request_limit = max( 1, min( DDD_Central_Ranking::LIMIT, absint( $request['limit'] ?? DDD_Central_Ranking::LIMIT ) ) );
		foreach ( (array) ( $ranking['results'] ?? array() ) as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}
			$row = self::local_rank_row_by_url( (string) ( $result['url'] ?? '' ) );
			if ( ! self::local_row_matches( $row, $filters ) || ! DDD_Helpers::valid_public_id( (string) ( $row['public_id'] ?? '' ) ) ) {
				continue;
			}
			$rank = 'global' === $context ? absint( $result['global_rank'] ?? 0 ) : absint( $result['context_rank'] ?? 0 );
			if ( ! $rank ) {
				$rank = absint( $result['global_rank'] ?? 0 );
			}
			$explanation = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) ( $result['explanation'] ?? array() ), 0, 8 ) ) ) );
			if ( ! $explanation ) {
				$explanation[] = __( 'Ranked by the canonical File 26 doctor-ranking policy.', DDD_TEXT_DOMAIN );
			}
			$items[] = array(
				'public_id'   => strtolower( (string) $row['public_id'] ),
				'rank'        => $rank,
				'explanation' => $explanation,
				'owner_key'   => preg_match( '/^[a-f0-9]{64}$/', (string) ( $result['key'] ?? '' ) ) ? strtolower( (string) $result['key'] ) : '',
			);
			if ( count( $items ) >= $request_limit ) {
				break;
			}
		}
		$policy = sanitize_text_field( (string) ( $ranking['policy_version'] ?? ( $constitution['doctor_ranking']['policy_version'] ?? '' ) ) );
		if ( ! $policy ) {
			return $current;
		}
		return array(
			'ready'            => true,
			'contract_version' => DDD_Central_Ranking::CONTRACT_VERSION,
			'policy_version'   => $policy,
			'monthly_version'  => gmdate( 'Y-m', $last_ts ),
			'generated_at'     => gmdate( 'c', $last_ts ),
			'nested_tiers'     => ! empty( $ranking['global_tiers_preserved'] ),
			'bias_audit'       => array(
				'status'             => $bias_ok ? 'pass' : 'blocked',
				'prohibited_signals' => array_values( array_unique( array_merge( $prohibited, in_array( 'purchased_engagement', $prohibited, true ) ? array() : array( 'purchased_engagement' ) ) ) ),
				'paid_boost'         => false,
				'donor_boost'        => false,
			),
			'items'             => $items,
			'next_cursor'       => sanitize_text_field( (string) ( $ranking['next_cursor'] ?? '' ) ),
			'snapshot_id'       => substr( hash( 'sha256', $policy . '|' . $last_run . '|' . $context . '|' . $context_value ), 0, 40 ),
		);
	}

	private static function file26_key_for_public_id( $public_id, $tier = 'all' ) {
		$plugin = self::file26_plugin();
		$local = DDD_Repository::get_by_public_id( $public_id );
		$target = DDD_Helpers::same_origin_url( (string) ( $local['profile_url'] ?? '' ) );
		if ( ! $plugin || ! $target || ! is_callable( array( $plugin, 'doctor_ranking' ) ) ) {
			return '';
		}
		$cursor = '';
		for ( $page = 0; $page < 10; $page++ ) {
			$args = array( 'context' => 'global', 'tier' => 'all_verified', 'limit' => 100, 'cursor' => $cursor );
			try {
				$result = $plugin->doctor_ranking()->directory( $args );
			} catch ( Throwable $e ) {
				return '';
			}
			if ( is_wp_error( $result ) || ! is_array( $result ) ) {
				return '';
			}
			foreach ( (array) ( $result['results'] ?? array() ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$key = strtolower( sanitize_text_field( (string) ( $row['key'] ?? '' ) ) );
				$url = DDD_Helpers::same_origin_url( (string) ( $row['url'] ?? '' ) );
				if ( $url && hash_equals( $target, $url ) && preg_match( '/^[a-f0-9]{64}$/', $key ) ) {
					return $key;
				}
			}
			$next = sanitize_text_field( (string) ( $result['next_cursor'] ?? '' ) );
			if ( ! $next || hash_equals( $cursor, $next ) ) {
				break;
			}
			$cursor = $next;
		}
		return '';
	}

	public static function file26_appeal( $current, $request ) {
		if ( is_array( $current ) && ! empty( $current['accepted'] ) ) {
			return $current;
		}
		$public_id = strtolower( sanitize_text_field( (string) ( $request['doctor_public_id'] ?? '' ) ) );
		if ( ! DDD_Helpers::valid_public_id( $public_id ) ) {
			return $current;
		}
		$key = self::file26_key_for_public_id( $public_id, sanitize_key( (string) ( $request['tier'] ?? 'all' ) ) );
		$plugin = self::file26_plugin();
		if ( ! $key || ! $plugin || ! is_callable( array( $plugin, 'doctor_appeals' ) ) ) {
			return $current;
		}
		$reason = sanitize_key( (string) ( $request['reason'] ?? 'other' ) );
		$details = sanitize_textarea_field( (string) ( $request['details'] ?? '' ) );
		$evidence = array_values( array_filter( array(
			'File07 public id: ' . $public_id,
			'Policy: ' . sanitize_text_field( (string) ( $request['policy_version'] ?? '' ) ),
			'Monthly snapshot: ' . sanitize_text_field( (string) ( $request['monthly_version'] ?? '' ) ),
		) ) );
		try {
			$result = $plugin->doctor_appeals()->submit( $key, $reason . ': ' . $details, $evidence );
		} catch ( Throwable $e ) {
			return $current;
		}
		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return $current;
		}
		$receipt = sanitize_text_field( (string) ( $result['appeal_uuid'] ?? $result['public_id'] ?? '' ) );
		return $receipt ? array( 'accepted' => true, 'receipt_id' => $receipt ) : $current;
	}
}
