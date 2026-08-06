<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_5 {
	public static function get_status( $user_id ) {
		global $wpdb;
		$table = self::table( 'projection' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE doctor_id=%d", absint( $user_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			$rebuilt = self::rebuild_doctor( $user_id, 'status_request' );
			if ( is_wp_error( $rebuilt ) ) {
				return array( 'eligible' => false, 'status' => 'unknown', 'reasons' => array( 'projection_unavailable' ) );
			}
			$row = $rebuilt;
		}
		return array(
			'eligible'       => ! empty( $row['eligible'] ),
			'status'         => sanitize_key( $row['status'] ),
			'reasons'        => array_values( array_filter( (array) json_decode( (string) $row['reasons_json'], true ) ) ),
			'public_id'      => (string) $row['public_id'],
			'public_url'     => DDD_Helpers::public_profile_url( $row['public_id'] ),
			'profile_url'    => esc_url_raw( $row['profile_url'] ),
			'clinic_url'     => esc_url_raw( $row['clinic_url'] ),
			'updated_at'     => $row['updated_at'],
			'version'        => absint( $row['version'] ),
		);
	}
	public static function public_dto( $row ) {
		$languages = json_decode( (string) $row['languages_json'], true );
		$modes = json_decode( (string) $row['consultation_modes_json'], true );
		$fee = null;
		if ( null !== $row['fee_min'] && '' !== $row['fee_min'] ) {
			$fee = array( 'min' => (float) $row['fee_min'], 'max' => null !== $row['fee_max'] ? (float) $row['fee_max'] : null, 'currency' => (string) $row['currency'] );
		}
		return array(
			'public_id'            => (string) $row['public_id'],
			'display_name'         => (string) $row['display_name'],
			'professional_title'   => (string) $row['professional_title'],
			'specialty'            => (string) $row['specialty'],
			'country'              => (string) $row['country'],
			'city'                 => (string) $row['city'],
			'languages'            => is_array( $languages ) ? $languages : array(),
			'qualification'        => (string) $row['qualification'],
			'experience_years'     => absint( $row['experience_years'] ),
			'consultation_modes'   => is_array( $modes ) ? $modes : array(),
			'accepting_patients'   => ! empty( $row['accepting_patients'] ),
			'fee'                  => $fee,
			'avatar_id'            => absint( $row['avatar_id'] ),
			'profile_url'          => (string) $row['profile_url'],
			'clinic_url'           => (string) $row['clinic_url'],
			'appointment_url'      => (string) $row['appointment_url'],
			'public_directory_url' => DDD_Helpers::public_profile_url( $row['public_id'] ),
			'completeness'         => absint( $row['completeness'] ),
			'verified_at'          => $row['verified_at'],
			'featured'             => ! empty( $row['featured'] ),
			'feature_label'        => ! empty( $row['featured'] ) ? (string) $row['feature_label'] : '',
			'ranking_explanation'  => self::ranking_explanation( $row ),
		);
	}
	private static function ranking_explanation( $row ) {
		$labels = array( __( 'Verified and publicly eligible', DDD_TEXT_DOMAIN ) );
		if ( ! empty( $row['featured'] ) ) { $labels[] = __( 'Editorially featured', DDD_TEXT_DOMAIN ); }
		if ( absint( $row['completeness'] ) >= 80 ) { $labels[] = __( 'Complete professional profile', DDD_TEXT_DOMAIN ); }
		if ( ! empty( $row['accepting_patients'] ) ) { $labels[] = __( 'Accepting patients', DDD_TEXT_DOMAIN ); }
		return $labels;
	}
	public static function set_feature( $doctor_id, $actor_id, $enabled, $label, $reason, $start, $end, $expected_version = null ) {
		global $wpdb;
		$doctor_id = absint( $doctor_id );
		$actor_id = absint( $actor_id );
		if ( ! $doctor_id || ! $actor_id || ! trim( $reason ) ) {
			return DDD_Helpers::safe_error( 'feature_invalid', __( 'Doctor, actor and reason are required.', DDD_TEXT_DOMAIN ), 400 );
		}
		$table = self::table( 'projection' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE doctor_id=%d", $doctor_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row || ! $row['eligible'] ) {
			return DDD_Helpers::safe_error( 'doctor_not_eligible', __( 'Only an eligible public doctor may be featured.', DDD_TEXT_DOMAIN ), 409 );
		}
		if ( null !== $expected_version && absint( $row['version'] ) !== absint( $expected_version ) ) {
			return DDD_Helpers::safe_error( 'version_conflict', __( 'The doctor record changed. Reload and retry.', DDD_TEXT_DOMAIN ), 409 );
		}
		$start = $enabled ? DDD_Helpers::mysql_datetime( $start ? $start : time() ) : null;
		$end = $enabled && $end ? DDD_Helpers::mysql_datetime( $end ) : null;
		if ( $enabled && $end && strtotime( $end . ' UTC' ) <= strtotime( $start . ' UTC' ) ) {
			return DDD_Helpers::safe_error( 'feature_time_invalid', __( 'Feature expiry must be after its start.', DDD_TEXT_DOMAIN ), 400 );
		}
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			$table,
			array(
				'featured'         => $enabled ? 1 : 0,
				'feature_label'    => $enabled ? sanitize_text_field( $label ) : '',
				'feature_reason'   => sanitize_textarea_field( $reason ),
				'feature_start'    => $start,
				'feature_end'      => $end,
				'feature_approver' => $actor_id,
				'version'          => absint( $row['version'] ) + 1,
				'updated_at'       => current_time( 'mysql', true ),
			),
			array( 'doctor_id' => $doctor_id, 'version' => absint( $row['version'] ) ),
			array( '%d','%s','%s','%s','%s','%d','%d','%s' ),
			array( '%d','%d' )
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return DDD_Helpers::safe_error( 'feature_write_conflict', __( 'Feature state could not be updated atomically.', DDD_TEXT_DOMAIN ), 409 );
		}
		$event = self::outbox_add( 'DoctorDirectoryFeatured.v1', (string) $doctor_id, array( 'doctor_id' => $doctor_id, 'featured' => (bool) $enabled, 'label' => sanitize_text_field( $label ), 'actor_id' => $actor_id, 'reason' => sanitize_textarea_field( $reason ) ) );
		if ( is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $event;
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		self::invalidate_cache( $doctor_id );
		return true;
	}
}
