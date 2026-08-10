<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Observability {
	public static function log( $level, $code, $context = array() ) {
		$allowed = array( 'debug','info','warning','error','critical' );
		$level = in_array( $level, $allowed, true ) ? $level : 'info';
		$context = self::redact( is_array( $context ) ? $context : array() );
		$entry = array( 'level' => $level, 'code' => sanitize_key( $code ), 'trace_id' => isset( $context['trace_id'] ) ? sanitize_text_field( $context['trace_id'] ) : DDD_Helpers::trace_id(), 'context' => $context, 'created_at' => current_time( 'mysql', true ) );
		do_action( 'ddd_structured_log', $entry );
		if ( in_array( $level, array( 'error','critical' ), true ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[DDD] ' . wp_json_encode( $entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	public static function record_health( $component, $status, $code, $context = array() ) {
		global $wpdb;
		$table = DDD_Repository::table( 'health_log' );
		if ( ! $table || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
		return false !== $wpdb->insert( $table, array( 'component' => sanitize_key( $component ), 'status' => sanitize_key( $status ), 'code' => sanitize_text_field( $code ), 'context_json' => wp_json_encode( self::redact( $context ) ), 'created_at' => current_time( 'mysql', true ) ), array( '%s','%s','%s','%s','%s' ) );
	}

	private static function redact( $context ) {
		if ( ! is_array( $context ) ) { return array(); }
		$clean = array();
		foreach ( $context as $key => $value ) {
			if ( preg_match( '/email|phone|name|address|detail|evidence|token|secret|password|cookie|authorization|ip/i', (string) $key ) ) { continue; }
			$clean[ sanitize_key( $key ) ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '[structured]';
		}
		return $clean;
	}

	public static function safe_mode() {
		$state = get_option( DDD_SAFE_MODE_OPTION, array() );
		return is_array( $state ) && ! empty( $state['enabled'] );
	}

	public static function set_safe_mode( $enabled, $reason, $actor_id ) {
		$state = array( 'enabled' => (bool) $enabled, 'reason' => sanitize_text_field( $reason ), 'actor_id' => absint( $actor_id ), 'updated_at' => current_time( 'mysql', true ) );
		update_option( DDD_SAFE_MODE_OPTION, $state, false );
		DDD_Repository::audit_admin( $enabled ? 'safe_mode_enable' : 'safe_mode_disable', $actor_id, 'system', 'directory', 'success', array( 'reason_code' => sanitize_key( $reason ) ) );
		self::log( 'warning', $enabled ? 'safe_mode_enabled' : 'safe_mode_disabled', array( 'actor_id' => absint( $actor_id ), 'reason_code' => sanitize_key( $reason ) ) );
		do_action( 'ddd_safe_mode_changed', $state );
		return $state;
	}

	public static function system_check() {
		global $wpdb;
		$checks = array();
		$dependency = DDD_Contracts::dependency_health();
		$checks[] = array( 'component' => 'contracts', 'status' => $dependency['ready'] ? 'pass' : 'fail', 'code' => $dependency['code'], 'message' => $dependency['message'] );
		$required_tables = array( 'projection','taxonomy','saved_refs','reports','report_audit','feature_audit','admin_audit','outbox','inbox','search_metrics','rate_limits','health_log' );
		foreach ( $required_tables as $name ) {
			$table = DDD_Repository::table( $name );
			$exists = $table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
			$checks[] = array( 'component' => 'table:' . $name, 'status' => $exists ? 'pass' : 'fail', 'code' => $exists ? 'table_present' : 'table_missing', 'message' => $exists ? __( 'Schema table is present.', DDD_TEXT_DOMAIN ) : __( 'Required schema table is missing.', DDD_TEXT_DOMAIN ) );
		}
		$schema_ok = version_compare( (string) get_option( 'ddd_db_version', '0' ), DDD_DB_VERSION, '>=' ) && absint( get_option( 'ddd_projection_schema', 0 ) ) === absint( DDD_PROJECTION_SCHEMA );
		$checks[] = array( 'component' => 'schema-version', 'status' => $schema_ok ? 'pass' : 'fail', 'code' => $schema_ok ? 'schema_current' : 'schema_outdated', 'message' => $schema_ok ? __( 'Database and projection schemas are current.', DDD_TEXT_DOMAIN ) : __( 'Database or projection schema requires repair.', DDD_TEXT_DOMAIN ) );
		$legacy_done = (bool) get_option( DDD_Activator::LEGACY_DONE_OPTION, false );
		$checks[] = array( 'component' => 'legacy-migration', 'status' => $legacy_done ? 'pass' : 'degraded', 'code' => $legacy_done ? 'migration_complete' : 'migration_pending', 'message' => $legacy_done ? __( 'Legacy report migration is complete or not required.', DDD_TEXT_DOMAIN ) : __( 'Legacy report migration is continuing in resumable batches.', DDD_TEXT_DOMAIN ) );
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		foreach ( array( 'directory','status' ) as $key ) {
			$ready = ! empty( $map[ $key ] ) && 'publish' === get_post_status( absint( $map[ $key ] ) );
			$checks[] = array( 'component' => 'route:' . $key, 'status' => $ready ? 'pass' : 'fail', 'code' => $ready ? 'route_ready' : 'route_missing', 'message' => $ready ? __( 'Managed route is published.', DDD_TEXT_DOMAIN ) : __( 'Managed route is not published.', DDD_TEXT_DOMAIN ) );
		}
		foreach ( array( 'ddd_reconcile_tick', 'ddd_expire_features_tick', 'ddd_process_outbox_tick' ) as $hook ) {
			$ready = (bool) wp_next_scheduled( $hook );
			$checks[] = array( 'component' => 'cron:' . $hook, 'status' => $ready ? 'pass' : 'degraded', 'code' => $ready ? 'cron_scheduled' : 'cron_missing', 'message' => $ready ? __( 'Scheduled task is registered.', DDD_TEXT_DOMAIN ) : __( 'Scheduled task requires repair.', DDD_TEXT_DOMAIN ) );
		}
		$outbox = DDD_Repository::table( 'outbox' );
		$dead = $outbox ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$outbox} WHERE status='dead'" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stale = $outbox ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$outbox} WHERE status='processing' AND locked_at<%s", gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS ) ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$checks[] = array( 'component' => 'outbox', 'status' => ( $dead || $stale ) ? 'degraded' : 'pass', 'code' => $dead ? 'dead_letters_present' : ( $stale ? 'stale_claims_present' : 'queue_healthy' ), 'message' => sprintf( __( '%1$d dead-letter and %2$d stale processing event(s).', DDD_TEXT_DOMAIN ), $dead, $stale ) );
		$projection = DDD_Repository::table( 'projection' );
		$count = $projection ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$projection}" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$eligible = $projection ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$projection} WHERE eligible=1" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$checks[] = array( 'component' => 'projection', 'status' => 'pass', 'code' => $count ? 'projection_populated' : 'projection_empty_valid', 'message' => sprintf( __( '%1$d projection(s), %2$d currently eligible. An empty directory is valid when no doctor has passed all owner contracts.', DDD_TEXT_DOMAIN ), $count, $eligible ) );
		$checks[] = array( 'component' => 'safe-mode', 'status' => self::safe_mode() ? 'degraded' : 'pass', 'code' => self::safe_mode() ? 'safe_mode_active' : 'safe_mode_off', 'message' => self::safe_mode() ? __( 'Nonessential mutations are intentionally disabled.', DDD_TEXT_DOMAIN ) : __( 'Normal mutation mode is active.', DDD_TEXT_DOMAIN ) );
		$overall = 'pass';
		foreach ( $checks as $check ) { if ( 'fail' === $check['status'] ) { $overall = 'fail'; break; } if ( 'degraded' === $check['status'] ) { $overall = 'degraded'; } }
		return array( 'overall' => $overall, 'summary' => 'pass' === $overall ? __( 'All local source/runtime checks pass.', DDD_TEXT_DOMAIN ) : __( 'One or more checks require attention before staging acceptance.', DDD_TEXT_DOMAIN ), 'checks' => $checks, 'generated_at' => current_time( 'mysql', true ), 'version' => DDD_VERSION );
	}
}

final class DDD_REST {
	const NS = 'doctors-directory-discovery/v1';

	public function hooks() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }

	public function routes() {
		register_rest_route( self::NS, '/doctors', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'search' ), 'permission_callback' => '__return_true', 'args' => $this->search_args() ) );
		register_rest_route( self::NS, '/doctors/(?P<public_id>[a-f0-9-]{36})', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'doctor' ), 'permission_callback' => '__return_true', 'args' => array( 'public_id' => array( 'required' => true, 'validate_callback' => static function ( $value ) { return DDD_Helpers::valid_public_id( $value ); } ) ) ) );
		register_rest_route( self::NS, '/facets/(?P<type>[a-z-]+)', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'facets' ), 'permission_callback' => '__return_true', 'args' => array( 'type' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ), 'term' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'limit' => array( 'sanitize_callback' => 'absint', 'default' => 20 ) ) ) );
		register_rest_route( self::NS, '/status', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'status' ), 'permission_callback' => 'is_user_logged_in' ) );
		register_rest_route( self::NS, '/reports', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'report' ), 'permission_callback' => 'is_user_logged_in', 'args' => array( 'public_id' => array( 'required' => true, 'validate_callback' => static function ( $value ) { return DDD_Helpers::valid_public_id( $value ); } ), 'reason' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ), 'details' => array( 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field', 'validate_callback' => static function ( $value ) { return strlen( trim( $value ) ) >= 10 && strlen( $value ) <= 2000; } ), 'evidence_url' => array( 'sanitize_callback' => 'esc_url_raw' ), 'idempotency_key' => array( 'sanitize_callback' => 'sanitize_text_field' ) ) ) );
		register_rest_route( self::NS, '/saved/(?P<public_id>[a-f0-9-]{36})', array( array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save' ), 'permission_callback' => 'is_user_logged_in' ), array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'unsave' ), 'permission_callback' => 'is_user_logged_in' ) ) );
		register_rest_route( self::NS, '/events', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'event' ), 'permission_callback' => array( $this, 'event_permission' ), 'args' => array( 'event_id' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ), 'event_type' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ), 'payload' => array( 'required' => true, 'validate_callback' => static function ( $value ) { return is_array( $value ) && ! empty( $value['doctor_id'] ); } ) ) ) );
		register_rest_route( self::NS, '/admin/reconcile', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reconcile' ), 'permission_callback' => static function () { return current_user_can( 'ddd_run_reconciliation' ); }, 'args' => array( 'cursor' => array( 'sanitize_callback' => 'absint' ), 'limit' => array( 'sanitize_callback' => 'absint' ) ) ) );
		register_rest_route( self::NS, '/admin/health', array( 'methods' => WP_REST_Server::READABLE, 'callback' => static function () { return rest_ensure_response( DDD_Observability::system_check() ); }, 'permission_callback' => static function () { return current_user_can( 'ddd_view_health' ); } ) );
		register_rest_route( self::NS, '/admin/repair', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'repair' ), 'permission_callback' => static function () { return current_user_can( 'ddd_repair_directory' ); }, 'args' => array( 'execute' => array( 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) ) ) );
	}

	private function search_args() {
		return array( 'q' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'country' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'city' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'specialty' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'language' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'qualification' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'min_experience' => array( 'sanitize_callback' => 'absint' ), 'mode' => array( 'sanitize_callback' => 'sanitize_key' ), 'accepting' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'currency' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'fee_min' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'fee_max' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'featured_only' => array( 'sanitize_callback' => 'rest_sanitize_boolean' ), 'recent_only' => array( 'sanitize_callback' => 'absint' ), 'cursor' => array( 'sanitize_callback' => 'sanitize_text_field' ), 'limit' => array( 'sanitize_callback' => 'absint', 'default' => DDD_Repository::DEFAULT_LIMIT ) );
	}

	public function search( WP_REST_Request $request ) {
		if ( ! DDD_Helpers::rate_limit( 'search', DDD_Helpers::current_ip_hash(), 120, MINUTE_IN_SECONDS ) ) { return DDD_Helpers::safe_error( 'search_rate_limited', __( 'Search rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 ); }
		$result = DDD_Repository::search( $request->get_params() );
		if ( is_wp_error( $result ) ) { return $result; }
		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'public, max-age=60, stale-while-revalidate=120' );
		$response->header( 'Vary', 'Accept-Encoding' );
		$response->header( 'ETag', '"' . hash( 'sha256', wp_json_encode( $result ) . '|' . get_option( 'ddd_cache_version', 1 ) ) . '"' );
		return $response;
	}

	public function doctor( WP_REST_Request $request ) {
		$doctor = DDD_Repository::get_by_public_id( $request['public_id'] );
		if ( ! $doctor ) { return DDD_Helpers::safe_error( 'doctor_not_found', __( 'Doctor not found.', DDD_TEXT_DOMAIN ), 404 ); }
		$response = rest_ensure_response( $doctor ); $response->header( 'Cache-Control', 'public, max-age=120, stale-while-revalidate=300' ); $response->header( 'Vary', 'Accept-Encoding' ); return $response;
	}

	public function facets( WP_REST_Request $request ) {
		if ( ! DDD_Helpers::rate_limit( 'facets', DDD_Helpers::current_ip_hash(), 120, MINUTE_IN_SECONDS ) ) { return DDD_Helpers::safe_error( 'facet_rate_limited', __( 'Autocomplete rate limit exceeded.', DDD_TEXT_DOMAIN ), 429 ); }
		$response = rest_ensure_response( array( 'items' => DDD_Repository::facets( $request['type'], $request['term'], $request['limit'] ) ) ); $response->header( 'Cache-Control', 'public, max-age=300' ); return $response;
	}

	public function status() {
		$claims = DDD_Contracts::verification_claims( get_current_user_id() );
		if ( ! $claims['doctor'] ) { return DDD_Helpers::safe_error( 'not_doctor', __( 'Doctor status is unavailable for this account.', DDD_TEXT_DOMAIN ), 403 ); }
		$response = rest_ensure_response( DDD_Repository::get_live_status( get_current_user_id() ) ); $response->header( 'Cache-Control', 'private, no-store, max-age=0' ); $response->header( 'Pragma', 'no-cache' ); return $response;
	}

	public function report( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) { return DDD_Helpers::safe_error( 'safe_mode', __( 'Reports are temporarily unavailable while Safe Mode is active.', DDD_TEXT_DOMAIN ), 503 ); }
		$result = DDD_Repository::report( DDD_Repository::resolve_doctor_id( $request['public_id'] ), get_current_user_id(), $request['reason'], $request['details'], $request['evidence_url'], ( $request['idempotency_key'] ? $request['idempotency_key'] : DDD_Helpers::idempotency_key( $request ) ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( array( 'report_id' => $result, 'status' => 'open' ), 201 );
	}

	public function save( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) { return DDD_Helpers::safe_error( 'safe_mode', __( 'Saves are temporarily unavailable while Safe Mode is active.', DDD_TEXT_DOMAIN ), 503 ); }
		$result = DDD_Repository::save_reference( get_current_user_id(), DDD_Repository::resolve_doctor_id( $request['public_id'] ), true ); return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'saved' => true ) );
	}

	public function unsave( WP_REST_Request $request ) {
		$result = DDD_Repository::save_reference( get_current_user_id(), DDD_Repository::resolve_doctor_id( $request['public_id'] ), false ); return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'saved' => false ) );
	}

	public function event_permission( WP_REST_Request $request ) {
		$secret = (string) get_option( 'ddd_event_shared_secret', '' );
		if ( strlen( $secret ) < 32 ) { return false; }
		$timestamp = (string) $request->get_header( 'X-DDD-Timestamp' ); $signature = strtolower( (string) $request->get_header( 'X-DDD-Signature' ) );
		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > 300 || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) { return false; }
		return hash_equals( hash_hmac( 'sha256', $timestamp . '.' . $request->get_body(), $secret ), $signature );
	}

	public function event( WP_REST_Request $request ) {
		$result = DDD_Repository::consume_event( $request['event_id'], $request['event_type'], (array) $request['payload'] ); return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'accepted' => true ) );
	}

	public function reconcile( WP_REST_Request $request ) {
		if ( DDD_Observability::safe_mode() ) { return DDD_Helpers::safe_error( 'safe_mode', __( 'Reconciliation is disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), 503 ); }
		return rest_ensure_response( DDD_Repository::reconcile( absint( $request['cursor'] ), absint( $request['limit'] ? $request['limit'] : DDD_Repository::RECONCILE_BATCH ) ) );
	}

	public function repair( WP_REST_Request $request ) {
		$result = DDD_Activator::repair( rest_sanitize_boolean( $request['execute'] ) );
		DDD_Repository::audit_admin( 'repair', get_current_user_id(), 'system', 'directory', is_wp_error( $result ) ? 'failure' : 'success', array( 'execute' => rest_sanitize_boolean( $request['execute'] ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}

final class DDD_Plugin {
	public function run() {
		( new DDD_Directory() )->hooks(); ( new DDD_Profile() )->hooks(); ( new DDD_Admin() )->hooks(); ( new DDD_Privacy() )->hooks(); ( new DDD_SEO() )->hooks(); ( new DDD_REST() )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) ); add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) ); add_action( 'template_redirect', array( $this, 'private_headers' ), 1 );
		add_action( 'ddd_reconcile_tick', array( $this, 'cron_reconcile' ) ); add_action( 'ddd_expire_features_tick', array( $this, 'cron_expire_features' ) ); add_action( 'ddd_process_outbox_tick', array( $this, 'process_outbox' ) ); add_action( 'ddd_continue_legacy_migration', array( 'DDD_Activator', 'continue_legacy_migration' ) );
		add_action( 'shutdown', array( $this, 'process_outbox' ) ); add_action( 'ddd_native_event', array( $this, 'native_event' ), 10, 3 ); add_action( 'deleted_user', array( $this, 'deleted_user' ) ); add_action( 'profile_update', array( $this, 'profile_update' ), 20, 2 ); add_action( 'set_user_role', array( $this, 'role_update' ), 20, 3 ); add_filter( 'ddd_public_index_document_v1', array( $this, 'index_document' ), 10, 2 );
	}

	public function assets() {
		global $post; $map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		$needed = $post instanceof WP_Post && ( in_array( absint( $post->ID ), array_map( 'absint', $map ), true ) || has_shortcode( $post->post_content, 'ddd_doctors_directory' ) || has_shortcode( $post->post_content, 'ddd_directory_status' ) || has_shortcode( $post->post_content, 'sdd_doctors_directory' ) || has_shortcode( $post->post_content, 'sdd_profile_settings' ) );
		if ( ! $needed ) { return; }
		wp_enqueue_style( 'ddd-directory', DDD_URL . 'assets/css/directory.css', array(), DDD_VERSION ); wp_enqueue_script( 'ddd-directory', DDD_URL . 'assets/js/directory.js', array(), DDD_VERSION, true );
		wp_localize_script( 'ddd-directory', 'dddDirectory', array( 'restUrl' => esc_url_raw( rest_url( DDD_REST::NS . '/doctors' ) ), 'facetsUrl' => esc_url_raw( rest_url( DDD_REST::NS . '/facets/' ) ), 'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '', 'messages' => array( 'loading' => __( 'Loading doctors…', DDD_TEXT_DOMAIN ), 'error' => __( 'Doctors could not be loaded. Retry or use the full search form.', DDD_TEXT_DOMAIN ) ) ) );
	}

	public function admin_assets( $hook ) { if ( false !== strpos( $hook, 'ddd-' ) ) { wp_enqueue_style( 'ddd-admin', DDD_URL . 'assets/css/admin.css', array(), DDD_VERSION ); } }

	public function private_headers() {
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		if ( ! empty( $map['status'] ) && is_page( absint( $map['status'] ) ) ) { nocache_headers(); header( 'Cache-Control: private, no-store, max-age=0', true ); header( 'X-Robots-Tag: noindex, noarchive, nosnippet', true ); header( 'Referrer-Policy: strict-origin-when-cross-origin', true ); header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()', true ); }
	}

	public function cron_reconcile() {
		if ( DDD_Observability::safe_mode() ) { return; }
		$cursor = absint( get_option( 'ddd_reconcile_cursor', 0 ) ); $result = DDD_Repository::reconcile( $cursor, DDD_Repository::RECONCILE_BATCH ); update_option( 'ddd_reconcile_cursor', $result['next_cursor'], false ); DDD_Observability::record_health( 'reconciliation', $result['errors'] ? 'degraded' : 'pass', 'cron_batch', array( 'processed' => $result['processed'], 'errors' => count( $result['errors'] ) ) );
	}
	public function cron_expire_features() { if ( ! DDD_Observability::safe_mode() ) { DDD_Repository::expire_features(); } }
	public function process_outbox() { DDD_Repository::process_outbox( 20 ); }
	public function native_event( $event_id, $event_type, $payload ) { DDD_Repository::consume_event( $event_id, $event_type, $payload ); }
	public function deleted_user( $user_id ) { DDD_Repository::delete_doctor_projection( absint( $user_id ), 'user_deleted' ); }
	public function profile_update( $user_id, $old_user_data ) {
		$claims = DDD_Contracts::verification_claims( $user_id );
		$status = DDD_Repository::get_status( $user_id );
		if ( $claims['doctor'] || DDD_Helpers::is_founder( $user_id ) || ! empty( $status['version'] ) ) {
			$result = DDD_Repository::rebuild_doctor( $user_id, 'profile_update' );
			if ( is_wp_error( $result ) ) { DDD_Observability::record_health( 'profile_projection', 'degraded', $result->get_error_code(), array( 'doctor_ref' => DDD_Helpers::hash_identifier( (string) $user_id ) ) ); }
		}
	}
	public function role_update( $user_id, $role, $old_roles ) {
		$claims = DDD_Contracts::verification_claims( $user_id ); $status = DDD_Repository::get_status( $user_id );
		if ( $claims['doctor'] || DDD_Helpers::is_founder( $user_id ) || ! empty( $status['version'] ) ) {
			$result = DDD_Repository::rebuild_doctor( $user_id, 'role_update' );
			if ( is_wp_error( $result ) ) { DDD_Observability::record_health( 'role_projection', 'degraded', $result->get_error_code(), array( 'doctor_ref' => DDD_Helpers::hash_identifier( (string) $user_id ) ) ); }
		}
	}

	public function index_document( $document, $doctor_id ) {
		$status = DDD_Repository::get_status( $doctor_id ); if ( empty( $status['eligible'] ) ) { return array(); }
		$doctor = DDD_Repository::get_by_public_id( $status['public_id'] ); if ( ! $doctor ) { return array(); }
		return array( 'type' => 'doctor', 'public_id' => $doctor['public_id'], 'title' => $doctor['display_name'], 'subtitle' => $doctor['professional_title'], 'url' => $doctor['profile_url'] ? $doctor['profile_url'] : $doctor['public_directory_url'], 'facets' => array( 'specialty' => $doctor['specialty'], 'country' => $doctor['country'], 'city' => $doctor['city'], 'languages' => $doctor['languages'], 'modes' => $doctor['consultation_modes'] ), 'updated_at' => $status['updated_at'], 'schema' => DDD_PROJECTION_SCHEMA );
	}
}

if ( ! class_exists( 'SDD_Plugin' ) ) { class_alias( 'DDD_Plugin', 'SDD_Plugin' ); }
