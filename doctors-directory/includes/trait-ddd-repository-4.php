<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_4 {
	public static function search( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'q'                  => '',
			'country'            => '',
			'city'               => '',
			'specialty'          => '',
			'language'           => '',
			'qualification'      => '',
			'min_experience'     => 0,
			'mode'               => '',
			'accepting'          => 0,
			'currency'           => '',
			'fee_min'            => null,
			'fee_max'            => null,
			'featured_only'      => 0,
			'recent_only'        => 0,
			'limit'              => self::DEFAULT_LIMIT,
			'cursor'             => '',
		);
		$args = wp_parse_args( $args, $defaults );
		$limit = max( 1, min( self::MAX_LIMIT, absint( $args['limit'] ) ) );
		$conditions = array( 'eligible=1' );
		$params = array();
		$now = current_time( 'mysql', true );
		$conditions[] = "(featured=0 OR feature_end IS NULL OR feature_end>%s)";
		$params[] = $now;
		if ( $args['featured_only'] ) {
			$conditions[] = 'featured=1';
			$conditions[] = '(feature_start IS NULL OR feature_start<=%s)';
			$params[] = $now;
		}
		if ( $args['recent_only'] ) {
			$days = max( 1, min( 365, absint( $args['recent_only'] ) ) );
			$conditions[] = 'verified_at>=%s';
			$params[] = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );
		}
		$q = DDD_Helpers::normalize_token( $args['q'] );
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$conditions[] = '(LOWER(display_name) LIKE %s OR LOWER(professional_title) LIKE %s OR specialty_norm LIKE %s OR country_norm LIKE %s OR city_norm LIKE %s OR languages_norm LIKE %s OR qualification_norm LIKE %s)';
			for ( $i = 0; $i < 7; $i++ ) { $params[] = $like; }
		}
		$map = array(
			'country'       => 'country_norm',
			'city'          => 'city_norm',
			'specialty'     => 'specialty_norm',
			'language'      => 'languages_norm',
			'qualification' => 'qualification_norm',
		);
		foreach ( $map as $key => $column ) {
			$value = DDD_Helpers::normalize_token( $args[ $key ] );
			if ( '' !== $value ) {
				$conditions[] = $column . ' LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $value ) . '%';
			}
		}
		if ( absint( $args['min_experience'] ) ) {
			$conditions[] = 'experience_years>=%d';
			$params[] = min( 100, absint( $args['min_experience'] ) );
		}
		if ( in_array( $args['mode'], array( 'online', 'in-person' ), true ) ) {
			$conditions[] = 'consultation_modes_json LIKE %s';
			$params[] = '%"' . $wpdb->esc_like( $args['mode'] ) . '"%';
		}
		if ( $args['accepting'] ) {
			$conditions[] = 'accepting_patients=1';
		}
		$currency = strtoupper( sanitize_text_field( (string) $args['currency'] ) );
		if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$conditions[] = 'currency=%s';
			$params[] = $currency;
		}
		if ( null !== DDD_Helpers::decimal_or_null( $args['fee_min'] ) ) {
			$conditions[] = '(fee_max IS NULL OR fee_max>=%f)';
			$params[] = DDD_Helpers::decimal_or_null( $args['fee_min'] );
		}
		if ( null !== DDD_Helpers::decimal_or_null( $args['fee_max'] ) ) {
			$conditions[] = '(fee_min IS NULL OR fee_min<=%f)';
			$params[] = DDD_Helpers::decimal_or_null( $args['fee_max'] );
		}

		$cursor = DDD_Helpers::cursor_decode( $args['cursor'] );
		if ( $cursor && isset( $cursor['f'], $cursor['q'], $cursor['v'], $cursor['id'] ) ) {
			$conditions[] = '((featured<%d) OR (featured=%d AND quality_score<%f) OR (featured=%d AND quality_score=%f AND COALESCE(verified_at,\'1970-01-01 00:00:00\')<%s) OR (featured=%d AND quality_score=%f AND COALESCE(verified_at,\'1970-01-01 00:00:00\')=%s AND doctor_id>%d))';
			$params[] = absint( $cursor['f'] );
			$params[] = absint( $cursor['f'] );
			$params[] = (float) $cursor['q'];
			$params[] = absint( $cursor['f'] );
			$params[] = (float) $cursor['q'];
			$params[] = sanitize_text_field( $cursor['v'] );
			$params[] = absint( $cursor['f'] );
			$params[] = (float) $cursor['q'];
			$params[] = sanitize_text_field( $cursor['v'] );
			$params[] = absint( $cursor['id'] );
		}

		$table = self::table( 'projection' );
		$where = implode( ' AND ', $conditions );
		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY featured DESC, quality_score DESC, COALESCE(verified_at,'1970-01-01 00:00:00') DESC, doctor_id ASC LIMIT %d";
		$params[] = $limit + 1;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$has_more = count( $rows ) > $limit;
		if ( $has_more ) {
			array_pop( $rows );
		}
		$items = array_map( array( __CLASS__, 'public_dto' ), $rows );
		$next_cursor = '';
		if ( $has_more && $rows ) {
			$last = end( $rows );
			$next_cursor = DDD_Helpers::cursor_encode( array(
				'f'  => absint( $last['featured'] ),
				'q'  => (float) $last['quality_score'],
				'v'  => $last['verified_at'] ? $last['verified_at'] : '1970-01-01 00:00:00',
				'id' => absint( $last['doctor_id'] ),
			) );
		}
		return array( 'items' => $items, 'next_cursor' => $next_cursor, 'has_more' => $has_more, 'limit' => $limit );
	}
	public static function resolve_doctor_id( $public_id ) {
		global $wpdb;
		$table = self::table( 'projection' );
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT doctor_id FROM {$table} WHERE public_id=%s AND eligible=1", sanitize_text_field( $public_id ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	public static function get_by_public_id( $public_id ) {
		global $wpdb;
		$table = self::table( 'projection' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id=%s AND eligible=1", sanitize_text_field( $public_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? self::public_dto( $row ) : array();
	}
}
