<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_2 {
	public static function rebuild_doctor( $user_id, $reason = 'manual', $expected_version = null ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return DDD_Helpers::safe_error( 'invalid_doctor', __( 'Invalid doctor identifier.', DDD_TEXT_DOMAIN ), 400 );
		}
		$eligibility = DDD_Contracts::eligibility( $user_id );
		$profile = $eligibility['profile'];
		$clinic = $eligibility['clinic'];
		$verification = $eligibility['verification'];
		$table = self::table( 'projection' );
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE doctor_id=%d", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( null !== $expected_version && $existing && absint( $existing['version'] ) !== absint( $expected_version ) ) {
			return DDD_Helpers::safe_error( 'version_conflict', __( 'The directory projection changed. Reload and retry.', DDD_TEXT_DOMAIN ), 409 );
		}

		$featured = $existing ? absint( $existing['featured'] ) : 0;
		$feature_label = $existing ? (string) $existing['feature_label'] : '';
		$feature_start = $existing ? $existing['feature_start'] : null;
		$feature_end = $existing ? $existing['feature_end'] : null;
		$feature_reason = $existing ? (string) $existing['feature_reason'] : '';
		$feature_approver = $existing ? absint( $existing['feature_approver'] ) : 0;
		if ( $feature_end && strtotime( $feature_end . ' UTC' ) <= time() ) {
			$featured = 0;
		}
		$languages = DDD_Helpers::list_value( $profile['languages'] );
		$modes = DDD_Helpers::list_value( $clinic['consultation_modes'] );
		$completeness = self::completeness( $profile, $clinic );
		$quality = self::quality_score( $eligibility, $completeness, $featured );
		$owner_versions = array(
			'identity'     => isset( $eligibility['identity']['claim_version'] ) ? $eligibility['identity']['claim_version'] : '',
			'verification' => isset( $verification['decision_version'] ) ? $verification['decision_version'] : '',
			'profile'      => isset( $profile['profile_version'] ) ? $profile['profile_version'] : '',
			'clinic'       => isset( $clinic['clinic_version'] ) ? $clinic['clinic_version'] : '',
		);
		$now = current_time( 'mysql', true );
		$data = array(
			'doctor_id'               => $user_id,
			'public_id'               => $profile['public_id'] ? (string) $profile['public_id'] : ( $existing && ! empty( $existing['public_id'] ) ? (string) $existing['public_id'] : DDD_Helpers::uuid_from_user( $user_id ) ),
			'status'                  => sanitize_key( $eligibility['status'] ),
			'eligible'                => $eligibility['eligible'] ? 1 : 0,
			'reasons_json'            => wp_json_encode( $eligibility['reasons'] ),
			'display_name'            => sanitize_text_field( (string) $profile['display_name'] ),
			'professional_title'      => sanitize_text_field( (string) $profile['professional_title'] ),
			'specialty'               => sanitize_text_field( (string) $profile['specialty'] ),
			'specialty_norm'          => self::taxonomy_normalize( 'specialty', (string) $profile['specialty'] ),
			'country'                 => sanitize_text_field( (string) $profile['country'] ),
			'country_norm'            => self::taxonomy_normalize( 'country', (string) $profile['country'] ),
			'city'                    => sanitize_text_field( (string) $profile['city'] ),
			'city_norm'               => self::taxonomy_normalize( 'city', (string) $profile['city'] ),
			'languages_json'          => wp_json_encode( $languages ),
			'languages_norm'          => DDD_Helpers::normalize_token( implode( ' ', $languages ) ),
			'qualification'           => sanitize_textarea_field( (string) $profile['qualification'] ),
			'qualification_norm'      => DDD_Helpers::normalize_token( (string) $profile['qualification'] ),
			'experience_years'        => min( 100, absint( $profile['experience_years'] ) ),
			'consultation_modes_json' => wp_json_encode( $modes ),
			'accepting_patients'      => ! empty( $clinic['accepting_patients'] ) ? 1 : 0,
			'fee_min'                 => $clinic['fee_min'],
			'fee_max'                 => $clinic['fee_max'],
			'currency'                => (string) $clinic['currency'],
			'avatar_id'               => absint( $profile['avatar_id'] ),
			'profile_url'             => esc_url_raw( (string) $profile['profile_url'] ),
			'clinic_url'              => esc_url_raw( (string) $clinic['clinic_url'] ),
			'appointment_url'         => esc_url_raw( (string) $clinic['appointment_url'] ),
			'completeness'            => $completeness,
			'quality_score'           => $quality,
			'verified_at'             => $verification['effective_at'] ? $verification['effective_at'] : null,
			'featured'                => $featured,
			'feature_label'           => $feature_label,
			'feature_start'           => $feature_start,
			'feature_end'             => $feature_end,
			'feature_reason'          => $feature_reason,
			'feature_approver'        => $feature_approver,
			'owner_versions_json'     => wp_json_encode( $owner_versions ),
			'version'                 => $existing ? absint( $existing['version'] ) + 1 : 1,
			'created_at'              => $existing ? $existing['created_at'] : $now,
			'updated_at'              => $now,
		);
		$checksum_data = $data;
		unset( $checksum_data['updated_at'], $checksum_data['version'] );
		$data['projection_checksum'] = hash( 'sha256', wp_json_encode( $checksum_data ) );
		$formats = array(
			'%d','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d','%f','%f','%s','%d','%s','%s','%s','%d','%f','%s','%d','%s','%s','%s','%d','%s','%s','%d','%s','%s',
		);
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->replace( $table, $data, $formats );
		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			DDD_Observability::log( 'error', 'projection_write_failed', array( 'doctor_id' => $user_id ) );
			return DDD_Helpers::safe_error( 'projection_write_failed', __( 'The directory projection could not be saved.', DDD_TEXT_DOMAIN ), 500 );
		}
		$event_type = $eligibility['eligible'] ? 'DoctorDirectoryEligibilityChanged.v1' : 'DoctorDirectoryEligibilityChanged.v1';
		$event = self::outbox_add( $event_type, (string) $user_id, array( 'doctor_id' => $user_id, 'eligible' => $eligibility['eligible'], 'reason' => sanitize_key( $reason ), 'version' => $data['version'] ) );
		if ( is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return $event;
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		self::invalidate_cache( $user_id );
		do_action( 'ddd_projection_rebuilt', $user_id, $data, $reason );
		return $data;
	}
}
