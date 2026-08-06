<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Activator_Trait_1 {
	public static function activate() {
		if ( ! self::acquire_lock() ) {
			wp_die( esc_html__( 'Doctors Directory activation is already running. Retry after the current migration completes.', DDD_TEXT_DOMAIN ), '', array( 'back_link' => true ) );
		}
		try {
			self::capabilities();
			self::tables();
			self::migrate_legacy();
			self::pages();
			self::schedule();
			update_option( 'ddd_version', DDD_VERSION, false );
			update_option( 'ddd_db_version', DDD_DB_VERSION, false );
			update_option( 'ddd_contract_version', DDD_CONTRACT_VERSION, false );
			update_option( 'ddd_projection_schema', DDD_PROJECTION_SCHEMA, false );
			set_transient( 'ddd_activation_notice', '1', 180 );
			flush_rewrite_rules();
			DDD_Observability::record_health( 'activation', 'pass', DDD_VERSION );
		} finally {
			self::release_lock();
		}
	}
	public static function maybe_upgrade() {
		$db_version = (string) get_option( 'ddd_db_version', '0' );
		if ( version_compare( $db_version, DDD_DB_VERSION, '<' ) && self::acquire_lock() ) {
			try {
				self::tables();
				self::migrate_legacy();
				self::pages();
				update_option( 'ddd_db_version', DDD_DB_VERSION, false );
				update_option( 'ddd_version', DDD_VERSION, false );
			} finally {
				self::release_lock();
			}
		}
	}
	public static function deactivate() {
		wp_clear_scheduled_hook( 'ddd_reconcile_tick' );
		wp_clear_scheduled_hook( 'ddd_expire_features_tick' );
		flush_rewrite_rules();
	}
	private static function acquire_lock() {
		$now = time();
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && ! empty( $lock['expires'] ) && absint( $lock['expires'] ) > $now ) {
			return false;
		}
		$token = DDD_Helpers::trace_id();
		update_option( self::LOCK_OPTION, array( 'token' => $token, 'expires' => $now + 300 ), false );
		$GLOBALS['ddd_activation_lock_token'] = $token;
		return true;
	}
	private static function release_lock() {
		$lock = get_option( self::LOCK_OPTION, array() );
		$token = isset( $GLOBALS['ddd_activation_lock_token'] ) ? $GLOBALS['ddd_activation_lock_token'] : '';
		if ( is_array( $lock ) && $token && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}
	private static function capabilities() {
		$roles = array( 'administrator' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( array( 'ddd_manage_directory', 'ddd_manage_features', 'ddd_review_reports', 'ddd_run_reconciliation', 'ddd_view_health' ) as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
		$founder_id = DDD_Helpers::founder_id();
		if ( $founder_id ) {
			$user = get_userdata( $founder_id );
			if ( $user ) {
				foreach ( array( 'ddd_manage_directory', 'ddd_manage_features', 'ddd_review_reports', 'ddd_run_reconciliation', 'ddd_view_health' ) as $cap ) {
					$user->add_cap( $cap );
				}
			}
		}
	}
}
