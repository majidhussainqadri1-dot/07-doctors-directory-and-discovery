<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Plugin {
	public function run() {
		( new SDD_Directory() )->hooks();
		( new SDD_Profile() )->hooks();
		( new SDD_Admin() )->hooks();
		( new SDD_Privacy() )->hooks();
		( new SDD_SEO() )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	public function assets() {
		global $post;
		$map = (array) get_option( 'sdd_page_map', array() );
		$needed = $post instanceof WP_Post && ( in_array( $post->ID, array_map( 'absint', $map ), true ) || has_shortcode( $post->post_content, 'sdd_doctors_directory' ) || has_shortcode( $post->post_content, 'sdd_doctor_profile' ) || has_shortcode( $post->post_content, 'sdd_profile_settings' ) );
		if ( ! $needed ) { return; }
		wp_enqueue_style( 'sdd-directory', SDD_URL . 'assets/css/directory.css', array(), SDD_VERSION );
		wp_enqueue_script( 'sdd-directory', SDD_URL . 'assets/js/directory.js', array(), SDD_VERSION, true );
	}

	public function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'doctors-management' ) || false !== strpos( $hook, 'doctors-profile-reports' ) ) { wp_enqueue_style( 'sdd-admin', SDD_URL . 'assets/css/admin.css', array(), SDD_VERSION ); }
	}
}

