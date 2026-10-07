<?php
defined( 'ABSPATH' ) || exit;

/** File 07 appeal UI; File 26 remains the canonical appeal owner. */
final class DDD_Ranking_Appeal {
	const FILTER = 'sabri_file26_doctor_ranking_appeal_v1'; // Legacy adapter.

	public static function register() {
		add_action( 'admin_post_ddd_ranking_appeal', array( __CLASS__, 'submit' ) );
	}

	public static function form( $snapshot, $tier ) {
		if ( ! is_user_logged_in() || empty( $snapshot['policy_version'] ) || empty( $snapshot['monthly_version'] ) ) { return ''; }
		$status = DDD_Repository::get_live_status( get_current_user_id() );
		if ( empty( $status['eligible'] ) || empty( $status['public_id'] ) ) { return ''; }
		$state = isset( $_GET['ddd_rank_appeal'] ) ? sanitize_key( wp_unslash( $_GET['ddd_rank_appeal'] ) ) : '';
		ob_start();
		if ( 'submitted' === $state ) {
			echo '<p role="status">' . esc_html__( 'Your ranking appeal was handed to the File 26 owner contract.', DDD_TEXT_DOMAIN ) . '</p>';
		}
		if ( 'failed' === $state ) {
			echo '<p role="alert">' . esc_html__( 'The ranking appeal could not be accepted. Retry later or contact support.', DDD_TEXT_DOMAIN ) . '</p>';
		}
		?>
		<details class="ddd-ranking-appeal">
			<summary><?php esc_html_e( 'Appeal my ranking', DDD_TEXT_DOMAIN ); ?></summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ddd_ranking_appeal">
				<input type="hidden" name="public_id" value="<?php echo esc_attr( $status['public_id'] ); ?>">
				<input type="hidden" name="tier" value="<?php echo esc_attr( $tier ); ?>">
				<input type="hidden" name="policy_version" value="<?php echo esc_attr( $snapshot['policy_version'] ); ?>">
				<input type="hidden" name="monthly_version" value="<?php echo esc_attr( $snapshot['monthly_version'] ); ?>">
				<?php wp_nonce_field( 'ddd_ranking_appeal_' . $status['public_id'] ); ?>
				<label><?php esc_html_e( 'Reason', DDD_TEXT_DOMAIN ); ?>
					<select name="reason" required>
						<option value=""><?php esc_html_e( 'Choose a reason', DDD_TEXT_DOMAIN ); ?></option>
						<option value="wrong-data"><?php esc_html_e( 'Incorrect source data', DDD_TEXT_DOMAIN ); ?></option>
						<option value="bias"><?php esc_html_e( 'Possible ranking bias', DDD_TEXT_DOMAIN ); ?></option>
						<option value="missing-signal"><?php esc_html_e( 'Relevant verified signal is missing', DDD_TEXT_DOMAIN ); ?></option>
						<option value="complaint-outcome"><?php esc_html_e( 'Complaint outcome is incorrect', DDD_TEXT_DOMAIN ); ?></option>
						<option value="appeal-outcome"><?php esc_html_e( 'Prior appeal outcome is not reflected', DDD_TEXT_DOMAIN ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', DDD_TEXT_DOMAIN ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Details', DDD_TEXT_DOMAIN ); ?>
					<textarea name="details" minlength="20" maxlength="2000" required></textarea>
				</label>
				<button class="ddd-button" type="submit"><?php esc_html_e( 'Submit ranking appeal', DDD_TEXT_DOMAIN ); ?></button>
			</form>
		</details>
		<?php
		return ob_get_clean();
	}

	private static function current_file26_submit( $public_id, $reason, $details, $policy_version, $monthly_version ) {
		$key = DDD_Central_Ranking::doctor_key_for_public_id( $public_id );
		if ( ! $key ) {
			return new WP_Error( 'file26_doctor_key_unavailable', __( 'The File 26 doctor-ranking reference could not be resolved safely.', DDD_TEXT_DOMAIN ) );
		}
		try {
			if ( ! class_exists( 'Sabri\\File26\\Plugin' ) || ! is_callable( array( 'Sabri\\File26\\Plugin', 'instance' ) ) ) {
				return new WP_Error( 'file26_appeal_provider_unavailable', __( 'The File 26 appeal provider is unavailable.', DDD_TEXT_DOMAIN ) );
			}
			$plugin = \Sabri\File26\Plugin::instance();
			if ( ! is_object( $plugin ) || ! method_exists( $plugin, 'doctor_appeals' ) ) {
				return new WP_Error( 'file26_appeal_provider_unavailable', __( 'The File 26 appeal provider is unavailable.', DDD_TEXT_DOMAIN ) );
			}
			$service = $plugin->doctor_appeals();
			if ( ! is_object( $service ) || ! method_exists( $service, 'submit' ) ) {
				return new WP_Error( 'file26_appeal_provider_unavailable', __( 'The File 26 appeal provider is unavailable.', DDD_TEXT_DOMAIN ) );
			}
			$evidence = array(
				'file07_reason:' . sanitize_key( $reason ),
				'file07_policy:' . sanitize_text_field( $policy_version ),
				'file07_month:' . sanitize_text_field( $monthly_version ),
			);
			$result = $service->submit( $key, $details, $evidence );
			if ( is_wp_error( $result ) ) { return $result; }
			if ( ! is_array( $result ) || empty( $result['appeal_uuid'] ) ) {
				return new WP_Error( 'file26_appeal_invalid_response', __( 'File 26 returned an invalid appeal receipt.', DDD_TEXT_DOMAIN ) );
			}
			return array( 'accepted'=>true, 'receipt_id'=>sanitize_text_field( (string) $result['appeal_uuid'] ), 'status'=>sanitize_key( (string) ( $result['status'] ?? 'submitted' ) ) );
		} catch ( Throwable $exception ) {
			return new WP_Error( 'file26_appeal_failed', __( 'The File 26 appeal provider failed safely.', DDD_TEXT_DOMAIN ) );
		}
	}

	public static function submit() {
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		$id = isset( $_POST['public_id'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['public_id'] ) ) ) : '';
		if ( ! DDD_Helpers::valid_public_id( $id ) ) {
			wp_die( esc_html__( 'Invalid ranking appeal.', DDD_TEXT_DOMAIN ), '', array( 'response'=>400 ) );
		}
		check_admin_referer( 'ddd_ranking_appeal_' . $id );
		$status = DDD_Repository::get_live_status( get_current_user_id() );
		if ( empty( $status['eligible'] ) || ! hash_equals( (string) $status['public_id'], $id ) ) {
			wp_die( esc_html__( 'You may appeal only your own eligible doctor ranking.', DDD_TEXT_DOMAIN ), '', array( 'response'=>403 ) );
		}
		if ( ! DDD_Helpers::rate_limit( 'ranking-appeal', get_current_user_id() . '|' . $id, 3, DAY_IN_SECONDS ) ) {
			wp_die( esc_html__( 'Ranking appeal limit reached. Try again later.', DDD_TEXT_DOMAIN ), '', array( 'response'=>429 ) );
		}
		$reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
		$details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
		$allowed = array( 'wrong-data', 'bias', 'missing-signal', 'complaint-outcome', 'appeal-outcome', 'other' );
		if ( ! in_array( $reason, $allowed, true ) || strlen( trim( $details ) ) < 20 || strlen( $details ) > 2000 ) {
			wp_die( esc_html__( 'Provide a valid appeal reason and details.', DDD_TEXT_DOMAIN ), '', array( 'response'=>400 ) );
		}
		$request = array(
			'contract_version'=>DDD_Central_Ranking::CONTRACT_VERSION,
			'actor_user_id'=>get_current_user_id(),
			'doctor_public_id'=>$id,
			'tier'=>isset($_POST['tier'])?sanitize_key(wp_unslash($_POST['tier'])):'all',
			'policy_version'=>isset($_POST['policy_version'])?sanitize_text_field(wp_unslash($_POST['policy_version'])):'',
			'monthly_version'=>isset($_POST['monthly_version'])?sanitize_text_field(wp_unslash($_POST['monthly_version'])):'',
			'reason'=>$reason,
			'details'=>$details,
			'idempotency_key'=>hash('sha256',$id.'|'.$reason.'|'.$details.'|'.gmdate('Y-m-d')),
		);
		if ( has_filter( self::FILTER ) ) {
			$result = apply_filters( self::FILTER, null, $request );
		} else {
			$result = self::current_file26_submit( $id, $reason, $details, $request['policy_version'], $request['monthly_version'] );
		}
		$ok = is_array( $result ) && ! empty( $result['accepted'] ) && ! empty( $result['receipt_id'] );
		DDD_Repository::audit_admin(
			'ranking_appeal_handoff',
			get_current_user_id(),
			'doctor_public_id',
			$id,
			$ok ? 'success' : 'failed',
			array(
				'reason_code'=>$reason,
				'policy_version'=>$request['policy_version'],
				'monthly_version'=>$request['monthly_version'],
				'provider_error'=>is_wp_error($result)?sanitize_key($result->get_error_code()):'',
			)
		);
		$back = DDD_Helpers::same_origin_url( wp_get_referer() ? wp_get_referer() : home_url( '/doctors/' ) );
		if ( ! $back ) { $back = home_url( '/doctors/' ); }
		wp_safe_redirect( add_query_arg( 'ddd_rank_appeal', $ok ? 'submitted' : 'failed', $back ) );
		exit;
	}
}
add_action( 'plugins_loaded', array( 'DDD_Ranking_Appeal', 'register' ), 33 );
