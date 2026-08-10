<?php
defined( 'ABSPATH' ) || exit;

/** F07-FUT-01..24 route/orchestration facade; canonical truths remain with owner files. */
final class DDD_Future_Discovery {
	const REST_NS          = 'doctors-directory-discovery/v1';
	const OPTION_DEMAND    = 'ddd_unmet_demand_v1';
	const MAX_COMPARE=4;
	const CONTRACT_VERSION = '1.0';

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'ddd_file07_discovery_integrity_v1', array( __CLASS__, 'integrity_contract' ), 10, 2 );
		DDD_Future_Preferences::register();
		DDD_Future_UI::register();
	}

	public static function routes() {
		$public = '__return_true';
		$discover_args = DDD_Future_Query::args();

		register_rest_route(
			self::REST_NS,
			'/future/discover',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_discover' ),
					'permission_callback' => $public,
					'args'                => $discover_args,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_discover' ),
					'permission_callback' => $public,
					'args'                => $discover_args,
				),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/future/guided',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_guided' ),
					'permission_callback' => $public,
					'args'                => $discover_args,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_guided' ),
					'permission_callback' => $public,
					'args'                => $discover_args,
				),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/future/compare',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_compare' ),
				'permission_callback' => $public,
				'args'                => array( 'ids' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ), 'timezone' => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'UTC' ) ),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/future/interpret',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_interpret' ),
				'permission_callback' => $public,
				'args'                => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/future/transparency',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_transparency' ),
				'permission_callback' => $public,
			)
		);
		register_rest_route(
			self::REST_NS,
			'/future/offline-pack',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_offline_pack' ),
				'permission_callback' => $public,
				'args'                => array(
					'country'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'city'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'language' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
		register_rest_route(
			self::REST_NS,
			'/future/demand',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_demand' ),
				'permission_callback' => static function () { return current_user_can( 'ddd_view_health' ); },
			)
		);
	}

	private static function public_rate_limit( $bucket, $limit, $window = MINUTE_IN_SECONDS ) {
		return DDD_Helpers::rate_limit( $bucket, DDD_Helpers::current_ip_hash(), $limit, $window );
	}

	private static function no_store_response( $payload, $status = 200 ) {
		$response = $payload instanceof WP_REST_Response ? $payload : new WP_REST_Response( $payload, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private static function public_response( $payload, $cache_control ) {
		$response = rest_ensure_response( $payload );
		$response->header( 'Cache-Control', $cache_control );
		return $response;
	}

	private static function safe_interpretation( $interpretation ) {
		if ( ! is_array( $interpretation ) ) {
			return array();
		}
		unset( $interpretation['original_query'] );
		if ( isset( $interpretation['q'] ) ) {
			unset( $interpretation['q'] );
		}
		return $interpretation;
	}

	private static function public_policy( $policy ) {
		if ( ! is_array( $policy ) ) { return array(); }
		$out = array();
		foreach ( array( 'policy_version', 'monthly_version', 'generated_at' ) as $key ) {
			if ( isset( $policy[ $key ] ) && is_scalar( $policy[ $key ] ) ) { $out[ $key ] = sanitize_text_field( (string) $policy[ $key ] ); }
		}
		if ( isset( $policy['signals'] ) && is_array( $policy['signals'] ) ) {
			$out['signals'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', array_slice( $policy['signals'], 0, 50 ) ) ) ) );
		}
		foreach ( array( 'appeal_url', 'explanation_url' ) as $key ) {
			if ( ! empty( $policy[ $key ] ) ) { $url = DDD_Helpers::same_origin_url( (string) $policy[ $key ] ); if ( $url ) { $out[ $key ] = $url; } }
		}
		return $out;
	}

	private static function public_assurance( $assurance ) {
		if ( ! is_array( $assurance ) ) {
			return array( 'status' => 'unavailable' );
		}
		$allowed = array( 'status', 'policy_version', 'monthly_version', 'generated_at', 'summary', 'public_report_url' );
		$out = array();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $assurance ) || ! is_scalar( $assurance[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( (string) $assurance[ $key ] );
			if ( 'public_report_url' === $key ) {
				$value = DDD_Helpers::same_origin_url( $value );
				if ( ! $value ) {
					continue;
				}
			}
			$out[ $key ] = $value;
		}
		if ( empty( $out['status'] ) ) {
			$out['status'] = 'unavailable';
		}
		return $out;
	}

	private static function get_sensitive_params( WP_REST_Request $request ) {
		$sensitive = array();
		foreach ( array( 'q', 'lat', 'lng', 'weights' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== (string) $value ) {
				$sensitive[] = $key;
			}
		}
		return $sensitive;
	}

	public static function sanitize_params( $request ) { return DDD_Future_Query::sanitize( $request ); }
	public static function enrich_doctor( $doctor, $params ) { return DDD_Future_Query::enrich( $doctor, $params ); }
	public static function matches_saved_search( $doctor, $params ) { return DDD_Future_Query::saved_match( $doctor, $params ); }

	public static function rest_discover( WP_REST_Request $request ) {
		if ( ! self::public_rate_limit( 'future_discover', 90 ) ) {
			return DDD_Helpers::safe_error( 'future_search_rate_limited', __( 'Discovery rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 );
		}
		if ( 'GET' === strtoupper( $request->get_method() ) && self::get_sensitive_params( $request ) ) {
			return DDD_Helpers::safe_error(
				'future_sensitive_query_requires_post',
				__( 'Free-text, precise-location and personal-order discovery parameters must be submitted with POST.', DDD_TEXT_DOMAIN ),
				400
			);
		}

		$params = DDD_Future_Query::sanitize( $request->get_params() );
		$interpretation = DDD_Future_Query::interpret( $params['q'] );
		if ( ! empty( $interpretation['safety_diversion'] ) ) {
			return self::no_store_response(
				array(
					'items'                => array(),
					'safety_diversion'     => $interpretation['safety_diversion'],
					'query_interpretation' => self::safe_interpretation( $interpretation ),
				)
			);
		}
		$params = DDD_Future_Query::merge( $params, $interpretation );
		$base = DDD_Repository::search( DDD_Future_Query::repository( $params ) );
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$items = array();
		foreach ( (array) ( $base['items'] ?? array() ) as $doctor ) {
			$doctor = DDD_Future_Query::enrich( $doctor, $params );
			if ( DDD_Future_Query::matches( $doctor, $params ) ) {
				$doctor['why_this_doctor'] = DDD_Future_Query::why( $doctor, $params );
				$items[] = $doctor;
			}
		}
		if ( null !== $params['lat'] && null !== $params['lng'] ) {
			usort(
				$items,
				static function ( $a, $b ) {
					return ( $a['distance_km'] ?? PHP_FLOAT_MAX ) <=> ( $b['distance_km'] ?? PHP_FLOAT_MAX );
				}
			);
		}

		$personal = false;
		$items = DDD_Future_Query::personal( $items, $params['weights'], $params, $personal );
		$has_more = ! empty( $base['next_cursor'] );
		if ( ! $items && ! $has_more ) {
			self::record_demand( $params );
		}
		$out = array(
			'items'                => array_values( $items ),
			'next_cursor'          => sanitize_text_field( (string) ( $base['next_cursor'] ?? '' ) ),
			'personal_order'       => $personal,
			'personal_order_notice'=> $personal ? __( 'This order reflects your own display preferences; it is not the official global merit rank.', DDD_TEXT_DOMAIN ) : '',
			'query_interpretation' => self::safe_interpretation( $interpretation ),
			'recovery'             => ( $items || $has_more ) ? array() : DDD_Future_Query::zero_result_recovery( $params ),
			'has_more'             => $has_more,
			'map_points'           => DDD_Future_Query::map_points( $items ),
			'generated_at'         => gmdate( 'c' ),
			'contract_version'     => self::CONTRACT_VERSION,
		);
		$sensitive = 'POST' === strtoupper( $request->get_method() ) || '' !== $params['q'] || null !== $params['lat'] || $personal;
		return $sensitive
			? self::no_store_response( $out )
			: self::public_response( $out, 'public, max-age=45, stale-while-revalidate=90' );
	}

	public static function rest_guided( WP_REST_Request $request ) {
		$params = $request->get_params();
		$params['accepting'] = true;
		$proxy = new WP_REST_Request( $request->get_method(), '/' );
		foreach ( $params as $key => $value ) {
			$proxy->set_param( $key, $value );
		}
		return self::rest_discover( $proxy );
	}

	public static function rest_interpret( WP_REST_Request $request ) {
		if ( ! self::public_rate_limit( 'future_interpret', 60 ) ) {
			return DDD_Helpers::safe_error( 'future_interpret_rate_limited', __( 'Interpretation rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 );
		}
		return self::no_store_response( self::safe_interpretation( DDD_Future_Query::interpret( (string) $request['q'] ) ) );
	}

	public static function rest_compare( WP_REST_Request $request ) {
		if ( ! self::public_rate_limit( 'future_compare', 60 ) ) {
			return DDD_Helpers::safe_error( 'future_compare_rate_limited', __( 'Comparison rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', (string) $request['ids'] ) ) ) ) );
		if ( count( $ids ) < 2 || count( $ids ) > self::MAX_COMPARE ) {
			return DDD_Helpers::safe_error( 'compare_count', __( 'Compare between two and four doctors.', DDD_TEXT_DOMAIN ), 400 );
		}
		$params = DDD_Future_Query::sanitize( array( 'timezone' => (string) $request['timezone'] ) );
		$items = array();
		foreach ( $ids as $id ) {
			if ( ! DDD_Helpers::valid_public_id( $id ) ) {
				continue;
			}
			$doctor = DDD_Repository::get_by_public_id( $id );
			if ( $doctor ) {
				$items[] = DDD_Future_Query::enrich( $doctor, $params );
			}
		}
		if ( count( $items ) < 2 ) {
			return DDD_Helpers::safe_error( 'compare_unavailable', __( 'At least two eligible doctors are required for comparison.', DDD_TEXT_DOMAIN ), 404 );
		}
		return self::public_response(
			array(
				'items'  => $items,
				'notice' => __( 'This is a factual comparison, not a platform recommendation or treatment guarantee.', DDD_TEXT_DOMAIN ),
			),
			'public, max-age=30'
		);
	}

	private static function record_demand( $params ) {
		$filters = array(
			'country'   => $params['country'],
			'city'      => $params['city'],
			'specialty' => $params['specialty'],
			'language'  => $params['language'],
			'mode'      => $params['mode'],
		);
		if ( ! array_filter( $filters ) ) {
			return;
		}
		$key = substr( hash( 'sha256', wp_json_encode( $filters ) ), 0, 20 );
		$all = get_option( self::OPTION_DEMAND, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$row = is_array( $all[ $key ] ?? null ) ? $all[ $key ] : array( 'filters' => $filters, 'count' => 0, 'first_seen' => gmdate( 'c' ) );
		$row['count'] = absint( $row['count'] ?? 0 ) + 1;
		$row['last_seen'] = gmdate( 'c' );
		$all[ $key ] = $row;
		uasort( $all, static function ( $a, $b ) { return absint( $b['count'] ?? 0 ) <=> absint( $a['count'] ?? 0 ); } );
		update_option( self::OPTION_DEMAND, array_slice( $all, 0, 200, true ), false );
	}

	public static function rest_demand() {
		return self::no_store_response(
			array(
				'items'   => array_values( (array) get_option( self::OPTION_DEMAND, array() ) ),
				'privacy' => 'Aggregated filters only; no free-text query, IP, precise user location or patient data.',
			)
		);
	}

	public static function rest_transparency() {
		if ( ! self::public_rate_limit( 'future_transparency', 60 ) ) {
			return DDD_Helpers::safe_error( 'future_transparency_rate_limited', __( 'Transparency endpoint rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 );
		}
		$policy = self::public_policy( apply_filters( 'sabri_file26_ranking_policy_public_v1', null, array( 'consumer' => 'file07', 'contract_version' => self::CONTRACT_VERSION ) ) );
		$assurance = self::public_assurance( apply_filters( 'sabri_file24_doctor_ranking_assurance_public_v1', null, array( 'consumer' => 'file07' ) ) );
		return self::public_response(
			array(
				'owner'                    => 'File 26',
				'consumer'                 => 'File 07',
				'official_tiers'           => array( 'Top 10', 'Top 100', 'Top 1000', 'All Verified Doctors' ),
				'prohibited_signals'       => array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism', 'purchased_engagement' ),
				'personal_sort_is_not_global_rank' => true,
				'policy'                   => $policy,
				'independent_assurance'    => $assurance,
			),
			'public, max-age=300, stale-while-revalidate=600'
		);
	}

	public static function integrity_contract( $result, $request ) {
		$signals = array_map( 'sanitize_key', (array) ( $request['signals'] ?? array() ) );
		$bad = array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism', 'purchased_engagement' );
		$blocked = array_values( array_intersect( $signals, $bad ) );
		return array(
			'contract_version'                     => self::CONTRACT_VERSION,
			'allow_as_merit_signal'                => empty( $blocked ),
			'blocked_signals'                      => $blocked,
			'engagement_requires_manipulation_screen' => true,
			'source'                               => 'file07-discovery-integrity',
		);
	}

	public static function rest_offline_pack( WP_REST_Request $request ) {
		if ( ! self::public_rate_limit( 'future_offline_pack', 30 ) ) {
			return DDD_Helpers::safe_error( 'future_offline_rate_limited', __( 'Offline-pack rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 );
		}
		$params = DDD_Future_Query::sanitize(
			array(
				'country'  => $request['country'],
				'city'     => $request['city'],
				'language' => $request['language'],
				'limit'    => DDD_Future_Query::MAX_RESULTS,
			)
		);
		$base = DDD_Repository::search( DDD_Future_Query::repository( $params ) );
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$items = array();
		foreach ( (array) ( $base['items'] ?? array() ) as $doctor ) {
			$items[] = array_intersect_key( $doctor, array_flip( array( 'public_id', 'display_name', 'professional_title', 'specialty', 'country', 'city', 'languages', 'consultation_modes', 'profile_url', 'clinic_url', 'appointment_url', 'verified_at' ) ) );
		}
		return self::public_response(
			array(
				'items'                => $items,
				'offline_copy'         => true,
				'stale_label_required' => true,
				'generated_at'         => gmdate( 'c' ),
				'expires_at'           => gmdate( 'c', time() + 6 * HOUR_IN_SECONDS ),
			),
			'public, max-age=21600, must-revalidate'
		);
	}
}
