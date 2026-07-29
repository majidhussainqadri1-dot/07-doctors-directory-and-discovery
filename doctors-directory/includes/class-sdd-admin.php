<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Admin {
	const PER_PAGE = 50;

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_sdd_manage_doctor', array( $this, 'manage' ) );
		add_action( 'admin_post_sdd_manage_report', array( $this, 'manage_report' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function menu() {
		add_menu_page( 'Doctors Management', 'Doctors Management', 'manage_doctors_directory', 'doctors-management', array( $this, 'dashboard' ), 'dashicons-id-alt', 28 );
		add_submenu_page( 'doctors-management', 'Directory Profiles', 'Directory Profiles', 'manage_doctors_directory', 'doctors-management', array( $this, 'dashboard' ) );
		add_submenu_page( 'doctors-management', 'Profile Reports', 'Profile Reports', 'manage_doctors_directory', 'doctors-profile-reports', array( $this, 'reports' ) );
	}

	public function dashboard() {
		$this->guard();
		$page  = isset( $_GET['doctor_admin_page'] ) ? max( 1, absint( $_GET['doctor_admin_page'] ) ) : 1;
		$query = new WP_User_Query(
			array(
				'role__in'    => array( 'sabri_doctor_pending', 'sabri_doctor_verified' ),
				'number'      => self::PER_PAGE,
				'paged'       => $page,
				'orderby'     => 'registered',
				'order'       => 'DESC',
				'count_total' => true,
			)
		);
		$users = $query->get_results();
		$total = (int) $query->get_total();
		$stats = $this->doctor_stats();
		?>
		<div class="wrap sdd-admin"><h1>Doctors Management</h1><p>This dashboard manages directory visibility and featured placement. Professional verification remains in File 03.</p>
		<div class="sdd-admin-stats"><div><strong><?php echo absint( $total ); ?></strong><span>doctor accounts</span></div><div><strong><?php echo absint( $stats['verified'] ); ?></strong><span>verified doctors</span></div><div><strong><?php echo absint( $stats['public'] ); ?></strong><span>public profiles</span></div></div>
		<table class="widefat striped"><thead><tr><th>Doctor</th><th>Verification</th><th>Profile</th><th>Directory status</th><th>Action</th></tr></thead><tbody>
		<?php if ( $users ) : foreach ( $users as $user ) : $hidden = '1' === SDD_Helpers::get( $user->ID, 'hidden', '0' ); $featured = '1' === SDD_Helpers::get( $user->ID, 'featured', '0' ); ?>
		<tr><td><strong><?php echo esc_html( $user->display_name ); ?></strong><br><a href="<?php echo esc_url( SDD_Helpers::profile_url( $user->ID ) ); ?>" target="_blank" rel="noopener">View profile</a><br><small><?php echo esc_html( SDD_Helpers::spd( $user->ID, 'country' ) ); ?></small></td><td><?php echo esc_html( SDD_Helpers::status_label( $user->ID ) ); ?></td><td><?php echo absint( SDD_Helpers::completion( $user->ID ) ); ?>% complete<br><?php echo absint( SDD_Helpers::contributions( $user->ID ) ); ?> contributions</td><td><?php echo esc_html( $hidden ? 'Hidden by administrator' : ( SDD_Helpers::is_public( $user->ID ) ? 'Public' : 'Not public' ) ); ?><?php echo $featured ? '<br><strong>Featured</strong>' : ''; ?></td><td><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="sdd_manage_doctor"><input type="hidden" name="user_id" value="<?php echo absint( $user->ID ); ?>"><?php wp_nonce_field( 'sdd_manage_' . $user->ID ); ?><select name="directory_action"><option value="feature"><?php echo esc_html( $featured ? 'Remove featured status' : 'Feature doctor' ); ?></option><option value="hide"><?php echo esc_html( $hidden ? 'Restore directory listing' : 'Hide directory listing' ); ?></option></select><input name="note" maxlength="250" placeholder="Internal reason"><button class="button button-primary" type="submit">Apply</button></form></td></tr>
		<?php endforeach; else : ?><tr><td colspan="5">No doctor accounts found.</td></tr><?php endif; ?>
		</tbody></table><?php echo $this->admin_pagination( $page, $total, self::PER_PAGE, 'doctor_admin_page', 'doctors-management' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php
	}

	private function doctor_stats() {
		global $wpdb;
		$caps_key = $wpdb->get_blog_prefix() . 'capabilities';
		$role_like = '%' . $wpdb->esc_like( '"sabri_doctor_verified"' ) . '%';
		$verified = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} caps ON caps.user_id=u.ID AND caps.meta_key=%s AND caps.meta_value LIKE %s INNER JOIN {$wpdb->usermeta} verify ON verify.user_id=u.ID AND verify.meta_key='_spd_verification_status' AND verify.meta_value='verified'",
				$caps_key,
				$role_like
			)
		);
		$founder = SDD_Helpers::founder_id();
		$sql = "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} caps ON caps.user_id=u.ID AND caps.meta_key=%s AND caps.meta_value LIKE %s INNER JOIN {$wpdb->usermeta} verify ON verify.user_id=u.ID AND verify.meta_key='_spd_verification_status' AND verify.meta_value='verified' WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} h WHERE h.user_id=u.ID AND h.meta_key='_sdd_hidden' AND h.meta_value='1') AND NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} d WHERE d.user_id=u.ID AND d.meta_key='_sdd_discoverable' AND d.meta_value='0')";
		$params = array( $caps_key, $role_like );
		if ( $founder ) {
			$sql .= ' AND u.ID<>%d';
			$params[] = $founder;
		}
		$public = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'verified' => $verified, 'public' => $public );
	}

	public function manage() {
		$this->guard();
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'sdd_manage_' . $user_id );
		if ( ! $user_id || ! SPD_Helpers::is_doctor( $user_id ) ) {
			wp_die( esc_html__( 'Invalid doctor.', 'doctors-directory' ), '', array( 'response' => 400 ) );
		}
		$action = isset( $_POST['directory_action'] ) ? sanitize_key( wp_unslash( $_POST['directory_action'] ) ) : '';
		$note   = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( 'hide' === $action && ! trim( $note ) ) {
			wp_die( esc_html__( 'An internal reason is required when changing directory visibility.', 'doctors-directory' ), '', array( 'response' => 400 ) );
		}
		if ( 'feature' === $action ) {
			$old = SDD_Helpers::get( $user_id, 'featured', '0' );
			$new = '1' === $old ? '0' : '1';
			$result = update_user_meta( $user_id, '_sdd_featured', $new );
			SDD_Helpers::maybe_audit( $user_id, 'featured_' . $old, 'featured_' . $new, $note ? $note : 'Directory featured status changed.' );
		} elseif ( 'hide' === $action ) {
			$old = SDD_Helpers::get( $user_id, 'hidden', '0' );
			$new = '1' === $old ? '0' : '1';
			$result = update_user_meta( $user_id, '_sdd_hidden', $new );
			SDD_Helpers::maybe_audit( $user_id, 'hidden_' . $old, 'hidden_' . $new, $note );
		} else {
			wp_die( esc_html__( 'Invalid directory action.', 'doctors-directory' ), '', array( 'response' => 400 ) );
		}
		if ( false === $result ) {
			wp_die( esc_html__( 'The directory change could not be saved.', 'doctors-directory' ), '', array( 'response' => 500 ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'doctors-management', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function reports() {
		$this->guard();
		global $wpdb;
		$page   = isset( $_GET['report_page'] ) ? max( 1, absint( $_GET['report_page'] ) ) : 1;
		$offset = ( $page - 1 ) * self::PER_PAGE;
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sdd_reports" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sdd_reports ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ) );
		?>
		<div class="wrap sdd-admin"><h1>Profile Reports</h1><p>Review concerns fairly. A report does not establish wrongdoing. Every transition requires a reason and is recorded in the audit trail.</p>
		<table class="widefat striped"><thead><tr><th>Doctor</th><th>Reporter</th><th>Concern</th><th>Status</th><th>Review</th></tr></thead><tbody>
		<?php if ( $rows ) : foreach ( $rows as $row ) : ?>
		<tr><td><?php echo esc_html( $row->doctor_id ? get_the_author_meta( 'display_name', $row->doctor_id ) : 'Removed user' ); ?></td><td><?php echo esc_html( $row->reporter_id ? get_the_author_meta( 'display_name', $row->reporter_id ) : 'Anonymized reporter' ); ?></td><td><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $row->reason ) ) ); ?></strong><br><?php echo esc_html( $row->details ); ?><br><small>Created <?php echo esc_html( $row->created_at ); ?> UTC · Updated <?php echo esc_html( $row->updated_at ); ?> UTC</small><?php if ( $row->review_note ) : ?><br><em>Latest reviewer note: <?php echo esc_html( $row->review_note ); ?></em><?php endif; ?></td><td><?php echo esc_html( ucfirst( $row->status ) ); ?></td><td><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="sdd_manage_report"><input type="hidden" name="report_id" value="<?php echo absint( $row->id ); ?>"><?php wp_nonce_field( 'sdd_report_manage_' . $row->id ); ?><select name="status"><option value="open" <?php selected( $row->status, 'open' ); ?>>Open</option><option value="reviewing" <?php selected( $row->status, 'reviewing' ); ?>>Reviewing</option><option value="resolved" <?php selected( $row->status, 'resolved' ); ?>>Resolved</option><option value="dismissed" <?php selected( $row->status, 'dismissed' ); ?>>Dismissed</option></select><textarea name="review_note" maxlength="1000" required placeholder="Required review reason or resolution note"></textarea><button class="button" type="submit">Save Review</button></form></td></tr>
		<?php endforeach; else : ?><tr><td colspan="5">No profile reports.</td></tr><?php endif; ?>
		</tbody></table><?php echo $this->admin_pagination( $page, $total, self::PER_PAGE, 'report_page', 'doctors-profile-reports' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php
	}

	public function manage_report() {
		$this->guard();
		$id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		check_admin_referer( 'sdd_report_manage_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$note   = isset( $_POST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ) : '';
		if ( ! in_array( $status, array( 'open', 'reviewing', 'resolved', 'dismissed' ), true ) || ! trim( $note ) ) {
			wp_die( esc_html__( 'Choose a valid report status and provide a review note.', 'doctors-directory' ), '', array( 'response' => 400 ) );
		}
		$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 1000 ) : substr( $note, 0, 1000 );
		global $wpdb;
		$table = $wpdb->prefix . 'sdd_reports';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT id,status FROM {$table} WHERE id=%d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			wp_die( esc_html__( 'The report no longer exists.', 'doctors-directory' ), '', array( 'response' => 404 ) );
		}
		$now = current_time( 'mysql', true );
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update( $table, array( 'status' => $status, 'reviewer_id' => get_current_user_id(), 'review_note' => $note, 'updated_at' => $now ), array( 'id' => $id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );
		$audited = false;
		if ( false !== $updated ) {
			$audited = $wpdb->insert( $wpdb->prefix . 'sdd_report_audit', array( 'report_id' => $id, 'actor_id' => get_current_user_id(), 'old_status' => $row->status, 'new_status' => $status, 'note' => $note, 'created_at' => $now ), array( '%d', '%d', '%s', '%s', '%s', '%s' ) );
		}
		if ( false === $updated || false === $audited ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_die( esc_html__( 'The report review and audit transition could not be saved atomically.', 'doctors-directory' ), '', array( 'response' => 500 ) );
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_safe_redirect( add_query_arg( array( 'page' => 'doctors-profile-reports', 'reviewed' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function admin_pagination( $page, $total, $per_page, $page_arg, $admin_page ) {
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		if ( $pages <= 1 ) {
			return '';
		}
		$big  = 999999999;
		$base = add_query_arg( array( 'page' => $admin_page, $page_arg => $big ), admin_url( 'admin.php' ) );
		$base = str_replace( (string) $big, '%#%', esc_url( $base ) );
		$links = paginate_links( array( 'base' => $base, 'current' => $page, 'total' => $pages, 'type' => 'list' ) );
		return $links ? '<nav class="sdd-admin-pagination" aria-label="Administration pages">' . $links . '</nav>' : '';
	}

	private function guard() {
		if ( ! current_user_can( 'manage_doctors_directory' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the doctors directory.', 'doctors-directory' ), '', array( 'response' => 403 ) );
		}
	}

	public function notice() {
		if ( current_user_can( 'activate_plugins' ) && get_transient( 'sdd_activation_notice' ) ) {
			delete_transient( 'sdd_activation_notice' );
			echo '<div class="notice notice-success is-dismissible"><p>Doctors Directory is active. Review Doctors Management and the public Doctors page.</p></div>';
		}
	}
}
