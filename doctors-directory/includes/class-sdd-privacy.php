<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Privacy {
	const BATCH = 50;

	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['doctors-directory'] = array( 'exporter_friendly_name' => 'Doctors Directory', 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		$page   = max( 1, absint( $page ) );
		$data   = array();
		$offset = ( $page - 1 ) * self::BATCH;
		if ( 1 === $page ) {
			$settings = array();
			foreach ( $this->meta_keys( true ) as $key ) {
				$value = SDD_Helpers::get( $user->ID, $key, '' );
				if ( '' !== $value ) {
					$settings[] = array( 'name' => ucwords( str_replace( '_', ' ', $key ) ), 'value' => $value );
				}
			}
			if ( $settings ) {
				$data[] = array( 'group_id' => 'doctors-directory', 'group_label' => 'Doctors Directory', 'item_id' => 'doctor-' . $user->ID, 'data' => $settings );
			}
		}
		global $wpdb;
		$table = $wpdb->prefix . 'sdd_reports';
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE reporter_id=%d OR doctor_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", $user->ID, $user->ID, self::BATCH, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			$relation = (int) $row->reporter_id === (int) $user->ID ? 'Submitted by you' : 'About your doctor profile';
			$data[] = array(
				'group_id'    => 'doctors-directory-reports',
				'group_label' => 'Doctors Directory Reports',
				'item_id'     => 'report-' . absint( $row->id ),
				'data'        => array(
					array( 'name' => 'Relationship', 'value' => $relation ),
					array( 'name' => 'Reason', 'value' => $row->reason ),
					array( 'name' => 'Details', 'value' => $row->details ),
					array( 'name' => 'Status', 'value' => $row->status ),
					array( 'name' => 'Created at UTC', 'value' => $row->created_at ),
					array( 'name' => 'Updated at UTC', 'value' => $row->updated_at ),
				),
			);
		}
		$done = count( $rows ) < self::BATCH;
		return array( 'data' => $data, 'done' => $done );
	}

	public function erasers( $erasers ) {
		$erasers['doctors-directory'] = array( 'eraser_friendly_name' => 'Doctors Directory', 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$page = max( 1, absint( $page ) );
		if ( 1 === $page ) {
			foreach ( $this->meta_keys( false ) as $key ) {
				delete_user_meta( $user->ID, '_sdd_' . $key );
			}
			update_user_meta( $user->ID, '_sdd_discoverable', '0' );
			update_user_meta( $user->ID, '_sdd_public_phone', '0' );
			update_user_meta( $user->ID, '_sdd_public_whatsapp', '0' );
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'sdd_reports';
		$offset = 0; // Always consume the next matching batch; processed rows leave the matching result set.
		$ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE reporter_id=%d OR doctor_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", $user->ID, $user->ID, self::BATCH, $offset ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$removed = false;
		foreach ( $ids as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT reporter_id,doctor_id FROM {$table} WHERE id=%d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) {
				continue;
			}
			$changes = array( 'details' => '[Removed through privacy request]', 'updated_at' => current_time( 'mysql', true ) );
			$formats = array( '%s', '%s' );
			if ( (int) $row->reporter_id === (int) $user->ID ) {
				$changes['reporter_id'] = 0;
				$formats[] = '%d';
			}
			if ( (int) $row->doctor_id === (int) $user->ID ) {
				$changes['doctor_id'] = 0;
				$formats[] = '%d';
			}
			if ( false !== $wpdb->update( $table, $changes, array( 'id' => $id ), $formats, array( '%d' ) ) ) {
				$removed = true;
			}
		}
		return array(
			'items_removed'  => $removed || 1 === $page,
			'items_retained' => true,
			'messages'       => array( 'Status transitions and non-identifying moderation audit records may be retained for platform integrity and accountability.' ),
			'done'           => count( $ids ) < self::BATCH,
		);
	}

	private function meta_keys( $include_admin ) {
		$keys = array( 'headline', 'website', 'linkedin', 'facebook', 'licensing_authority', 'professional_address', 'consultation_fee', 'fee_currency', 'consultation_timings', 'timezone', 'online_available', 'in_person_available', 'accepting_patients', 'public_phone', 'public_whatsapp', 'discoverable' );
		if ( $include_admin ) {
			$keys[] = 'featured';
			$keys[] = 'hidden';
		}
		return $keys;
	}
}
