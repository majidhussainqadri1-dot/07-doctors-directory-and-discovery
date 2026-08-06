<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Activator {
	const LOCK_OPTION = 'ddd_activation_lock';
	const PAGE_MAP_OPTION = 'ddd_page_map';
	const LEGACY_CURSOR_OPTION = 'ddd_legacy_reports_cursor';
	const LEGACY_DONE_OPTION = 'ddd_legacy_reports_migrated';
	const LEGACY_BATCH = 250;

	public static function activate() {
		if ( ! self::acquire_lock() ) {
			wp_die( esc_html__( 'Doctors Directory activation is already running. Retry after the current migration completes.', DDD_TEXT_DOMAIN ), '', array( 'back_link' => true ) );
		}
		try {
			self::capabilities();
			self::tables();
			self::migrate_legacy_batch();
			self::pages();
			self::schedule();
			self::write_versions();
			set_transient( 'ddd_activation_notice', '1', 180 );
			flush_rewrite_rules();
			if ( class_exists( 'DDD_Observability' ) ) {
				DDD_Observability::record_health( 'activation', 'pass', DDD_VERSION );
			}
		} finally {
			self::release_lock();
		}
	}

	public static function maybe_upgrade() {
		$db_version = (string) get_option( 'ddd_db_version', '0' );
		if ( version_compare( $db_version, DDD_DB_VERSION, '>=' ) ) {
			return;
		}
		if ( ! self::acquire_lock() ) {
			return;
		}
		try {
			self::tables();
			self::migrate_legacy_batch();
			self::pages();
			self::schedule();
			self::write_versions();
		} finally {
			self::release_lock();
		}
	}

	public static function continue_legacy_migration() {
		if ( get_option( self::LEGACY_DONE_OPTION, false ) ) {
			return;
		}
		if ( ! self::acquire_lock( 120 ) ) {
			return;
		}
		try {
			self::migrate_legacy_batch();
		} finally {
			self::release_lock();
		}
	}

	public static function repair( $execute = false ) {
		$plan = array(
			'tables'      => array( 'projection', 'taxonomy', 'saved_refs', 'reports', 'report_audit', 'feature_audit', 'admin_audit', 'outbox', 'inbox', 'search_metrics', 'rate_limits', 'health_log' ),
			'pages'       => array( 'directory', 'status' ),
			'schedules'   => array( 'ddd_reconcile_tick', 'ddd_expire_features_tick', 'ddd_process_outbox_tick', 'ddd_continue_legacy_migration' ),
			'execute'     => (bool) $execute,
			'changed'     => false,
			'completed_at'=> current_time( 'mysql', true ),
		);
		if ( ! $execute ) {
			return $plan;
		}
		if ( ! self::acquire_lock() ) {
			return DDD_Helpers::safe_error( 'repair_locked', __( 'A directory repair or migration is already running.', DDD_TEXT_DOMAIN ), 409 );
		}
		try {
			self::tables();
			self::pages();
			self::schedule();
			self::migrate_legacy_batch();
			self::write_versions();
			$plan['changed'] = true;
			$plan['completed_at'] = current_time( 'mysql', true );
			if ( class_exists( 'DDD_Observability' ) ) {
				DDD_Observability::record_health( 'repair', 'pass', 'repair_completed' );
			}
			return $plan;
		} finally {
			self::release_lock();
		}
	}

	public static function deactivate() {
		foreach ( array( 'ddd_reconcile_tick', 'ddd_expire_features_tick', 'ddd_process_outbox_tick', 'ddd_continue_legacy_migration' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		flush_rewrite_rules();
	}

	private static function write_versions() {
		update_option( 'ddd_version', DDD_VERSION, false );
		update_option( 'ddd_db_version', DDD_DB_VERSION, false );
		update_option( 'ddd_contract_version', DDD_CONTRACT_VERSION, false );
		update_option( 'ddd_projection_schema', DDD_PROJECTION_SCHEMA, false );
	}

	private static function acquire_lock( $ttl = 300 ) {
		global $wpdb;
		$now = time();
		$token = DDD_Helpers::trace_id();
		$payload = wp_json_encode( array( 'token' => $token, 'expires' => $now + max( 30, absint( $ttl ) ) ) );

		if ( add_option( self::LOCK_OPTION, $payload, '', 'no' ) ) {
			$GLOBALS['ddd_activation_lock_token'] = $token;
			return true;
		}

		$current = json_decode( (string) get_option( self::LOCK_OPTION, '' ), true );
		if ( ! is_array( $current ) || empty( $current['expires'] ) || absint( $current['expires'] ) <= $now ) {
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", self::LOCK_OPTION, (string) get_option( self::LOCK_OPTION, '' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $deleted && add_option( self::LOCK_OPTION, $payload, '', 'no' ) ) {
				$GLOBALS['ddd_activation_lock_token'] = $token;
				return true;
			}
		}
		return false;
	}

	private static function release_lock() {
		global $wpdb;
		$token = isset( $GLOBALS['ddd_activation_lock_token'] ) ? (string) $GLOBALS['ddd_activation_lock_token'] : '';
		if ( ! $token ) {
			return;
		}
		$current_raw = (string) get_option( self::LOCK_OPTION, '' );
		$current = json_decode( $current_raw, true );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], $token ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", self::LOCK_OPTION, $current_raw ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		unset( $GLOBALS['ddd_activation_lock_token'] );
	}

	private static function capabilities() {
		$caps = array( 'ddd_manage_directory', 'ddd_manage_features', 'ddd_manage_taxonomy', 'ddd_review_reports', 'ddd_run_reconciliation', 'ddd_view_health', 'ddd_repair_directory' );
		$role = get_role( 'administrator' );
		if ( $role ) {
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
		$founder_id = DDD_Helpers::founder_id();
		if ( $founder_id ) {
			$user = get_userdata( $founder_id );
			if ( $user ) {
				foreach ( $caps as $cap ) {
					$user->add_cap( $cap );
				}
			}
		}
	}

	public static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$p = $wpdb->prefix;
		$engine = ' ENGINE=InnoDB ';
		$sql = array();

		$sql[] = "CREATE TABLE {$p}ddd_projection (
			doctor_id bigint(20) unsigned NOT NULL,
			public_id char(36) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'limited', eligible tinyint(1) unsigned NOT NULL DEFAULT 0,
			reasons_json longtext NOT NULL, display_name varchar(191) NOT NULL DEFAULT '', display_name_norm varchar(191) NOT NULL DEFAULT '', professional_title varchar(191) NOT NULL,
			specialty varchar(191) NOT NULL, specialty_norm varchar(191) NOT NULL, country varchar(120) NOT NULL,
			country_norm varchar(120) NOT NULL, city varchar(120) NOT NULL, city_norm varchar(120) NOT NULL,
			languages_json longtext NOT NULL, languages_norm text NOT NULL, qualification text NOT NULL,
			qualification_norm text NOT NULL, search_text_norm longtext NOT NULL,
			experience_years smallint(5) unsigned NOT NULL DEFAULT 0, consultation_modes_json text NOT NULL,
			accepting_patients tinyint(1) unsigned NOT NULL DEFAULT 0, fee_min decimal(14,2) NULL, fee_max decimal(14,2) NULL,
			currency char(3) NOT NULL DEFAULT '', avatar_id bigint(20) unsigned NOT NULL DEFAULT 0,
			profile_url text NOT NULL, clinic_url text NOT NULL, appointment_url text NOT NULL,
			completeness smallint(5) unsigned NOT NULL DEFAULT 0, quality_score decimal(8,3) NOT NULL DEFAULT 0,
			verified_at datetime NULL, featured tinyint(1) unsigned NOT NULL DEFAULT 0, feature_label varchar(120) NOT NULL DEFAULT '',
			feature_start datetime NULL, feature_end datetime NULL, feature_reason text NOT NULL,
			feature_approver bigint(20) unsigned NOT NULL DEFAULT 0, owner_versions_json longtext NOT NULL,
			projection_checksum char(64) NOT NULL, version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY  (doctor_id), UNIQUE KEY public_id (public_id),
			KEY eligible_rank (eligible,featured,quality_score,doctor_id), KEY verified_recent (eligible,verified_at,doctor_id),
			KEY location (country_norm,city_norm), KEY specialty (specialty_norm), KEY name_search (display_name_norm), KEY fee (currency,fee_min,fee_max), KEY updated_at (updated_at)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_taxonomy (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, type varchar(32) NOT NULL, canonical_key varchar(191) NOT NULL,
			canonical_label varchar(191) NOT NULL, aliases_json longtext NOT NULL, status varchar(20) NOT NULL DEFAULT 'active',
			version bigint(20) unsigned NOT NULL DEFAULT 1, created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY type_key (type,canonical_key), KEY status (status)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_saved_refs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, user_id bigint(20) unsigned NOT NULL, doctor_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL, PRIMARY KEY  (id), UNIQUE KEY user_doctor (user_id,doctor_id), KEY doctor_id (doctor_id)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			doctor_public_id char(36) NOT NULL DEFAULT '', reporter_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(40) NOT NULL, details text NOT NULL, evidence_url text NOT NULL, status varchar(20) NOT NULL DEFAULT 'open',
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0, review_note text NOT NULL, idempotency_key varchar(128) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '', retention_hold tinyint(1) unsigned NOT NULL DEFAULT 0,
			version bigint(20) unsigned NOT NULL DEFAULT 1, created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY idempotency_key (idempotency_key), KEY doctor_status (doctor_id,status),
			KEY public_status (doctor_public_id,status), KEY reporter_id (reporter_id), KEY updated_at (updated_at)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_report_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, report_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0, old_status varchar(20) NOT NULL, new_status varchar(20) NOT NULL,
			note text NOT NULL, trace_id varchar(32) NOT NULL, created_at datetime NOT NULL,
			PRIMARY KEY  (id), KEY report_id (report_id), KEY created_at (created_at)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_feature_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, doctor_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0, old_state_json longtext NOT NULL, new_state_json longtext NOT NULL,
			reason text NOT NULL, trace_id varchar(32) NOT NULL, created_at datetime NOT NULL,
			PRIMARY KEY  (id), KEY doctor_created (doctor_id,created_at), KEY actor_id (actor_id)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_admin_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, action varchar(80) NOT NULL, actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			object_type varchar(40) NOT NULL, object_id varchar(191) NOT NULL, outcome varchar(20) NOT NULL,
			context_json longtext NOT NULL, trace_id varchar(32) NOT NULL, created_at datetime NOT NULL,
			PRIMARY KEY  (id), KEY action_created (action,created_at), KEY object_ref (object_type,object_id)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_outbox (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, event_id char(36) NOT NULL, event_type varchar(120) NOT NULL,
			aggregate_id varchar(191) NOT NULL, payload_json longtext NOT NULL, status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0, available_at datetime NOT NULL, locked_by varchar(32) NOT NULL DEFAULT '',
			locked_at datetime NULL, last_error varchar(191) NOT NULL DEFAULT '', created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY event_id (event_id), KEY queue (status,available_at), KEY lock_state (locked_by,locked_at)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_inbox (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, event_id varchar(191) NOT NULL, event_type varchar(120) NOT NULL,
			payload_hash char(64) NOT NULL, status varchar(20) NOT NULL DEFAULT 'processing', attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error varchar(191) NOT NULL DEFAULT '', processed_at datetime NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY event_id (event_id), KEY status_updated (status,updated_at)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_search_metrics (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, metric_date date NOT NULL, filter_hash char(64) NOT NULL,
			query_class varchar(32) NOT NULL DEFAULT 'browse', result_bucket varchar(20) NOT NULL DEFAULT 'unknown',
			request_count bigint(20) unsigned NOT NULL DEFAULT 0, updated_at datetime NOT NULL,
			PRIMARY KEY  (id), UNIQUE KEY metric_key (metric_date,filter_hash,query_class,result_bucket), KEY updated_at (updated_at)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_rate_limits (
			scope_key char(64) NOT NULL, bucket bigint(20) unsigned NOT NULL, request_count int(10) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL, updated_at datetime NOT NULL,
			PRIMARY KEY  (scope_key,bucket), KEY expires_at (expires_at)
		) {$engine}{$charset};";

		$sql[] = "CREATE TABLE {$p}ddd_health_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT, component varchar(80) NOT NULL, status varchar(20) NOT NULL,
			code varchar(120) NOT NULL, context_json text NOT NULL, created_at datetime NOT NULL,
			PRIMARY KEY  (id), KEY component_created (component,created_at)
		) {$engine}{$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		foreach ( array( 'projection','taxonomy','saved_refs','reports','report_audit','feature_audit','admin_audit','outbox','inbox','search_metrics','rate_limits','health_log' ) as $name ) {
			$table = $p . 'ddd_' . $name;
			$engine_name = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
			if ( $engine_name && 'InnoDB' !== $engine_name ) {
				$wpdb->query( "ALTER TABLE `{$table}` ENGINE=InnoDB" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
	}

	private static function migrate_legacy_batch() {
		global $wpdb;
		if ( get_option( self::LEGACY_DONE_OPTION, false ) ) {
			return;
		}
		$legacy = $wpdb->prefix . 'sdd_reports';
		$new = $wpdb->prefix . 'ddd_reports';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) ) !== $legacy ) {
			update_option( self::LEGACY_DONE_OPTION, current_time( 'mysql', true ), false );
			return;
		}
		$cursor = absint( get_option( self::LEGACY_CURSOR_OPTION, 0 ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$legacy} WHERE id>%d ORDER BY id ASC LIMIT %d", $cursor, self::LEGACY_BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$last = $cursor;
		foreach ( (array) $rows as $row ) {
			$last = absint( $row->id );
			$created = ! empty( $row->created_at ) ? $row->created_at : current_time( 'mysql', true );
			$updated = ! empty( $row->updated_at ) && '0000-00-00 00:00:00' !== $row->updated_at ? $row->updated_at : $created;
			$public_id = (string) $wpdb->get_var( $wpdb->prepare( "SELECT public_id FROM {$wpdb->prefix}ddd_projection WHERE doctor_id=%d", absint( $row->doctor_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$new} (id,doctor_id,doctor_public_id,reporter_id,reason,details,evidence_url,status,reviewer_id,review_note,idempotency_key,ip_hash,retention_hold,version,created_at,updated_at) VALUES (%d,%d,%s,%d,%s,%s,'',%s,%d,%s,%s,'',0,1,%s,%s)",
					absint( $row->id ), absint( $row->doctor_id ), DDD_Helpers::valid_public_id( $public_id ) ? $public_id : '', absint( $row->reporter_id ),
					sanitize_key( $row->reason ), sanitize_textarea_field( $row->details ), sanitize_key( $row->status ),
					isset( $row->reviewer_id ) ? absint( $row->reviewer_id ) : 0,
					isset( $row->review_note ) ? sanitize_textarea_field( $row->review_note ) : '',
					'legacy:' . absint( $row->id ), $created, $updated
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		update_option( self::LEGACY_CURSOR_OPTION, $last, false );
		if ( count( $rows ) < self::LEGACY_BATCH ) {
			update_option( self::LEGACY_DONE_OPTION, current_time( 'mysql', true ), false );
			delete_option( self::LEGACY_CURSOR_OPTION );
		} elseif ( ! wp_next_scheduled( 'ddd_continue_legacy_migration' ) ) {
			wp_schedule_single_event( time() + 30, 'ddd_continue_legacy_migration' );
		}
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
		if ( $id ) { $candidates[] = get_post( $id ); }
		$path_page = get_page_by_path( $slug );
		if ( $path_page instanceof WP_Post ) { $candidates[] = $path_page; }
		foreach ( $candidates as $page ) {
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) { continue; }
			$content = trim( (string) $page->post_content );
			$managed = '1' === get_post_meta( $page->ID, '_ddd_managed_page', true );
			if ( '' === $content || $managed || in_array( $content, array_merge( array( $shortcode ), $legacy_shortcodes ), true ) ) {
				if ( ! metadata_exists( 'post', $page->ID, '_ddd_previous_content' ) ) {
					update_post_meta( $page->ID, '_ddd_previous_content', (string) $page->post_content );
					update_post_meta( $page->ID, '_ddd_previous_status', (string) $page->post_status );
				}
				if ( $content !== $shortcode || 'publish' !== $page->post_status ) {
					wp_update_post( array( 'ID' => $page->ID, 'post_content' => $shortcode, 'post_status' => 'publish' ) );
				}
				update_post_meta( $page->ID, '_ddd_managed_page', '1' );
				return absint( $page->ID );
			}
		}
		$parent_id = self::ensure_parent_path( $slug );
		$new_id = wp_insert_post( array( 'post_title' => $title, 'post_name' => sanitize_title( basename( $slug ) ), 'post_parent' => $parent_id, 'post_content' => $shortcode, 'post_status' => 'publish', 'post_type' => 'page' ), true );
		if ( is_wp_error( $new_id ) ) {
			if ( class_exists( 'DDD_Observability' ) ) { DDD_Observability::log( 'error', 'page_create_failed', array( 'slug' => $slug ) ); }
			return 0;
		}
		update_post_meta( $new_id, '_ddd_managed_page', '1' );
		update_post_meta( $new_id, '_ddd_created_page', '1' );
		return absint( $new_id );
	}

	private static function schedule() {
		$events = array(
			'ddd_reconcile_tick' => array( 300, 'hourly' ),
			'ddd_expire_features_tick' => array( 600, 'hourly' ),
			'ddd_process_outbox_tick' => array( 120, 'hourly' ),
		);
		foreach ( $events as $hook => $definition ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + $definition[0], $definition[1], $hook );
			}
		}
		if ( ! get_option( self::LEGACY_DONE_OPTION, false ) && ! wp_next_scheduled( 'ddd_continue_legacy_migration' ) ) {
			wp_schedule_single_event( time() + 30, 'ddd_continue_legacy_migration' );
		}
	}

	private static function ensure_parent_path( $slug ) {
		$parts = array_values( array_filter( explode( '/', trim( (string) $slug, '/' ) ) ) );
		array_pop( $parts );
		if ( ! $parts ) { return 0; }
		$parent_id = 0;
		$path = '';
		foreach ( $parts as $part ) {
			$path = $path ? $path . '/' . $part : $part;
			$page = get_page_by_path( $path );
			if ( $page instanceof WP_Post ) { $parent_id = absint( $page->ID ); continue; }
			$new_id = wp_insert_post( array( 'post_title' => ucwords( str_replace( array( '-', '_' ), ' ', $part ) ), 'post_name' => sanitize_title( $part ), 'post_parent' => $parent_id, 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'page' ), true );
			if ( is_wp_error( $new_id ) ) { return 0; }
			update_post_meta( $new_id, '_ddd_route_parent', '1' );
			$parent_id = absint( $new_id );
		}
		return $parent_id;
	}
}

if ( ! class_exists( 'SDD_Activator' ) ) {
	class_alias( 'DDD_Activator', 'SDD_Activator' );
}
