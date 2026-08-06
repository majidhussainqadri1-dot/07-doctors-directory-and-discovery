<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Observability_Trait_1 {
	public static function log( $level, $code, $context = array() ) {
		$allowed = array( 'debug','info','warning','error','critical' );
		$level = in_array( $level, $allowed, true ) ? $level : 'info';
		$context = is_array( $context ) ? $context : array();
		foreach ( array( 'email','phone','details','evidence','password','token','secret','cookie','authorization' ) as $sensitive ) {
			unset( $context[ $sensitive ] );
		}
		$entry = array(
			'level'     => $level,
			'code'      => sanitize_key( $code ),
			'trace_id'  => isset( $context['trace_id'] ) ? sanitize_text_field( $context['trace_id'] ) : DDD_Helpers::trace_id(),
			'context'   => $context,
			'created_at'=> current_time( 'mysql', true ),
		);
		do_action( 'ddd_structured_log', $entry );
		if ( in_array( $level, array( 'error','critical' ), true ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[DDD] ' . wp_json_encode( $entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
	public static function record_health( $component, $status, $code, $context = array() ) {
		global $wpdb;
		$table = DDD_Repository::table( 'health_log' );
		if ( ! $table ) {
			return false;
		}
		return false !== $wpdb->insert(
			$table,
			array(
				'component'   => sanitize_key( $component ),
				'status'      => sanitize_key( $status ),
				'code'        => sanitize_text_field( $code ),
				'context_json'=> wp_json_encode( self::redact( $context ) ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s','%s','%s','%s','%s' )
		);
	}
	private static function redact( $context ) {
		if ( ! is_array( $context ) ) {
			return array();
		}
		$clean = array();
		foreach ( $context as $key => $value ) {
			if ( preg_match( '/email|phone|name|address|detail|evidence|token|secret|password|cookie|authorization/i', (string) $key ) ) {
				continue;
			}
			$clean[ sanitize_key( $key ) ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '[structured]';
		}
		return $clean;
	}
	public static function safe_mode() {
		$state = get_option( DDD_SAFE_MODE_OPTION, array() );
		return is_array( $state ) && ! empty( $state['enabled'] );
	}
	public static function set_safe_mode( $enabled, $reason, $actor_id ) {
		$state = array(
			'enabled'    => (bool) $enabled,
			'reason'     => sanitize_text_field( $reason ),
			'actor_id'   => absint( $actor_id ),
			'updated_at' => current_time( 'mysql', true ),
		);
		update_option( DDD_SAFE_MODE_OPTION, $state, false );
		self::log( 'warning', $enabled ? 'safe_mode_enabled' : 'safe_mode_disabled', array( 'actor_id' => absint( $actor_id ), 'reason_code' => sanitize_key( $reason ) ) );
		do_action( 'ddd_safe_mode_changed', $state );
		return $state;
	}
	public static function system_check() {
		global $wpdb;
		$checks = array();
		$dependency = DDD_Contracts::dependency_health();
		$checks[] = array( 'component' => 'contracts', 'status' => $dependency['ready'] ? 'pass' : 'degraded', 'code' => $dependency['code'], 'message' => $dependency['message'] );
		$required_tables = array( 'projection','taxonomy','saved_refs','reports','report_audit','outbox','inbox','health_log' );
		foreach ( $required_tables as $name ) {
			$table = DDD_Repository::table( $name );
			$exists = $table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
			$checks[] = array( 'component' => 'table:' . $name, 'status' => $exists ? 'pass' : 'fail', 'code' => $exists ? 'table_present' : 'table_missing', 'message' => $exists ? __( 'Schema table is present.', DDD_TEXT_DOMAIN ) : __( 'Required schema table is missing.', DDD_TEXT_DOMAIN ) );
		}
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		foreach ( array( 'directory','status' ) as $key ) {
			$ready = ! empty( $map[ $key ] ) && 'publish' === get_post_status( absint( $map[ $key ] ) );
			$checks[] = array( 'component' => 'route:' . $key, 'status' => $ready ? 'pass' : 'degraded', 'code' => $ready ? 'route_ready' : 'route_missing', 'message' => $ready ? __( 'Managed route is published.', DDD_TEXT_DOMAIN ) : __( 'Managed route is not published.', DDD_TEXT_DOMAIN ) );
		}
		$cron = wp_next_scheduled( 'ddd_reconcile_tick' );
		$checks[] = array( 'component' => 'cron:reconciliation', 'status' => $cron ? 'pass' : 'degraded', 'code' => $cron ? 'cron_scheduled' : 'cron_missing', 'message' => $cron ? __( 'Reconciliation cron is scheduled.', DDD_TEXT_DOMAIN ) : __( 'Reconciliation cron is not scheduled.', DDD_TEXT_DOMAIN ) );
		$outbox = DDD_Repository::table( 'outbox' );
		$dead = $outbox ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$outbox} WHERE status='dead'" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$checks[] = array( 'component' => 'outbox', 'status' => $dead ? 'degraded' : 'pass', 'code' => $dead ? 'dead_letters_present' : 'queue_healthy', 'message' => $dead ? sprintf( __( '%d dead-letter event(s) require review.', DDD_TEXT_DOMAIN ), $dead ) : __( 'No dead-letter events are pending.', DDD_TEXT_DOMAIN ) );
		$projection = DDD_Repository::table( 'projection' );
		$count = $projection ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$projection}" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$eligible = $projection ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$projection} WHERE eligible=1" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$checks[] = array( 'component' => 'projection', 'status' => $count ? 'pass' : 'degraded', 'code' => $count ? 'projection_populated' : 'projection_empty', 'message' => sprintf( __( '%1$d projection(s), %2$d currently eligible.', DDD_TEXT_DOMAIN ), $count, $eligible ) );
		$checks[] = array( 'component' => 'safe-mode', 'status' => self::safe_mode() ? 'degraded' : 'pass', 'code' => self::safe_mode() ? 'safe_mode_active' : 'safe_mode_off', 'message' => self::safe_mode() ? __( 'High-risk mutations are intentionally disabled.', DDD_TEXT_DOMAIN ) : __( 'Normal mutation mode is active.', DDD_TEXT_DOMAIN ) );
		$overall = 'pass';
		foreach ( $checks as $check ) {
			if ( 'fail' === $check['status'] ) { $overall = 'fail'; break; }
			if ( 'degraded' === $check['status'] ) { $overall = 'degraded'; }
		}
		return array( 'overall' => $overall, 'summary' => $overall === 'pass' ? __( 'All local system checks pass.', DDD_TEXT_DOMAIN ) : __( 'One or more checks require attention before production acceptance.', DDD_TEXT_DOMAIN ), 'checks' => $checks );
	}
}
