<?php
defined( 'ABSPATH' ) || exit;

/**
 * Forty-round review hardening.
 *
 * This adapter does not take canonical ownership from Files 00, 03, 08 or 09.
 * It only makes the documented legacy compatibility path fail closed and
 * replaces the privacy eraser with a terminating legal-hold-safe batch worker.
 */
final class DDD_Review_Hardening {
	public static function register() {
		if ( self::identity_provider_present() ) {
			add_filter( DDD_Contracts::IDENTITY_FILTER, array( __CLASS__, 'identity_claims' ), 99, 3 );
		}
		if ( self::verification_provider_present() ) {
			add_filter( DDD_Contracts::VERIFICATION_FILTER, array( __CLASS__, 'verification_claims' ), 99, 3 );
		}
		add_filter( DDD_Contracts::FOUNDER_FILTER, array( __CLASS__, 'founder_claim' ), 99, 2 );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'replace_privacy_eraser' ), 99 );
	}

	private static function identity_provider_present() {
		return has_filter( DDD_Contracts::IDENTITY_FILTER )
			|| defined( 'SMC_VERSION' )
			|| class_exists( 'SMC_Contracts' )
			|| false !== get_option( 'smc_db_version', false )
			|| false !== get_option( 'smc_version', false );
	}

	private static function verification_provider_present() {
		return has_filter( DDD_Contracts::VERIFICATION_FILTER )
			|| defined( 'GDO_VERSION' )
			|| class_exists( 'GDO_Contracts' )
			|| class_exists( 'SPD_Helpers' )
			|| false !== get_option( 'gdo_db_version', false );
	}

	public static function identity_claims( $claims, $user_id, $contract_version ) {
		if ( is_array( $claims ) ) {
			return $claims;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id || ! self::identity_provider_present() ) {
			return $claims;
		}

		$status      = sanitize_key( (string) get_user_meta( $user_id, '_smc_membership_status', true ) );
		$suspension  = sanitize_key( (string) get_user_meta( $user_id, '_smc_suspension_status', true ) );
		$risk        = sanitize_key( (string) get_user_meta( $user_id, '_smc_risk_status', true ) );
		$age         = sanitize_key( (string) get_user_meta( $user_id, '_smc_age_eligibility', true ) );
		$guardian    = sanitize_key( (string) get_user_meta( $user_id, '_smc_guardian_status', true ) );

		$account_active = in_array( $status, array( 'approved', 'active', 'verified', 'institutional' ), true );
		$risk_clear = in_array( $risk, array( 'none', 'clear', 'low', 'approved' ), true );
		$not_suspended = in_array( $suspension, array( 'none', 'clear', 'active' ), true );
		$age_eligible = in_array( $age, array( 'eligible', 'adult', 'approved', 'verified' ), true );
		$guardian_valid = 'adult' === $age || in_array( $guardian, array( 'not-required', 'not_required', 'approved', 'verified', 'valid', 'adult' ), true );

		return array(
			'user_id'            => $user_id,
			'provider_available' => true,
			'account_active'     => $account_active,
			'suspended'          => ! $not_suspended,
			'risk_blocked'       => ! $risk_clear,
			'age_eligible'       => $age_eligible,
			'guardian_valid'     => $guardian_valid,
			'institutional'      => DDD_Helpers::is_founder( $user_id ) || 'institutional' === $status,
			'claim_version'      => 'file00-compat-v1.1-hardening',
			'source_updated_at'  => '',
		);
	}

	public static function verification_claims( $claims, $user_id, $contract_version ) {
		if ( is_array( $claims ) ) {
			return $claims;
		}
		$user_id = absint( $user_id );
		if ( ! $user_id || ! self::verification_provider_present() ) {
			return $claims;
		}

		$status = '';
		$doctor = false;
		if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'is_doctor' ) ) ) {
			$doctor = (bool) SPD_Helpers::is_doctor( $user_id );
		}
		if ( class_exists( 'SPD_Helpers' ) && is_callable( array( 'SPD_Helpers', 'verification_status' ) ) ) {
			$status = sanitize_key( (string) SPD_Helpers::verification_status( $user_id ) );
		}
		if ( '' === $status ) {
			$status = sanitize_key( (string) get_user_meta( $user_id, '_gdo_verification_status', true ) );
		}
		if ( '' === $status ) {
			$status = sanitize_key( (string) get_user_meta( $user_id, '_spd_verification_status', true ) );
		}

		$positive = in_array( $status, array( 'verified', 'approved', 'active' ), true );
		if ( ! $doctor ) {
			$doctor = $positive && '1' === (string) get_user_meta( $user_id, '_spd_is_doctor', true );
		}
		$effective = (string) get_user_meta( $user_id, '_gdo_verified_at', true );
		if ( '' === $effective ) {
			$effective = (string) get_user_meta( $user_id, '_spd_verified_at', true );
		}

		return array(
			'user_id'            => $user_id,
			'provider_available' => true,
			'doctor'             => $doctor,
			'verified'           => $doctor && $positive,
			'status'             => $status ?: 'unverified',
			'effective_at'       => $effective,
			'expires_at'         => (string) get_user_meta( $user_id, '_gdo_verification_expires_at', true ),
			'decision_version'   => 'file09-compat-v1.1-hardening',
			'source_updated_at'  => '',
		);
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
