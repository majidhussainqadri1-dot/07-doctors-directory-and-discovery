<?php
defined( 'ABSPATH' ) || exit;

final class DDD_Admin {
	const PER_PAGE = 50;

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_ddd_feature_doctor', array( $this, 'feature_doctor' ) );
		add_action( 'admin_post_ddd_transition_report', array( $this, 'transition_report' ) );
		add_action( 'admin_post_ddd_run_reconciliation', array( $this, 'run_reconciliation' ) );
		add_action( 'admin_post_ddd_set_safe_mode', array( $this, 'set_safe_mode' ) );
		add_action( 'admin_post_ddd_rebuild_doctor', array( $this, 'rebuild_doctor' ) );
		add_action( 'admin_post_ddd_taxonomy_upsert', array( $this, 'taxonomy_upsert' ) );
		add_action( 'admin_post_ddd_repair_directory', array( $this, 'repair_directory' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function menu() {
		add_menu_page( __( 'Doctors Directory', DDD_TEXT_DOMAIN ), __( 'Doctors Directory', DDD_TEXT_DOMAIN ), 'ddd_manage_directory', 'ddd-directory', array( $this, 'directory' ), 'dashicons-id-alt', 28 );
		add_submenu_page( 'ddd-directory', __( 'Directory Records', DDD_TEXT_DOMAIN ), __( 'Directory Records', DDD_TEXT_DOMAIN ), 'ddd_manage_directory', 'ddd-directory', array( $this, 'directory' ) );
		add_submenu_page( 'ddd-directory', __( 'Taxonomy and Aliases', DDD_TEXT_DOMAIN ), __( 'Taxonomy', DDD_TEXT_DOMAIN ), 'ddd_manage_taxonomy', 'ddd-taxonomy', array( $this, 'taxonomy' ) );
		add_submenu_page( 'ddd-directory', __( 'Listing Reports', DDD_TEXT_DOMAIN ), __( 'Listing Reports', DDD_TEXT_DOMAIN ), 'ddd_review_reports', 'ddd-reports', array( $this, 'reports' ) );
		add_submenu_page( 'ddd-directory', __( 'System Check', DDD_TEXT_DOMAIN ), __( 'System Check', DDD_TEXT_DOMAIN ), 'ddd_view_health', 'ddd-health', array( $this, 'health' ) );
	}

	public function directory() {
		$this->guard( 'ddd_manage_directory' );
		global $wpdb;
		$table = DDD_Repository::table( 'projection' );
		$page = isset( $_GET['ddd_page'] ) ? max( 1, absint( $_GET['ddd_page'] ) ) : 1;
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY eligible DESC, featured DESC, updated_at DESC, doctor_id ASC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap ddd-admin"><h1><?php esc_html_e( 'Doctors Directory Records', DDD_TEXT_DOMAIN ); ?></h1><p><?php esc_html_e( 'File 07 owns only the public projection, search, feature state and moderation records. Identity, verification, profiles and clinics remain with their canonical modules.', DDD_TEXT_DOMAIN ); ?></p>
		<div class="ddd-admin-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_run_reconciliation"><?php wp_nonce_field( 'ddd_run_reconciliation' ); ?><input type="hidden" name="cursor" value="0"><button class="button button-primary" type="submit"><?php esc_html_e( 'Run bounded reconciliation', DDD_TEXT_DOMAIN ); ?></button></form></div>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Doctor', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Eligibility', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Projection', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Feature state', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Actions', DDD_TEXT_DOMAIN ); ?></th></tr></thead><tbody>
		<?php if ( $rows ) : foreach ( $rows as $row ) : $reasons = (array) json_decode( $row['reasons_json'], true ); ?>
		<tr><td><strong><?php echo esc_html( $row['display_name'] ? $row['display_name'] : sprintf( __( 'Doctor #%d', DDD_TEXT_DOMAIN ), $row['doctor_id'] ) ); ?></strong><br><code><?php echo esc_html( $row['public_id'] ); ?></code><br><small><?php echo esc_html( trim( $row['city'] . ', ' . $row['country'], ', ' ) ); ?></small></td>
		<td><strong><?php echo esc_html( $row['eligible'] ? __( 'Eligible', DDD_TEXT_DOMAIN ) : __( 'Not eligible', DDD_TEXT_DOMAIN ) ); ?></strong><br><small><?php echo esc_html( $reasons ? implode( ', ', $reasons ) : __( 'No blockers', DDD_TEXT_DOMAIN ) ); ?></small></td>
		<td><?php echo esc_html( sprintf( __( 'Version %1$d · %2$d%% complete', DDD_TEXT_DOMAIN ), $row['version'], $row['completeness'] ) ); ?><br><small><?php echo esc_html( $row['updated_at'] ); ?> UTC</small></td>
		<td><?php echo esc_html( $row['featured'] ? ( $row['feature_label'] ? $row['feature_label'] : __( 'Featured', DDD_TEXT_DOMAIN ) ) : __( 'Not featured', DDD_TEXT_DOMAIN ) ); ?><br><small><?php echo esc_html( $row['feature_end'] ? sprintf( __( 'Expires %s UTC', DDD_TEXT_DOMAIN ), $row['feature_end'] ) : '' ); ?></small></td>
		<td><form class="ddd-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_rebuild_doctor"><input type="hidden" name="doctor_id" value="<?php echo absint( $row['doctor_id'] ); ?>"><input type="hidden" name="expected_version" value="<?php echo absint( $row['version'] ); ?>"><?php wp_nonce_field( 'ddd_rebuild_doctor_' . $row['doctor_id'] ); ?><button class="button" type="submit"><?php esc_html_e( 'Reconcile now', DDD_TEXT_DOMAIN ); ?></button></form>
		<?php if ( current_user_can( 'ddd_manage_features' ) && $row['eligible'] ) : ?><details><summary><?php esc_html_e( 'Feature controls', DDD_TEXT_DOMAIN ); ?></summary><form class="ddd-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_feature_doctor"><input type="hidden" name="doctor_id" value="<?php echo absint( $row['doctor_id'] ); ?>"><input type="hidden" name="expected_version" value="<?php echo absint( $row['version'] ); ?>"><?php wp_nonce_field( 'ddd_feature_doctor_' . $row['doctor_id'] ); ?><label><input type="checkbox" name="enabled" value="1" <?php checked( $row['featured'], 1 ); ?>> <?php esc_html_e( 'Featured', DDD_TEXT_DOMAIN ); ?></label><label><?php esc_html_e( 'Public label', DDD_TEXT_DOMAIN ); ?><input name="label" maxlength="120" value="<?php echo esc_attr( $row['feature_label'] ); ?>"></label><label><?php esc_html_e( 'Start UTC', DDD_TEXT_DOMAIN ); ?><input type="datetime-local" name="start" value="<?php echo esc_attr( $row['feature_start'] ? gmdate( 'Y-m-d\TH:i', strtotime( $row['feature_start'] . ' UTC' ) ) : '' ); ?>"></label><label><?php esc_html_e( 'Expiry UTC', DDD_TEXT_DOMAIN ); ?><input type="datetime-local" name="end" value="<?php echo esc_attr( $row['feature_end'] ? gmdate( 'Y-m-d\TH:i', strtotime( $row['feature_end'] . ' UTC' ) ) : '' ); ?>"></label><label><?php esc_html_e( 'Required reason', DDD_TEXT_DOMAIN ); ?><textarea name="reason" maxlength="1000" required><?php echo esc_textarea( $row['feature_reason'] ); ?></textarea></label><button class="button button-primary" type="submit"><?php esc_html_e( 'Save feature state', DDD_TEXT_DOMAIN ); ?></button></form></details><?php endif; ?></td></tr>
		<?php endforeach; else : ?><tr><td colspan="5"><?php esc_html_e( 'No directory projections exist yet. Run reconciliation.', DDD_TEXT_DOMAIN ); ?></td></tr><?php endif; ?>
		</tbody></table><?php echo $this->pagination( $page, $total, self::PER_PAGE, 'ddd_page', 'ddd-directory' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php
	}

	public function reports() {
		$this->guard( 'ddd_review_reports' );
		global $wpdb;
		$table = DDD_Repository::table( 'reports' );
		$page = isset( $_GET['ddd_report_page'] ) ? max( 1, absint( $_GET['ddd_report_page'] ) ) : 1;
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY updated_at DESC,id DESC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap ddd-admin"><h1><?php esc_html_e( 'Listing Reports', DDD_TEXT_DOMAIN ); ?></h1><p><?php esc_html_e( 'A report is an allegation, not a finding. Every transition requires a reason and an atomic audit record.', DDD_TEXT_DOMAIN ); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Listing', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Concern', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Status', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Review', DDD_TEXT_DOMAIN ); ?></th></tr></thead><tbody>
		<?php if ( $rows ) : foreach ( $rows as $row ) : ?>
		<tr><td><?php echo esc_html( $row['doctor_id'] ? get_the_author_meta( 'display_name', $row['doctor_id'] ) : __( 'Removed or anonymized doctor', DDD_TEXT_DOMAIN ) ); ?><br><code><?php echo esc_html( $row['doctor_public_id'] ); ?></code><br><small><?php echo esc_html( sprintf( __( 'Reporter: %s', DDD_TEXT_DOMAIN ), $row['reporter_id'] ? get_the_author_meta( 'display_name', $row['reporter_id'] ) : __( 'anonymized', DDD_TEXT_DOMAIN ) ) ); ?></small></td><td><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $row['reason'] ) ) ); ?></strong><br><?php echo esc_html( $row['details'] ); ?><?php if ( $row['evidence_url'] ) : ?><br><a href="<?php echo esc_url( $row['evidence_url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php esc_html_e( 'Review submitted evidence', DDD_TEXT_DOMAIN ); ?></a><?php endif; ?><br><small><?php echo esc_html( $row['created_at'] ); ?> UTC</small></td><td><strong><?php echo esc_html( ucfirst( $row['status'] ) ); ?></strong><br><small><?php echo esc_html( sprintf( __( 'Version %d', DDD_TEXT_DOMAIN ), $row['version'] ) ); ?></small></td><td><form class="ddd-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_transition_report"><input type="hidden" name="report_id" value="<?php echo absint( $row['id'] ); ?>"><input type="hidden" name="expected_version" value="<?php echo absint( $row['version'] ); ?>"><?php wp_nonce_field( 'ddd_transition_report_' . $row['id'] ); ?><label><?php esc_html_e( 'Status', DDD_TEXT_DOMAIN ); ?><select name="status"><?php foreach ( array( 'open','reviewing','escalated','resolved','dismissed' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $row['status'], $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option><?php endforeach; ?></select></label><label><?php esc_html_e( 'Required review note', DDD_TEXT_DOMAIN ); ?><textarea name="note" maxlength="1000" required><?php echo esc_textarea( $row['review_note'] ); ?></textarea></label><button class="button button-primary" type="submit"><?php esc_html_e( 'Save reviewed state', DDD_TEXT_DOMAIN ); ?></button></form></td></tr>
		<?php endforeach; else : ?><tr><td colspan="4"><?php esc_html_e( 'No reports.', DDD_TEXT_DOMAIN ); ?></td></tr><?php endif; ?></tbody></table><?php echo $this->pagination( $page, $total, self::PER_PAGE, 'ddd_report_page', 'ddd-reports' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php
	}

	public function health() {
		$this->guard( 'ddd_view_health' );
		$health = DDD_Observability::system_check();
		$safe_mode = DDD_Observability::safe_mode();
		?>
		<div class="wrap ddd-admin"><h1><?php esc_html_e( 'Doctors Directory System Check', DDD_TEXT_DOMAIN ); ?></h1><p><?php esc_html_e( 'Read-first diagnostics show contracts, schema, routes, cron, outbox, cache and reconciliation state without exposing private evidence.', DDD_TEXT_DOMAIN ); ?></p>
		<div class="notice <?php echo esc_attr( $health['overall'] === 'pass' ? 'notice-success' : ( $health['overall'] === 'degraded' ? 'notice-warning' : 'notice-error' ) ); ?>"><p><strong><?php echo esc_html( strtoupper( $health['overall'] ) ); ?></strong> — <?php echo esc_html( $health['summary'] ); ?></p></div>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Component', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Status', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Evidence', DDD_TEXT_DOMAIN ); ?></th></tr></thead><tbody><?php foreach ( $health['checks'] as $check ) : ?><tr><td><?php echo esc_html( $check['component'] ); ?></td><td><strong><?php echo esc_html( strtoupper( $check['status'] ) ); ?></strong></td><td><code><?php echo esc_html( $check['code'] ); ?></code> — <?php echo esc_html( $check['message'] ); ?></td></tr><?php endforeach; ?></tbody></table>
		<h2><?php esc_html_e( 'Safe Mode', DDD_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Safe Mode disables high-risk mutations and background projection changes while preserving safe public reading of already eligible cached data where possible.', DDD_TEXT_DOMAIN ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_set_safe_mode"><?php wp_nonce_field( 'ddd_set_safe_mode' ); ?><label><input type="checkbox" name="enabled" value="1" <?php checked( $safe_mode ); ?>> <?php esc_html_e( 'Enable File 07 Safe Mode', DDD_TEXT_DOMAIN ); ?></label><label><?php esc_html_e( 'Reason', DDD_TEXT_DOMAIN ); ?><input name="reason" maxlength="250" required></label><button class="button button-primary" type="submit"><?php esc_html_e( 'Update Safe Mode', DDD_TEXT_DOMAIN ); ?></button></form>
		<?php if ( current_user_can( 'ddd_repair_directory' ) ) : ?><h2><?php esc_html_e( 'Reversible Repair', DDD_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Dry-run shows the bounded repair plan. Execute recreates owned schema, routes and schedules without deleting companion or directory records.', DDD_TEXT_DOMAIN ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_repair_directory"><?php wp_nonce_field( 'ddd_repair_directory' ); ?><button class="button" name="execute" value="0" type="submit"><?php esc_html_e( 'Repair dry-run', DDD_TEXT_DOMAIN ); ?></button> <button class="button button-primary" name="execute" value="1" type="submit"><?php esc_html_e( 'Execute bounded repair', DDD_TEXT_DOMAIN ); ?></button></form><?php endif; ?></div>
		<?php
	}


	public function taxonomy() {
		$this->guard( 'ddd_manage_taxonomy' );
		global $wpdb;
		$table = DDD_Repository::table( 'taxonomy' );
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY type,canonical_label LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap ddd-admin"><h1><?php esc_html_e( 'Directory Taxonomy and Aliases', DDD_TEXT_DOMAIN ); ?></h1><p><?php esc_html_e( 'Canonical labels and aliases improve Urdu/English discovery without altering profile or verification truth.', DDD_TEXT_DOMAIN ); ?></p>
		<form class="ddd-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_taxonomy_upsert"><?php wp_nonce_field( 'ddd_taxonomy_upsert' ); ?><label><?php esc_html_e( 'Type', DDD_TEXT_DOMAIN ); ?><select name="type"><?php foreach ( array( 'specialty','country','city','language','qualification' ) as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( ucfirst( $type ) ); ?></option><?php endforeach; ?></select></label><label><?php esc_html_e( 'Canonical key', DDD_TEXT_DOMAIN ); ?><input name="key" required></label><label><?php esc_html_e( 'Public label', DDD_TEXT_DOMAIN ); ?><input name="label" required></label><label><?php esc_html_e( 'Aliases (comma separated)', DDD_TEXT_DOMAIN ); ?><textarea name="aliases"></textarea></label><label><?php esc_html_e( 'Status', DDD_TEXT_DOMAIN ); ?><select name="status"><option value="active"><?php esc_html_e( 'Active', DDD_TEXT_DOMAIN ); ?></option><option value="inactive"><?php esc_html_e( 'Inactive', DDD_TEXT_DOMAIN ); ?></option></select></label><button class="button button-primary" type="submit"><?php esc_html_e( 'Save taxonomy entry', DDD_TEXT_DOMAIN ); ?></button></form>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Type', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Key / label', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Aliases', DDD_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'State', DDD_TEXT_DOMAIN ); ?></th></tr></thead><tbody><?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row['type'] ); ?></td><td><code><?php echo esc_html( $row['canonical_key'] ); ?></code><br><?php echo esc_html( $row['canonical_label'] ); ?></td><td><?php echo esc_html( implode( ', ', (array) json_decode( $row['aliases_json'], true ) ) ); ?></td><td><?php echo esc_html( $row['status'] . ' · v' . absint( $row['version'] ) ); ?></td></tr><?php endforeach; ?></tbody></table></div>
		<?php
	}

	public function taxonomy_upsert() {
		$this->guard( 'ddd_manage_taxonomy' );
		check_admin_referer( 'ddd_taxonomy_upsert' );
		if ( DDD_Observability::safe_mode() ) { wp_die( esc_html__( 'Taxonomy changes are disabled while Safe Mode is active.', DDD_TEXT_DOMAIN ), '', array( 'response' => 503 ) ); }
		$result = DDD_Repository::taxonomy_upsert( isset( $_POST['type'] ) ? wp_unslash( $_POST['type'] ) : '', isset( $_POST['key'] ) ? wp_unslash( $_POST['key'] ) : '', isset( $_POST['label'] ) ? wp_unslash( $_POST['label'] ) : '', isset( $_POST['aliases'] ) ? wp_unslash( $_POST['aliases'] ) : '', isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'active', get_current_user_id() );
		$this->redirect_result( $result, 'ddd-taxonomy' );
	}

	public function repair_directory() {
		$this->guard( 'ddd_repair_directory' );
		check_admin_referer( 'ddd_repair_directory' );
		$execute = ! empty( $_POST['execute'] );
		$result = DDD_Activator::repair( $execute );
		DDD_Repository::audit_admin( 'repair', get_current_user_id(), 'system', 'directory', is_wp_error( $result ) ? 'failure' : 'success', array( 'execute' => $execute ? 1 : 0 ) );
		if ( is_wp_error( $result ) ) { $this->redirect_result( $result, 'ddd-health' ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'ddd-health', 'repair' => $execute ? 'executed' : 'dry-run' ), admin_url( 'admin.php' ) ) ); exit;
	}

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
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Doctors Directory and Discovery %s is active. Run System Check and reconciliation before staging use.', DDD_TEXT_DOMAIN ), DDD_VERSION ) ) . '</p></div>';
		}
	}
}

if ( ! class_exists( 'SDD_Admin' ) ) {
	class_alias( 'DDD_Admin', 'SDD_Admin' );
}
