<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Privacy {
	const BATCH = 50;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['doctors-directory-discovery'] = array( 'exporter_friendly_name' => __( 'Doctors Directory and Discovery', DDD_TEXT_DOMAIN ), 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		global $wpdb;
		$page = max( 1, absint( $page ) ); $offset = ( $page - 1 ) * self::BATCH; $data = array();
		if ( 1 === $page ) {
			$meta = array();
			foreach ( array( 'discoverable', 'public_phone', 'public_whatsapp' ) as $key ) {
				$value = DDD_Helpers::meta( $user->ID, $key, '' );
				if ( '' !== $value ) { $meta[] = array( 'name' => ucwords( str_replace( '_', ' ', $key ) ), 'value' => $value ); }
			}
			$status = DDD_Repository::get_status( $user->ID );
			$meta[] = array( 'name' => __( 'Directory eligibility', DDD_TEXT_DOMAIN ), 'value' => $status['eligible'] ? 'eligible' : 'not eligible' );
			$meta[] = array( 'name' => __( 'Eligibility reasons', DDD_TEXT_DOMAIN ), 'value' => implode( ', ', $status['reasons'] ) );
			$meta[] = array( 'name' => __( 'Public directory identifier', DDD_TEXT_DOMAIN ), 'value' => $status['public_id'] );
			$meta[] = array( 'name' => __( 'Projection updated at', DDD_TEXT_DOMAIN ), 'value' => $status['updated_at'] );
			$data[] = array( 'group_id' => 'ddd-profile', 'group_label' => __( 'Doctors Directory Projection', DDD_TEXT_DOMAIN ), 'item_id' => 'ddd-profile', 'data' => $meta );
		}

		$reports = DDD_Repository::table( 'reports' );
		$report_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$reports} WHERE reporter_id=%d OR doctor_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", $user->ID, $user->ID, self::BATCH, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $report_rows as $row ) {
			$relation = absint( $row['reporter_id'] ) === absint( $user->ID ) ? __( 'Submitted by you', DDD_TEXT_DOMAIN ) : __( 'About your public doctor listing', DDD_TEXT_DOMAIN );
			$item = array( array( 'name' => __( 'Relationship', DDD_TEXT_DOMAIN ), 'value' => $relation ), array( 'name' => __( 'Public doctor identifier', DDD_TEXT_DOMAIN ), 'value' => $row['doctor_public_id'] ), array( 'name' => __( 'Reason', DDD_TEXT_DOMAIN ), 'value' => $row['reason'] ), array( 'name' => __( 'Status', DDD_TEXT_DOMAIN ), 'value' => $row['status'] ), array( 'name' => __( 'Created at UTC', DDD_TEXT_DOMAIN ), 'value' => $row['created_at'] ), array( 'name' => __( 'Updated at UTC', DDD_TEXT_DOMAIN ), 'value' => $row['updated_at'] ) );
			if ( absint( $row['reporter_id'] ) === absint( $user->ID ) ) { $item[] = array( 'name' => __( 'Details', DDD_TEXT_DOMAIN ), 'value' => $row['details'] ); $item[] = array( 'name' => __( 'Evidence URL', DDD_TEXT_DOMAIN ), 'value' => $row['evidence_url'] ); }
			$data[] = array( 'group_id' => 'ddd-reports', 'group_label' => __( 'Doctors Directory Reports', DDD_TEXT_DOMAIN ), 'item_id' => 'ddd-report-' . absint( $row['id'] ), 'data' => $item );
		}

		$saves = DDD_Repository::table( 'saved_refs' ); $projection = DDD_Repository::table( 'projection' );
		$save_rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.public_id,p.display_name,s.created_at FROM {$saves} s LEFT JOIN {$projection} p ON p.doctor_id=s.doctor_id WHERE s.user_id=%d ORDER BY s.id ASC LIMIT %d OFFSET %d", $user->ID, self::BATCH, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $save_rows as $save ) {
			$data[] = array( 'group_id' => 'ddd-saves', 'group_label' => __( 'Saved Doctors', DDD_TEXT_DOMAIN ), 'item_id' => 'ddd-save-' . sanitize_key( $save['public_id'] ), 'data' => array( array( 'name' => __( 'Doctor name', DDD_TEXT_DOMAIN ), 'value' => $save['display_name'] ), array( 'name' => __( 'Public doctor identifier', DDD_TEXT_DOMAIN ), 'value' => $save['public_id'] ), array( 'name' => __( 'Saved at UTC', DDD_TEXT_DOMAIN ), 'value' => $save['created_at'] ) ) );
		}
		return array( 'data' => $data, 'done' => count( $report_rows ) < self::BATCH && count( $save_rows ) < self::BATCH );
	}

	public function erasers( $erasers ) {
		$erasers['doctors-directory-discovery'] = array( 'eraser_friendly_name' => __( 'Doctors Directory and Discovery', DDD_TEXT_DOMAIN ), 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		global $wpdb;
		$page = max( 1, absint( $page ) ); $removed = false; $retained = false; $messages = array();
		$legal_hold = (bool) apply_filters( 'ddd_privacy_legal_hold', false, $user->ID );

		if ( 1 === $page ) {
			DDD_Helpers::set_meta( $user->ID, 'discoverable', '0' );
			delete_user_meta( $user->ID, '_ddd_public_phone' ); delete_user_meta( $user->ID, '_ddd_public_whatsapp' );
			$wpdb->delete( DDD_Repository::table( 'saved_refs' ), array( 'user_id' => $user->ID ), array( '%d' ) );
			$deleted = DDD_Repository::delete_doctor_projection( $user->ID, 'privacy_erasure' );
			$removed = ! is_wp_error( $deleted );
		}

		$reports = DDD_Repository::table( 'reports' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$reports} WHERE reporter_id=%d OR doctor_id=%d ORDER BY id ASC LIMIT %d", $user->ID, $user->ID, self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			$row_hold = $legal_hold || ! empty( $row['retention_hold'] );
			if ( $row_hold ) {
				$wpdb->update( $reports, array( 'retention_hold' => 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $row['id'] ) ), array( '%d','%s' ), array( '%d' ) );
				$retained = true;
				continue;
			}
			$changes = array( 'updated_at' => current_time( 'mysql', true ), 'version' => absint( $row['version'] ) + 1 );
			if ( absint( $row['reporter_id'] ) === absint( $user->ID ) ) { $changes['reporter_id'] = 0; $changes['details'] = '[Removed through privacy request]'; $changes['evidence_url'] = ''; $changes['ip_hash'] = ''; }
			if ( absint( $row['doctor_id'] ) === absint( $user->ID ) ) { $changes['doctor_id'] = 0; }
			if ( false !== $wpdb->update( $reports, $changes, array( 'id' => absint( $row['id'] ) ) ) ) { $removed = true; }
		}
		if ( $retained ) { $messages[] = __( 'Some report records were retained unchanged under an approved legal or safety hold.', DDD_TEXT_DOMAIN ); }
		$messages[] = __( 'Non-identifying moderation and audit records may be retained for accountability and platform integrity.', DDD_TEXT_DOMAIN );
		DDD_Repository::audit_admin( 'privacy_erasure', 0, 'user', (string) $user->ID, 'success', array( 'removed' => $removed ? 1 : 0, 'retained' => $retained ? 1 : 0 ) );
		return array( 'items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => count( $rows ) < self::BATCH || $legal_hold );
	}
}

if ( ! class_exists( 'SDD_Privacy' ) ) { class_alias( 'DDD_Privacy', 'SDD_Privacy' ); }
