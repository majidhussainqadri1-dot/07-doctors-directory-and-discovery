<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Repository {
	const DEFAULT_LIMIT = 24;
	const MAX_LIMIT = 60;
	const RECONCILE_BATCH = 100;

	public static function table( $name ) {
		global $wpdb;
		$allowed = array( 'projection', 'taxonomy', 'saved_refs', 'reports', 'report_audit', 'feature_audit', 'admin_audit', 'outbox', 'inbox', 'search_metrics', 'rate_limits', 'health_log' );
		return in_array( $name, $allowed, true ) ? $wpdb->prefix . 'ddd_' . $name : '';
	}

	public static function audit_admin( $action, $actor_id, $object_type, $object_id, $outcome, $context = array() ) {
		global $wpdb;
		$table = self::table( 'admin_audit' );
		if ( ! $table ) { return false; }
		$clean = array();
		foreach ( (array) $context as $key => $value ) {
			if ( preg_match( '/token|secret|password|cookie|authorization|evidence|details|phone|email/i', (string) $key ) ) { continue; }
			$clean[ sanitize_key( $key ) ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '[structured]';
		}
		return false !== $wpdb->insert(
			$table,
			array( 'action' => sanitize_key( $action ), 'actor_id' => absint( $actor_id ), 'object_type' => sanitize_key( $object_type ), 'object_id' => sanitize_text_field( (string) $object_id ), 'outcome' => sanitize_key( $outcome ), 'context_json' => wp_json_encode( $clean ), 'trace_id' => DDD_Helpers::trace_id(), 'created_at' => current_time( 'mysql', true ) ),
			array( '%s','%d','%s','%s','%s','%s','%s','%s' )
		);
	}

	public static function rebuild_doctor( $user_id, $reason = 'manual', $expected_version = null ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return DDD_Helpers::safe_error( 'invalid_doctor', __( 'Invalid doctor identifier.', DDD_TEXT_DOMAIN ), 400 );
		}
		$table = self::table( 'projection' );
		$eligibility = DDD_Contracts::eligibility( $user_id );
		$profile = $eligibility['profile'];
		$clinic = $eligibility['clinic'];
		$verification = $eligibility['verification'];
		$now = current_time( 'mysql', true );

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE doctor_id=%d FOR UPDATE", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( null !== $expected_version && $existing && absint( $existing['version'] ) !== absint( $expected_version ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return DDD_Helpers::safe_error( 'version_conflict', __( 'The directory projection changed. Reload and retry.', DDD_TEXT_DOMAIN ), 409 );
		}

		$public_id = ! empty( $profile['public_id'] ) ? strtolower( (string) $profile['public_id'] ) : ( $existing && DDD_Helpers::valid_public_id( $existing['public_id'] ) ? strtolower( $existing['public_id'] ) : DDD_Helpers::uuid_from_user( $user_id ) );
		if ( ! DDD_Helpers::valid_public_id( $public_id ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return DDD_Helpers::safe_error( 'public_id_unavailable', __( 'A privacy-safe public doctor identifier could not be created.', DDD_TEXT_DOMAIN ), 500 );
		}

		$featured = $existing ? absint( $existing['featured'] ) : 0;
		$feature_label = $existing ? (string) $existing['feature_label'] : '';
		$feature_start = $existing ? $existing['feature_start'] : null;
		$feature_end = $existing ? $existing['feature_end'] : null;
		$feature_reason = $existing ? (string) $existing['feature_reason'] : '';
		$feature_approver = $existing ? absint( $existing['feature_approver'] ) : 0;
		$expired_feature = $featured && $feature_end && strtotime( $feature_end . ' UTC' ) <= time();
		if ( $expired_feature ) {
			$featured = 0;
			$feature_label = '';
		}

		$languages = DDD_Helpers::list_value( $profile['languages'] );
		$language_keys = array_map( static function ( $language ) { return DDD_Repository::taxonomy_normalize( 'language', $language ); }, $languages );
		$modes = DDD_Helpers::list_value( $clinic['consultation_modes'] );
		$completeness = self::completeness( $profile, $clinic );
		$quality = self::quality_score( $eligibility, $completeness );
		$search_text = DDD_Helpers::normalize_token( implode( ' ', array_merge( array( $profile['display_name'], $profile['professional_title'], $profile['specialty'], $profile['country'], $profile['city'], $profile['qualification'] ), $languages, $modes ) ) );
		$version = $existing ? absint( $existing['version'] ) + 1 : 1;
		$data = array(
			'doctor_id' => $user_id, 'public_id' => $public_id, 'status' => sanitize_key( $eligibility['status'] ), 'eligible' => $eligibility['eligible'] ? 1 : 0,
			'reasons_json' => wp_json_encode( array_values( array_unique( (array) $eligibility['reasons'] ) ) ),
			'display_name' => sanitize_text_field( (string) $profile['display_name'] ), 'display_name_norm' => DDD_Helpers::normalize_token( (string) $profile['display_name'] ), 'professional_title' => sanitize_text_field( (string) $profile['professional_title'] ),
			'specialty' => sanitize_text_field( (string) $profile['specialty'] ), 'specialty_norm' => self::taxonomy_normalize( 'specialty', (string) $profile['specialty'] ),
			'country' => sanitize_text_field( (string) $profile['country'] ), 'country_norm' => self::taxonomy_normalize( 'country', (string) $profile['country'] ),
			'city' => sanitize_text_field( (string) $profile['city'] ), 'city_norm' => self::taxonomy_normalize( 'city', (string) $profile['city'] ),
			'languages_json' => wp_json_encode( $languages ), 'languages_norm' => DDD_Helpers::normalize_token( implode( ' ', $language_keys ) ),
			'qualification' => sanitize_textarea_field( (string) $profile['qualification'] ), 'qualification_norm' => DDD_Helpers::normalize_token( (string) $profile['qualification'] ),
			'search_text_norm' => $search_text, 'experience_years' => min( 100, absint( $profile['experience_years'] ) ), 'consultation_modes_json' => wp_json_encode( $modes ),
			'accepting_patients' => ! empty( $clinic['accepting_patients'] ) ? 1 : 0, 'fee_min' => $clinic['fee_min'], 'fee_max' => $clinic['fee_max'], 'currency' => (string) $clinic['currency'],
			'avatar_id' => absint( $profile['avatar_id'] ), 'profile_url' => esc_url_raw( (string) $profile['profile_url'] ), 'clinic_url' => esc_url_raw( (string) $clinic['clinic_url'] ),
			'appointment_url' => esc_url_raw( (string) $clinic['appointment_url'] ), 'completeness' => $completeness, 'quality_score' => $quality,
			'verified_at' => ! empty( $verification['effective_at'] ) ? $verification['effective_at'] : null,
			'featured' => $featured, 'feature_label' => $feature_label, 'feature_start' => $feature_start, 'feature_end' => $feature_end,
			'feature_reason' => $feature_reason, 'feature_approver' => $feature_approver,
			'owner_versions_json' => wp_json_encode( array( 'identity' => (string) ( $eligibility['identity']['claim_version'] ?? '' ), 'verification' => (string) ( $verification['decision_version'] ?? '' ), 'profile' => (string) ( $profile['profile_version'] ?? '' ), 'clinic' => (string) ( $clinic['clinic_version'] ?? '' ) ) ),
			'version' => $version, 'created_at' => $existing ? $existing['created_at'] : $now, 'updated_at' => $now,
		);
		$checksum = $data;
		unset( $checksum['updated_at'], $checksum['version'] );
		$data['projection_checksum'] = hash( 'sha256', wp_json_encode( $checksum ) );

		if ( $existing ) {
			$write = $wpdb->update( $table, $data, array( 'doctor_id' => $user_id, 'version' => absint( $existing['version'] ) ), null, array( '%d','%d' ) );
			$ok = 1 === $write;
		} else {
			$ok = false !== $wpdb->insert( $table, $data );
		}
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			DDD_Observability::log( 'error', 'projection_write_failed', array( 'doctor_id' => $user_id ) );
			return DDD_Helpers::safe_error( 'projection_write_failed', __( 'The directory projection could not be saved.', DDD_TEXT_DOMAIN ), 409 );
		}
		if ( $expired_feature ) {
			self::feature_audit( $user_id, 0, array( 'featured' => 1, 'end' => $feature_end ), array( 'featured' => 0, 'end' => $feature_end ), 'automatic_expiry' );
		}
		$event = self::outbox_add( 'DoctorDirectoryEligibilityChanged.v1', $public_id, array( 'public_id' => $public_id, 'eligible' => (bool) $eligibility['eligible'], 'reason' => sanitize_key( $reason ), 'version' => $version ) );
		$feature_event = $expired_feature ? self::outbox_add( 'DoctorDirectoryFeatured.v1', $public_id, array( 'public_id' => $public_id, 'featured' => false, 'reason' => 'expired' ) ) : true;
		if ( is_wp_error( $event ) || is_wp_error( $feature_event ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return is_wp_error( $event ) ? $event : $feature_event;
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		self::invalidate_cache( $user_id );
		do_action( 'ddd_projection_rebuilt', $user_id, $data, $reason );
		return $data;
	}

	private static function completeness( $profile, $clinic ) {
		$checks = array( ! empty( $profile['display_name'] ), ! empty( $profile['professional_title'] ), ! empty( $profile['specialty'] ), ! empty( $profile['country'] ), ! empty( $profile['city'] ), ! empty( $profile['languages'] ), ! empty( $profile['qualification'] ), ! empty( $profile['experience_years'] ), ! empty( $profile['avatar_id'] ), ! empty( $profile['profile_url'] ), ! empty( $clinic['consultation_modes'] ), ! empty( $clinic['clinic_url'] ) || ! empty( $clinic['appointment_url'] ) );
		return (int) round( 100 * count( array_filter( $checks ) ) / count( $checks ) );
	}

	private static function quality_score( $eligibility, $completeness ) {
		$score = $completeness * 0.65;
		$score += ! empty( $eligibility['clinic']['accepting_patients'] ) ? 10 : 0;
		$score += ! empty( $eligibility['clinic']['consultation_modes'] ) ? 10 : 0;
		$score += ! empty( $eligibility['verification']['effective_at'] ) ? 10 : 0;
		return min( 100, round( $score, 3 ) );
	}

	public static function taxonomy_normalize( $type, $value ) {
		global $wpdb;
		$normalized = DDD_Helpers::normalize_token( $value );
		if ( '' === $normalized ) { return ''; }
		$table = self::table( 'taxonomy' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT canonical_key,aliases_json FROM {$table} WHERE type=%s AND status='active'", sanitize_key( $type ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$aliases = json_decode( (string) $row['aliases_json'], true );
			$aliases = array_merge( array( $row['canonical_key'] ), is_array( $aliases ) ? $aliases : array() );
			foreach ( $aliases as $alias ) {
				if ( $normalized === DDD_Helpers::normalize_token( $alias ) ) { return sanitize_title( $row['canonical_key'] ); }
			}
		}
		return $normalized;
	}

	public static function taxonomy_upsert( $type, $key, $label, $aliases, $status, $actor_id, $expected_version = null ) {
		global $wpdb;
		$type = sanitize_key( $type );
		$key = sanitize_title( $key );
		$status = in_array( sanitize_key( $status ), array( 'active', 'inactive' ), true ) ? sanitize_key( $status ) : 'active';
		if ( ! in_array( $type, array( 'specialty', 'country', 'city', 'language', 'qualification' ), true ) || ! $key || ! trim( $label ) ) {
			return DDD_Helpers::safe_error( 'taxonomy_invalid', __( 'A valid taxonomy type, key and label are required.', DDD_TEXT_DOMAIN ), 400 );
		}
		$table = self::table( 'taxonomy' );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE type=%s AND canonical_key=%s FOR UPDATE", $type, $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( null !== $expected_version && $row && absint( $row['version'] ) !== absint( $expected_version ) ) {
			$wpdb->query( 'ROLLBACK' );
			return DDD_Helpers::safe_error( 'version_conflict', __( 'The taxonomy record changed. Reload and retry.', DDD_TEXT_DOMAIN ), 409 );
		}
		$data = array( 'type' => $type, 'canonical_key' => $key, 'canonical_label' => sanitize_text_field( $label ), 'aliases_json' => wp_json_encode( DDD_Helpers::list_value( $aliases ) ), 'status' => $status, 'version' => $row ? absint( $row['version'] ) + 1 : 1, 'created_by' => absint( $actor_id ), 'created_at' => $row ? $row['created_at'] : current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) );
		$ok = $row ? 1 === $wpdb->update( $table, $data, array( 'id' => absint( $row['id'] ), 'version' => absint( $row['version'] ) ), null, array( '%d','%d' ) ) : false !== $wpdb->insert( $table, $data );
		$audited = $ok && self::audit_admin( 'taxonomy_upsert', $actor_id, 'taxonomy', $type . ':' . $key, 'success', array( 'status' => $status, 'version' => $data['version'] ) );
		$event = $audited ? self::outbox_add( 'DoctorDirectoryTaxonomyChanged.v1', $type . ':' . $key, array( 'type' => $type, 'canonical_key' => $key, 'status' => $status, 'version' => absint( $data['version'] ) ) ) : DDD_Helpers::safe_error( 'taxonomy_write_failed', __( 'Taxonomy and its audit record could not be saved atomically.', DDD_TEXT_DOMAIN ), 409 );
		if ( ! $ok || ! $audited || is_wp_error( $event ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::audit_admin( 'taxonomy_upsert', $actor_id, 'taxonomy', $type . ':' . $key, 'failure', array( 'status' => $status ) );
			return is_wp_error( $event ) ? $event : DDD_Helpers::safe_error( 'taxonomy_write_failed', __( 'Taxonomy could not be saved.', DDD_TEXT_DOMAIN ), 409 );
		}
		$wpdb->query( 'COMMIT' );
		update_option( 'ddd_reconcile_cursor', 0, false );
		if ( ! wp_next_scheduled( 'ddd_reconcile_tick' ) ) { wp_schedule_single_event( time() + 5, 'ddd_reconcile_tick' ); }
		self::invalidate_cache();
		return true;
	}

	public static function search( $args = array() ) {
		global $wpdb;
		$defaults = array( 'q' => '', 'country' => '', 'city' => '', 'specialty' => '', 'language' => '', 'qualification' => '', 'min_experience' => 0, 'mode' => '', 'accepting' => 0, 'currency' => '', 'fee_min' => null, 'fee_max' => null, 'featured_only' => 0, 'recent_only' => 0, 'limit' => self::DEFAULT_LIMIT, 'cursor' => '' );
		$args = wp_parse_args( $args, $defaults );
		$limit = max( 1, min( self::MAX_LIMIT, absint( $args['limit'] ) ) );
		$filter_hash = DDD_Helpers::filter_hash( $args );
		$cursor = array();
		if ( ! empty( $args['cursor'] ) ) {
			$cursor = DDD_Helpers::cursor_decode( $args['cursor'], $filter_hash );
			if ( ! $cursor || ! isset( $cursor['r'], $cursor['f'], $cursor['q'], $cursor['v'], $cursor['p'] ) || ! DDD_Helpers::valid_public_id( $cursor['p'] ) ) {
				return DDD_Helpers::safe_error( 'cursor_invalid', __( 'The search cursor is invalid, expired or belongs to different filters.', DDD_TEXT_DOMAIN ), 400 );
			}
		}
		$conditions = array( 'eligible=1' );
		$params = array();
		$now = current_time( 'mysql', true );
		$q = DDD_Helpers::normalize_token( $args['q'] );
		if ( '' !== $q ) { $conditions[] = 'search_text_norm LIKE %s'; $params[] = '%' . $wpdb->esc_like( $q ) . '%'; }
		$map = array( 'country' => 'country_norm', 'city' => 'city_norm', 'specialty' => 'specialty_norm', 'language' => 'languages_norm', 'qualification' => 'qualification_norm' );
		foreach ( $map as $key => $column ) {
			$value = self::taxonomy_normalize( $key, (string) $args[ $key ] );
			if ( '' !== $value ) { $conditions[] = $column . ' LIKE %s'; $params[] = '%' . $wpdb->esc_like( $value ) . '%'; }
		}
		if ( absint( $args['min_experience'] ) ) { $conditions[] = 'experience_years>=%d'; $params[] = min( 100, absint( $args['min_experience'] ) ); }
		$mode = sanitize_key( (string) $args['mode'] );
		if ( in_array( $mode, array( 'online', 'in-person', 'video', 'phone', 'chat', 'home-visit' ), true ) ) { $conditions[] = 'consultation_modes_json LIKE %s'; $params[] = '%"' . $wpdb->esc_like( $mode ) . '"%'; }
		if ( $args['accepting'] ) { $conditions[] = 'accepting_patients=1'; }
		$currency = strtoupper( sanitize_text_field( (string) $args['currency'] ) );
		if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) { $conditions[] = 'currency=%s'; $params[] = $currency; }
		$fee_min = DDD_Helpers::decimal_or_null( $args['fee_min'] );
		$fee_max = DDD_Helpers::decimal_or_null( $args['fee_max'] );
		if ( null !== $fee_min ) { $conditions[] = '(fee_max IS NULL OR fee_max>=%f)'; $params[] = $fee_min; }
		if ( null !== $fee_max ) { $conditions[] = '(fee_min IS NULL OR fee_min<=%f)'; $params[] = $fee_max; }
		if ( $args['recent_only'] ) { $days = max( 1, min( 365, absint( $args['recent_only'] ) ) ); $conditions[] = 'verified_at>=%s'; $params[] = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days ); }

		$active_expr = "CASE WHEN featured=1 AND (feature_start IS NULL OR feature_start<=%s) AND (feature_end IS NULL OR feature_end>%s) THEN 1 ELSE 0 END";
		$select_params = array( $now, $now );
		if ( '' !== $q ) {
			$relevance_expr = "CASE WHEN display_name_norm=%s THEN 100 WHEN display_name_norm LIKE %s THEN 85 WHEN specialty_norm=%s THEN 75 WHEN specialty_norm LIKE %s THEN 65 WHEN search_text_norm LIKE %s THEN 40 ELSE 0 END";
			$select_params = array_merge( $select_params, array( $q, $wpdb->esc_like( $q ) . '%', $q, $wpdb->esc_like( $q ) . '%', '%' . $wpdb->esc_like( $q ) . '%' ) );
		} else {
			$relevance_expr = '0';
		}
		$having = array();
		$having_params = array();
		if ( $args['featured_only'] ) { $having[] = 'active_featured=1'; }
		if ( $cursor ) {
			$having[] = '((relevance_score<%d) OR (relevance_score=%d AND active_featured<%d) OR (relevance_score=%d AND active_featured=%d AND quality_score<%f) OR (relevance_score=%d AND active_featured=%d AND quality_score=%f AND COALESCE(verified_at,\'1970-01-01 00:00:00\')<%s) OR (relevance_score=%d AND active_featured=%d AND quality_score=%f AND COALESCE(verified_at,\'1970-01-01 00:00:00\')=%s AND public_id>%s))';
			$having_params = array( absint( $cursor['r'] ), absint( $cursor['r'] ), absint( $cursor['f'] ), absint( $cursor['r'] ), absint( $cursor['f'] ), (float) $cursor['q'], absint( $cursor['r'] ), absint( $cursor['f'] ), (float) $cursor['q'], sanitize_text_field( $cursor['v'] ), absint( $cursor['r'] ), absint( $cursor['f'] ), (float) $cursor['q'], sanitize_text_field( $cursor['v'] ), strtolower( $cursor['p'] ) );
		}
		$table = self::table( 'projection' );
		$sql = "SELECT *, {$active_expr} AS active_featured, {$relevance_expr} AS relevance_score FROM {$table} WHERE " . implode( ' AND ', $conditions );
		if ( $having ) { $sql .= ' HAVING ' . implode( ' AND ', $having ); }
		$sql .= " ORDER BY relevance_score DESC, active_featured DESC, quality_score DESC, COALESCE(verified_at,'1970-01-01 00:00:00') DESC, public_id ASC LIMIT %d";
		$all_params = array_merge( $select_params, $params, $having_params, array( $limit + 1 ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $all_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$has_more = count( $rows ) > $limit;
		if ( $has_more ) { array_pop( $rows ); }
		$items = array_map( array( __CLASS__, 'public_dto' ), $rows );
		$next = '';
		if ( $has_more && $rows ) {
			$last = end( $rows );
			$next = DDD_Helpers::cursor_encode( array( 'fh' => $filter_hash, 'r' => absint( $last['relevance_score'] ), 'f' => absint( $last['active_featured'] ), 'q' => (float) $last['quality_score'], 'v' => $last['verified_at'] ? $last['verified_at'] : '1970-01-01 00:00:00', 'p' => strtolower( $last['public_id'] ) ) );
		}
		self::record_search_metric( $filter_hash, '' !== $q ? 'query' : 'browse', count( $items ), $has_more );
		return array( 'items' => $items, 'next_cursor' => $next, 'has_more' => $has_more, 'limit' => $limit, 'filter_hash' => substr( $filter_hash, 0, 12 ) );
	}

	private static function record_search_metric( $filter_hash, $query_class, $count, $has_more ) {
		global $wpdb;
		$table = self::table( 'search_metrics' );
		$bucket = $has_more ? 'more' : ( 0 === $count ? 'zero' : ( $count <= 10 ? '1-10' : '11-60' ) );
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (metric_date,filter_hash,query_class,result_bucket,request_count,updated_at) VALUES (%s,%s,%s,%s,1,%s) ON DUPLICATE KEY UPDATE request_count=request_count+1,updated_at=VALUES(updated_at)", gmdate( 'Y-m-d' ), $filter_hash, sanitize_key( $query_class ), $bucket, current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function facets( $type, $term = '', $limit = 20 ) {
		global $wpdb;
		$type = sanitize_key( $type );
		$columns = array( 'specialty' => array( 'specialty_norm', 'specialty' ), 'country' => array( 'country_norm', 'country' ), 'city' => array( 'city_norm', 'city' ), 'language' => array( 'languages_norm', 'languages_json' ) );
		if ( ! isset( $columns[ $type ] ) ) { return array(); }
		$limit = max( 1, min( 50, absint( $limit ) ) );
		$table = self::table( 'projection' );
		$term = self::taxonomy_normalize( $type, $term );
		if ( 'language' === $type ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT languages_json FROM {$table} WHERE eligible=1 AND languages_norm LIKE %s LIMIT 500", '%' . $wpdb->esc_like( $term ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$values = array();
			foreach ( $rows as $json ) { foreach ( (array) json_decode( $json, true ) as $value ) { if ( '' === $term || false !== strpos( DDD_Helpers::normalize_token( $value ), $term ) ) { $values[] = sanitize_text_field( $value ); } } }
			$counts = array_count_values( array_filter( $values ) ); arsort( $counts );
			return array_slice( array_map( static function ( $label, $count ) { return array( 'label' => $label, 'count' => $count ); }, array_keys( $counts ), array_values( $counts ) ), 0, $limit );
		}
		list( $norm, $label ) = $columns[ $type ];
		$where = 'eligible=1'; $params = array();
		if ( $term ) { $where .= " AND {$norm} LIKE %s"; $params[] = '%' . $wpdb->esc_like( $term ) . '%'; }
		$sql = "SELECT {$label} label, COUNT(*) total FROM {$table} WHERE {$where} AND {$label}<>'' GROUP BY {$norm},{$label} ORDER BY total DESC,{$label} ASC LIMIT %d";
		$params[] = $limit;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( static function ( $row ) { return array( 'label' => (string) $row['label'], 'count' => absint( $row['total'] ) ); }, $rows );
	}

	public static function resolve_doctor_id( $public_id, $recheck = true ) {
		global $wpdb;
		if ( ! DDD_Helpers::valid_public_id( $public_id ) ) { return 0; }
		$table = self::table( 'projection' );
		$doctor_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT doctor_id FROM {$table} WHERE public_id=%s AND eligible=1", strtolower( $public_id ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $doctor_id && $recheck ) {
			$live = DDD_Contracts::eligibility( $doctor_id );
			if ( empty( $live['eligible'] ) ) { return 0; }
		}
		return $doctor_id;
	}

	public static function get_by_public_id( $public_id ) {
		global $wpdb;
		if ( ! DDD_Helpers::valid_public_id( $public_id ) ) { return array(); }
		$table = self::table( 'projection' );
		$now = current_time( 'mysql', true );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT *, CASE WHEN featured=1 AND (feature_start IS NULL OR feature_start<=%s) AND (feature_end IS NULL OR feature_end>%s) THEN 1 ELSE 0 END active_featured FROM {$table} WHERE public_id=%s AND eligible=1", $now, $now, strtolower( $public_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) { return array(); }
		$live = DDD_Contracts::eligibility( absint( $row['doctor_id'] ) );
		return ! empty( $live['eligible'] ) ? self::public_dto( $row ) : array();
	}

	public static function get_status( $user_id ) {
		global $wpdb;
		$table = self::table( 'projection' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE doctor_id=%d", absint( $user_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) { return array( 'eligible' => false, 'status' => 'unknown', 'reasons' => array( 'projection_unavailable' ), 'public_id' => '', 'public_url' => '', 'profile_url' => '', 'clinic_url' => '', 'updated_at' => '', 'version' => 0 ); }
		return array( 'eligible' => ! empty( $row['eligible'] ), 'status' => sanitize_key( $row['status'] ), 'reasons' => array_values( array_filter( (array) json_decode( (string) $row['reasons_json'], true ) ) ), 'public_id' => (string) $row['public_id'], 'public_url' => DDD_Helpers::public_profile_url( $row['public_id'] ), 'profile_url' => DDD_Helpers::same_origin_url( $row['profile_url'] ), 'clinic_url' => DDD_Helpers::same_origin_url( $row['clinic_url'] ), 'updated_at' => $row['updated_at'], 'version' => absint( $row['version'] ) );
	}

	public static function get_live_status( $user_id ) {
		$projection = self::get_status( $user_id );
		$projection_eligible = (bool) $projection['eligible'];
		$live = DDD_Contracts::eligibility( $user_id );
		$owner_eligible = ! empty( $live['eligible'] );
		$projection['owner_eligible'] = $owner_eligible;
		$projection['projection_eligible'] = $projection_eligible;
		$projection['projection_stale'] = ( $owner_eligible !== $projection_eligible );
		$projection['eligible'] = $owner_eligible && $projection_eligible;
		$projection['status'] = $owner_eligible ? ( $projection_eligible ? 'eligible' : 'pending_projection' ) : sanitize_key( (string) $live['status'] );
		$projection['reasons'] = array_values( array_unique( (array) $live['reasons'] ) );
		if ( $owner_eligible && ! $projection_eligible ) { $projection['reasons'][] = 'projection_unavailable'; }
		if ( ! $owner_eligible && $projection_eligible ) { $projection['reasons'][] = 'projection_stale'; }
		$projection['reasons'] = array_values( array_unique( $projection['reasons'] ) );
		$projection['owner_versions'] = array(
			'identity' => sanitize_text_field( (string) ( $live['identity']['claim_version'] ?? '' ) ),
			'verification' => sanitize_text_field( (string) ( $live['verification']['decision_version'] ?? '' ) ),
			'profile' => sanitize_text_field( (string) ( $live['profile']['profile_version'] ?? '' ) ),
			'clinic' => sanitize_text_field( (string) ( $live['clinic']['clinic_version'] ?? '' ) ),
		);
		return $projection;
	}

	public static function public_dto( $row ) {
		$languages = json_decode( (string) $row['languages_json'], true );
		$modes = json_decode( (string) $row['consultation_modes_json'], true );
		$fee = null;
		if ( null !== $row['fee_min'] && '' !== $row['fee_min'] ) { $fee = array( 'min' => (float) $row['fee_min'], 'max' => null !== $row['fee_max'] ? (float) $row['fee_max'] : null, 'currency' => (string) $row['currency'] ); }
		$avatar_url = absint( $row['avatar_id'] ) ? wp_get_attachment_image_url( absint( $row['avatar_id'] ), 'thumbnail' ) : '';
		$active_featured = isset( $row['active_featured'] ) ? (bool) $row['active_featured'] : ( ! empty( $row['featured'] ) && ( empty( $row['feature_start'] ) || strtotime( $row['feature_start'] . ' UTC' ) <= time() ) && ( empty( $row['feature_end'] ) || strtotime( $row['feature_end'] . ' UTC' ) > time() ) );
		return array( 'public_id' => (string) $row['public_id'], 'display_name' => (string) $row['display_name'], 'professional_title' => (string) $row['professional_title'], 'specialty' => (string) $row['specialty'], 'country' => (string) $row['country'], 'city' => (string) $row['city'], 'languages' => is_array( $languages ) ? $languages : array(), 'qualification' => (string) $row['qualification'], 'experience_years' => absint( $row['experience_years'] ), 'consultation_modes' => is_array( $modes ) ? $modes : array(), 'accepting_patients' => ! empty( $row['accepting_patients'] ), 'fee' => $fee, 'avatar_url' => $avatar_url ? esc_url_raw( $avatar_url ) : '', 'profile_url' => DDD_Helpers::same_origin_url( (string) $row['profile_url'] ), 'clinic_url' => DDD_Helpers::same_origin_url( (string) $row['clinic_url'] ), 'appointment_url' => DDD_Helpers::same_origin_url( (string) $row['appointment_url'] ), 'public_directory_url' => DDD_Helpers::public_profile_url( $row['public_id'] ), 'completeness' => absint( $row['completeness'] ), 'verified_at' => $row['verified_at'], 'featured' => $active_featured, 'feature_label' => $active_featured ? (string) $row['feature_label'] : '', 'ranking_explanation' => self::ranking_explanation( $row, $active_featured ) );
	}

	private static function ranking_explanation( $row, $active_featured ) {
		$labels = array( __( 'Verified and publicly eligible', DDD_TEXT_DOMAIN ) );
		if ( ! empty( $row['relevance_score'] ) ) { $labels[] = __( 'Relevant to your search terms', DDD_TEXT_DOMAIN ); }
		if ( $active_featured ) { $labels[] = __( 'Transparently editorially featured', DDD_TEXT_DOMAIN ); }
		if ( absint( $row['completeness'] ) >= 80 ) { $labels[] = __( 'Complete professional profile', DDD_TEXT_DOMAIN ); }
		if ( ! empty( $row['accepting_patients'] ) ) { $labels[] = __( 'Accepting patients', DDD_TEXT_DOMAIN ); }
		return $labels;
	}

	private static function feature_audit( $doctor_id, $actor_id, $old, $new, $reason ) {
		global $wpdb;
		return false !== $wpdb->insert( self::table( 'feature_audit' ), array( 'doctor_id' => absint( $doctor_id ), 'actor_id' => absint( $actor_id ), 'old_state_json' => wp_json_encode( $old ), 'new_state_json' => wp_json_encode( $new ), 'reason' => sanitize_textarea_field( $reason ), 'trace_id' => DDD_Helpers::trace_id(), 'created_at' => current_time( 'mysql', true ) ), array( '%d','%d','%s','%s','%s','%s','%s' ) );
	}

	public static function set_feature( $doctor_id, $actor_id, $enabled, $label, $reason, $start, $end, $expected_version = null ) {
		global $wpdb;
		$doctor_id = absint( $doctor_id ); $actor_id = absint( $actor_id );
		if ( ! $doctor_id || ( $enabled && ! $actor_id ) || ! trim( $reason ) || ( $enabled && ! trim( $label ) ) ) { return DDD_Helpers::safe_error( 'feature_invalid', __( 'Doctor, actor, public label and reason are required.', DDD_TEXT_DOMAIN ), 400 ); }
		$table = self::table( 'projection' );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE doctor_id=%d FOR UPDATE", $doctor_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row || ( $enabled && ! $row['eligible'] ) ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'doctor_not_eligible', __( 'Only an eligible public doctor may be featured.', DDD_TEXT_DOMAIN ), 409 ); }
		if ( null !== $expected_version && absint( $row['version'] ) !== absint( $expected_version ) ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'version_conflict', __( 'The doctor record changed. Reload and retry.', DDD_TEXT_DOMAIN ), 409 ); }
		$start = $enabled ? DDD_Helpers::mysql_datetime( $start ? $start : time() ) : null;
		$end = $enabled && $end ? DDD_Helpers::mysql_datetime( $end ) : null;
		if ( $enabled && $end && strtotime( $end . ' UTC' ) <= strtotime( $start . ' UTC' ) ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'feature_time_invalid', __( 'Feature expiry must be after its start.', DDD_TEXT_DOMAIN ), 400 ); }
		$old = array( 'featured' => (bool) $row['featured'], 'label' => $row['feature_label'], 'start' => $row['feature_start'], 'end' => $row['feature_end'], 'approver' => absint( $row['feature_approver'] ) );
		$new = array( 'featured' => (bool) $enabled, 'label' => $enabled ? sanitize_text_field( $label ) : '', 'start' => $start, 'end' => $end, 'approver' => $actor_id );
		$updated = $wpdb->update( $table, array( 'featured' => $enabled ? 1 : 0, 'feature_label' => $new['label'], 'feature_reason' => sanitize_textarea_field( $reason ), 'feature_start' => $start, 'feature_end' => $end, 'feature_approver' => $actor_id, 'version' => absint( $row['version'] ) + 1, 'updated_at' => current_time( 'mysql', true ) ), array( 'doctor_id' => $doctor_id, 'version' => absint( $row['version'] ) ), array( '%d','%s','%s','%s','%s','%d','%d','%s' ), array( '%d','%d' ) );
		$audited = 1 === $updated && self::feature_audit( $doctor_id, $actor_id, $old, $new, $reason );
		$event = $audited ? self::outbox_add( 'DoctorDirectoryFeatured.v1', $row['public_id'], array( 'public_id' => $row['public_id'], 'featured' => (bool) $enabled, 'label' => $new['label'] ) ) : DDD_Helpers::safe_error( 'feature_write_conflict', __( 'Feature state could not be updated atomically.', DDD_TEXT_DOMAIN ), 409 );
		if ( 1 !== $updated || ! $audited || is_wp_error( $event ) ) { $wpdb->query( 'ROLLBACK' ); return is_wp_error( $event ) ? $event : DDD_Helpers::safe_error( 'feature_write_conflict', __( 'Feature state could not be updated atomically.', DDD_TEXT_DOMAIN ), 409 ); }
		$wpdb->query( 'COMMIT' ); self::invalidate_cache( $doctor_id ); return true;
	}

	public static function save_reference( $user_id, $doctor_id, $save = true ) {
		global $wpdb;
		$user_id = absint( $user_id ); $doctor_id = absint( $doctor_id );
		if ( ! $user_id || ! $doctor_id ) { return DDD_Helpers::safe_error( 'save_invalid', __( 'Invalid save request.', DDD_TEXT_DOMAIN ), 400 ); }
		if ( $save ) { $status = DDD_Contracts::eligibility( $doctor_id ); if ( empty( $status['eligible'] ) ) { return DDD_Helpers::safe_error( 'save_target_unavailable', __( 'This doctor is not currently available in the public directory.', DDD_TEXT_DOMAIN ), 409 ); } }
		$table = self::table( 'saved_refs' );
		if ( $save ) { return false !== $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (user_id,doctor_id,created_at) VALUES (%d,%d,%s)", $user_id, $doctor_id, current_time( 'mysql', true ) ) ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false !== $wpdb->delete( $table, array( 'user_id' => $user_id, 'doctor_id' => $doctor_id ), array( '%d','%d' ) );
	}

	public static function report( $doctor_id, $reporter_id, $reason, $details, $evidence_url = '', $idempotency_key = '' ) {
		global $wpdb;
		$allowed = array( 'credentials', 'incorrect-information', 'medical-safety', 'impersonation', 'spam', 'harassment', 'copyright', 'other' );
		$doctor_id = absint( $doctor_id ); $reporter_id = absint( $reporter_id ); $reason = sanitize_key( $reason ); $details = sanitize_textarea_field( $details );
		if ( ! $doctor_id || ! $reporter_id || ! in_array( $reason, $allowed, true ) || strlen( trim( $details ) ) < 10 ) { return DDD_Helpers::safe_error( 'report_invalid', __( 'Provide a valid reason and sufficient detail.', DDD_TEXT_DOMAIN ), 400 ); }
		$status = self::get_status( $doctor_id );
		if ( empty( $status['eligible'] ) || empty( $status['public_id'] ) ) { return DDD_Helpers::safe_error( 'report_target_unavailable', __( 'This public listing is unavailable.', DDD_TEXT_DOMAIN ), 404 ); }
		$raw_idempotency = $idempotency_key ? sanitize_text_field( substr( $idempotency_key, 0, 128 ) ) : hash( 'sha256', $status['public_id'] . '|' . $reason . '|' . $details );
		$idempotency_key = 'reporter:' . $reporter_id . ':' . hash( 'sha256', $raw_idempotency );
		$table = self::table( 'reports' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE idempotency_key=%s", $idempotency_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing ) { return absint( $existing ); }
		if ( ! DDD_Helpers::rate_limit( 'report', $reporter_id . '|' . DDD_Helpers::current_ip_hash(), 5, HOUR_IN_SECONDS ) ) { return DDD_Helpers::safe_error( 'report_rate_limited', __( 'Too many reports were submitted. Try again later.', DDD_TEXT_DOMAIN ), 429 ); }
		$now = current_time( 'mysql', true );
		$result = $wpdb->insert( $table, array( 'doctor_id' => $doctor_id, 'doctor_public_id' => $status['public_id'], 'reporter_id' => $reporter_id, 'reason' => $reason, 'details' => function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 2000 ) : substr( $details, 0, 2000 ), 'evidence_url' => esc_url_raw( $evidence_url ), 'status' => 'open', 'reviewer_id' => 0, 'review_note' => '', 'idempotency_key' => $idempotency_key, 'ip_hash' => DDD_Helpers::current_ip_hash(), 'retention_hold' => 0, 'version' => 1, 'created_at' => $now, 'updated_at' => $now ) );
		if ( false === $result ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE idempotency_key=%s", $idempotency_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $existing ? absint( $existing ) : DDD_Helpers::safe_error( 'report_write_failed', __( 'The report could not be saved.', DDD_TEXT_DOMAIN ), 500 );
		}
		return absint( $wpdb->insert_id );
	}

	public static function transition_report( $report_id, $actor_id, $new_status, $note, $expected_version ) {
		global $wpdb;
		$allowed = array( 'open', 'reviewing', 'resolved', 'dismissed', 'escalated' ); $new_status = sanitize_key( $new_status );
		if ( ! in_array( $new_status, $allowed, true ) || ! trim( $note ) ) { return DDD_Helpers::safe_error( 'report_transition_invalid', __( 'A valid status and reviewer reason are required.', DDD_TEXT_DOMAIN ), 400 ); }
		$transitions = array(
			'open' => array( 'reviewing', 'escalated', 'resolved', 'dismissed' ),
			'reviewing' => array( 'open', 'escalated', 'resolved', 'dismissed' ),
			'escalated' => array( 'reviewing', 'resolved', 'dismissed' ),
			'resolved' => array( 'open' ),
			'dismissed' => array( 'open' ),
		);
		$table = self::table( 'reports' ); $audit = self::table( 'report_audit' );
		$wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d FOR UPDATE", absint( $report_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'report_not_found', __( 'Report not found.', DDD_TEXT_DOMAIN ), 404 ); }
		if ( absint( $row['version'] ) !== absint( $expected_version ) ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'version_conflict', __( 'The report changed. Reload and retry.', DDD_TEXT_DOMAIN ), 409 ); }
		if ( ! isset( $transitions[ $row['status'] ] ) || ! in_array( $new_status, $transitions[ $row['status'] ], true ) ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'report_transition_forbidden', __( 'This report state transition is not permitted.', DDD_TEXT_DOMAIN ), 409 ); }
		$now = current_time( 'mysql', true ); $trace = DDD_Helpers::trace_id();
		$updated = $wpdb->update( $table, array( 'status' => $new_status, 'reviewer_id' => absint( $actor_id ), 'review_note' => sanitize_textarea_field( $note ), 'version' => absint( $row['version'] ) + 1, 'updated_at' => $now ), array( 'id' => absint( $report_id ), 'version' => absint( $row['version'] ) ), array( '%s','%d','%s','%d','%s' ), array( '%d','%d' ) );
		$audited = 1 === $updated && false !== $wpdb->insert( $audit, array( 'report_id' => absint( $report_id ), 'actor_id' => absint( $actor_id ), 'old_status' => $row['status'], 'new_status' => $new_status, 'note' => sanitize_textarea_field( $note ), 'trace_id' => $trace, 'created_at' => $now ), array( '%d','%d','%s','%s','%s','%s','%s' ) );
		if ( 1 !== $updated || ! $audited ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'report_transition_failed', __( 'The report and audit record could not be updated atomically.', DDD_TEXT_DOMAIN ), 409 ); }
		$wpdb->query( 'COMMIT' ); return true;
	}

	public static function outbox_add( $event_type, $aggregate_id, $payload ) {
		global $wpdb;
		$table = self::table( 'outbox' ); $now = current_time( 'mysql', true ); $event_id = wp_generate_uuid4();
		$result = $wpdb->insert( $table, array( 'event_id' => $event_id, 'event_type' => sanitize_text_field( $event_type ), 'aggregate_id' => sanitize_text_field( $aggregate_id ), 'payload_json' => wp_json_encode( $payload ), 'status' => 'pending', 'attempts' => 0, 'available_at' => $now, 'locked_by' => '', 'locked_at' => null, 'last_error' => '', 'created_at' => $now, 'updated_at' => $now ) );
		return false === $result ? DDD_Helpers::safe_error( 'outbox_write_failed', __( 'The change could not be queued reliably.', DDD_TEXT_DOMAIN ), 500 ) : $event_id;
	}

	public static function process_outbox( $limit = 50 ) {
		global $wpdb;
		$table = self::table( 'outbox' ); $limit = max( 1, min( 100, absint( $limit ) ) ); $worker = DDD_Helpers::trace_id(); $now = current_time( 'mysql', true ); $stale = gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE available_at<=%s AND (status='pending' OR (status='processing' AND locked_at<%s)) ORDER BY id ASC LIMIT %d", $now, $stale, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$claimed = array();
		foreach ( $ids as $id ) {
			if ( 1 === $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET locked_by=%s,locked_at=%s,status='processing',updated_at=%s WHERE id=%d AND (status='pending' OR (status='processing' AND locked_at<%s))", $worker, $now, $now, absint( $id ), $stale ) ) ) { $claimed[] = absint( $id ); } // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		foreach ( $claimed as $id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d AND locked_by=%s", $id, $worker ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) { continue; }
			try {
				do_action( 'ddd_outbox_event', $row['event_type'], json_decode( $row['payload_json'], true ), $row['event_id'] );
				$wpdb->update( $table, array( 'status' => 'sent', 'locked_by' => '', 'locked_at' => null, 'last_error' => '', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id, 'locked_by' => $worker ) );
			} catch ( Throwable $e ) {
				$attempts = absint( $row['attempts'] ) + 1; $status = $attempts >= 8 ? 'dead' : 'pending'; $delay = min( DAY_IN_SECONDS, (int) pow( 2, $attempts ) * 60 );
				$wpdb->update( $table, array( 'status' => $status, 'attempts' => $attempts, 'available_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ), 'locked_by' => '', 'locked_at' => null, 'last_error' => sanitize_text_field( substr( $e->getMessage(), 0, 180 ) ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id, 'locked_by' => $worker ) );
			}
		}
		return count( $claimed );
	}

	public static function consume_event( $event_id, $event_type, $payload ) {
		global $wpdb;
		$event_id = sanitize_text_field( substr( (string) $event_id, 0, 191 ) );
		$event_type = sanitize_text_field( substr( (string) $event_type, 0, 120 ) );
		if ( ! $event_id || ! is_array( $payload ) || empty( $payload['doctor_id'] ) ) { return DDD_Helpers::safe_error( 'event_invalid', __( 'Invalid directory event.', DDD_TEXT_DOMAIN ), 400 ); }
		$allowed = array( 'DoctorVerified.v1', 'DoctorSuspended.v1', 'PublicProfileUpdated.v1', 'ClinicAvailabilityChanged.v1', 'DoctorDeleted.v1' );
		if ( ! in_array( $event_type, $allowed, true ) ) { return DDD_Helpers::safe_error( 'event_type_unsupported', __( 'Unsupported directory event.', DDD_TEXT_DOMAIN ), 400 ); }
		$inbox = self::table( 'inbox' );
		$hash = hash( 'sha256', wp_json_encode( $payload ) );
		$now = current_time( 'mysql', true );
		$stale = gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$inbox} (event_id,event_type,payload_hash,status,attempts,last_error,processed_at,created_at,updated_at) VALUES (%s,%s,%s,'received',0,'',NULL,%s,%s)", $event_id, $event_type, $hash, $now, $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$inbox} WHERE event_id=%s", $event_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row || ! hash_equals( (string) $row['payload_hash'], $hash ) || ! hash_equals( (string) $row['event_type'], $event_type ) ) {
			return DDD_Helpers::safe_error( 'event_replay_mismatch', __( 'A repeated event did not match its original envelope.', DDD_TEXT_DOMAIN ), 409 );
		}
		if ( 'processed' === $row['status'] ) { return true; }
		if ( 'dead' === $row['status'] || absint( $row['attempts'] ) >= 8 ) { return DDD_Helpers::safe_error( 'event_dead_lettered', __( 'This event requires operator reconciliation after repeated failures.', DDD_TEXT_DOMAIN ), 409 ); }
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$inbox} SET status='processing',attempts=attempts+1,last_error='',updated_at=%s WHERE id=%d AND (status IN ('received','failed') OR (status='processing' AND updated_at<%s))", $now, absint( $row['id'] ), $stale ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 !== $claimed ) {
			return DDD_Helpers::safe_error( 'event_in_progress', __( 'This event is already being processed.', DDD_TEXT_DOMAIN ), 409 );
		}
		$result = 'DoctorDeleted.v1' === $event_type ? self::delete_doctor_projection( absint( $payload['doctor_id'] ), 'owner_event' ) : self::rebuild_doctor( absint( $payload['doctor_id'] ), 'event:' . $event_type );
		if ( is_wp_error( $result ) ) {
			$next_status = absint( $row['attempts'] ) + 1 >= 8 ? 'dead' : 'failed';
			$wpdb->update( $inbox, array( 'status' => $next_status, 'last_error' => sanitize_text_field( $result->get_error_code() ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $row['id'] ), 'status' => 'processing' ), array( '%s','%s','%s' ), array( '%d','%s' ) );
			return $result;
		}
		$wpdb->update( $inbox, array( 'status' => 'processed', 'processed_at' => current_time( 'mysql', true ), 'last_error' => '', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $row['id'] ), 'status' => 'processing' ), array( '%s','%s','%s','%s' ), array( '%d','%s' ) );
		return true;
	}

	public static function reconcile( $cursor = 0, $limit = self::RECONCILE_BATCH ) {
		global $wpdb;
		$cursor = absint( $cursor ); $limit = max( 1, min( 500, absint( $limit ) ) );
		$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID>%d ORDER BY ID ASC LIMIT %d", $cursor, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$processed = 0; $last = $cursor; $errors = array();
		foreach ( $user_ids as $user_id ) {
			$last = absint( $user_id ); $claims = DDD_Contracts::verification_claims( $user_id );
			if ( ! $claims['doctor'] && ! self::projection_exists( $user_id ) ) { continue; }
			$result = self::rebuild_doctor( $user_id, 'reconciliation' ); $processed++;
			if ( is_wp_error( $result ) ) { $errors[] = array( 'user_id' => absint( $user_id ), 'code' => $result->get_error_code() ); }
		}
		$done = count( $user_ids ) < $limit;
		if ( $done ) {
			$event = self::outbox_add( 'DoctorDirectoryIndexReconciled.v1', 'directory', array( 'processed' => $processed, 'errors' => count( $errors ), 'completed_at' => current_time( 'mysql', true ) ) );
			if ( is_wp_error( $event ) ) { $errors[] = array( 'user_id' => 0, 'code' => $event->get_error_code() ); }
		}
		return array( 'processed' => $processed, 'errors' => $errors, 'next_cursor' => $done ? 0 : $last, 'done' => $done );
	}

	private static function projection_exists( $user_id ) {
		global $wpdb; $table = self::table( 'projection' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE doctor_id=%d", absint( $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function expire_features() {
		global $wpdb;
		$table = self::table( 'projection' );
		$now = current_time( 'mysql', true );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT doctor_id,version FROM {$table} WHERE featured=1 AND feature_end IS NOT NULL AND feature_end<=%s ORDER BY feature_end ASC LIMIT 500", $now ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$expired = 0;
		foreach ( $rows as $row ) {
			$result = self::set_feature( absint( $row['doctor_id'] ), 0, false, '', 'automatic_expiry', null, null, absint( $row['version'] ) );
			if ( true === $result ) { $expired++; }
		}
		return $expired;
	}

	public static function delete_doctor_projection( $doctor_id, $reason = 'deleted' ) {
		global $wpdb; $doctor_id = absint( $doctor_id ); if ( ! $doctor_id ) { return true; }
		$projection = self::table( 'projection' ); $saved = self::table( 'saved_refs' ); $reports = self::table( 'reports' );
		$wpdb->query( 'START TRANSACTION' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT public_id FROM {$projection} WHERE doctor_id=%d FOR UPDATE", $doctor_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$public_id = $row && DDD_Helpers::valid_public_id( $row['public_id'] ) ? $row['public_id'] : (string) get_user_meta( $doctor_id, '_ddd_public_id', true );
		$ok1 = false !== $wpdb->delete( $saved, array( 'doctor_id' => $doctor_id ), array( '%d' ) );
		$ok2 = false !== $wpdb->query( $wpdb->prepare( "UPDATE {$reports} SET doctor_public_id=IF(doctor_public_id='',%s,doctor_public_id),doctor_id=0,updated_at=%s WHERE doctor_id=%d", DDD_Helpers::valid_public_id( $public_id ) ? $public_id : '', current_time( 'mysql', true ), $doctor_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ok3 = false !== $wpdb->delete( $projection, array( 'doctor_id' => $doctor_id ), array( '%d' ) );
		$event = self::outbox_add( 'DoctorDirectoryProjectionDeleted.v1', DDD_Helpers::valid_public_id( $public_id ) ? $public_id : 'redacted', array( 'public_id' => DDD_Helpers::valid_public_id( $public_id ) ? $public_id : '', 'reason' => sanitize_key( $reason ) ) );
		if ( ! $ok1 || ! $ok2 || ! $ok3 || is_wp_error( $event ) ) { $wpdb->query( 'ROLLBACK' ); return DDD_Helpers::safe_error( 'projection_delete_failed', __( 'The doctor projection could not be removed safely.', DDD_TEXT_DOMAIN ), 500 ); }
		$wpdb->query( 'COMMIT' ); self::invalidate_cache( $doctor_id ); return true;
	}

	public static function invalidate_cache( $doctor_id = 0 ) {
		$version = absint( get_option( 'ddd_cache_version', 1 ) ) + 1; update_option( 'ddd_cache_version', $version, false );
		if ( $doctor_id ) { clean_user_cache( absint( $doctor_id ) ); }
		do_action( 'ddd_directory_cache_invalidated', absint( $doctor_id ), $version );
	}
}
final class DDD_Directory {
	public function hooks() {
		add_shortcode( 'ddd_doctors_directory', array( $this, 'render' ) );
		add_shortcode( 'sdd_doctors_directory', array( $this, 'render' ) );
		add_action( 'init', array( $this, 'rewrites' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'doctor_redirect' ), 2 );
	}

	public function rewrites() {
		add_rewrite_rule( '^doctors/search/?$', 'index.php?ddd_directory_search=1', 'top' );
		add_rewrite_rule( '^doctors/([a-f0-9-]{36})/?$', 'index.php?ddd_doctor_public_id=$matches[1]', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'ddd_directory_search';
		$vars[] = 'ddd_doctor_public_id';
		return $vars;
	}

	public function doctor_redirect() {
		if ( get_query_var( 'ddd_directory_search' ) ) {
			$map = array(
				'q' => 'doctor_search', 'country' => 'doctor_country', 'city' => 'doctor_city', 'specialty' => 'doctor_specialty',
				'language' => 'doctor_language', 'qualification' => 'doctor_qualification', 'min_experience' => 'doctor_experience',
				'mode' => 'doctor_mode', 'accepting' => 'doctor_accepting', 'currency' => 'doctor_currency', 'fee_min' => 'doctor_fee_min',
				'fee_max' => 'doctor_fee_max', 'cursor' => 'doctor_cursor',
			);
			$query = array();
			foreach ( $map as $source => $target ) {
				if ( isset( $_GET[ $source ] ) && is_scalar( $_GET[ $source ] ) ) {
					$query[ $target ] = sanitize_text_field( wp_unslash( $_GET[ $source ] ) );
				}
			}
			$page_map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
			$base = ! empty( $page_map['directory'] ) ? get_permalink( absint( $page_map['directory'] ) ) : home_url( '/doctors/' );
			wp_safe_redirect( add_query_arg( $query, $base ), 302, 'Doctors Directory and Discovery' );
			exit;
		}
		$public_id = get_query_var( 'ddd_doctor_public_id' );
		if ( ! $public_id ) { return; }
		$doctor = DDD_Repository::get_by_public_id( $public_id );
		if ( ! $doctor ) {
			global $wp_query;
			if ( $wp_query instanceof WP_Query ) { $wp_query->set_404(); }
			status_header( 404 );
			nocache_headers();
			return;
		}
		$destination = DDD_Helpers::same_origin_url( $doctor['profile_url'] ? $doctor['profile_url'] : $doctor['clinic_url'] );
		if ( $destination ) {
			wp_safe_redirect( $destination, 302, 'Doctors Directory and Discovery' );
			exit;
		}
	}

	private function filters() {
		$mode = isset( $_GET['doctor_mode'] ) ? sanitize_key( wp_unslash( $_GET['doctor_mode'] ) ) : '';
		if ( ! in_array( $mode, array( '', 'online', 'in-person', 'video', 'phone', 'chat', 'home-visit' ), true ) ) {
			$mode = '';
		}
		return array(
			'q'              => isset( $_GET['doctor_search'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_search'] ) ) : '',
			'country'        => isset( $_GET['doctor_country'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_country'] ) ) : '',
			'city'           => isset( $_GET['doctor_city'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_city'] ) ) : '',
			'specialty'      => isset( $_GET['doctor_specialty'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_specialty'] ) ) : '',
			'language'       => isset( $_GET['doctor_language'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_language'] ) ) : '',
			'qualification'  => isset( $_GET['doctor_qualification'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_qualification'] ) ) : '',
			'min_experience' => isset( $_GET['doctor_experience'] ) ? min( 100, absint( $_GET['doctor_experience'] ) ) : 0,
			'mode'           => $mode,
			'accepting'      => ! empty( $_GET['doctor_accepting'] ) ? 1 : 0,
			'currency'       => isset( $_GET['doctor_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['doctor_currency'] ) ) ) : '',
			'fee_min'        => isset( $_GET['doctor_fee_min'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_min'] ) ) : null,
			'fee_max'        => isset( $_GET['doctor_fee_max'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_max'] ) ) : null,
			'cursor'         => isset( $_GET['doctor_cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_cursor'] ) ) : '',
			'limit'          => DDD_Repository::DEFAULT_LIMIT,
		);
	}

	public function render() {
		$filters = $this->filters();
		$result = DDD_Repository::search( $filters );
		$search_error = is_wp_error( $result ) ? $result : null;
		if ( $search_error ) {
			$filters['cursor'] = '';
			$result = array( 'items' => array(), 'next_cursor' => '', 'has_more' => false, 'limit' => DDD_Repository::DEFAULT_LIMIT );
		}
		$has_filters = (bool) array_filter( array_diff_key( $filters, array( 'cursor' => true, 'limit' => true ) ) );
		$featured = $has_filters ? array( 'items' => array() ) : DDD_Repository::search( array( 'featured_only' => 1, 'limit' => 6 ) );
		$recent = $has_filters ? array( 'items' => array() ) : DDD_Repository::search( array( 'recent_only' => 90, 'limit' => 6 ) );
		if ( is_wp_error( $featured ) ) { $featured = array( 'items' => array() ); }
		if ( is_wp_error( $recent ) ) { $recent = array( 'items' => array() ); }
		ob_start();
		?>
		<main class="ddd-shell" id="doctors-directory" data-ddd-directory>
			<?php do_action( 'ddd_before_directory' ); ?>
			<header class="ddd-hero">
				<div><span><?php esc_html_e( 'Global Professional Directory', DDD_TEXT_DOMAIN ); ?></span><h1><?php esc_html_e( 'Doctors', DDD_TEXT_DOMAIN ); ?></h1><p><?php esc_html_e( 'Find publicly eligible, verified homeopathic practitioners by specialty, location, language, consultation mode, availability and fee. Verification is not an endorsement or treatment guarantee.', DDD_TEXT_DOMAIN ); ?></p></div>
			</header>
			<?php echo $this->search_form( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $search_error ) : ?><div class="ddd-error" role="alert"><?php echo esc_html( $search_error->get_error_message() ); ?> <?php esc_html_e( 'The cursor was cleared; run the search again.', DDD_TEXT_DOMAIN ); ?></div><?php endif; ?>
			<?php if ( ! $has_filters ) : ?>
				<?php echo $this->founder_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->section( __( 'Featured Doctors', DDD_TEXT_DOMAIN ), __( 'Editorially selected with a visible label, reason, expiry and audit trail; no hidden paid ranking.', DDD_TEXT_DOMAIN ), $featured['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->section( __( 'Recently Verified Doctors', DDD_TEXT_DOMAIN ), __( 'Ordered by authoritative verification effective date, never by account registration date.', DDD_TEXT_DOMAIN ), $recent['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php echo $this->section( $has_filters ? __( 'Search Results', DDD_TEXT_DOMAIN ) : __( 'All Doctors', DDD_TEXT_DOMAIN ), __( 'Stable cursor ordering uses bounded, explainable eligibility and quality signals.', DDD_TEXT_DOMAIN ), $result['items'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $result['next_cursor'] ) : ?>
				<nav class="ddd-pagination" aria-label="<?php esc_attr_e( 'Doctors directory pagination', DDD_TEXT_DOMAIN ); ?>"><a class="ddd-button ddd-button-light" href="<?php echo esc_url( add_query_arg( 'doctor_cursor', $result['next_cursor'] ) ); ?>"><?php esc_html_e( 'Next results', DDD_TEXT_DOMAIN ); ?></a></nav>
			<?php endif; ?>
			<p class="ddd-disclaimer"><?php esc_html_e( 'Always confirm professional licensing, local jurisdictional requirements and clinical suitability directly. Emergency care is outside this directory.', DDD_TEXT_DOMAIN ); ?></p>
			<?php do_action( 'ddd_after_directory' ); ?>
		</main>
		<?php
		return ob_get_clean();
	}

	private function search_form( $f ) {
		ob_start();
		?>
		<form class="ddd-search" method="get" aria-label="<?php esc_attr_e( 'Search verified doctors', DDD_TEXT_DOMAIN ); ?>">
			<div class="ddd-search-grid">
				<label><?php esc_html_e( 'Name, title or specialty', DDD_TEXT_DOMAIN ); ?><input type="search" name="doctor_search" value="<?php echo esc_attr( $f['q'] ); ?>" autocomplete="off"></label>
				<label><?php esc_html_e( 'Specialty', DDD_TEXT_DOMAIN ); ?><input name="doctor_specialty" value="<?php echo esc_attr( $f['specialty'] ); ?>" list="ddd-specialty-options" data-ddd-facet="specialty"></label>
				<label><?php esc_html_e( 'Country', DDD_TEXT_DOMAIN ); ?><input name="doctor_country" value="<?php echo esc_attr( $f['country'] ); ?>" list="ddd-country-options" data-ddd-facet="country"></label>
				<label><?php esc_html_e( 'City', DDD_TEXT_DOMAIN ); ?><input name="doctor_city" value="<?php echo esc_attr( $f['city'] ); ?>" list="ddd-city-options" data-ddd-facet="city"></label>
				<label><?php esc_html_e( 'Language', DDD_TEXT_DOMAIN ); ?><input name="doctor_language" value="<?php echo esc_attr( $f['language'] ); ?>" list="ddd-language-options" data-ddd-facet="language"></label>
				<label><?php esc_html_e( 'Qualification', DDD_TEXT_DOMAIN ); ?><input name="doctor_qualification" value="<?php echo esc_attr( $f['qualification'] ); ?>"></label>
				<label><?php esc_html_e( 'Minimum experience', DDD_TEXT_DOMAIN ); ?><select name="doctor_experience"><option value="0"><?php esc_html_e( 'Any experience', DDD_TEXT_DOMAIN ); ?></option><?php foreach ( array( 1,3,5,10,15,20,30 ) as $years ) : ?><option value="<?php echo absint( $years ); ?>" <?php selected( $f['min_experience'], $years ); ?>><?php echo absint( $years ); ?>+ <?php esc_html_e( 'years', DDD_TEXT_DOMAIN ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Consultation mode', DDD_TEXT_DOMAIN ); ?><select name="doctor_mode"><option value=""><?php esc_html_e( 'Any mode', DDD_TEXT_DOMAIN ); ?></option><option value="online" <?php selected( $f['mode'], 'online' ); ?>><?php esc_html_e( 'Online', DDD_TEXT_DOMAIN ); ?></option><option value="in-person" <?php selected( $f['mode'], 'in-person' ); ?>><?php esc_html_e( 'In person', DDD_TEXT_DOMAIN ); ?></option><option value="video" <?php selected( $f['mode'], 'video' ); ?>><?php esc_html_e( 'Video', DDD_TEXT_DOMAIN ); ?></option><option value="phone" <?php selected( $f['mode'], 'phone' ); ?>><?php esc_html_e( 'Phone', DDD_TEXT_DOMAIN ); ?></option><option value="chat" <?php selected( $f['mode'], 'chat' ); ?>><?php esc_html_e( 'Chat', DDD_TEXT_DOMAIN ); ?></option><option value="home-visit" <?php selected( $f['mode'], 'home-visit' ); ?>><?php esc_html_e( 'Home visit', DDD_TEXT_DOMAIN ); ?></option></select></label>
				<label><?php esc_html_e( 'Currency', DDD_TEXT_DOMAIN ); ?><input name="doctor_currency" maxlength="3" value="<?php echo esc_attr( $f['currency'] ); ?>" placeholder="PKR"></label>
				<label><?php esc_html_e( 'Minimum fee', DDD_TEXT_DOMAIN ); ?><input type="number" min="0" step="0.01" name="doctor_fee_min" value="<?php echo esc_attr( null === $f['fee_min'] ? '' : $f['fee_min'] ); ?>"></label>
				<label><?php esc_html_e( 'Maximum fee', DDD_TEXT_DOMAIN ); ?><input type="number" min="0" step="0.01" name="doctor_fee_max" value="<?php echo esc_attr( null === $f['fee_max'] ? '' : $f['fee_max'] ); ?>"></label>
				<label class="ddd-check"><input type="checkbox" name="doctor_accepting" value="1" <?php checked( $f['accepting'], 1 ); ?>> <?php esc_html_e( 'Accepting new patients', DDD_TEXT_DOMAIN ); ?></label>
				<button class="ddd-button" type="submit"><?php esc_html_e( 'Search Doctors', DDD_TEXT_DOMAIN ); ?></button>
			</div>
			<datalist id="ddd-specialty-options"></datalist><datalist id="ddd-country-options"></datalist><datalist id="ddd-city-options"></datalist><datalist id="ddd-language-options"></datalist>
			<a class="ddd-clear" href="<?php echo esc_url( remove_query_arg( array( 'doctor_search','doctor_specialty','doctor_country','doctor_city','doctor_language','doctor_qualification','doctor_experience','doctor_mode','doctor_currency','doctor_fee_min','doctor_fee_max','doctor_accepting','doctor_cursor' ) ) ); ?>"><?php esc_html_e( 'Clear all filters', DDD_TEXT_DOMAIN ); ?></a>
		</form>
		<?php
		return ob_get_clean();
	}

	private function founder_section() {
		$founder = DDD_Contracts::founder();
		if ( ! $founder || empty( $founder['display_name'] ) ) {
			return '';
		}
		$card = array(
			'public_id' => isset( $founder['public_id'] ) ? $founder['public_id'] : '',
			'display_name' => $founder['display_name'],
			'professional_title' => isset( $founder['professional_title'] ) ? $founder['professional_title'] : '',
			'specialty' => isset( $founder['specialty'] ) ? $founder['specialty'] : '',
			'country' => isset( $founder['country'] ) ? $founder['country'] : '',
			'city' => isset( $founder['city'] ) ? $founder['city'] : '',
			'languages' => isset( $founder['languages'] ) ? DDD_Helpers::list_value( $founder['languages'] ) : array(),
			'qualification' => isset( $founder['qualification'] ) ? $founder['qualification'] : '',
			'experience_years' => isset( $founder['experience_years'] ) ? absint( $founder['experience_years'] ) : 0,
			'consultation_modes' => array(),
			'accepting_patients' => false,
			'fee' => null,
			'avatar_url' => isset( $founder['avatar_id'] ) && $founder['avatar_id'] ? esc_url_raw( wp_get_attachment_image_url( absint( $founder['avatar_id'] ), 'thumbnail' ) ) : '',
			'profile_url' => isset( $founder['profile_url'] ) ? $founder['profile_url'] : '',
			'clinic_url' => '',
			'appointment_url' => '',
			'public_directory_url' => isset( $founder['profile_url'] ) ? $founder['profile_url'] : '',
			'completeness' => 100,
			'verified_at' => '',
			'featured' => true,
			'feature_label' => __( 'Verified Founder', DDD_TEXT_DOMAIN ),
			'ranking_explanation' => array( __( 'Institutional Founder identity', DDD_TEXT_DOMAIN ) ),
		);
		return $this->section( __( 'Founder', DDD_TEXT_DOMAIN ), __( 'The official Founder is institutionally pinned and never mixed into ordinary or recent-doctor groups.', DDD_TEXT_DOMAIN ), array( $card ) );
	}

	private function section( $title, $description, $items, $empty_state = false ) {
		if ( ! $items && ! $empty_state ) {
			return '';
		}
		ob_start();
		?>
		<section class="ddd-section" aria-labelledby="ddd-<?php echo esc_attr( sanitize_title( $title ) ); ?>">
			<div class="ddd-section-head"><div><h2 id="ddd-<?php echo esc_attr( sanitize_title( $title ) ); ?>"><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div></div>
			<div class="ddd-grid">
				<?php if ( $items ) : foreach ( $items as $item ) : echo $this->card( $item ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?><div class="ddd-empty" role="status"><h3><?php esc_html_e( 'No eligible doctors matched this search', DDD_TEXT_DOMAIN ); ?></h3><p><?php esc_html_e( 'Remove one or more filters, check spelling or try a broader location or specialty.', DDD_TEXT_DOMAIN ); ?></p></div><?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function card( $doctor ) {
		$name = $doctor['display_name'];
		$location = trim( $doctor['city'] . ', ' . $doctor['country'], ', ' );
		$destination = $doctor['profile_url'] ? $doctor['profile_url'] : ( $doctor['clinic_url'] ? $doctor['clinic_url'] : $doctor['public_directory_url'] );
		ob_start();
		?>
		<article class="ddd-card" data-public-id="<?php echo esc_attr( $doctor['public_id'] ); ?>">
			<div class="ddd-avatar"><?php if ( ! empty( $doctor['avatar_url'] ) ) : ?><img src="<?php echo esc_url( $doctor['avatar_url'] ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" decoding="async"><?php else : echo esc_html( DDD_Helpers::initials( $name ) ); endif; ?></div>
			<div class="ddd-card-body">
				<div class="ddd-badges"><span class="ddd-badge"><?php esc_html_e( 'Verified Doctor', DDD_TEXT_DOMAIN ); ?></span><?php if ( $doctor['featured'] ) : ?><span class="ddd-badge ddd-badge-featured"><?php echo esc_html( $doctor['feature_label'] ? $doctor['feature_label'] : __( 'Featured', DDD_TEXT_DOMAIN ) ); ?></span><?php endif; ?></div>
				<h3><a href="<?php echo esc_url( $destination ); ?>"><?php echo esc_html( $name ); ?></a></h3>
				<p class="ddd-headline"><?php echo esc_html( $doctor['professional_title'] ? $doctor['professional_title'] : $doctor['specialty'] ); ?></p>
				<?php if ( $location ) : ?><p><?php echo esc_html( $location ); ?></p><?php endif; ?>
				<div class="ddd-tags"><?php foreach ( array_slice( $doctor['consultation_modes'], 0, 3 ) as $mode ) : ?><span><?php echo esc_html( ucwords( str_replace( '-', ' ', $mode ) ) ); ?></span><?php endforeach; ?><?php if ( $doctor['accepting_patients'] ) : ?><span><?php esc_html_e( 'Accepting patients', DDD_TEXT_DOMAIN ); ?></span><?php endif; ?><?php foreach ( array_slice( $doctor['languages'], 0, 3 ) as $language ) : ?><span><?php echo esc_html( $language ); ?></span><?php endforeach; ?></div>
				<?php if ( $doctor['fee'] ) : ?><p><strong><?php esc_html_e( 'Fee:', DDD_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( $doctor['fee']['currency'] . ' ' . number_format_i18n( $doctor['fee']['min'], 2 ) ); ?></p><?php endif; ?>
				<details class="ddd-ranking"><summary><?php esc_html_e( 'Why this result appears', DDD_TEXT_DOMAIN ); ?></summary><ul><?php foreach ( $doctor['ranking_explanation'] as $label ) : ?><li><?php echo esc_html( $label ); ?></li><?php endforeach; ?></ul></details>
				<nav class="ddd-actions" aria-label="<?php echo esc_attr( sprintf( __( 'Actions for %s', DDD_TEXT_DOMAIN ), $name ) ); ?>"><a class="ddd-button" href="<?php echo esc_url( $destination ); ?>"><?php esc_html_e( 'View Profile', DDD_TEXT_DOMAIN ); ?></a><?php if ( $doctor['clinic_url'] ) : ?><a class="ddd-button ddd-button-light" href="<?php echo esc_url( $doctor['clinic_url'] ); ?>"><?php esc_html_e( 'Clinic', DDD_TEXT_DOMAIN ); ?></a><?php endif; ?><?php if ( $doctor['appointment_url'] ) : ?><a class="ddd-button ddd-button-light" href="<?php echo esc_url( is_user_logged_in() ? $doctor['appointment_url'] : wp_login_url( $doctor['appointment_url'] ) ); ?>"><?php esc_html_e( 'Appointment', DDD_TEXT_DOMAIN ); ?></a><?php endif; ?></nav>
				<?php if ( is_user_logged_in() && $doctor['public_id'] ) : ?>
				<div class="ddd-secondary-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_save_doctor"><input type="hidden" name="public_id" value="<?php echo esc_attr( $doctor['public_id'] ); ?>"><input type="hidden" name="save" value="1"><?php wp_nonce_field( 'ddd_save_doctor_' . $doctor['public_id'] ); ?><button class="ddd-clear" type="submit"><?php esc_html_e( 'Save doctor', DDD_TEXT_DOMAIN ); ?></button></form><details class="ddd-report"><summary><?php esc_html_e( 'Report listing concern', DDD_TEXT_DOMAIN ); ?></summary><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_report_listing"><input type="hidden" name="public_id" value="<?php echo esc_attr( $doctor['public_id'] ); ?>"><input type="hidden" name="idempotency_key" value="<?php echo esc_attr( 'listing-' . wp_generate_uuid4() ); ?>"><?php wp_nonce_field( 'ddd_report_listing_' . $doctor['public_id'] ); ?><label><?php esc_html_e( 'Reason', DDD_TEXT_DOMAIN ); ?><select name="reason" required><option value=""><?php esc_html_e( 'Choose a reason', DDD_TEXT_DOMAIN ); ?></option><option value="credentials"><?php esc_html_e( 'Credentials concern', DDD_TEXT_DOMAIN ); ?></option><option value="incorrect-information"><?php esc_html_e( 'Incorrect information', DDD_TEXT_DOMAIN ); ?></option><option value="medical-safety"><?php esc_html_e( 'Medical safety concern', DDD_TEXT_DOMAIN ); ?></option><option value="impersonation"><?php esc_html_e( 'Impersonation', DDD_TEXT_DOMAIN ); ?></option><option value="spam"><?php esc_html_e( 'Spam', DDD_TEXT_DOMAIN ); ?></option><option value="harassment"><?php esc_html_e( 'Harassment', DDD_TEXT_DOMAIN ); ?></option><option value="copyright"><?php esc_html_e( 'Copyright', DDD_TEXT_DOMAIN ); ?></option><option value="other"><?php esc_html_e( 'Other', DDD_TEXT_DOMAIN ); ?></option></select></label><label><?php esc_html_e( 'Details', DDD_TEXT_DOMAIN ); ?><textarea name="details" minlength="10" maxlength="2000" required></textarea></label><label><?php esc_html_e( 'Optional evidence URL', DDD_TEXT_DOMAIN ); ?><input type="url" name="evidence_url"></label><button class="ddd-button" type="submit"><?php esc_html_e( 'Submit report', DDD_TEXT_DOMAIN ); ?></button></form></details></div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}

if ( ! class_exists( 'SDD_Directory' ) ) {
	class_alias( 'DDD_Directory', 'SDD_Directory' );
}
