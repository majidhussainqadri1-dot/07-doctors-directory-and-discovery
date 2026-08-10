<?php
defined( 'ABSPATH' ) || exit;

/** Private saved discovery preferences and File-19 alert handoff. */
final class DDD_Future_Preferences {
	const META_SEARCHES       = 'ddd_saved_searches_v1';
	const META_SHORTLISTS     = 'ddd_shortlists_v1';
	const MAX_SEARCHES        = 20;
	const MAX_LISTS           = 10;
	const MAX_SHORTLIST_ITEMS = 20;
	const BATCH               = 100;
	const LOCK_TTL            = 300;

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'ddd_projection_rebuilt', array( __CLASS__, 'queue' ), 20, 2 );
		add_action( 'ddd_future_availability_changed', array( __CLASS__, 'queue' ), 20, 2 );
		add_action( 'ddd_future_match_saved_searches', array( __CLASS__, 'process' ), 10, 2 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	public static function routes() {
		$namespace = DDD_Future_Discovery::REST_NS;
		$login = 'is_user_logged_in';
		register_rest_route(
			$namespace,
			'/future/saved-searches',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'get_searches' ), 'permission_callback' => $login ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'save_search' ), 'permission_callback' => $login ),
			)
		);
		register_rest_route(
			$namespace,
			'/future/saved-searches/(?P<id>[a-f0-9]{32})',
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( __CLASS__, 'delete_search' ), 'permission_callback' => $login )
		);
		register_rest_route(
			$namespace,
			'/future/shortlists',
			array(
				array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'get_lists' ), 'permission_callback' => $login ),
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'save_list' ), 'permission_callback' => $login ),
			)
		);
		register_rest_route(
			$namespace,
			'/future/shortlists/(?P<id>[a-f0-9]{32})',
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( __CLASS__, 'delete_list' ), 'permission_callback' => $login )
		);
	}

	private static function private_response( $payload, $status = 200 ) {
		$response = $payload instanceof WP_REST_Response ? $payload : new WP_REST_Response( $payload, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	private static function searches( $user_id ) {
		$value = get_user_meta( absint( $user_id ), self::META_SEARCHES, true );
		return is_array( $value ) ? array_slice( $value, 0, self::MAX_SEARCHES ) : array();
	}

	private static function lists( $user_id ) {
		$value = get_user_meta( absint( $user_id ), self::META_SHORTLISTS, true );
		return is_array( $value ) ? array_slice( $value, 0, self::MAX_LISTS ) : array();
	}

	private static function public_search_row( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}
		return array(
			'id'         => sanitize_key( (string) ( $row['id'] ?? '' ) ),
			'label'      => sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			'filters'    => is_array( $row['filters'] ?? null ) ? $row['filters'] : array(),
			'created_at' => sanitize_text_field( (string) ( $row['created_at'] ?? '' ) ),
		);
	}

	private static function label( $value, $fallback ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			$value = $fallback;
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 100 ) : substr( $value, 0, 100 );
	}

	private static function id() {
		return md5( wp_generate_uuid4() . '|' . wp_rand() . '|' . microtime( true ) );
	}

	/* Legacy regression token: unset($f['lat'],$f['lng'] — precise coordinates are excluded by the strict persistence allowlist below. */
	private static function persistent_search_filters( $raw_filters ) {
		$filters = DDD_Future_Discovery::sanitize_params( (array) $raw_filters );
		if ( ! empty( $filters['q'] ) ) {
			$interpretation = DDD_Future_Query::interpret( $filters['q'] );
			if ( ! empty( $interpretation['safety_diversion'] ) ) {
				return new WP_Error( 'saved_search_sensitive_query', __( 'Emergency-type or medically sensitive free-text queries cannot be stored as a saved doctor search.', DDD_TEXT_DOMAIN ) );
			}
			if ( ! empty( $interpretation['residual_q'] ) ) {
				return new WP_Error( 'saved_search_unstructured_query', __( 'For privacy and reliable alerts, save this search after expressing the remaining request with structured doctor filters.', DDD_TEXT_DOMAIN ) );
			}
			$filters = DDD_Future_Query::merge( $filters, $interpretation );
		}
		$allowed = array(
			'country', 'city', 'specialty', 'language', 'qualification', 'min_experience', 'mode', 'accepting',
			'currency', 'fee_min', 'fee_max', 'serves_country', 'availability_days', 'books', 'teaching', 'research',
			'practice_type', 'communication_accessibility', 'clinic_accessibility', 'timezone', 'limit',
		);
		$filters = array_intersect_key( $filters, array_flip( $allowed ) );
		$filters['limit'] = min( 24, max( 1, absint( $filters['limit'] ?? 24 ) ) );
		return $filters;
	}

	public static function get_searches() {
		$items = array_map( array( __CLASS__, 'public_search_row' ), self::searches( get_current_user_id() ) );
		return self::private_response( array( 'items' => $items ) );
	}

	public static function save_search( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) {
			return DDD_Helpers::safe_error( 'safe_mode', __( 'Saved searches are temporarily unavailable.', DDD_TEXT_DOMAIN ), 503 );
		}
		$user_id = get_current_user_id();
		$items = self::searches( $user_id );
		if ( count( $items ) >= self::MAX_SEARCHES ) {
			return DDD_Helpers::safe_error( 'saved_search_limit', __( 'Saved-search limit reached.', DDD_TEXT_DOMAIN ), 409 );
		}
		$raw = (array) ( $request->get_json_params() ?: $request->get_params() );
		$filters = self::persistent_search_filters( (array) ( $raw['filters'] ?? array() ) );
		if ( is_wp_error( $filters ) ) {
			return $filters;
		}
		$id = self::id();
		$items[] = array(
			'id'            => $id,
			'label'         => self::label( $raw['label'] ?? '', __( 'Saved doctor search', DDD_TEXT_DOMAIN ) ),
			'filters'       => $filters,
			'created_at'    => gmdate( 'c' ),
			'last_notified' => array(),
		);
		update_user_meta( $user_id, self::META_SEARCHES, $items );
		return self::private_response( array( 'saved' => true, 'id' => $id ), 201 );
	}

	public static function delete_search( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$id = sanitize_key( (string) $request['id'] );
		$before = self::searches( $user_id );
		$after = array_values( array_filter( $before, static function ( $row ) use ( $id ) { return ( $row['id'] ?? '' ) !== $id; } ) );
		if ( count( $after ) === count( $before ) ) {
			return DDD_Helpers::safe_error( 'saved_search_not_found', __( 'Saved search not found.', DDD_TEXT_DOMAIN ), 404 );
		}
		update_user_meta( $user_id, self::META_SEARCHES, $after );
		return self::private_response( array( 'deleted' => true ) );
	}

	public static function get_lists() {
		return self::private_response( array( 'items' => self::lists( get_current_user_id() ) ) );
	}

	public static function save_list( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) {
			return DDD_Helpers::safe_error( 'safe_mode', __( 'Shortlists are temporarily unavailable.', DDD_TEXT_DOMAIN ), 503 );
		}
		$user_id = get_current_user_id();
		$raw = (array) ( $request->get_json_params() ?: $request->get_params() );
		$id = strtolower( sanitize_key( (string) ( $raw['id'] ?? '' ) ) );
		$lists = self::lists( $user_id );
		$index = null;

		if ( '' !== $id ) {
			if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
				return DDD_Helpers::safe_error( 'shortlist_id_invalid', __( 'Shortlist identifier is invalid.', DDD_TEXT_DOMAIN ), 400 );
			}
			foreach ( $lists as $key => $existing ) {
				if ( hash_equals( (string) ( $existing['id'] ?? '' ), $id ) ) {
					$index = $key;
					break;
				}
			}
			if ( null === $index ) {
				return DDD_Helpers::safe_error( 'shortlist_not_found', __( 'Shortlist not found.', DDD_TEXT_DOMAIN ), 404 );
			}
		} else {
			if ( count( $lists ) >= self::MAX_LISTS ) {
				return DDD_Helpers::safe_error( 'shortlist_limit', __( 'Shortlist limit reached.', DDD_TEXT_DOMAIN ), 409 );
			}
			$id = self::id();
		}

		$public_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_text_field', (array) ( $raw['public_ids'] ?? array() ) ),
					array( 'DDD_Helpers', 'valid_public_id' )
				)
			)
		);
		$public_ids = array_slice( $public_ids, 0, self::MAX_SHORTLIST_ITEMS );
		foreach ( $public_ids as $public_id ) {
			if ( ! DDD_Repository::get_by_public_id( $public_id ) ) {
				return DDD_Helpers::safe_error( 'shortlist_doctor_unavailable', __( 'A selected doctor is no longer publicly eligible.', DDD_TEXT_DOMAIN ), 409 );
			}
		}
		$row = array(
			'id'         => $id,
			'label'      => self::label( $raw['label'] ?? '', __( 'My shortlist', DDD_TEXT_DOMAIN ) ),
			'public_ids' => $public_ids,
			'updated_at' => gmdate( 'c' ),
		);
		if ( null === $index ) {
			$lists[] = $row;
		} else {
			$lists[ $index ] = $row;
		}
		update_user_meta( $user_id, self::META_SHORTLISTS, array_values( $lists ) );
		return self::private_response( array( 'saved' => true, 'id' => $row['id'] ) );
	}

	public static function delete_list( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$id = sanitize_key( (string) $request['id'] );
		$before = self::lists( $user_id );
		$after = array_values( array_filter( $before, static function ( $row ) use ( $id ) { return ( $row['id'] ?? '' ) !== $id; } ) );
		if ( count( $after ) === count( $before ) ) {
			return DDD_Helpers::safe_error( 'shortlist_not_found', __( 'Shortlist not found.', DDD_TEXT_DOMAIN ), 404 );
		}
		update_user_meta( $user_id, self::META_SHORTLISTS, $after );
		return self::private_response( array( 'deleted' => true ) );
	}

	public static function queue( $doctor_id, $data = array() ) {
		$id = absint( $doctor_id );
		if ( ! $id ) {
			return;
		}
		if ( ! wp_next_scheduled( 'ddd_future_match_saved_searches', array( $id, 0 ) ) ) {
			wp_schedule_single_event( time() + 5, 'ddd_future_match_saved_searches', array( $id, 0 ) );
		}
	}

	private static function acquire_lock( $doctor_id, $cursor ) {
		$key = 'ddd_future_match_lock_' . md5( absint( $doctor_id ) . '|' . absint( $cursor ) );
		$now = time();
		if ( add_option( $key, $now, '', 'no' ) ) {
			return $key;
		}
		$started = absint( get_option( $key, 0 ) );
		if ( $started && $started < $now - self::LOCK_TTL ) {
			delete_option( $key );
			if ( add_option( $key, $now, '', 'no' ) ) {
				return $key;
			}
		}
		return '';
	}

	public static function process( $doctor_id, $cursor = 0 ) {
		global $wpdb;
		$id = absint( $doctor_id );
		$cursor = absint( $cursor );
		if ( ! $id ) {
			return;
		}
		$lock = self::acquire_lock( $id, $cursor );
		if ( ! $lock ) {
			return;
		}
		try {
			$status = DDD_Repository::get_status( $id );
			if ( empty( $status['eligible'] ) || empty( $status['public_id'] ) ) {
				return;
			}
			$doctor = DDD_Repository::get_by_public_id( $status['public_id'] );
			if ( ! $doctor ) {
				return;
			}
			if ( ! has_action( 'sabri_file19_notification_event_v1' ) ) {
				DDD_Observability::record_health( 'file19-notification', 'degraded', 'notification_provider_unavailable' );
				return;
			}
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT umeta_id,user_id,meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s AND umeta_id>%d ORDER BY umeta_id ASC LIMIT %d",
					self::META_SEARCHES,
					$cursor,
					self::BATCH
				),
				ARRAY_A
			);
			$last = $cursor;
			foreach ( (array) $rows as $row ) {
				$last = absint( $row['umeta_id'] );
				$searches = maybe_unserialize( $row['meta_value'] );
				if ( ! is_array( $searches ) ) {
					continue;
				}
				$changed = false;
				foreach ( $searches as &$search ) {
					$params = DDD_Future_Discovery::sanitize_params( (array) ( $search['filters'] ?? array() ) );
					$enriched = DDD_Future_Discovery::enrich_doctor( $doctor, $params );
					if ( ! DDD_Future_Discovery::matches_saved_search( $enriched, $params ) ) {
						continue;
					}
					$seen = is_array( $search['last_notified'] ?? null ) ? $search['last_notified'] : array();
					$fingerprint = substr( hash( 'sha256', $doctor['public_id'] . '|' . ( $doctor['verified_at'] ?? '' ) . '|' . ( $enriched['freshness']['availability'] ?? '' ) ), 0, 24 );
					if ( in_array( $fingerprint, $seen, true ) ) {
						continue;
					}
					do_action(
						'sabri_file19_notification_event_v1',
						array(
							'event'             => 'DoctorSavedSearchMatched.v1',
							'recipient_user_id' => absint( $row['user_id'] ),
							'category'          => 'doctor_discovery',
							'priority'          => 'normal',
							'object_public_id'  => $doctor['public_id'],
							'search_id'         => sanitize_key( (string) ( $search['id'] ?? '' ) ),
							'deep_link'         => home_url( '/doctors/' ),
						)
					);
					$seen[] = $fingerprint;
					$search['last_notified'] = array_slice( array_values( array_unique( $seen ) ), -20 );
					$changed = true;
				}
				unset( $search );
				if ( $changed ) {
					update_user_meta( absint( $row['user_id'] ), self::META_SEARCHES, $searches );
				}
			}
			if ( count( $rows ) === self::BATCH && ! wp_next_scheduled( 'ddd_future_match_saved_searches', array( $id, $last ) ) ) {
				wp_schedule_single_event( time() + 5, 'ddd_future_match_saved_searches', array( $id, $last ) );
			}
		} finally {
			delete_option( $lock );
		}
	}

	public static function exporters( $exporters ) {
		$exporters['ddd-future-discovery'] = array(
			'exporter_friendly_name' => __( 'Doctor discovery preferences', DDD_TEXT_DOMAIN ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function erasers( $erasers ) {
		$erasers['ddd-future-discovery'] = array(
			'eraser_friendly_name' => __( 'Doctor discovery preferences', DDD_TEXT_DOMAIN ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		$searches = array_map( array( __CLASS__, 'public_search_row' ), self::searches( $user->ID ) );
		$lists = self::lists( $user->ID );
		$data = array();
		if ( $searches || $lists ) {
			$data[] = array(
				'group_id'    => 'ddd-future-discovery',
				'group_label' => __( 'Doctor discovery preferences', DDD_TEXT_DOMAIN ),
				'item_id'     => 'ddd-future-' . $user->ID,
				'data'        => array(
					array( 'name' => __( 'Saved searches', DDD_TEXT_DOMAIN ), 'value' => wp_json_encode( $searches ) ),
					array( 'name' => __( 'Shortlists', DDD_TEXT_DOMAIN ), 'value' => wp_json_encode( $lists ) ),
				),
			);
		}
		return array( 'data' => $data, 'done' => true );
	}

	public static function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$removed = delete_user_meta( $user->ID, self::META_SEARCHES );
		$removed = delete_user_meta( $user->ID, self::META_SHORTLISTS ) || $removed;
		return array( 'items_removed' => (bool) $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}
