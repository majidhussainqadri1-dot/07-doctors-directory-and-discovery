<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Privacy {
	const BATCH = 50;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['doctors-directory-discovery'] = array(
			'exporter_friendly_name' => __( 'Doctors Directory and Discovery', DDD_TEXT_DOMAIN ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$page = max( 1, absint( $page ) );
		$offset = ( $page - 1 ) * self::BATCH;
		$data = array();
		if ( 1 === $page ) {
			$meta = array();
			foreach ( array( 'discoverable', 'public_phone', 'public_whatsapp' ) as $key ) {
				$value = DDD_Helpers::meta( $user->ID, $key, '' );
				if ( '' !== $value ) {
					$meta[] = array( 'name' => ucwords( str_replace( '_', ' ', $key ) ), 'value' => $value );
				}
			}
			$status = DDD_Repository::get_status( $user->ID );
			$meta[] = array( 'name' => __( 'Directory eligibility', DDD_TEXT_DOMAIN ), 'value' => $status['eligible'] ? 'eligible' : 'not eligible' );
			$meta[] = array( 'name' => __( 'Eligibility reasons', DDD_TEXT_DOMAIN ), 'value' => implode( ', ', $status['reasons'] ) );
			$meta[] = array( 'name' => __( 'Projection updated at', DDD_TEXT_DOMAIN ), 'value' => $status['updated_at'] );
			$data[] = array( 'group_id' => 'ddd-profile', 'group_label' => __( 'Doctors Directory Projection', DDD_TEXT_DOMAIN ), 'item_id' => 'ddd-user-' . $user->ID, 'data' => $meta );
		}

		$reports_table = DDD_Repository::table( 'reports' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$reports_table} WHERE reporter_id=%d OR doctor_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", $user->ID, $user->ID, self::BATCH, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			$relation = absint( $row['reporter_id'] ) === absint( $user->ID ) ? __( 'Submitted by you', DDD_TEXT_DOMAIN ) : __( 'About your public doctor listing', DDD_TEXT_DOMAIN );
			$data[] = array(
				'group_id'    => 'ddd-reports',
				'group_label' => __( 'Doctors Directory Reports', DDD_TEXT_DOMAIN ),
				'item_id'     => 'ddd-report-' . absint( $row['id'] ),
				'data'        => array(
					array( 'name' => __( 'Relationship', DDD_TEXT_DOMAIN ), 'value' => $relation ),
					array( 'name' => __( 'Reason', DDD_TEXT_DOMAIN ), 'value' => $row['reason'] ),
					array( 'name' => __( 'Details', DDD_TEXT_DOMAIN ), 'value' => $row['details'] ),
					array( 'name' => __( 'Evidence URL', DDD_TEXT_DOMAIN ), 'value' => $row['evidence_url'] ),
					array( 'name' => __( 'Status', DDD_TEXT_DOMAIN ), 'value' => $row['status'] ),
					array( 'name' => __( 'Created at UTC', DDD_TEXT_DOMAIN ), 'value' => $row['created_at'] ),
					array( 'name' => __( 'Updated at UTC', DDD_TEXT_DOMAIN ), 'value' => $row['updated_at'] ),
				),
			);
		}

		$saves_table = DDD_Repository::table( 'saved_refs' );
		$saves = $wpdb->get_results( $wpdb->prepare( "SELECT doctor_id,created_at FROM {$saves_table} WHERE user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", $user->ID, self::BATCH, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $saves as $save ) {
			$data[] = array(
				'group_id'    => 'ddd-saves',
				'group_label' => __( 'Saved Doctors', DDD_TEXT_DOMAIN ),
				'item_id'     => 'ddd-save-' . absint( $save['doctor_id'] ),
				'data'        => array(
					array( 'name' => __( 'Doctor identifier', DDD_TEXT_DOMAIN ), 'value' => absint( $save['doctor_id'] ) ),
					array( 'name' => __( 'Saved at UTC', DDD_TEXT_DOMAIN ), 'value' => $save['created_at'] ),
				),
			);
		}
		$done = count( $rows ) < self::BATCH && count( $saves ) < self::BATCH;
		return array( 'data' => $data, 'done' => $done );
	}

	public function erasers( $erasers ) {
		$erasers['doctors-directory-discovery'] = array(
			'eraser_friendly_name' => __( 'Doctors Directory and Discovery', DDD_TEXT_DOMAIN ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$page = max( 1, absint( $page ) );
		$removed = false;
		$messages = array();

		$legal_hold = (bool) apply_filters( 'ddd_privacy_legal_hold', false, $user->ID );
		if ( 1 === $page ) {
			DDD_Helpers::set_meta( $user->ID, 'discoverable', '0' );
			delete_user_meta( $user->ID, '_ddd_public_phone' );
			delete_user_meta( $user->ID, '_ddd_public_whatsapp' );
			$wpdb->delete( DDD_Repository::table( 'saved_refs' ), array( 'user_id' => $user->ID ), array( '%d' ) );
			$projection = DDD_Repository::rebuild_doctor( $user->ID, 'privacy_erasure' );
			$removed = ! is_wp_error( $projection );
		}

		$reports = DDD_Repository::table( 'reports' );
		$ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$reports} WHERE reporter_id=%d OR doctor_id=%d ORDER BY id ASC LIMIT %d", $user->ID, $user->ID, self::BATCH ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT reporter_id,doctor_id,status,version FROM {$reports} WHERE id=%d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) {
				continue;
			}
			$changes = array( 'updated_at' => current_time( 'mysql', true ), 'version' => absint( $row['version'] ) + 1 );
			$formats = array( '%s', '%d' );
			if ( absint( $row['reporter_id'] ) === absint( $user->ID ) ) {
				$changes['reporter_id'] = 0;
				$changes['details'] = $legal_hold ? '[Restricted under legal hold]' : '[Removed through privacy request]';
				$changes['evidence_url'] = '';
				$formats[] = '%d';
				$formats[] = '%s';
				$formats[] = '%s';
			}
			if ( absint( $row['doctor_id'] ) === absint( $user->ID ) ) {
				$changes['doctor_id'] = 0;
				$formats[] = '%d';
			}
			$updated = $wpdb->update( $reports, $changes, array( 'id' => $id ), $formats, array( '%d' ) );
			if ( false !== $updated ) {
				$removed = true;
			}
		}
		if ( $legal_hold ) {
			$messages[] = __( 'Some report content is restricted rather than erased because an approved legal hold applies.', DDD_TEXT_DOMAIN );
		}
		$messages[] = __( 'Non-identifying moderation transition records may be retained for accountability and platform integrity.', DDD_TEXT_DOMAIN );
		return array(
			'items_removed'  => $removed,
			'items_retained' => true,
			'messages'       => $messages,
			'done'           => count( $ids ) < self::BATCH,
		);
	}
}

if ( ! class_exists( 'SDD_Privacy' ) ) {
	class_alias( 'DDD_Privacy', 'SDD_Privacy' );
}
