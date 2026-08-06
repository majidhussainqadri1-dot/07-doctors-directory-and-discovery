<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Activator_Trait_3 {
	private static function migrate_legacy() {
		global $wpdb;
		$legacy = $wpdb->prefix . 'sdd_reports';
		$new = $wpdb->prefix . 'ddd_reports';
		$legacy_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
		if ( $legacy_exists !== $legacy ) {
			return;
		}
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$new}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}
		$rows = $wpdb->get_results( "SELECT * FROM {$legacy} ORDER BY id ASC LIMIT 5000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$created = ! empty( $row->created_at ) ? $row->created_at : current_time( 'mysql', true );
			$updated = ! empty( $row->updated_at ) && '0000-00-00 00:00:00' !== $row->updated_at ? $row->updated_at : $created;
			$wpdb->insert(
				$new,
				array(
					'id'              => absint( $row->id ),
					'doctor_id'       => absint( $row->doctor_id ),
					'reporter_id'     => absint( $row->reporter_id ),
					'reason'          => sanitize_key( $row->reason ),
					'details'         => sanitize_textarea_field( $row->details ),
					'evidence_url'    => '',
					'status'          => sanitize_key( $row->status ),
					'reviewer_id'     => isset( $row->reviewer_id ) ? absint( $row->reviewer_id ) : 0,
					'review_note'     => isset( $row->review_note ) ? sanitize_textarea_field( $row->review_note ) : '',
					'idempotency_key' => '',
					'ip_hash'         => '',
					'version'         => 1,
					'created_at'      => $created,
					'updated_at'      => $updated,
				),
				array( '%d','%d','%d','%s','%s','%s','%s','%d','%s','%s','%s','%d','%s','%s' )
			);
		}
		update_option( 'ddd_legacy_reports_migrated', current_time( 'mysql', true ), false );
	}
	private static function pages() {
		$map = (array) get_option( self::PAGE_MAP_OPTION, array() );
		$map['directory'] = self::managed_page( isset( $map['directory'] ) ? absint( $map['directory'] ) : 0, 'Doctors', 'doctors', '[ddd_doctors_directory]', array( '[sdd_doctors_directory]', '[sabri_doctor_directory]' ) );
		$map['status'] = self::managed_page( isset( $map['status'] ) ? absint( $map['status'] ) : 0, 'Directory Status', 'account/directory-status', '[ddd_directory_status]', array( '[sdd_profile_settings]' ) );
		update_option( self::PAGE_MAP_OPTION, $map, false );
		update_option( 'sdd_page_map', array( 'directory' => $map['directory'], 'settings' => $map['status'] ), false );
	}
	private static function managed_page( $id, $title, $slug, $shortcode, $legacy_shortcodes ) {
		$candidates = array();
		if ( $id ) {
			$candidates[] = get_post( $id );
		}
		$path_page = get_page_by_path( $slug );
		if ( $path_page instanceof WP_Post ) {
			$candidates[] = $path_page;
		}
		foreach ( $candidates as $page ) {
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
				continue;
			}
			$content = trim( (string) $page->post_content );
			$managed = '1' === get_post_meta( $page->ID, '_ddd_managed_page', true );
			if ( '' === $content || $managed || in_array( $content, array_merge( array( $shortcode ), $legacy_shortcodes ), true ) ) {
				if ( $content !== $shortcode ) {
					update_post_meta( $page->ID, '_ddd_previous_content', $content );
					wp_update_post( array( 'ID' => $page->ID, 'post_content' => $shortcode, 'post_status' => 'publish' ) );
				}
				update_post_meta( $page->ID, '_ddd_managed_page', '1' );
				return absint( $page->ID );
			}
		}
		$parent_id = self::ensure_parent_path( $slug );
		$new_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => sanitize_title( basename( $slug ) ),
				'post_parent'  => $parent_id,
				'post_content' => $shortcode,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			DDD_Observability::log( 'error', 'page_create_failed', array( 'slug' => $slug ) );
			return 0;
		}
		update_post_meta( $new_id, '_ddd_managed_page', '1' );
		update_post_meta( $new_id, '_ddd_created_page', '1' );
		return absint( $new_id );
	}
	private static function schedule() {
		if ( ! wp_next_scheduled( 'ddd_reconcile_tick' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'ddd_reconcile_tick' );
		}
		if ( ! wp_next_scheduled( 'ddd_expire_features_tick' ) ) {
			wp_schedule_event( time() + 600, 'hourly', 'ddd_expire_features_tick' );
		}
	}
	private static function ensure_parent_path( $slug ) {
		$parts = array_values( array_filter( explode( '/', trim( (string) $slug, '/' ) ) ) );
		array_pop( $parts );
		if ( ! $parts ) {
			return 0;
		}
		$parent_id = 0;
		$path = '';
		foreach ( $parts as $part ) {
			$path = $path ? $path . '/' . $part : $part;
			$page = get_page_by_path( $path );
			if ( $page instanceof WP_Post ) {
				$parent_id = absint( $page->ID );
				continue;
			}
			$new_id = wp_insert_post(
				array(
					'post_title'   => ucwords( str_replace( array( '-', '_' ), ' ', $part ) ),
					'post_name'    => sanitize_title( $part ),
					'post_parent'  => $parent_id,
					'post_content' => '',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				true
			);
			if ( is_wp_error( $new_id ) ) {
				return 0;
			}
			update_post_meta( $new_id, '_ddd_route_parent', '1' );
			$parent_id = absint( $new_id );
		}
		return $parent_id;
	}
}
