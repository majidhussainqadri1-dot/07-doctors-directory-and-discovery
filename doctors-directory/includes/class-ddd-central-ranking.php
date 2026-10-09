<?php
defined( 'ABSPATH' ) || exit;

/**
 * File 26 global-ranking read bridge.
 *
 * File 26 owns ranking truth. File 07 owns only the public-safe doctor directory
 * projection and re-validates every returned doctor against current File 00/03/09
 * eligibility before rendering.
 */
final class DDD_Central_Ranking {
	const RANKING_FILTER = 'sabri_file26_doctor_ranking_v1'; // Legacy compatibility only.
	const ASSURANCE_FILTER = 'sabri_file24_doctor_ranking_assurance_v1';
	const CONTRACT_VERSION = '1.0';
	const MAX_SNAPSHOT_AGE = 2678400; // 31 days; aligned with File 24 fairness freshness policy.
	const LIMIT = 24;
	const MAX_CURSOR = 512;

	public static function register() { add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) ); }

	public static function tier() {
		$tier = isset( $_GET['doctor_tier'] ) ? sanitize_key( wp_unslash( $_GET['doctor_tier'] ) ) : 'all';
		return in_array( $tier, array( 'top10', 'top100', 'top1000', 'all' ), true ) ? $tier : 'all';
	}

	public static function filters() {
		$mode = isset( $_GET['doctor_mode'] ) ? sanitize_key( wp_unslash( $_GET['doctor_mode'] ) ) : '';
		if ( ! in_array( $mode, array( '', 'online', 'in-person', 'video', 'phone', 'chat', 'home-visit' ), true ) ) { $mode = ''; }
		return array(
			'q' => isset( $_GET['doctor_search'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_search'] ) ) : '',
			'country' => isset( $_GET['doctor_country'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_country'] ) ) : '',
			'city' => isset( $_GET['doctor_city'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_city'] ) ) : '',
			'specialty' => isset( $_GET['doctor_specialty'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_specialty'] ) ) : '',
			'language' => isset( $_GET['doctor_language'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_language'] ) ) : '',
			'qualification' => isset( $_GET['doctor_qualification'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_qualification'] ) ) : '',
			'min_experience' => isset( $_GET['doctor_experience'] ) ? min( 100, absint( $_GET['doctor_experience'] ) ) : 0,
			'mode' => $mode,
			'accepting' => ! empty( $_GET['doctor_accepting'] ) ? 1 : 0,
			'currency' => isset( $_GET['doctor_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['doctor_currency'] ) ) ) : '',
			'fee_min' => isset( $_GET['doctor_fee_min'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_min'] ) ) : null,
			'fee_max' => isset( $_GET['doctor_fee_max'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_max'] ) ) : null,
		);
	}

	private static function prohibited() { return array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism', 'purchased_engagement' ); }

	private static function public_assurance( $assurance ) {
		if ( ! is_array( $assurance ) ) { return array( 'status' => 'unverified' ); }
		$out = array();
		foreach ( array( 'status', 'policy_version', 'monthly_version', 'generated_at', 'summary', 'public_report_url' ) as $key ) {
			if ( ! array_key_exists( $key, $assurance ) || ! is_scalar( $assurance[ $key ] ) ) { continue; }
			$value = sanitize_text_field( (string) $assurance[ $key ] );
			if ( 'public_report_url' === $key ) { $value = DDD_Helpers::same_origin_url( $value ); if ( ! $value ) { continue; } }
			$out[ $key ] = $value;
		}
		$status = sanitize_key( (string) ( $out['status'] ?? '' ) );
		$out['status'] = in_array( $status, array( 'pass', 'blocked', 'unverified' ), true ) ? $status : 'unverified';
		return $out;
	}


	private static function current_file24_assurance( $bias, $policy, $monthly, $generated, $response ) {
		if ( ! has_filter( 'spcrc/evaluate_ranking_fairness' ) ) {
			return null;
		}
		$provider = self::current_file26_provider();
		$constitution = array();
		$constitution_cb = $provider['ranking_constitution'] ?? null;
		try {
			if ( is_callable( $constitution_cb ) ) {
				$constitution = call_user_func( $constitution_cb );
			} elseif ( function_exists( 'sabri_file26_ranking_constitution' ) ) {
				$constitution = sabri_file26_ranking_constitution();
			}
		} catch ( Throwable $exception ) {
			$constitution = array();
		}
		if ( ! is_array( $constitution ) ) {
			$constitution = array();
		}
		$doctor = is_array( $constitution['doctor_ranking'] ?? null ) ? $constitution['doctor_ranking'] : array();
		$signals = array_map( 'sanitize_key', array_keys( is_array( $doctor['signals'] ?? null ) ? $doctor['signals'] : array() ) );
		$prohibited = array_map( 'sanitize_key', (array) ( $constitution['prohibited_signals'] ?? array() ) );

		$native_controls = array();
		try {
			$manifests = apply_filters( 'sabri_file24_module_manifest', array() );
			if ( is_array( $manifests ) && is_array( $manifests['file26'] ?? null ) ) {
				$native_controls = array_map( 'sanitize_key', (array) ( $manifests['file26']['native_controls'] ?? array() ) );
			}
		} catch ( Throwable $exception ) {
			$native_controls = array();
		}

		$controls = array();
		if ( is_callable( $provider['doctor_ranking'] ?? null ) && ! empty( $provider['contract_version'] ) ) { $controls[] = 'file26_owner_contract'; }
		if ( '' !== trim( (string) $policy ) ) { $controls[] = 'versioned_policy'; }
		if ( ! empty( $constitution['why_this_result_required'] ) ) { $controls[] = 'explainability'; }
		if ( in_array( 'audit', $native_controls, true ) ) { $controls[] = 'audit_log'; }
		if ( in_array( 'doctor-ranking-appeals', $native_controls, true ) ) { $controls[] = 'appeal_path'; }
		if ( in_array( 'manipulation_resistant_engagement_score', $signals, true ) ) { $controls[] = 'manipulation_resistance'; }
		if ( in_array( 'patient_verified_review_score', $signals, true ) ) { $controls[] = 'verified_review_weighting'; }
		if ( false !== stripos( (string) ( $doctor['recompute'] ?? '' ), 'monthly' ) && $generated > 0 ) { $controls[] = 'monthly_recomputation'; }
		if ( in_array( 'donation', $prohibited, true ) && empty( $bias['donor_boost'] ) ) { $controls[] = 'donation_independence'; }
		if ( in_array( 'payment', $prohibited, true ) && empty( $bias['paid_boost'] ) ) { $controls[] = 'payment_independence'; }
		if ( in_array( 'founder_favoritism', $prohibited, true ) ) { $controls[] = 'founder_non_favoritism'; }

		$active_influences = array_values(
			array_intersect(
				array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism', 'purchased_engagement', 'undisclosed_manual_boost' ),
				$signals
			)
		);
		$snapshot_id = sanitize_text_field( (string) ( $response['snapshot_id'] ?? '' ) );
		$evidence_ref = preg_match( '/^[a-z][a-z0-9_-]{1,31}:[A-Za-z0-9][A-Za-z0-9._:-]{2,220}$/', $snapshot_id )
			? $snapshot_id
			: 'file26:' . substr( hash( 'sha256', (string) $policy . '|' . (string) $monthly . '|' . (string) $generated ), 0, 48 );

		$evidence = array(
			'controls'      => array_values( array_unique( $controls ) ),
			'influences'    => $active_influences,
			'policy_version'=> (string) $policy,
			'evidence_ref'  => $evidence_ref,
			'tested_at'     => gmdate( 'c' ),
			'recomputed_at' => gmdate( 'c', $generated ),
		);
		try {
			$result = apply_filters( 'spcrc/evaluate_ranking_fairness', $evidence );
		} catch ( Throwable $exception ) {
			DDD_Observability::record_health( 'file24-ranking-assurance', 'degraded', 'file24_assurance_provider_failed' );
			return array(
				'status'          => 'unverified',
				'policy_version'  => (string) $policy,
				'monthly_version' => (string) $monthly,
				'generated_at'    => gmdate( 'c', $generated ),
				'summary'         => __( 'File 24 ranking assurance is temporarily unavailable.', DDD_TEXT_DOMAIN ),
			);
		}
		if ( ! is_array( $result ) ) {
			return array(
				'status'          => 'unverified',
				'policy_version'  => (string) $policy,
				'monthly_version' => (string) $monthly,
				'generated_at'    => gmdate( 'c', $generated ),
				'summary'         => __( 'File 24 returned no usable ranking-assurance decision.', DDD_TEXT_DOMAIN ),
			);
		}
		$state = sanitize_key( (string) ( $result['state'] ?? '' ) );
		$status = 'verified' === $state ? 'pass' : ( 'blocked' === $state ? 'blocked' : 'unverified' );
		DDD_Observability::record_health(
			'file24-ranking-assurance',
			'pass' === $status ? 'pass' : 'degraded',
			'pass' === $status ? 'file24_assurance_verified' : ( 'blocked' === $status ? 'file24_assurance_blocked' : 'file24_assurance_unverified' )
		);
		return array(
			'status'          => $status,
			'policy_version'  => (string) $policy,
			'monthly_version' => (string) $monthly,
			'generated_at'    => gmdate( 'c', $generated ),
			'summary'         => 'pass' === $status
				? __( 'Current File 24 fairness assurance verified the File 26 ranking evidence.', DDD_TEXT_DOMAIN )
				: ( 'blocked' === $status
					? __( 'Current File 24 fairness assurance did not verify this ranking evidence.', DDD_TEXT_DOMAIN )
					: __( 'Current File 24 fairness assurance is not yet verified for this ranking evidence.', DDD_TEXT_DOMAIN ) ),
		);
	}

	private static function request( $tier, $filters ) {
		$cursor = isset( $_GET['doctor_rank_cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_rank_cursor'] ) ) : '';
		if ( strlen( $cursor ) > self::MAX_CURSOR ) { $cursor = ''; }
		return array(
			'contract' => 'doctor_global_ranking', 'contract_version' => self::CONTRACT_VERSION, 'consumer' => 'file07',
			'tier' => $tier, 'limit' => 'top10' === $tier ? 10 : self::LIMIT, 'cursor' => $cursor, 'filters' => $filters,
			'require_nested_tiers' => true, 'require_monthly_version' => true, 'require_explanations' => true,
			'require_appeal' => true, 'require_bias_audit' => true, 'prohibited_signals' => self::prohibited(),
		);
	}

	/**
	 * Current File 26 publishes its versioned visual/search provider registry
	 * through the File 25 integration surface. Consume that owner callback rather
	 * than inventing a parallel File 07 ranking implementation.
	 */
	private static function current_file26_provider() {
		try {
			$providers = apply_filters( 'sabri_file25_search_provider', array() );
		} catch ( Throwable $exception ) {
			DDD_Observability::record_health( 'file26-ranking', 'degraded', 'file26_provider_registry_failed' );
			return array();
		}
		if ( ! is_array( $providers ) || ! is_array( $providers['file26'] ?? null ) ) {
			return array();
		}
		return $providers['file26'];
	}

	private static function current_file26_health() {
		try {
			if ( class_exists( 'Sabri\\File26\\Plugin' ) && is_callable( array( 'Sabri\\File26\\Plugin', 'instance' ) ) ) {
				$plugin = \Sabri\File26\Plugin::instance();
				if ( is_object( $plugin ) && method_exists( $plugin, 'health' ) ) {
					$health = $plugin->health();
					if ( is_object( $health ) && method_exists( $health, 'snapshot' ) ) {
						$result = $health->snapshot();
						return is_array( $result ) ? $result : array();
					}
				}
			}
		} catch ( Throwable $exception ) {
			DDD_Observability::record_health( 'file26-ranking', 'degraded', 'file26_health_unavailable' );
		}
		return array();
	}

	private static function context_from_filters( $filters ) {
		if ( ! empty( $filters['city'] ) ) { return array( 'city', (string) $filters['city'] ); }
		if ( ! empty( $filters['country'] ) ) { return array( 'country', (string) $filters['country'] ); }
		if ( ! empty( $filters['language'] ) ) { return array( 'language', (string) $filters['language'] ); }
		if ( ! empty( $filters['specialty'] ) ) { return array( 'specialization', (string) $filters['specialty'] ); }
		return array( 'global', '' );
	}

	private static function file26_tier( $tier ) {
		$map = array( 'top10' => 'top_10', 'top100' => 'top_100', 'top1000' => 'top_1000', 'all' => 'all_verified' );
		return $map[ $tier ] ?? 'all_verified';
	}

	private static function public_id_from_ranked_item( $item ) {
		if ( ! is_array( $item ) ) { return ''; }
		foreach ( array( 'public_id', 'object_id' ) as $key ) {
			$value = strtolower( sanitize_text_field( (string) ( $item[ $key ] ?? '' ) ) );
			if ( DDD_Helpers::valid_public_id( $value ) ) { return $value; }
		}
		foreach ( array( 'url', 'canonical_url', 'key' ) as $key ) {
			$value = (string) ( $item[ $key ] ?? '' );
			if ( preg_match( '/[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}/i', $value, $match ) ) {
				$id = strtolower( $match[0] );
				if ( DDD_Helpers::valid_public_id( $id ) ) { return $id; }
			}
		}

		/*
		 * File 26 canonical keys are deliberately opaque and do not have to be
		 * File 03 UUIDs. Resolve a current same-origin canonical URL against the
		 * local rebuildable File 07 projection instead of guessing identity from
		 * File 26's private key format.
		 */
		$url = DDD_Helpers::same_origin_url( (string) ( $item['url'] ?? $item['canonical_url'] ?? '' ) );
		if ( ! $url ) { return ''; }
		global $wpdb;
		$table = DDD_Repository::table( 'projection' );
		if ( ! $table ) { return ''; }
		$id = strtolower(
			sanitize_text_field(
				(string) $wpdb->get_var(
					$wpdb->prepare( "SELECT public_id FROM {$table} WHERE eligible=1 AND profile_url=%s LIMIT 1", $url )
				)
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return DDD_Helpers::valid_public_id( $id ) ? $id : '';
	}

	private static function current_bias_attestation( $constitution ) {
		if ( ! is_array( $constitution ) ) {
			return new WP_Error( 'file26_constitution_missing', __( 'File 26 ranking constitution is unavailable.', DDD_TEXT_DOMAIN ) );
		}
		$blocked = array_values( array_unique( array_map( 'sanitize_key', (array) ( $constitution['prohibited_signals'] ?? array() ) ) ) );
		$doctor = is_array( $constitution['doctor_ranking'] ?? null ) ? $constitution['doctor_ranking'] : array();
		$signals = array_keys( is_array( $doctor['signals'] ?? null ) ? $doctor['signals'] : array() );
		$signals = array_map( 'sanitize_key', $signals );
		$direct_forbidden = array_intersect( array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism', 'purchased_engagement', 'follower_count' ), $signals );
		$explicit = array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism' );
		if ( array_diff( $explicit, $blocked ) || $direct_forbidden || empty( $constitution['single_free_tier_rank_parity'] ) || ! array_key_exists( 'paid_or_sponsored_organic_results', $constitution ) || ! empty( $constitution['paid_or_sponsored_organic_results'] ) ) {
			return new WP_Error( 'file26_bias_guard_incomplete', __( 'File 26 did not prove the required paid/donor/favoritism ranking guards.', DDD_TEXT_DOMAIN ) );
		}
		/*
		 * Purchased engagement is not a direct policy signal. File 26 exposes
		 * only its manipulation-resistant engagement score. Treat this as a
		 * guard only while the raw purchased/follower signals themselves remain
		 * absent from the active published weight map.
		 */
		if ( ! in_array( 'manipulation_resistant_engagement_score', $signals, true ) ) {
			return new WP_Error( 'file26_manipulation_guard_missing', __( 'File 26 manipulation-resistant engagement control is missing.', DDD_TEXT_DOMAIN ) );
		}
		return array(
			'status' => 'pass',
			'prohibited_signals' => self::prohibited(),
			'paid_boost' => false,
			'donor_boost' => false,
		);
	}

	private static function current_snapshot( $tier, $filters ) {
		$provider = self::current_file26_provider();
		$ranking = $provider['doctor_ranking'] ?? null;
		if ( ! is_callable( $ranking ) ) {
			return new WP_Error( 'file26_ranking_unavailable', __( 'The current File 26 doctor-ranking provider is unavailable.', DDD_TEXT_DOMAIN ) );
		}
		list( $context, $value ) = self::context_from_filters( $filters );
		$cursor = isset( $_GET['doctor_rank_cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_rank_cursor'] ) ) : '';
		if ( strlen( $cursor ) > self::MAX_CURSOR ) { $cursor = ''; }
		$request = array(
			'context' => $context,
			'value' => $value,
			'tier' => self::file26_tier( $tier ),
			'limit' => 'top10' === $tier ? 10 : self::LIMIT,
			'cursor' => $cursor,
		);
		try {
			$response = call_user_func( $ranking, $request );
		} catch ( Throwable $exception ) {
			return new WP_Error( 'file26_ranking_provider_failed', __( 'The File 26 ranking provider failed safely.', DDD_TEXT_DOMAIN ) );
		}
		if ( is_wp_error( $response ) ) { return $response; }
		if ( ! is_array( $response ) || empty( $response['contract_version'] ) || empty( $response['policy_version'] ) || empty( $response['global_tiers_preserved'] ) ) {
			return new WP_Error( 'file26_ranking_invalid', __( 'File 26 returned an incomplete ranking contract.', DDD_TEXT_DOMAIN ) );
		}
		if ( ! empty( $response['policy_safe_fallback'] ) && 'all' !== $tier ) {
			return new WP_Error( 'file26_ranking_safe_fallback', __( 'File 26 is in ranking safe-fallback mode; merit tiers are withheld.', DDD_TEXT_DOMAIN ) );
		}

		$constitution = array();
		$constitution_cb = $provider['ranking_constitution'] ?? null;
		try {
			if ( is_callable( $constitution_cb ) ) {
				$constitution = call_user_func( $constitution_cb );
			} elseif ( function_exists( 'sabri_file26_ranking_constitution' ) ) {
				$constitution = sabri_file26_ranking_constitution();
			}
		} catch ( Throwable $exception ) {
			$constitution = array();
		}
		$bias = self::current_bias_attestation( $constitution );
		if ( is_wp_error( $bias ) ) { return $bias; }

		$health = self::current_file26_health();
		$last_run = sanitize_text_field( (string) ( $health['doctor_ranking']['last_run'] ?? '' ) );
		$generated = strtotime( $last_run );
		if ( ! $generated || $generated > time() + DAY_IN_SECONDS || $generated < time() - self::MAX_SNAPSHOT_AGE ) {
			return new WP_Error( 'file26_snapshot_stale', __( 'File 26 monthly doctor-ranking evidence is missing or stale.', DDD_TEXT_DOMAIN ) );
		}
		$monthly = gmdate( 'Y-m', $generated );

		$items = array();
		$raw_results = array_values( (array) ( $response['results'] ?? array() ) );
		foreach ( $raw_results as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$public_id = self::public_id_from_ranked_item( $row );
			if ( ! $public_id ) { continue; }
			$rank = absint( $row['global_rank'] ?? $row['context_rank'] ?? 0 );
			$why = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) ( $row['explanation'] ?? array() ), 0, 8 ) ) ) );
			if ( ! $rank || ! $why ) { continue; }
			$items[] = array(
				'public_id' => $public_id,
				'rank' => $rank,
				'explanation' => $why,
				'file26_key' => preg_match( '/^[a-f0-9]{64}$/', (string) ( $row['key'] ?? '' ) ) ? strtolower( (string) $row['key'] ) : '',
			);
		}
		if ( $raw_results && ! $items ) {
			return new WP_Error( 'file26_identity_mapping_unavailable', __( 'File 26 ranking results could not be mapped to canonical File 03 public doctor identifiers.', DDD_TEXT_DOMAIN ) );
		}

		$normalized = array(
			'ready' => true,
			'contract_version' => sanitize_text_field( (string) $response['contract_version'] ),
			'policy_version' => sanitize_text_field( (string) $response['policy_version'] ),
			'monthly_version' => $monthly,
			'generated_at' => gmdate( 'c', $generated ),
			'nested_tiers' => true,
			'bias_audit' => $bias,
			'items' => $items,
			'next_cursor' => sanitize_text_field( substr( (string) ( $response['next_cursor'] ?? '' ), 0, self::MAX_CURSOR ) ),
			'snapshot_id' => 'file26:' . $monthly . ':' . sanitize_key( (string) $response['policy_version'] ),
		);
		$validated = self::validate( $normalized, self::request( $tier, $filters ) );
		if ( is_wp_error( $validated ) ) { return $validated; }
		$validated['filters'] = $filters;
		$validated['file26_context'] = $context;
		return $validated;
	}

	public static function transparency_policy() {
		$provider = self::current_file26_provider();
		$constitution = array();
		$callback = $provider['ranking_constitution'] ?? null;
		try {
			if ( is_callable( $callback ) ) {
				$constitution = call_user_func( $callback );
			} elseif ( function_exists( 'sabri_file26_ranking_constitution' ) ) {
				$constitution = sabri_file26_ranking_constitution();
			}
		} catch ( Throwable $exception ) {
			$constitution = array();
		}
		if ( ! is_array( $constitution ) ) { $constitution = array(); }

		$doctor = is_array( $constitution['doctor_ranking'] ?? null ) ? $constitution['doctor_ranking'] : array();
		$signals = array_keys( is_array( $doctor['signals'] ?? null ) ? $doctor['signals'] : array() );
		$health = self::current_file26_health();
		$last_run = sanitize_text_field( (string) ( $health['doctor_ranking']['last_run'] ?? '' ) );
		$generated = strtotime( $last_run );
		$out = array(
			'policy_version' => sanitize_text_field( (string) ( $doctor['policy_version'] ?? '' ) ),
			'monthly_version' => $generated ? gmdate( 'Y-m', $generated ) : '',
			'generated_at' => $generated ? gmdate( 'c', $generated ) : '',
			'signals' => array_values( array_unique( array_filter( array_map( 'sanitize_key', $signals ) ) ) ),
			'appeal_url' => DDD_Helpers::same_origin_url( home_url( '/doctors/' ) ),
			'explanation_url' => DDD_Helpers::same_origin_url( rest_url( DDD_REST::NS . '/ranking' ) ),
		);
		if ( empty( $out['policy_version'] ) ) {
			$legacy = apply_filters( 'sabri_file26_ranking_policy_public_v1', null, array( 'consumer'=>'file07', 'contract_version'=>self::CONTRACT_VERSION ) );
			return is_array( $legacy ) ? $legacy : array();
		}
		return $out;
	}

	public static function snapshot( $tier, $filters ) {
		$current = self::current_snapshot( $tier, $filters );
		if ( ! is_wp_error( $current ) ) {
			DDD_Observability::record_health( 'file26-ranking', 'pass', 'current_ranking_contract_valid', array( 'policy_version' => $current['policy_version'], 'monthly_version' => $current['monthly_version'] ) );
			return $current;
		}

		/* Old installations may still publish the legacy File 07 adapter filter. */
		if ( has_filter( self::RANKING_FILTER ) ) {
			$request = self::request( $tier, $filters );
			$validated = self::validate( apply_filters( self::RANKING_FILTER, null, $request ), $request );
			if ( ! is_wp_error( $validated ) ) {
				$validated['filters'] = $filters;
				DDD_Observability::record_health( 'file26-ranking', 'pass', 'legacy_ranking_contract_valid', array( 'policy_version' => $validated['policy_version'], 'monthly_version' => $validated['monthly_version'] ) );
				return $validated;
			}
		}

		DDD_Observability::record_health( 'file26-ranking', 'degraded', $current->get_error_code() );
		return 'all' === $tier ? self::neutral( $filters ) : $current;
	}

	private static function validate( $response, $request ) {
		if ( ! is_array( $response ) || empty( $response['ready'] ) ) { return new WP_Error( 'file26_ranking_invalid', __( 'File 26 did not return a ready ranking snapshot.', DDD_TEXT_DOMAIN ) ); }
		$contract = sanitize_text_field( (string) ( $response['contract_version'] ?? '' ) );
		if ( ! $contract || version_compare( $contract, self::CONTRACT_VERSION, '<' ) ) { return new WP_Error( 'file26_contract_incompatible', __( 'File 26 ranking contract is incompatible.', DDD_TEXT_DOMAIN ) ); }
		$policy = sanitize_text_field( (string) ( $response['policy_version'] ?? '' ) );
		$monthly = sanitize_text_field( (string) ( $response['monthly_version'] ?? '' ) );
		if ( ! $policy || ! preg_match( '/^20\d{2}-(0[1-9]|1[0-2])(?:[.-][A-Za-z0-9_-]+)?$/', $monthly ) ) { return new WP_Error( 'file26_policy_version_missing', __( 'Ranking policy/monthly version is missing or invalid.', DDD_TEXT_DOMAIN ) ); }
		$generated = strtotime( (string) ( $response['generated_at'] ?? '' ) );
		if ( ! $generated || $generated > time() + DAY_IN_SECONDS || $generated < time() - self::MAX_SNAPSHOT_AGE ) { return new WP_Error( 'file26_snapshot_stale', __( 'Ranking snapshot is stale or has an invalid timestamp.', DDD_TEXT_DOMAIN ) ); }
		if ( empty( $response['nested_tiers'] ) ) { return new WP_Error( 'file26_nested_tiers_unproven', __( 'File 26 did not attest nested Top 10/100/1000 tiers.', DDD_TEXT_DOMAIN ) ); }
		$bias = is_array( $response['bias_audit'] ?? null ) ? $response['bias_audit'] : array();
		if ( 'pass' !== sanitize_key( (string) ( $bias['status'] ?? '' ) ) ) { return new WP_Error( 'file26_bias_audit_missing', __( 'Ranking bias audit has not passed.', DDD_TEXT_DOMAIN ) ); }
		$blocked = array_map( 'sanitize_key', (array) ( $bias['prohibited_signals'] ?? array() ) );
		foreach ( self::prohibited() as $signal ) { if ( ! in_array( $signal, $blocked, true ) ) { return new WP_Error( 'file26_bias_guard_incomplete', __( 'Ranking policy does not attest every prohibited paid/donor influence.', DDD_TEXT_DOMAIN ) ); } }
		if ( ! empty( $bias['paid_boost'] ) || ! empty( $bias['donor_boost'] ) ) { return new WP_Error( 'file26_paid_bias_detected', __( 'Paid or donor ranking advantage is forbidden.', DDD_TEXT_DOMAIN ) ); }

		$raw_items = array_values( (array) ( $response['items'] ?? array() ) );
		$request_limit = max( 1, min( self::LIMIT, absint( $request['limit'] ?? self::LIMIT ) ) );
		if ( count( $raw_items ) > $request_limit ) { return new WP_Error( 'file26_page_oversized', __( 'File 26 returned more ranking items than the bounded page contract permits.', DDD_TEXT_DOMAIN ) ); }

		$cap = 'top10' === $request['tier'] ? 10 : ( 'top100' === $request['tier'] ? 100 : ( 'top1000' === $request['tier'] ? 1000 : PHP_INT_MAX ) );
		$seen = array(); $items = array();
		foreach ( $raw_items as $item ) {
			$id = strtolower( sanitize_text_field( (string) ( $item['public_id'] ?? '' ) ) );
			$rank = absint( $item['rank'] ?? 0 );
			$why = array_values( array_filter( array_map( 'sanitize_text_field', array_slice( (array) ( $item['explanation'] ?? array() ), 0, 8 ) ) ) );
			if ( ! DDD_Helpers::valid_public_id( $id ) ) { return new WP_Error( 'file26_public_id_invalid', __( 'File 26 returned an invalid public doctor identifier.', DDD_TEXT_DOMAIN ) ); }
			if ( ! $rank || $rank > $cap ) { return new WP_Error( 'file26_rank_invalid', __( 'File 26 returned a rank outside the requested tier.', DDD_TEXT_DOMAIN ) ); }
			if ( isset( $seen[ $id ] ) ) { return new WP_Error( 'file26_duplicate_public_id', __( 'File 26 returned the same doctor more than once in one ranking page.', DDD_TEXT_DOMAIN ) ); }
			if ( ! $why ) { return new WP_Error( 'file26_explanation_missing', __( 'File 26 returned a ranked doctor without a public explanation.', DDD_TEXT_DOMAIN ) ); }
			$seen[ $id ] = true;
			$items[] = array( 'public_id' => $id, 'rank' => $rank, 'explanation' => $why, 'file26_key' => sanitize_text_field( (string) ( $item['file26_key'] ?? '' ) ) );
		}
		usort( $items, static function ( $a, $b ) { return $a['rank'] <=> $b['rank']; } );

		$assurance = self::current_file24_assurance( $bias, $policy, $monthly, $generated, $response );
		if ( null === $assurance && has_filter( self::ASSURANCE_FILTER ) ) {
			$assurance = self::public_assurance(
				apply_filters(
					self::ASSURANCE_FILTER,
					null,
					$bias,
					array(
						'policy_version'  => $policy,
						'monthly_version' => $monthly,
						'snapshot_id'     => sanitize_text_field( (string) ( $response['snapshot_id'] ?? '' ) ),
					)
				)
			);
		} elseif ( is_array( $assurance ) ) {
			$assurance = self::public_assurance( $assurance );
		}
		if ( is_array( $assurance ) && 'blocked' === sanitize_key( (string) ( $assurance['status'] ?? '' ) ) ) {
			DDD_Observability::record_health( 'file24-ranking-assurance', 'degraded', 'file24_assurance_blocked_merit_ranking' );
			return new WP_Error(
				'file24_ranking_assurance_blocked',
				__( 'Official merit ranking is withheld because File 24 did not verify the current ranking evidence.', DDD_TEXT_DOMAIN )
			);
		}
		return array( 'source' => 'file26', 'ready' => true, 'policy_version' => $policy, 'monthly_version' => $monthly, 'generated_at' => gmdate( 'Y-m-d H:i:s', $generated ), 'items' => $items, 'next_cursor' => sanitize_text_field( substr( (string) ( $response['next_cursor'] ?? '' ), 0, self::MAX_CURSOR ) ), 'assurance' => $assurance );
	}

	private static function neutral( $f ) {
		global $wpdb; $table = DDD_Repository::table( 'projection' );
		if ( ! $table ) { return new WP_Error( 'projection_unavailable', __( 'Directory projection is unavailable.', DDD_TEXT_DOMAIN ) ); }
		$where = array( 'eligible=1' ); $p = array(); $q = DDD_Helpers::normalize_token( $f['q'] );
		if ( $q ) { $like = '%' . $wpdb->esc_like( $q ) . '%'; $where[] = '(display_name_norm LIKE %s OR specialty_norm LIKE %s OR search_text_norm LIKE %s)'; array_push( $p, $like, $like, $like ); }
		foreach ( array( 'country'=>'country_norm', 'city'=>'city_norm', 'specialty'=>'specialty_norm' ) as $k=>$col ) { if ( '' !== (string) $f[$k] ) { $where[] = "$col=%s"; $p[] = DDD_Repository::taxonomy_normalize( $k, $f[$k] ); } }
		if ( $f['language'] ) { $where[]='languages_norm LIKE %s'; $p[]='%'.$wpdb->esc_like( DDD_Repository::taxonomy_normalize( 'language', $f['language'] ) ).'%'; }
		if ( $f['qualification'] ) { $where[]='qualification_norm LIKE %s'; $p[]='%'.$wpdb->esc_like( DDD_Helpers::normalize_token( $f['qualification'] ) ).'%'; }
		if ( $f['min_experience'] ) { $where[]='experience_years>=%d'; $p[]=absint($f['min_experience']); }
		if ( $f['mode'] ) { $where[]='consultation_modes_json LIKE %s'; $p[]='%"'.$wpdb->esc_like($f['mode']).'"%'; }
		if ( $f['accepting'] ) { $where[]='accepting_patients=1'; }
		if ( $f['currency'] ) { $where[]='currency=%s'; $p[]=strtoupper(substr($f['currency'],0,3)); }
		if ( null !== $f['fee_min'] ) { $where[]='fee_max IS NOT NULL AND fee_max>=%f'; $p[]=(float)$f['fee_min']; }
		if ( null !== $f['fee_max'] ) { $where[]='fee_min IS NOT NULL AND fee_min<=%f'; $p[]=(float)$f['fee_max']; }
		$hash = DDD_Helpers::filter_hash( $f ); $raw = isset($_GET['doctor_rank_cursor']) ? sanitize_text_field(wp_unslash($_GET['doctor_rank_cursor'])) : ''; $cursor = $raw ? DDD_Helpers::cursor_decode($raw,$hash) : array();
		if ( $raw && ! $cursor ) { return new WP_Error( 'neutral_cursor_invalid', __( 'The All Verified cursor expired or does not match these filters. Restart the view.', DDD_TEXT_DOMAIN ) ); }
		if ( $cursor ) { $where[]='(display_name_norm>%s OR (display_name_norm=%s AND public_id>%s))'; $p[]=(string)($cursor['n']??''); $p[]=(string)($cursor['n']??''); $p[]=(string)($cursor['p']??''); }
		$p[] = self::LIMIT + 1; $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$table} WHERE ".implode(' AND ',$where).' ORDER BY display_name_norm ASC, public_id ASC LIMIT %d',$p), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$more = count($rows)>self::LIMIT; if($more){array_pop($rows);} $items=array();
		foreach($rows as $row){ $dto=DDD_Repository::get_by_public_id($row['public_id']); if($dto){$items[]=array('public_id'=>$dto['public_id'],'rank'=>0,'explanation'=>array(__( 'Verified and publicly eligible', DDD_TEXT_DOMAIN ),__( 'Neutral alphabetical fallback; no merit rank is asserted while File 26 is unavailable.', DDD_TEXT_DOMAIN )));}}
		$next=''; if($more&&$rows){$last=end($rows);$next=DDD_Helpers::cursor_encode(array('fh'=>$hash,'n'=>(string)$last['display_name_norm'],'p'=>(string)$last['public_id']));}
		return array('source'=>'neutral','ready'=>true,'items'=>$items,'next_cursor'=>$next,'policy_version'=>'','monthly_version'=>'','generated_at'=>'','filters'=>$f);
	}

	private static function item_matches_filters( $doctor, $f ) {
		if ( ! is_array( $doctor ) ) { return false; }
		if ( ! empty( $f['q'] ) ) {
			$needle = DDD_Helpers::normalize_token( $f['q'] );
			$hay = DDD_Helpers::normalize_token( implode( ' ', array( $doctor['display_name'] ?? '', $doctor['professional_title'] ?? '', $doctor['specialty'] ?? '', $doctor['qualification'] ?? '', implode( ' ', (array) ( $doctor['languages'] ?? array() ) ) ) ) );
			if ( '' !== $needle && false === strpos( $hay, $needle ) ) { return false; }
		}
		foreach ( array( 'country', 'city' ) as $key ) {
			if ( ! empty( $f[$key] ) && DDD_Helpers::normalize_token( $doctor[$key] ?? '' ) !== DDD_Helpers::normalize_token( $f[$key] ) ) { return false; }
		}
		if ( ! empty( $f['specialty'] ) && false === strpos( DDD_Helpers::normalize_token( $doctor['specialty'] ?? '' ), DDD_Helpers::normalize_token( $f['specialty'] ) ) ) { return false; }
		if ( ! empty( $f['qualification'] ) && false === strpos( DDD_Helpers::normalize_token( $doctor['qualification'] ?? '' ), DDD_Helpers::normalize_token( $f['qualification'] ) ) ) { return false; }
		if ( ! empty( $f['language'] ) ) {
			$found = false; foreach ( (array) ( $doctor['languages'] ?? array() ) as $language ) { if ( DDD_Helpers::normalize_token( $language ) === DDD_Helpers::normalize_token( $f['language'] ) ) { $found = true; break; } }
			if ( ! $found ) { return false; }
		}
		if ( ! empty( $f['min_experience'] ) && absint( $doctor['experience_years'] ?? 0 ) < absint( $f['min_experience'] ) ) { return false; }
		if ( ! empty( $f['mode'] ) && ! in_array( $f['mode'], (array) ( $doctor['consultation_modes'] ?? array() ), true ) ) { return false; }
		if ( ! empty( $f['accepting'] ) && empty( $doctor['accepting_patients'] ) ) { return false; }
		$fee = is_array( $doctor['fee'] ?? null ) ? $doctor['fee'] : array();
		if ( ! empty( $f['currency'] ) && ( empty( $fee['currency'] ) || 0 !== strcasecmp( (string) $fee['currency'], (string) $f['currency'] ) ) ) { return false; }
		if ( null !== $f['fee_min'] && ( ! isset( $fee['max'] ) || null === $fee['max'] || ! is_numeric( $fee['max'] ) || (float) $fee['max'] < (float) $f['fee_min'] ) ) { return false; }
		if ( null !== $f['fee_max'] && ( ! isset( $fee['min'] ) || null === $fee['min'] || ! is_numeric( $fee['min'] ) || (float) $fee['min'] > (float) $f['fee_max'] ) ) { return false; }
		return true;
	}

	public static function public_items( $snapshot ) {
		$items = array();
		$filters = is_array( $snapshot['filters'] ?? null ) ? $snapshot['filters'] : array();
		foreach ( (array) ( $snapshot['items'] ?? array() ) as $ranked ) {
			$doctor = DDD_Repository::get_by_public_id( $ranked['public_id'] );
			if ( ! $doctor ) { continue; }
			if ( $filters && ! self::item_matches_filters( $doctor, $filters ) ) { continue; }
			$doctor['global_rank'] = 'file26' === ( $snapshot['source'] ?? '' ) ? absint( $ranked['rank'] ) : 0;
			$doctor['ranking_explanation'] = $ranked['explanation'];
			if ( ! empty( $ranked['file26_key'] ) ) { $doctor['file26_key'] = sanitize_text_field( (string) $ranked['file26_key'] ); }
			$items[] = $doctor;
		}
		return $items;
	}

	/** Resolve a File 07 public doctor UUID through the current File 26 owner query. */
	public static function doctor_key_for_public_id( $public_id ) {
		$public_id = strtolower( sanitize_text_field( (string) $public_id ) );
		if ( ! DDD_Helpers::valid_public_id( $public_id ) ) { return ''; }
		$provider = self::current_file26_provider();
		$ranking = $provider['doctor_ranking'] ?? null;
		if ( ! is_callable( $ranking ) ) { return ''; }
		$doctor = DDD_Repository::get_by_public_id( $public_id );
		$context = 'global'; $value = '';
		if ( is_array( $doctor ) && ! empty( $doctor['country'] ) ) { $context = 'country'; $value = (string) $doctor['country']; }
		$cursor = '';
		for ( $page = 0; $page < 100; $page++ ) {
			try {
				$result = call_user_func( $ranking, array( 'context'=>$context, 'value'=>$value, 'tier'=>'all_verified', 'limit'=>100, 'cursor'=>$cursor ) );
			} catch ( Throwable $exception ) {
				return '';
			}
			if ( is_wp_error( $result ) || ! is_array( $result ) ) { return ''; }
			foreach ( (array) ( $result['results'] ?? array() ) as $row ) {
				if ( self::public_id_from_ranked_item( $row ) !== $public_id ) { continue; }
				$key = strtolower( sanitize_text_field( (string) ( $row['key'] ?? '' ) ) );
				return preg_match( '/^[a-f0-9]{64}$/', $key ) ? $key : '';
			}
			$cursor = sanitize_text_field( (string) ( $result['next_cursor'] ?? '' ) );
			if ( ! $cursor ) { break; }
		}
		return '';
	}

	public static function rest_routes() {
		register_rest_route( DDD_REST::NS, '/ranking', array( 'methods'=>WP_REST_Server::READABLE, 'callback'=>array(__CLASS__,'rest_ranking'), 'permission_callback'=>'__return_true', 'args'=>array('tier'=>array('sanitize_callback'=>'sanitize_key','default'=>'all')) ) );
	}

	public static function rest_ranking( WP_REST_Request $request ) {
		if ( ! DDD_Helpers::rate_limit( 'ranking', DDD_Helpers::current_ip_hash(), 60, MINUTE_IN_SECONDS ) ) { return DDD_Helpers::safe_error( 'ranking_rate_limited', __( 'Ranking request rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 ); }
		$tier=sanitize_key((string)$request->get_param('tier')); if(!in_array($tier,array('top10','top100','top1000','all'),true)){$tier='all';}
		$s=self::snapshot($tier,self::filters()); if(is_wp_error($s)){return $s;}
		return rest_ensure_response(array('source'=>$s['source'],'policy_version'=>(string)$s['policy_version'],'monthly_version'=>(string)$s['monthly_version'],'generated_at'=>(string)$s['generated_at'],'items'=>self::public_items($s),'next_cursor'=>(string)$s['next_cursor']));
	}
}
add_action( 'plugins_loaded', array( 'DDD_Central_Ranking', 'register' ), 31 );
