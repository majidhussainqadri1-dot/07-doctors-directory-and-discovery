<?php
defined( 'ABSPATH' ) || exit;

trait DDD_REST_Trait_1 {
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}
	public function routes() {
		register_rest_route( self::NS, '/doctors', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'search' ),
			'permission_callback' => '__return_true',
			'args'                => $this->search_args(),
		) );
		register_rest_route( self::NS, '/doctors/(?P<public_id>[a-f0-9-]{36})', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'doctor' ),
			'permission_callback' => '__return_true',
		) );
		register_rest_route( self::NS, '/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'status' ),
			'permission_callback' => 'is_user_logged_in',
		) );
		register_rest_route( self::NS, '/reports', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'report' ),
			'permission_callback' => 'is_user_logged_in',
		) );
		register_rest_route( self::NS, '/saved/(?P<public_id>[a-f0-9-]{36})', array(
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save' ), 'permission_callback' => 'is_user_logged_in' ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'unsave' ), 'permission_callback' => 'is_user_logged_in' ),
		) );
		register_rest_route( self::NS, '/events', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'event' ),
			'permission_callback' => array( $this, 'event_permission' ),
		) );
		register_rest_route( self::NS, '/admin/reconcile', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'reconcile' ),
			'permission_callback' => static function () { return current_user_can( 'ddd_run_reconciliation' ); },
		) );
		register_rest_route( self::NS, '/admin/health', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => static function () { return rest_ensure_response( DDD_Observability::system_check() ); },
			'permission_callback' => static function () { return current_user_can( 'ddd_view_health' ); },
		) );
	}
	private function search_args() {
		return array(
			'q' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'country' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'city' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'specialty' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'language' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'qualification' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'min_experience' => array( 'sanitize_callback' => 'absint' ),
			'mode' => array( 'sanitize_callback' => 'sanitize_key' ),
			'accepting' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'currency' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'fee_min' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'fee_max' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'cursor' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'limit' => array( 'sanitize_callback' => 'absint', 'default' => DDD_Repository::DEFAULT_LIMIT ),
		);
	}
	public function search( WP_REST_Request $request ) {
		$actor = DDD_Helpers::current_ip_hash();
		if ( ! DDD_Helpers::rate_limit( 'search', $actor, 120, MINUTE_IN_SECONDS ) ) {
			return DDD_Helpers::safe_error( 'search_rate_limited', __( 'Search rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 );
		}
		$result = DDD_Repository::search( $request->get_params() );
		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=120' );
		$response->header( 'ETag', '"' . hash( 'sha256', wp_json_encode( $result ) . '|' . get_option( 'ddd_cache_version', 1 ) ) . '"' );
		return $response;
	}
	public function doctor( WP_REST_Request $request ) {
		$doctor = DDD_Repository::get_by_public_id( $request['public_id'] );
		if ( ! $doctor ) {
			return DDD_Helpers::safe_error( 'doctor_not_found', __( 'Doctor not found.', DDD_TEXT_DOMAIN ), 404 );
		}
		$response = rest_ensure_response( $doctor );
		$response->header( 'Cache-Control', 'public, max-age=120, stale-while-revalidate=300' );
		return $response;
	}
	public function status() {
		$claims = DDD_Contracts::verification_claims( get_current_user_id() );
		if ( ! $claims['doctor'] ) {
			return DDD_Helpers::safe_error( 'not_doctor', __( 'Doctor status is unavailable for this account.', DDD_TEXT_DOMAIN ), 403 );
		}
		$response = rest_ensure_response( DDD_Repository::get_status( get_current_user_id() ) );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
	public function report( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) {
			return DDD_Helpers::safe_error( 'safe_mode', __( 'Reports are temporarily unavailable while Safe Mode is active.', DDD_TEXT_DOMAIN ), 503 );
		}
		$result = DDD_Repository::report( DDD_Repository::resolve_doctor_id( sanitize_text_field( $request['public_id'] ) ), get_current_user_id(), $request['reason'], $request['details'], $request['evidence_url'], DDD_Helpers::idempotency_key( $request ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'report_id' => $result, 'status' => 'open' ), 201 );
	}
	public function save( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) {
			return DDD_Helpers::safe_error( 'safe_mode', __( 'Saves are temporarily unavailable while Safe Mode is active.', DDD_TEXT_DOMAIN ), 503 );
		}
		$result = DDD_Repository::save_reference( get_current_user_id(), DDD_Repository::resolve_doctor_id( sanitize_text_field( $request['public_id'] ) ), true );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'saved' => true ) );
	}
	public function unsave( WP_REST_Request $request ) {
		$result = DDD_Repository::save_reference( get_current_user_id(), DDD_Repository::resolve_doctor_id( sanitize_text_field( $request['public_id'] ) ), false );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'saved' => false ) );
	}
}
