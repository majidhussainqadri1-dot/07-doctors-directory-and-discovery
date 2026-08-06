<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_7 {
	public static function outbox_add( $event_type, $aggregate_id, $payload ) {
		global $wpdb;
		$table = self::table( 'outbox' );
		$event_id = wp_generate_uuid4();
		$now = current_time( 'mysql', true );
		$result = $wpdb->insert(
			$table,
			array( 'event_id' => $event_id, 'event_type' => sanitize_text_field( $event_type ), 'aggregate_id' => sanitize_text_field( $aggregate_id ), 'payload_json' => wp_json_encode( $payload ), 'status' => 'pending', 'attempts' => 0, 'available_at' => $now, 'last_error' => '', 'created_at' => $now, 'updated_at' => $now ),
			array( '%s','%s','%s','%s','%s','%d','%s','%s','%s','%s' )
		);
		if ( false === $result ) {
			return DDD_Helpers::safe_error( 'outbox_write_failed', __( 'The change could not be queued reliably.', DDD_TEXT_DOMAIN ), 500 );
		}
		return $event_id;
	}
	public static function process_outbox( $limit = 50 ) {
		global $wpdb;
		$table = self::table( 'outbox' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status='pending' AND available_at<=%s ORDER BY id ASC LIMIT %d", current_time( 'mysql', true ), max( 1, min( 100, absint( $limit ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			try {
				do_action( 'ddd_outbox_event', $row['event_type'], json_decode( $row['payload_json'], true ), $row['event_id'] );
				$wpdb->update( $table, array( 'status' => 'sent', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $row['id'] ) ), array( '%s','%s' ), array( '%d' ) );
			} catch ( Throwable $e ) {
				$attempts = absint( $row['attempts'] ) + 1;
				$status = $attempts >= 8 ? 'dead' : 'pending';
				$delay = min( DAY_IN_SECONDS, (int) pow( 2, $attempts ) * 60 );
				$wpdb->update( $table, array( 'status' => $status, 'attempts' => $attempts, 'available_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ), 'last_error' => sanitize_text_field( substr( $e->getMessage(), 0, 180 ) ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $row['id'] ) ), array( '%s','%d','%s','%s','%s' ), array( '%d' ) );
			}
		}
		return count( $rows );
	}
	public static function consume_event( $event_id, $event_type, $payload ) {
		global $wpdb;
		$event_id = sanitize_text_field( $event_id );
		$event_type = sanitize_text_field( $event_type );
		if ( ! $event_id || ! is_array( $payload ) || empty( $payload['doctor_id'] ) ) {
			return DDD_Helpers::safe_error( 'event_invalid', __( 'Invalid directory event.', DDD_TEXT_DOMAIN ), 400 );
		}
		$allowed = array( 'DoctorVerified.v1', 'DoctorSuspended.v1', 'PublicProfileUpdated.v1', 'ClinicAvailabilityChanged.v1', 'DoctorDeleted.v1' );
		if ( ! in_array( $event_type, $allowed, true ) ) {
			return DDD_Helpers::safe_error( 'event_type_unsupported', __( 'Unsupported directory event.', DDD_TEXT_DOMAIN ), 400 );
		}
		$inbox = self::table( 'inbox' );
		$hash = hash( 'sha256', wp_json_encode( $payload ) );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT payload_hash,processed_at FROM {$inbox} WHERE event_id=%s", $event_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing ) {
			return hash_equals( $existing['payload_hash'], $hash ) ? true : DDD_Helpers::safe_error( 'event_replay_mismatch', __( 'A repeated event did not match its original payload.', DDD_TEXT_DOMAIN ), 409 );
		}
		$inserted = $wpdb->insert(
			$inbox,
			array( 'event_id' => $event_id, 'event_type' => $event_type, 'payload_hash' => $hash, 'processed_at' => '1970-01-01 00:00:01' ),
			array( '%s','%s','%s','%s' )
		);
		if ( false === $inserted ) {
			return DDD_Helpers::safe_error( 'event_inbox_failed', __( 'The event could not be reserved for processing.', DDD_TEXT_DOMAIN ), 500 );
		}
		$rebuilt = self::rebuild_doctor( absint( $payload['doctor_id'] ), 'event:' . $event_type );
		if ( is_wp_error( $rebuilt ) ) {
			$wpdb->delete( $inbox, array( 'event_id' => $event_id ), array( '%s' ) );
			return $rebuilt;
		}
		$wpdb->update( $inbox, array( 'processed_at' => current_time( 'mysql', true ) ), array( 'event_id' => $event_id ), array( '%s' ), array( '%s' ) );
		return true;
	}
	public static function reconcile( $cursor = 0, $limit = self::RECONCILE_BATCH ) {
		global $wpdb;
		$cursor = absint( $cursor );
		$limit = max( 1, min( 500, absint( $limit ) ) );
		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID>%d ORDER BY ID ASC LIMIT %d", $cursor, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$processed = 0;
		$last = $cursor;
		$errors = array();
		foreach ( $user_ids as $user_id ) {
			$last = absint( $user_id );
			$claims = DDD_Contracts::verification_claims( $user_id );
			if ( ! $claims['doctor'] && ! self::projection_exists( $user_id ) ) {
				continue;
			}
			$result = self::rebuild_doctor( $user_id, 'reconciliation' );
			$processed++;
			if ( is_wp_error( $result ) ) {
				$errors[] = array( 'user_id' => absint( $user_id ), 'code' => $result->get_error_code() );
			}
		}
		$done = count( $user_ids ) < $limit;
		if ( $done ) {
			self::outbox_add( 'DoctorDirectoryIndexReconciled.v1', 'directory', array( 'processed' => $processed, 'errors' => count( $errors ), 'completed_at' => current_time( 'mysql', true ) ) );
		}
		return array( 'processed' => $processed, 'errors' => $errors, 'next_cursor' => $done ? 0 : $last, 'done' => $done );
	}
	private static function projection_exists( $user_id ) {
		global $wpdb;
		$table = self::table( 'projection' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE doctor_id=%d", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
