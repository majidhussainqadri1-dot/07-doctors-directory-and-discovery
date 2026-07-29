<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Admin {
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
		$users = get_users( array( 'role__in' => array( 'sabri_doctor_pending', 'sabri_doctor_verified' ), 'number' => 250, 'orderby' => 'registered', 'order' => 'DESC' ) );
		$verified = count( array_filter( $users, function( $u ) { return SDD_Helpers::is_verified( $u->ID ); } ) );
		$public = count( array_filter( $users, function( $u ) { return SDD_Helpers::is_public( $u->ID ); } ) );
		?><div class="wrap sdd-admin"><h1>Doctors Management</h1><p>This dashboard manages directory visibility and featured placement. Professional verification remains in File 03.</p><div class="sdd-admin-stats"><div><strong><?php echo absint( count( $users ) ); ?></strong><span>doctor accounts</span></div><div><strong><?php echo absint( $verified ); ?></strong><span>verified doctors</span></div><div><strong><?php echo absint( $public ); ?></strong><span>public profiles</span></div></div><table class="widefat striped"><thead><tr><th>Doctor</th><th>Verification</th><th>Profile</th><th>Directory status</th><th>Action</th></tr></thead><tbody><?php if ( $users ) : foreach ( $users as $user ) : $hidden = '1' === SDD_Helpers::get( $user->ID, 'hidden', '0' ); $featured = '1' === SDD_Helpers::get( $user->ID, 'featured', '0' ); ?><tr><td><strong><?php echo esc_html( $user->display_name ); ?></strong><br><a href="<?php echo esc_url( SDD_Helpers::profile_url( $user->ID ) ); ?>" target="_blank" rel="noopener">View profile</a><br><small><?php echo esc_html( SDD_Helpers::spd( $user->ID, 'country' ) ); ?></small></td><td><?php echo esc_html( SPD_Helpers::status_label( SPD_Helpers::verification_status( $user->ID ) ) ); ?></td><td><?php echo absint( SDD_Helpers::completion( $user->ID ) ); ?>% complete<br><?php echo absint( SDD_Helpers::contributions( $user->ID ) ); ?> contributions</td><td><?php echo $hidden ? 'Hidden by administrator' : ( SDD_Helpers::is_public( $user->ID ) ? 'Public' : 'Not public' ); ?><?php echo $featured ? '<br><strong>Featured</strong>' : ''; ?></td><td><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="sdd_manage_doctor"><input type="hidden" name="user_id" value="<?php echo absint( $user->ID ); ?>"><?php wp_nonce_field( 'sdd_manage_' . $user->ID ); ?><select name="directory_action"><option value="feature"><?php echo $featured ? 'Remove featured status' : 'Feature doctor'; ?></option><option value="hide"><?php echo $hidden ? 'Restore directory listing' : 'Hide directory listing'; ?></option></select><input name="note" maxlength="250" placeholder="Internal reason"><button class="button button-primary" type="submit">Apply</button></form></td></tr><?php endforeach; else : ?><tr><td colspan="5">No doctor accounts found.</td></tr><?php endif; ?></tbody></table></div><?php
	}

	public function manage() {
		$this->guard();
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'sdd_manage_' . $user_id );
		if ( ! $user_id || ! SPD_Helpers::is_doctor( $user_id ) ) { wp_die( esc_html__( 'Invalid doctor.', 'doctors-directory' ), '', array( 'response' => 400 ) ); }
		$action = isset( $_POST['directory_action'] ) ? sanitize_key( wp_unslash( $_POST['directory_action'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';
		if ( 'feature' === $action ) { $old = SDD_Helpers::get( $user_id, 'featured', '0' ); $new = '1' === $old ? '0' : '1'; update_user_meta( $user_id, '_sdd_featured', $new ); SPD_Helpers::audit( $user_id, 'featured_' . $old, 'featured_' . $new, $note ); }
		elseif ( 'hide' === $action ) { $old = SDD_Helpers::get( $user_id, 'hidden', '0' ); $new = '1' === $old ? '0' : '1'; update_user_meta( $user_id, '_sdd_hidden', $new ); SPD_Helpers::audit( $user_id, 'hidden_' . $old, 'hidden_' . $new, $note ); }
		else { wp_die( esc_html__( 'Invalid directory action.', 'doctors-directory' ), '', array( 'response' => 400 ) ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'doctors-management', 'updated' => '1' ), admin_url( 'admin.php' ) ) ); exit;
	}

	public function reports() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sdd_reports ORDER BY created_at DESC LIMIT 250" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		?><div class="wrap sdd-admin"><h1>Profile Reports</h1><p>Review concerns fairly. A report does not establish wrongdoing.</p><table class="widefat striped"><thead><tr><th>Doctor</th><th>Reporter</th><th>Concern</th><th>Status</th><th>Action</th></tr></thead><tbody><?php if ( $rows ) : foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( get_the_author_meta( 'display_name', $row->doctor_id ) ); ?></td><td><?php echo esc_html( get_the_author_meta( 'display_name', $row->reporter_id ) ); ?></td><td><strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $row->reason ) ) ); ?></strong><br><?php echo esc_html( $row->details ); ?><br><small><?php echo esc_html( $row->created_at ); ?> UTC</small></td><td><?php echo esc_html( ucfirst( $row->status ) ); ?></td><td><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="sdd_manage_report"><input type="hidden" name="report_id" value="<?php echo absint( $row->id ); ?>"><?php wp_nonce_field( 'sdd_report_manage_' . $row->id ); ?><select name="status"><option value="open" <?php selected( $row->status, 'open' ); ?>>Open</option><option value="reviewing" <?php selected( $row->status, 'reviewing' ); ?>>Reviewing</option><option value="resolved" <?php selected( $row->status, 'resolved' ); ?>>Resolved</option><option value="dismissed" <?php selected( $row->status, 'dismissed' ); ?>>Dismissed</option></select><button class="button" type="submit">Save</button></form></td></tr><?php endforeach; else : ?><tr><td colspan="5">No profile reports.</td></tr><?php endif; ?></tbody></table></div><?php
	}

	public function manage_report() {
		$this->guard();
		$id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
		check_admin_referer( 'sdd_report_manage_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! in_array( $status, array( 'open', 'reviewing', 'resolved', 'dismissed' ), true ) ) { wp_die( esc_html__( 'Invalid report status.', 'doctors-directory' ), '', array( 'response' => 400 ) ); }
		global $wpdb; $wpdb->update( $wpdb->prefix . 'sdd_reports', array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		wp_safe_redirect( add_query_arg( 'page', 'doctors-profile-reports', admin_url( 'admin.php' ) ) ); exit;
	}

	private function guard() {
		if ( ! current_user_can( 'manage_doctors_directory' ) ) { wp_die( esc_html__( 'You are not allowed to manage the doctors directory.', 'doctors-directory' ), '', array( 'response' => 403 ) ); }
	}

	public function notice() {
		if ( current_user_can( 'activate_plugins' ) && get_transient( 'sdd_activation_notice' ) ) { delete_transient( 'sdd_activation_notice' ); echo '<div class="notice notice-success is-dismissible"><p>Doctors Directory is active. Review Doctors Management and the public Doctors page.</p></div>'; }
	}
}
