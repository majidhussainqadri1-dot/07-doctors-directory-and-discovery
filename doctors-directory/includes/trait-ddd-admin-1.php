<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Admin_Trait_1 {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_ddd_feature_doctor', array( $this, 'feature_doctor' ) );
		add_action( 'admin_post_ddd_transition_report', array( $this, 'transition_report' ) );
		add_action( 'admin_post_ddd_run_reconciliation', array( $this, 'run_reconciliation' ) );
		add_action( 'admin_post_ddd_set_safe_mode', array( $this, 'set_safe_mode' ) );
		add_action( 'admin_post_ddd_rebuild_doctor', array( $this, 'rebuild_doctor' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}
	public function menu() {
		add_menu_page( __( 'Doctors Directory', DDD_TEXT_DOMAIN ), __( 'Doctors Directory', DDD_TEXT_DOMAIN ), 'ddd_manage_directory', 'ddd-directory', array( $this, 'directory' ), 'dashicons-id-alt', 28 );
		add_submenu_page( 'ddd-directory', __( 'Directory Records', DDD_TEXT_DOMAIN ), __( 'Directory Records', DDD_TEXT_DOMAIN ), 'ddd_manage_directory', 'ddd-directory', array( $this, 'directory' ) );
		add_submenu_page( 'ddd-directory', __( 'Listing Reports', DDD_TEXT_DOMAIN ), __( 'Listing Reports', DDD_TEXT_DOMAIN ), 'ddd_review_reports', 'ddd-reports', array( $this, 'reports' ) );
		add_submenu_page( 'ddd-directory', __( 'System Check', DDD_TEXT_DOMAIN ), __( 'System Check', DDD_TEXT_DOMAIN ), 'ddd_view_health', 'ddd-health', array( $this, 'health' ) );
	}
}
