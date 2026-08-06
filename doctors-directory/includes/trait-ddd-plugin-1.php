<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Plugin_Trait_1 {
	public function run() {
		( new DDD_Directory() )->hooks();
		( new DDD_Profile() )->hooks();
		( new DDD_Admin() )->hooks();
		( new DDD_Privacy() )->hooks();
		( new DDD_SEO() )->hooks();
		( new DDD_REST() )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'template_redirect', array( $this, 'private_headers' ), 1 );
		add_action( 'ddd_reconcile_tick', array( $this, 'cron_reconcile' ) );
		add_action( 'ddd_expire_features_tick', array( $this, 'cron_expire_features' ) );
		add_action( 'shutdown', array( $this, 'process_outbox' ) );
		add_action( 'ddd_native_event', array( $this, 'native_event' ), 10, 3 );
		add_action( 'deleted_user', array( $this, 'deleted_user' ) );
		add_action( 'profile_update', array( $this, 'profile_update' ), 20, 2 );
		add_action( 'set_user_role', array( $this, 'role_update' ), 20, 3 );
		add_filter( 'ddd_public_index_document_v1', array( $this, 'index_document' ), 10, 2 );
	}
	public function assets() {
		global $post;
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		$needed = $post instanceof WP_Post && ( in_array( absint( $post->ID ), array_map( 'absint', $map ), true ) || has_shortcode( $post->post_content, 'ddd_doctors_directory' ) || has_shortcode( $post->post_content, 'ddd_directory_status' ) || has_shortcode( $post->post_content, 'sdd_doctors_directory' ) || has_shortcode( $post->post_content, 'sdd_profile_settings' ) );
		if ( ! $needed ) {
			return;
		}
		wp_enqueue_style( 'ddd-directory', DDD_URL . 'assets/css/directory.css', array(), DDD_VERSION );
		wp_enqueue_script( 'ddd-directory', DDD_URL . 'assets/js/directory.js', array(), DDD_VERSION, true );
		wp_localize_script( 'ddd-directory', 'dddDirectory', array( 'restUrl' => esc_url_raw( rest_url( DDD_REST::NS . '/doctors' ) ), 'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '', 'messages' => array( 'loading' => __( 'Loading doctors…', DDD_TEXT_DOMAIN ), 'error' => __( 'Doctors could not be loaded. Retry or use the full search form.', DDD_TEXT_DOMAIN ) ) ) );
	}
	public function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'ddd-' ) ) {
			wp_enqueue_style( 'ddd-admin', DDD_URL . 'assets/css/admin.css', array(), DDD_VERSION );
		}
	}
	public function private_headers() {
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		if ( ! empty( $map['status'] ) && is_page( absint( $map['status'] ) ) ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, noarchive, nosnippet', true );
			header( 'Referrer-Policy: strict-origin-when-cross-origin', true );
		}
	}
	public function cron_reconcile() {
		if ( DDD_Observability::safe_mode() ) {
			return;
		}
		$cursor = absint( get_option( 'ddd_reconcile_cursor', 0 ) );
		$result = DDD_Repository::reconcile( $cursor, DDD_Repository::RECONCILE_BATCH );
		update_option( 'ddd_reconcile_cursor', $result['next_cursor'], false );
		DDD_Observability::record_health( 'reconciliation', $result['errors'] ? 'degraded' : 'pass', 'cron_batch', array( 'processed' => $result['processed'], 'errors' => count( $result['errors'] ) ) );
	}
	public function cron_expire_features() {
		if ( ! DDD_Observability::safe_mode() ) {
			DDD_Repository::expire_features();
		}
	}
	public function process_outbox() {
		if ( ! DDD_Observability::safe_mode() ) {
			DDD_Repository::process_outbox( 20 );
		}
	}
	public function native_event( $event_id, $event_type, $payload ) {
		DDD_Repository::consume_event( $event_id, $event_type, $payload );
	}
	public function deleted_user( $user_id ) {
		DDD_Repository::rebuild_doctor( absint( $user_id ), 'user_deleted' );
	}
	public function profile_update( $user_id, $old_user_data ) {
		$claims = DDD_Contracts::verification_claims( $user_id );
		if ( $claims['doctor'] || DDD_Helpers::is_founder( $user_id ) ) {
			DDD_Repository::rebuild_doctor( $user_id, 'profile_update' );
		}
	}
	public function role_update( $user_id, $role, $old_roles ) {
		DDD_Repository::rebuild_doctor( $user_id, 'role_update' );
	}
	public function index_document( $document, $doctor_id ) {
		$status = DDD_Repository::get_status( $doctor_id );
		if ( empty( $status['eligible'] ) ) {
			return array();
		}
		$doctor = DDD_Repository::get_by_public_id( $status['public_id'] );
		if ( ! $doctor ) {
			return array();
		}
		return array(
			'type'        => 'doctor',
			'public_id'   => $doctor['public_id'],
			'title'       => $doctor['display_name'],
			'subtitle'    => $doctor['professional_title'],
			'url'         => $doctor['profile_url'] ? $doctor['profile_url'] : $doctor['public_directory_url'],
			'facets'      => array( 'specialty' => $doctor['specialty'], 'country' => $doctor['country'], 'city' => $doctor['city'], 'languages' => $doctor['languages'], 'modes' => $doctor['consultation_modes'] ),
			'updated_at'  => $status['updated_at'],
			'schema'      => 1,
		);
	}
}
