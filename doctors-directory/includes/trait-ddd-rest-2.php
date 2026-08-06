<?php
defined( 'ABSPATH' ) || exit;

trait DDD_REST_Trait_2 {
	public function event_permission( WP_REST_Request $request ) {
		$secret = (string) get_option( 'ddd_event_shared_secret', '' );
		if ( strlen( $secret ) < 32 ) {
			return false;
		}
		$timestamp = (string) $request->get_header( 'X-DDD-Timestamp' );
		$signature = (string) $request->get_header( 'X-DDD-Signature' );
		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}
		$body = $request->get_body();
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		return $signature && hash_equals( $expected, $signature );
	}
	public function event( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) {
			return DDD_Helpers::safe_error( 'safe_mode', __( 'Event consumption is disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), 503 );
		}
		$result = DDD_Repository::consume_event( sanitize_text_field( $request['event_id'] ), sanitize_text_field( $request['event_type'] ), (array) $request['payload'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'accepted' => true ) );
	}
	public function reconcile( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) {
			return DDD_Helpers::safe_error( 'safe_mode', __( 'Reconciliation is disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), 503 );
		}
		return rest_ensure_response( DDD_Repository::reconcile( absint( $request['cursor'] ), absint( $request['limit'] ? $request['limit'] : DDD_Repository::RECONCILE_BATCH ) ) );
	}
}
