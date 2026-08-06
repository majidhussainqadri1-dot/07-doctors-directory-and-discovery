<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Admin_Trait_4 {
	public function feature_doctor() {
		$this->guard( 'ddd_manage_features' );
		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		check_admin_referer( 'ddd_feature_doctor_' . $doctor_id );
		if ( DDD_Observability::safe_mode() ) {
			wp_die( esc_html__( 'Feature mutations are disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) );
		}
		$result = DDD_Repository::set_feature(
			$doctor_id,
			get_current_user_id(),
			! empty( $_POST['enabled'] ),
			isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '',
			isset( $_POST['reason'] ) ? wp_unslash( $_POST['reason'] ) : '',
			isset( $_POST['start'] ) ? wp_unslash( $_POST['start'] ) : '',
			isset( $_POST['end'] ) ? wp_unslash( $_POST['end'] ) : '',
			isset( $_POST['expected_version'] ) ? absint( $_POST['expected_version'] ) : null
		);
		$this->redirect_result( $result, 'ddd-directory' );
	}
	public function transition_report() {
		$this->guard( 'ddd_review_reports' );
		$report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		check_admin_referer( 'ddd_transition_report_' . $report_id );
		if ( DDD_Observability::safe_mode() ) {
			wp_die( esc_html__( 'Report transitions are disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) );
		}
		$result = DDD_Repository::transition_report( $report_id, get_current_user_id(), isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : '', isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '', isset( $_POST['expected_version'] ) ? absint( $_POST['expected_version'] ) : 0 );
		$this->redirect_result( $result, 'ddd-reports' );
	}
	public function run_reconciliation() {
		$this->guard( 'ddd_run_reconciliation' );
		check_admin_referer( 'ddd_run_reconciliation' );
		if ( DDD_Observability::safe_mode() ) {
			wp_die( esc_html__( 'Reconciliation is disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) );
		}
		$cursor = isset( $_POST['cursor'] ) ? absint( $_POST['cursor'] ) : 0;
		$result = DDD_Repository::reconcile( $cursor, DDD_Repository::RECONCILE_BATCH );
		update_option( 'ddd_reconcile_cursor', $result['next_cursor'], false );
		DDD_Observability::record_health( 'reconciliation', $result['errors'] ? 'degraded' : 'pass', 'processed_' . $result['processed'] );
		$url = add_query_arg( array( 'page' => 'ddd-directory', 'reconciled' => $result['processed'], 'next_cursor' => $result['next_cursor'] ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
	public function rebuild_doctor() {
		$this->guard( 'ddd_manage_directory' );
		$doctor_id = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
		check_admin_referer( 'ddd_rebuild_doctor_' . $doctor_id );
		if ( DDD_Observability::safe_mode() ) {
			wp_die( esc_html__( 'Projection rebuilds are disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) );
		}
		$result = DDD_Repository::rebuild_doctor( $doctor_id, 'administrator', isset( $_POST['expected_version'] ) ? absint( $_POST['expected_version'] ) : null );
		$this->redirect_result( $result, 'ddd-directory' );
	}
	public function set_safe_mode() {
		$this->guard( 'ddd_manage_directory' );
		check_admin_referer( 'ddd_set_safe_mode' );
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		if ( ! $reason ) {
			wp_die( esc_html__( 'A reason is required.', DDD_TEXT_DOMAIN ), '', array( 'response' => 400 ) );
		}
		DDD_Observability::set_safe_mode( ! empty( $_POST['enabled'] ), $reason, get_current_user_id() );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ddd-health', 'safe_mode_updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
	private function redirect_result( $result, $page ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 400;
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $status ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => $page, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}
	private function guard( $capability ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You are not authorized to perform this directory action.', DDD_TEXT_DOMAIN ), '', array( 'response' => 403 ) );
		}
	}
	private function pagination( $page, $total, $per_page, $arg, $admin_page ) {
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $pages <= 1 ) {
			return '';
		}
		$big = 999999999;
		$base = add_query_arg( array( 'page' => $admin_page, $arg => $big ), admin_url( 'admin.php' ) );
		$base = str_replace( (string) $big, '%#%', esc_url( $base ) );
		$links = paginate_links( array( 'base' => $base, 'current' => $page, 'total' => $pages, 'type' => 'list' ) );
		return $links ? '<nav class="ddd-admin-pagination" aria-label="' . esc_attr__( 'Administration pages', DDD_TEXT_DOMAIN ) . '">' . $links . '</nav>' : '';
	}
	public function notice() {
		if ( current_user_can( 'activate_plugins' ) && get_transient( 'ddd_activation_notice' ) ) {
			delete_transient( 'ddd_activation_notice' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Doctors Directory and Discovery 1.0.0 is active. Run System Check and reconciliation before production use.', DDD_TEXT_DOMAIN ) . '</p></div>';
		}
	}
}
