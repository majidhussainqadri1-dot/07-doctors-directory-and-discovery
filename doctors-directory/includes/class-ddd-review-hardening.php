<?php
defined( 'ABSPATH' ) || exit;

/**
 * Post-review hardening that does not take canonical ownership from companion
 * modules. Canonical identity/profile/verification/clinic reads are installed by
 * DDD_Cross_File_Adapters; this class only narrows Founder claims and supplies
 * the terminating legal-hold-safe privacy eraser.
 */
final class DDD_Review_Hardening {
	public static function register() {
		add_filter( DDD_Contracts::FOUNDER_FILTER, array( __CLASS__, 'founder_claim' ), 99, 2 );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'replace_privacy_eraser' ), 99 );
	}

	public static function founder_claim( $founder, $contract_version ) {
		if ( ! is_array( $founder ) ) {
			return $founder;
		}
		$user_id = absint( $founder['user_id'] ?? 0 );
		if ( ! $user_id ) {
			return null;
		}
		$identity = DDD_Contracts::identity_claims( $user_id );
		$profile  = DDD_Contracts::public_profile( $user_id );
		if ( empty( $identity['provider_available'] ) || empty( $identity['institutional'] ) || empty( $profile['public'] ) || empty( $profile['discoverable'] ) ) {
			return null;
		}
		return $founder;
	}

	public static function replace_privacy_eraser( $erasers ) {
		$erasers['doctors-directory-discovery'] = array(
			'eraser_friendly_name' => __( 'Doctors Directory and Discovery', DDD_TEXT_DOMAIN ),
			'callback'             => array( 'DDD_Privacy_Hardening', 'erase' ),
		);
		return $erasers;
	}
}

final class DDD_Privacy_Hardening {
	const BATCH = 50;

	public static function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		global $wpdb;
		$page = max( 1, absint( $page ) );
		$removed = false;
		$retained = false;
		$messages = array();
		$global_hold = (bool) apply_filters( 'ddd_privacy_legal_hold', false, $user->ID );

		if ( 1 === $page ) {
			DDD_Helpers::set_meta( $user->ID, 'discoverable', '0' );
			delete_user_meta( $user->ID, '_ddd_public_phone' );
			delete_user_meta( $user->ID, '_ddd_public_whatsapp' );
			$wpdb->delete( DDD_Repository::table( 'saved_refs' ), array( 'user_id' => $user->ID ), array( '%d' ) );
			$deleted = DDD_Repository::delete_doctor_projection( $user->ID, 'privacy_erasure' );
			$removed = ! is_wp_error( $deleted );
		}

		$reports = DDD_Repository::table( 'reports' );
		$held_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$reports} WHERE (reporter_id=%d OR doctor_id=%d) AND retention_hold=1",
				$user->ID,
				$user->ID
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$retained = $global_hold || $held_count > 0;

		if ( $global_hold ) {
			$remaining = 0;
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$reports} WHERE (reporter_id=%d OR doctor_id=%d) AND retention_hold=0 ORDER BY id ASC LIMIT %d",
					$user->ID,
					$user->ID,
					self::BATCH
				),
				ARRAY_A
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( $rows as $row ) {
				$changes = array(
					'updated_at' => current_time( 'mysql', true ),
					'version'    => absint( $row['version'] ) + 1,
				);
				if ( absint( $row['reporter_id'] ) === absint( $user->ID ) ) {
					$changes['reporter_id']  = 0;
					$changes['details']      = '[Removed through privacy request]';
					$changes['evidence_url'] = '';
					$changes['ip_hash']      = '';
				}
				if ( absint( $row['doctor_id'] ) === absint( $user->ID ) ) {
					$changes['doctor_id'] = 0;
				}
				if ( false !== $wpdb->update( $reports, $changes, array( 'id' => absint( $row['id'] ) ) ) ) {
					$removed = true;
				}
			}

			$remaining = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$reports} WHERE (reporter_id=%d OR doctor_id=%d) AND retention_hold=0",
					$user->ID,
					$user->ID
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $retained ) {
			$messages[] = __( 'Some report records were retained under an approved legal or safety hold.', DDD_TEXT_DOMAIN );
		}
		$messages[] = __( 'Non-identifying moderation and audit records may be retained for accountability and platform integrity.', DDD_TEXT_DOMAIN );
		DDD_Repository::audit_admin(
			'privacy_erasure',
			0,
			'user',
			(string) $user->ID,
			'success',
			array( 'removed' => $removed ? 1 : 0, 'retained' => $retained ? 1 : 0, 'remaining_unheld' => $remaining )
		);

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => 0 === $remaining,
		);
	}
}

