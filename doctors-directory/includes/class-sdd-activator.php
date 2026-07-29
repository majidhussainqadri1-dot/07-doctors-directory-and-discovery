<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Activator {
	public static function activate() {
		if ( ! class_exists( 'SPD_Helpers' ) ) {
			deactivate_plugins( plugin_basename( SDD_FILE ) );
			wp_die( esc_html__( 'Activate File 03 — Sabri Profiles and Doctors before activating Doctors Directory and Discovery.', 'doctors-directory' ), '', array( 'back_link' => true ) );
		}
		self::capability();
		self::table();
		self::pages();
		update_option( 'sdd_version', SDD_VERSION, false );
		set_transient( 'sdd_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		$map = (array) get_option( 'sdd_page_map', array() );
		if ( ! empty( $map['directory'] ) ) { self::restore_shortcode( $map['directory'], '[sdd_doctors_directory]', '[sabri_doctor_directory]' ); }
		if ( ! empty( $map['profile'] ) ) { self::restore_shortcode( $map['profile'], '[sdd_doctor_profile]', '[sabri_member_profile]' ); }
		flush_rewrite_rules();
	}

	private static function restore_shortcode( $page_id, $current, $fallback ) {
		$page = get_post( absint( $page_id ) );
		if ( $page instanceof WP_Post && trim( $page->post_content ) === $current ) { wp_update_post( array( 'ID' => $page->ID, 'post_content' => $fallback ) ); }
	}

	private static function capability() {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_doctors_directory' );
		}
	}

	private static function table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$wpdb->prefix}sdd_reports (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			doctor_id bigint(20) unsigned NOT NULL,
			reporter_id bigint(20) unsigned NOT NULL,
			reason varchar(40) NOT NULL,
			details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id),
			KEY status (status)
		) {$charset};" );
	}

	private static function pages() {
		$spd = (array) get_option( 'spd_page_map', array() );
		$map = (array) get_option( 'sdd_page_map', array() );
		$map['directory'] = self::managed_page( ! empty( $spd['doctors'] ) ? absint( $spd['doctors'] ) : 0, 'Doctors', 'homeopathy-doctors', '[sdd_doctors_directory]' );
		$map['profile'] = self::managed_page( ! empty( $spd['profile'] ) ? absint( $spd['profile'] ) : 0, 'Member Profile', 'member-profile', '[sdd_doctor_profile]' );
		$map['settings'] = self::managed_page( 0, 'Doctor Directory Settings', 'doctor-directory-settings', '[sdd_profile_settings]' );
		update_option( 'sdd_page_map', $map, false );
		$spd['doctors'] = $map['directory'];
		$spd['profile'] = $map['profile'];
		update_option( 'spd_page_map', $spd, false );
		$spf = (array) get_option( 'spf_page_map', array() );
		$spf['doctors'] = $map['directory'];
		update_option( 'spf_page_map', $spf, false );
	}

	private static function managed_page( $id, $title, $slug, $shortcode ) {
		$page = $id ? get_post( $id ) : get_page_by_path( $slug );
		if ( $page instanceof WP_Post ) {
			$managed = get_post_meta( $page->ID, '_spd_managed_page', true ) || get_post_meta( $page->ID, '_spf_managed_page', true ) || get_post_meta( $page->ID, '_sdd_managed_page', true );
			if ( $managed || false !== strpos( $page->post_content, '[sabri_' ) || false !== strpos( $page->post_content, '[sdd_' ) ) {
				wp_update_post( array( 'ID' => $page->ID, 'post_content' => $shortcode ) );
				update_post_meta( $page->ID, '_sdd_managed_page', '1' );
			}
			return $page->ID;
		}
		$id = wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_content' => $shortcode, 'post_status' => 'publish', 'post_type' => 'page' ), true );
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_sdd_managed_page', '1' );
			return $id;
		}
		return 0;
	}
}
