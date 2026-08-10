<?php
defined( 'ABSPATH' ) || exit;

/**
 * F07-FUT-08/09 mutation constitution.
 *
 * Saved-search and shortlist writes remain private user-meta owned by File 07,
 * but every REST mutation is serialized per user, bounded, replay-safe,
 * idempotent and auditable without adding a new database schema.
 */
final class DDD_Future_Mutation_Guard {
	const META_RECEIPTS = 'ddd_future_mutation_receipts_v1';
	const RECEIPT_TTL   = DAY_IN_SECONDS;
	const MAX_RECEIPTS  = 50;
	const LOCK_TTL      = 30;
	const RATE_LIMIT    = 30;

	private static $active = null;

	public static function register() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 9, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'post_dispatch' ), 9, 3 );
		add_action( 'ddd_future_mutation_receipt_cleanup', array( __CLASS__, 'cleanup_receipts' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	private static function mutation_scope( WP_REST_Request $request ) {
		$route  = (string) $request->get_route();
		$method = strtoupper( (string) $request->get_method() );
		$base   = '#^/doctors-directory-discovery/v1/future/';
		if ( 'POST' === $method && preg_match( $base . 'saved-searches/?$#', $route ) ) { return 'saved_search_save'; }
		if ( 'DELETE' === $method && preg_match( $base . 'saved-searches/[a-f0-9]{32}/?$#', $route ) ) { return 'saved_search_delete'; }
		if ( 'POST' === $method && preg_match( $base . 'shortlists/?$#', $route ) ) { return 'shortlist_save'; }
		if ( 'DELETE' === $method && preg_match( $base . 'shortlists/[a-f0-9]{32}/?$#', $route ) ) { return 'shortlist_delete'; }
		return '';
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			if ( is_bool( $value ) || is_null( $value ) || is_numeric( $value ) ) { return $value; }
			return sanitize_text_field( (string) $value );
		}
		$keys = array_keys( $value );
		$is_list = empty( $value ) || $keys === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) { ksort( $value, SORT_STRING ); }
		foreach ( $value as $key => $item ) { $value[ $key ] = self::canonicalize( $item ); }
		return $value;
	}

	private static function request_hash( WP_REST_Request $request, $scope ) {
		$payload = self::canonicalize( (array) $request->get_params() );
		return hash( 'sha256', wp_json_encode( array( 'scope' => $scope, 'payload' => $payload ) ) );
	}

	private static function receipt_key( $scope, $key ) {
		return hash_hmac( 'sha256', sanitize_key( $scope ) . '|' . $key, wp_salt( 'nonce' ) );
	}

	private static function receipts( $user_id ) {
		$rows = get_user_meta( absint( $user_id ), self::META_RECEIPTS, true );
		$rows = is_array( $rows ) ? $rows : array();
		$now  = time();
		foreach ( $rows as $key => $row ) {
			if ( ! is_array( $row ) || absint( $row['expires_at'] ?? 0 ) <= $now ) { unset( $rows[ $key ] ); }
		}
		return $rows;
	}

	private static function schedule_cleanup( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id && ! wp_next_scheduled( 'ddd_future_mutation_receipt_cleanup', array( $user_id ) ) ) {
			wp_schedule_single_event( time() + self::RECEIPT_TTL + MINUTE_IN_SECONDS, 'ddd_future_mutation_receipt_cleanup', array( $user_id ) );
		}
	}

	public static function cleanup_receipts( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return; }
		$rows = self::receipts( $user_id );
		if ( $rows ) {
			update_user_meta( $user_id, self::META_RECEIPTS, $rows );
			$next = min( array_map( static function ( $row ) { return absint( $row['expires_at'] ?? 0 ); }, $rows ) );
			wp_schedule_single_event( max( time() + MINUTE_IN_SECONDS, $next + MINUTE_IN_SECONDS ), 'ddd_future_mutation_receipt_cleanup', array( $user_id ) );
		} else {
			delete_user_meta( $user_id, self::META_RECEIPTS );
		}
	}

	private static function acquire_lock( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$name = 'ddd_future_mutation_lock_' . substr( hash_hmac( 'sha256', (string) $user_id, wp_salt( 'auth' ) ), 0, 32 );
		$token = DDD_Helpers::trace_id();
		$raw = wp_json_encode( array( 'token' => $token, 'expires' => time() + self::LOCK_TTL ) );
		if ( add_option( $name, $raw, '', 'no' ) ) { return array( 'name' => $name, 'token' => $token ); }
		$current_raw = (string) get_option( $name, '' );
		$current = json_decode( $current_raw, true );
		if ( ! is_array( $current ) || empty( $current['expires'] ) || absint( $current['expires'] ) <= time() ) {
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $name, $current_raw ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $deleted && add_option( $name, $raw, '', 'no' ) ) { return array( 'name' => $name, 'token' => $token ); }
		}
		return array();
	}

	private static function release_lock( $lock ) {
		global $wpdb;
		if ( empty( $lock['name'] ) || empty( $lock['token'] ) ) { return; }
		$current_raw = (string) get_option( $lock['name'], '' );
		$current = json_decode( $current_raw, true );
		if ( ! is_array( $current ) || empty( $current['token'] ) || ! hash_equals( (string) $current['token'], (string) $lock['token'] ) ) { return; }
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $lock['name'], $current_raw ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private static function audit( $scope, $user_id, $outcome, $receipt_hash, $request ) {
		DDD_Repository::audit_admin(
			'future_mutation', absint( $user_id ), sanitize_key( $scope ), substr( sanitize_text_field( (string) $receipt_hash ), 0, 24 ), sanitize_key( $outcome ),
			array( 'method' => strtoupper( (string) $request->get_method() ), 'route' => (string) $request->get_route() )
		);
	}

	private static function replay_response( $entry ) {
		$status = absint( $entry['status'] ?? 200 );
		$status = $status >= 200 && $status < 300 ? $status : 200;
		$payload = is_array( $entry['payload'] ?? null ) ? $entry['payload'] : array( 'ok' => true );
		$response = new WP_REST_Response( $payload, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-DDD-Idempotent-Replay', '1' );
		return $response;
	}

	public static function pre_dispatch( $result, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request ) { return $result; }
		$scope = self::mutation_scope( $request );
		if ( ! $scope || ! is_user_logged_in() ) { return $result; }
		$user_id = get_current_user_id();
		if ( ! DDD_Helpers::rate_limit( 'future_mutation_' . $scope, $user_id, self::RATE_LIMIT, MINUTE_IN_SECONDS ) ) {
			return DDD_Helpers::safe_error( 'future_mutation_rate_limited', __( 'Too many saved-search or shortlist changes. Retry shortly.', DDD_TEXT_DOMAIN ), 429 );
		}
		$idempotency_key = DDD_Helpers::idempotency_key( $request );
		if ( ! $idempotency_key ) { return DDD_Helpers::safe_error( 'future_idempotency_key_required', __( 'An Idempotency-Key header is required for this private mutation.', DDD_TEXT_DOMAIN ), 400 ); }
		$receipt_hash = self::receipt_key( $scope, $idempotency_key );
		$request_hash = self::request_hash( $request, $scope );
		$lock = self::acquire_lock( $user_id );
		if ( ! $lock ) { return DDD_Helpers::safe_error( 'future_mutation_busy', __( 'Another private discovery change is still being committed. Retry shortly.', DDD_TEXT_DOMAIN ), 409, array( 'retry_after' => 1 ) ); }
		$receipts = self::receipts( $user_id );
		if ( isset( $receipts[ $receipt_hash ] ) ) {
			$entry = $receipts[ $receipt_hash ];
			if ( empty( $entry['request_hash'] ) || ! hash_equals( (string) $entry['request_hash'], $request_hash ) ) {
				self::audit( $scope, $user_id, 'conflict', $receipt_hash, $request ); self::release_lock( $lock );
				return DDD_Helpers::safe_error( 'future_idempotency_reuse_conflict', __( 'This Idempotency-Key was already used for a different mutation.', DDD_TEXT_DOMAIN ), 409 );
			}
			self::audit( $scope, $user_id, 'replay', $receipt_hash, $request ); self::release_lock( $lock );
			return self::replay_response( $entry );
		}
		self::$active = array( 'user_id' => $user_id, 'scope' => $scope, 'receipt_hash' => $receipt_hash, 'request_hash' => $request_hash, 'lock' => $lock );
		return $result;
	}

	public static function post_dispatch( $result, $server, $request ) {
		if ( ! self::$active || ! $request instanceof WP_REST_Request ) { return $result; }
		$ctx = self::$active; self::$active = null;
		try {
			if ( self::mutation_scope( $request ) !== $ctx['scope'] || get_current_user_id() !== absint( $ctx['user_id'] ) ) {
				self::audit( $ctx['scope'], $ctx['user_id'], 'context_mismatch', $ctx['receipt_hash'], $request ); return $result;
			}
			if ( is_wp_error( $result ) ) { self::audit( $ctx['scope'], $ctx['user_id'], 'failed', $ctx['receipt_hash'], $request ); return $result; }
			$response = rest_ensure_response( $result );
			$status = $response instanceof WP_HTTP_Response ? absint( $response->get_status() ) : 200;
			if ( $status < 200 || $status >= 300 ) { self::audit( $ctx['scope'], $ctx['user_id'], 'failed', $ctx['receipt_hash'], $request ); return $result; }
			$payload = $response instanceof WP_HTTP_Response ? $response->get_data() : array( 'ok' => true );
			$payload = is_array( $payload ) ? $payload : array( 'result' => sanitize_text_field( (string) $payload ) );
			$receipts = self::receipts( $ctx['user_id'] );
			$receipts[ $ctx['receipt_hash'] ] = array( 'scope' => $ctx['scope'], 'request_hash' => $ctx['request_hash'], 'status' => $status, 'payload' => $payload, 'created_at' => time(), 'expires_at' => time() + self::RECEIPT_TTL );
			if ( count( $receipts ) > self::MAX_RECEIPTS ) { $receipts = array_slice( $receipts, -self::MAX_RECEIPTS, null, true ); }
			update_user_meta( $ctx['user_id'], self::META_RECEIPTS, $receipts );
			self::schedule_cleanup( $ctx['user_id'] );
			self::audit( $ctx['scope'], $ctx['user_id'], 'success', $ctx['receipt_hash'], $request );
			return $result;
		} finally {
			self::release_lock( $ctx['lock'] );
		}
	}

	public static function erasers( $erasers ) {
		$erasers['ddd-future-mutation-receipts'] = array(
			'eraser_friendly_name' => __( 'Doctor discovery mutation security receipts', DDD_TEXT_DOMAIN ),
			'callback' => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$removed = delete_user_meta( $user->ID, self::META_RECEIPTS );
		wp_clear_scheduled_hook( 'ddd_future_mutation_receipt_cleanup', array( $user->ID ) );
		return array( 'items_removed' => (bool) $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}
}
