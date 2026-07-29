<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Activator {
	public static function activate() {
		if ( ! SDD_Helpers::dependency_ready() ) {
			deactivate_plugins( plugin_basename( SDD_FILE ) );
			wp_die( esc_html( SDD_Helpers::dependency_message() ), '', array( 'back_link' => true ) );
		}
		self::capability();
		self::tables();
		self::pages();
		update_option( 'sdd_version', SDD_VERSION, false );
		update_option( 'sdd_db_version', SDD_DB_VERSION, false );
		set_transient( 'sdd_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( version_compare( (string) get_option( 'sdd_db_version', '0' ), SDD_DB_VERSION, '<' ) ) {
			self::tables();
			self::pages();
			update_option( 'sdd_db_version', SDD_DB_VERSION, false );
		}
		if ( version_compare( (string) get_option( 'sdd_version', '0' ), SDD_VERSION, '<' ) ) {
			update_option( 'sdd_version', SDD_VERSION, false );
		}
	}

	public static function deactivate() {
		$map = (array) get_option( 'sdd_page_map', array() );
		foreach ( $map as $page_id ) {
			self::restore_page( absint( $page_id ) );
		}
		flush_rewrite_rules();
	}

	private static function restore_page( $page_id ) {
		$page = get_post( $page_id );
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
			return;
		}
		$created = '1' === get_post_meta( $page_id, '_sdd_created_page', true );
		$current = trim( (string) $page->post_content );
		if ( $created ) {
			if ( in_array( $current, array( '[sdd_doctors_directory]', '[sdd_doctor_profile]', '[sdd_profile_settings]' ), true ) ) {
				wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ) );
			}
			return;
		}
		$previous = get_post_meta( $page_id, '_sdd_previous_content', true );
		if ( '' !== $previous && in_array( $current, array( '[sdd_doctors_directory]', '[sdd_doctor_profile]', '[sdd_profile_settings]' ), true ) ) {
			wp_update_post( array( 'ID' => $page_id, 'post_content' => $previous ) );
			delete_post_meta( $page_id, '_sdd_previous_content' );
		}
	}

	private static function capability() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_doctors_directory' );
		}
	}

	private static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$wpdb->prefix}sdd_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reporter_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(40) NOT NULL,
			details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			reviewer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			review_note text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id),
			KEY reporter_id (reporter_id),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$wpdb->prefix}sdd_report_audit (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			report_id bigint(20) unsigned NOT NULL,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			old_status varchar(20) NOT NULL,
			new_status varchar(20) NOT NULL,
			note text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY report_id (report_id),
			KEY created_at (created_at)
		) {$charset};" );
		$wpdb->query( "UPDATE {$wpdb->prefix}sdd_reports SET updated_at = created_at WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function pages() {
		$spd = (array) get_option( 'spd_page_map', array() );
		$map = (array) get_option( 'sdd_page_map', array() );
		$map['directory'] = self::managed_page( ! empty( $spd['doctors'] ) ? absint( $spd['doctors'] ) : 0, 'Doctors', 'homeopathy-doctors', '[sdd_doctors_directory]', array( '[sabri_doctor_directory]', '[sdd_doctors_directory]' ) );
		$map['profile']   = self::managed_page( ! empty( $spd['profile'] ) ? absint( $spd['profile'] ) : 0, 'Member Profile', 'member-profile', '[sdd_doctor_profile]', array( '[sabri_member_profile]', '[sdd_doctor_profile]' ) );
		$map['settings']  = self::managed_page( ! empty( $map['settings'] ) ? absint( $map['settings'] ) : 0, 'Doctor Directory Settings', 'doctor-directory-settings', '[sdd_profile_settings]', array( '[sdd_profile_settings]' ) );
		update_option( 'sdd_page_map', $map, false );
		$spd['doctors'] = $map['directory'];
		$spd['profile'] = $map['profile'];
		update_option( 'spd_page_map', $spd, false );
		$spf = (array) get_option( 'spf_page_map', array() );
		$spf['doctors'] = $map['directory'];
		update_option( 'spf_page_map', $spf, false );
	}

	private static function managed_page( $id, $title, $slug, $shortcode, $replaceable ) {
		$candidates = array();
		if ( $id ) {
			$candidates[] = get_post( $id );
		}
		$slug_page = get_page_by_path( $slug );
		if ( $slug_page instanceof WP_Post && ( ! $id || absint( $slug_page->ID ) !== absint( $id ) ) ) {
			$candidates[] = $slug_page;
		}
		foreach ( $candidates as $page ) {
			if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
				continue;
			}
			$content = trim( (string) $page->post_content );
			$managed = get_post_meta( $page->ID, '_sdd_managed_page', true ) || get_post_meta( $page->ID, '_spd_managed_page', true ) || get_post_meta( $page->ID, '_spf_managed_page', true );
			if ( in_array( $content, $replaceable, true ) ) {
				if ( $content !== $shortcode ) {
					update_post_meta( $page->ID, '_sdd_previous_content', $content );
					wp_update_post( array( 'ID' => $page->ID, 'post_content' => $shortcode ) );
				}
				update_post_meta( $page->ID, '_sdd_managed_page', '1' );
				if ( 'publish' !== $page->post_status && $managed ) {
					wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) );
				}
				return $page->ID;
			}
			if ( $managed && '' === $content ) {
				wp_update_post( array( 'ID' => $page->ID, 'post_content' => $shortcode, 'post_status' => 'publish' ) );
				update_post_meta( $page->ID, '_sdd_managed_page', '1' );
				return $page->ID;
			}
		}
		$new_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $shortcode,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);
		if ( ! is_wp_error( $new_id ) ) {
			update_post_meta( $new_id, '_sdd_managed_page', '1' );
			update_post_meta( $new_id, '_sdd_created_page', '1' );
			return $new_id;
		}
		return 0;
	}
}
