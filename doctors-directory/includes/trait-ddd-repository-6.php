<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_6 {
	public static function save_reference( $user_id, $doctor_id, $save = true ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$doctor_id = absint( $doctor_id );
		if ( ! $user_id || ! $doctor_id ) {
			return DDD_Helpers::safe_error( 'save_invalid', __( 'Invalid save request.', DDD_TEXT_DOMAIN ), 400 );
		}
		$projection = self::get_status( $doctor_id );
		if ( empty( $projection['eligible'] ) ) {
			return DDD_Helpers::safe_error( 'save_target_unavailable', __( 'This doctor is not currently available in the public directory.', DDD_TEXT_DOMAIN ), 409 );
		}
		$table = self::table( 'saved_refs' );
		if ( $save ) {
			$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (user_id,doctor_id,created_at) VALUES (%d,%d,%s)", $user_id, $doctor_id, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return false !== $result;
		}
		return false !== $wpdb->delete( $table, array( 'user_id' => $user_id, 'doctor_id' => $doctor_id ), array( '%d','%d' ) );
	}
	public static function report( $doctor_id, $reporter_id, $reason, $details, $evidence_url = '', $idempotency_key = '' ) {
		global $wpdb;
		$allowed = array( 'credentials', 'incorrect-information', 'medical-safety', 'impersonation', 'spam', 'harassment', 'copyright', 'other' );
		$doctor_id = absint( $doctor_id );
		$reporter_id = absint( $reporter_id );
		$reason = sanitize_key( $reason );
		$details = sanitize_textarea_field( $details );
		if ( ! $doctor_id || ! $reporter_id || ! in_array( $reason, $allowed, true ) || strlen( trim( $details ) ) < 10 ) {
			return DDD_Helpers::safe_error( 'report_invalid', __( 'Provide a valid reason and sufficient detail.', DDD_TEXT_DOMAIN ), 400 );
		}
		$status = self::get_status( $doctor_id );
		if ( empty( $status['eligible'] ) ) {
			return DDD_Helpers::safe_error( 'report_target_unavailable', __( 'This public listing is unavailable.', DDD_TEXT_DOMAIN ), 404 );
		}
		if ( ! DDD_Helpers::rate_limit( 'report', $reporter_id . '|' . DDD_Helpers::current_ip_hash(), 5, HOUR_IN_SECONDS ) ) {
			return DDD_Helpers::safe_error( 'report_rate_limited', __( 'Too many reports were submitted. Try again later.', DDD_TEXT_DOMAIN ), 429 );
		}
		$idempotency_key = $idempotency_key ? sanitize_text_field( $idempotency_key ) : 'report:' . $reporter_id . ':' . substr( hash( 'sha256', $doctor_id . '|' . $reason . '|' . $details ), 0, 32 );
		$table = self::table( 'reports' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE idempotency_key=%s", $idempotency_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing ) {
			return absint( $existing );
		}
		$now = current_time( 'mysql', true );
		$result = $wpdb->insert(
			$table,
			array(
				'doctor_id'       => $doctor_id,
				'reporter_id'     => $reporter_id,
				'reason'          => $reason,
				'details'         => function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 2000 ) : substr( $details, 0, 2000 ),
				'evidence_url'    => esc_url_raw( $evidence_url ),
				'status'          => 'open',
				'reviewer_id'     => 0,
				'review_note'     => '',
				'idempotency_key' => $idempotency_key,
				'ip_hash'         => DDD_Helpers::current_ip_hash(),
				'version'         => 1,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d','%d','%s','%s','%s','%s','%d','%s','%s','%s','%d','%s','%s' )
		);
		if ( false === $result ) {
			return DDD_Helpers::safe_error( 'report_write_failed', __( 'The report could not be saved.', DDD_TEXT_DOMAIN ), 500 );
		}
		return absint( $wpdb->insert_id );
	}
	public static function transition_report( $report_id, $actor_id, $new_status, $note, $expected_version ) {
		global $wpdb;
		$allowed = array( 'open', 'reviewing', 'resolved', 'dismissed', 'escalated' );
		$new_status = sanitize_key( $new_status );
		if ( ! in_array( $new_status, $allowed, true ) || ! trim( $note ) ) {
			return DDD_Helpers::safe_error( 'report_transition_invalid', __( 'A valid status and reviewer reason are required.', DDD_TEXT_DOMAIN ), 400 );
		}
		$table = self::table( 'reports' );
		$audit = self::table( 'report_audit' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", absint( $report_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return DDD_Helpers::safe_error( 'report_not_found', __( 'Report not found.', DDD_TEXT_DOMAIN ), 404 );
		}
		if ( absint( $row['version'] ) !== absint( $expected_version ) ) {
			return DDD_Helpers::safe_error( 'version_conflict', __( 'The report changed. Reload and retry.', DDD_TEXT_DOMAIN ), 409 );
		}
		$trace_id = DDD_Helpers::trace_id();
		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$table,
			array( 'status' => $new_status, 'reviewer_id' => absint( $actor_id ), 'review_note' => sanitize_textarea_field( $note ), 'version' => absint( $row['version'] ) + 1, 'updated_at' => $now ),
			array( 'id' => absint( $report_id ), 'version' => absint( $row['version'] ) ),
			array( '%s','%d','%s','%d','%s' ),
			array( '%d','%d' )
		);
		$audited = false;
		if ( 1 === $updated ) {
			$audited = $wpdb->insert( $audit, array( 'report_id' => absint( $report_id ), 'actor_id' => absint( $actor_id ), 'old_status' => $row['status'], 'new_status' => $new_status, 'note' => sanitize_textarea_field( $note ), 'trace_id' => $trace_id, 'created_at' => $now ), array( '%d','%d','%s','%s','%s','%s','%s' ) );
		}
		if ( 1 !== $updated || false === $audited ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return DDD_Helpers::safe_error( 'report_transition_failed', __( 'The report and audit record could not be updated atomically.', DDD_TEXT_DOMAIN ), 409 );
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return true;
	}
}
