<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Admin_Trait_3 {
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
		<tr><td><?php echo esc_html( $row['doctor_id'] ? get_the_author_meta( 'display_name', $row['doctor_id'] ) : __( 'Removed or anonymized doctor', DDD_TEXT_DOMAIN ) ); ?><br><small><?php echo esc_html( sprintf( __( 'Reporter: %s', DDD_TEXT_DOMAIN ), $row['reporter_id'] ? get_the_author_meta( 'display_name', $row['reporter_id'] ) : __( 'anonymized', DDD_TEXT_DOMAIN ) ) ); ?></small></td><td><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $row['reason'] ) ) ); ?></strong><br><?php echo esc_html( $row['details'] ); ?><?php if ( $row['evidence_url'] ) : ?><br><a href="<?php echo esc_url( $row['evidence_url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php esc_html_e( 'Review submitted evidence', DDD_TEXT_DOMAIN ); ?></a><?php endif; ?><br><small><?php echo esc_html( $row['created_at'] ); ?> UTC</small></td><td><strong><?php echo esc_html( ucfirst( $row['status'] ) ); ?></strong><br><small><?php echo esc_html( sprintf( __( 'Version %d', DDD_TEXT_DOMAIN ), $row['version'] ) ); ?></small></td><td><form class="ddd-admin-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_transition_report"><input type="hidden" name="report_id" value="<?php echo absint( $row['id'] ); ?>"><input type="hidden" name="expected_version" value="<?php echo absint( $row['version'] ); ?>"><?php wp_nonce_field( 'ddd_transition_report_' . $row['id'] ); ?><label><?php esc_html_e( 'Status', DDD_TEXT_DOMAIN ); ?><select name="status"><?php foreach ( array( 'open','reviewing','escalated','resolved','dismissed' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $row['status'], $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option><?php endforeach; ?></select></label><label><?php esc_html_e( 'Required review note', DDD_TEXT_DOMAIN ); ?><textarea name="note" maxlength="1000" required><?php echo esc_textarea( $row['review_note'] ); ?></textarea></label><button class="button button-primary" type="submit"><?php esc_html_e( 'Save reviewed state', DDD_TEXT_DOMAIN ); ?></button></form></td></tr>
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
		<h2><?php esc_html_e( 'Safe Mode', DDD_TEXT_DOMAIN ); ?></h2><p><?php esc_html_e( 'Safe Mode disables high-risk mutations and background projection changes while preserving safe public reading of already eligible cached data where possible.', DDD_TEXT_DOMAIN ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_set_safe_mode"><?php wp_nonce_field( 'ddd_set_safe_mode' ); ?><label><input type="checkbox" name="enabled" value="1" <?php checked( $safe_mode ); ?>> <?php esc_html_e( 'Enable File 07 Safe Mode', DDD_TEXT_DOMAIN ); ?></label><label><?php esc_html_e( 'Reason', DDD_TEXT_DOMAIN ); ?><input name="reason" maxlength="250" required></label><button class="button button-primary" type="submit"><?php esc_html_e( 'Update Safe Mode', DDD_TEXT_DOMAIN ); ?></button></form></div>
		<?php
	}
}
