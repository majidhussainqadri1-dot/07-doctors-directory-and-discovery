<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Activator_Trait_2 {
	public static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$prefix = $wpdb->prefix;

		$sql = array();
		$sql[] = "CREATE TABLE {$prefix}ddd_projection (
			doctor_id bigint(20) unsigned NOT NULL,
			public_id char(36) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'limited',
			eligible tinyint(1) unsigned NOT NULL DEFAULT 0,
			reasons_json longtext NOT NULL,
			display_name varchar(191) NOT NULL,
			professional_title varchar(191) NOT NULL,
			specialty varchar(191) NOT NULL,
			specialty_norm varchar(191) NOT NULL,
			country varchar(120) NOT NULL,
			country_norm varchar(120) NOT NULL,
			city varchar(120) NOT NULL,
			city_norm varchar(120) NOT NULL,
			languages_json longtext NOT NULL,
			languages_norm text NOT NULL,
			qualification text NOT NULL,
			qualification_norm text NOT NULL,
			experience_years smallint(5) unsigned NOT NULL DEFAULT 0,
			consultation_modes_json text NOT NULL,
			accepting_patients tinyint(1) unsigned NOT NULL DEFAULT 0,
			fee_min decimal(14,2) NULL,
			fee_max decimal(14,2) NULL,
			currency char(3) NOT NULL DEFAULT '',
			avatar_id bigint(20) unsigned NOT NULL DEFAULT 0,
			profile_url text NOT NULL,
			clinic_url text NOT NULL,
			appointment_url text NOT NULL,
			completeness smallint(5) unsigned NOT NULL DEFAULT 0,
			quality_score decimal(8,3) NOT NULL DEFAULT 0,
			verified_at datetime NULL,
			featured tinyint(1) unsigned NOT NULL DEFAULT 0,
			feature_label varchar(120) NOT NULL DEFAULT '',
			feature_start datetime NULL,
			feature_end datetime NULL,
			feature_reason text NOT NULL,
			feature_approver bigint(20) unsigned NOT NULL DEFAULT 0,
			owner_versions_json longtext NOT NULL,
			projection_checksum char(64) NOT NULL,
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (doctor_id),
			UNIQUE KEY public_id (public_id),
			KEY eligible_rank (eligible, featured, quality_score, doctor_id),
			KEY verified_recent (eligible, verified_at, doctor_id),
			KEY location (country_norm, city_norm),
			KEY specialty (specialty_norm),
			KEY fee (currency, fee_min, fee_max),
			KEY updated_at (updated_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}ddd_taxonomy (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(32) NOT NULL,
			canonical_key varchar(191) NOT NULL,
			canonical_label varchar(191) NOT NULL,
			aliases_json longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY type_key (type, canonical_key),
			KEY status (status)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}ddd_saved_refs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			doctor_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_doctor (user_id, doctor_id),
			KEY doctor_id (doctor_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}ddd_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reporter_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(40) NOT NULL,
			details text NOT NULL,
			evidence_url text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			review_note text NOT NULL,
			idempotency_key varchar(128) NOT NULL DEFAULT '',
			ip_hash char(64) NOT NULL DEFAULT '',
			version bigint(20) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY doctor_status (doctor_id, status),
			KEY reporter_id (reporter_id),
			KEY updated_at (updated_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}ddd_report_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			report_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			old_status varchar(20) NOT NULL,
			new_status varchar(20) NOT NULL,
			note text NOT NULL,
			trace_id varchar(32) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY report_id (report_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}ddd_outbox (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id char(36) NOT NULL,
			event_type varchar(120) NOT NULL,
			aggregate_id varchar(191) NOT NULL,
			payload_json longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			available_at datetime NOT NULL,
			last_error varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY queue (status, available_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}ddd_inbox (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id varchar(191) NOT NULL,
			event_type varchar(120) NOT NULL,
			payload_hash char(64) NOT NULL,
			processed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$prefix}ddd_health_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			component varchar(80) NOT NULL,
			status varchar(20) NOT NULL,
			code varchar(120) NOT NULL,
			context_json text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY component_created (component, created_at)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
