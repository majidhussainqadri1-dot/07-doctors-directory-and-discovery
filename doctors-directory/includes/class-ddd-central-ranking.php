<?php
defined( 'ABSPATH' ) || exit;

/** File 26 global-ranking read bridge; File 07 remains the public-safe directory projection owner. */
final class DDD_Central_Ranking {
	const RANKING_FILTER = 'sabri_file26_doctor_ranking_v1';
	const ASSURANCE_FILTER = 'sabri_file24_doctor_ranking_assurance_v1';
	const CONTRACT_VERSION = '1.0';
	const MAX_SNAPSHOT_AGE = 3024000; // 35 days.
	const LIMIT = 24;
	const MAX_CURSOR = 512;

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );
	}

	public static function tier() {
		$tier = isset( $_GET['doctor_tier'] ) ? sanitize_key( wp_unslash( $_GET['doctor_tier'] ) ) : 'all';
		return in_array( $tier, array( 'top10', 'top100', 'top1000', 'all' ), true ) ? $tier : 'all';
	}

	public static function filters() {
		$mode = isset( $_GET['doctor_mode'] ) ? sanitize_key( wp_unslash( $_GET['doctor_mode'] ) ) : '';
		if ( ! in_array( $mode, array( '', 'online', 'in-person', 'video', 'phone', 'chat', 'home-visit' ), true ) ) { $mode = ''; }
		return array(
			'q' => isset( $_GET['doctor_search'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_search'] ) ) : '',
			'country' => isset( $_GET['doctor_country'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_country'] ) ) : '',
			'city' => isset( $_GET['doctor_city'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_city'] ) ) : '',
			'specialty' => isset( $_GET['doctor_specialty'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_specialty'] ) ) : '',
			'language' => isset( $_GET['doctor_language'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_language'] ) ) : '',
			'qualification' => isset( $_GET['doctor_qualification'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_qualification'] ) ) : '',
			'min_experience' => isset( $_GET['doctor_experience'] ) ? min( 100, absint( $_GET['doctor_experience'] ) ) : 0,
			'mode' => $mode,
			'accepting' => ! empty( $_GET['doctor_accepting'] ) ? 1 : 0,
			'currency' => isset( $_GET['doctor_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['doctor_currency'] ) ) ) : '',
			'fee_min' => isset( $_GET['doctor_fee_min'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_min'] ) ) : null,
			'fee_max' => isset( $_GET['doctor_fee_max'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_max'] ) ) : null,
		);
	}

	private static function prohibited() { return array( 'donation', 'payment', 'paid_promotion', 'founder_favoritism', 'purchased_engagement' ); }

	private static function public_assurance( $assurance ) {
		if ( ! is_array( $assurance ) ) { return array( 'status' => 'unverified' ); }
		$out = array();
		foreach ( array( 'status', 'policy_version', 'monthly_version', 'generated_at', 'summary', 'public_report_url' ) as $key ) {
			if ( ! array_key_exists( $key, $assurance ) || ! is_scalar( $assurance[ $key ] ) ) { continue; }
			$value = sanitize_text_field( (string) $assurance[ $key ] );
			if ( 'public_report_url' === $key ) { $value = DDD_Helpers::same_origin_url( $value ); if ( ! $value ) { continue; } }
			$out[ $key ] = $value;
		}
		if ( 'pass' !== sanitize_key( (string) ( $out['status'] ?? '' ) ) ) { $out['status'] = 'unverified'; }
		return $out;
	}

	private static function request( $tier, $filters ) {
		$cursor = isset( $_GET['doctor_rank_cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_rank_cursor'] ) ) : '';
		if ( strlen( $cursor ) > self::MAX_CURSOR ) { $cursor = ''; }
		return array(
			'contract' => 'doctor_global_ranking', 'contract_version' => self::CONTRACT_VERSION, 'consumer' => 'file07',
			'tier' => $tier, 'limit' => 'top10' === $tier ? 10 : self::LIMIT, 'cursor' => $cursor, 'filters' => $filters,
			'require_nested_tiers' => true, 'require_monthly_version' => true, 'require_explanations' => true,
			'require_appeal' => true, 'require_bias_audit' => true, 'prohibited_signals' => self::prohibited(),
		);
	}

	public static function snapshot( $tier, $filters ) {
		if ( ! has_filter( self::RANKING_FILTER ) ) {
			return 'all' === $tier ? self::neutral( $filters ) : new WP_Error( 'file26_ranking_unavailable', __( 'Global ranking is temporarily unavailable. Top tiers are not fabricated.', DDD_TEXT_DOMAIN ) );
		}
		$request = self::request( $tier, $filters );
		$validated = self::validate( apply_filters( self::RANKING_FILTER, null, $request ), $request );
		if ( is_wp_error( $validated ) ) {
			DDD_Observability::record_health( 'file26-ranking', 'degraded', $validated->get_error_code() );
			return 'all' === $tier ? self::neutral( $filters ) : $validated;
		}
		DDD_Observability::record_health( 'file26-ranking', 'pass', 'ranking_contract_valid', array( 'policy_version' => $validated['policy_version'], 'monthly_version' => $validated['monthly_version'] ) );
		return $validated;
	}

	private static function validate( $response, $request ) {
		if ( ! is_array( $response ) || empty( $response['ready'] ) ) { return new WP_Error( 'file26_ranking_invalid', __( 'File 26 did not return a ready ranking snapshot.', DDD_TEXT_DOMAIN ) ); }
		$contract = sanitize_text_field( (string) ( $response['contract_version'] ?? '' ) );
		if ( ! $contract || version_compare( $contract, self::CONTRACT_VERSION, '<' ) ) { return new WP_Error( 'file26_contract_incompatible', __( 'File 26 ranking contract is incompatible.', DDD_TEXT_DOMAIN ) ); }
		$policy = sanitize_text_field( (string) ( $response['policy_version'] ?? '' ) );
		$monthly = sanitize_text_field( (string) ( $response['monthly_version'] ?? '' ) );
		if ( ! $policy || ! preg_match( '/^20\d{2}-(0[1-9]|1[0-2])(?:[.-][A-Za-z0-9_-]+)?$/', $monthly ) ) { return new WP_Error( 'file26_policy_version_missing', __( 'Ranking policy/monthly version is missing or invalid.', DDD_TEXT_DOMAIN ) ); }
		$generated = strtotime( (string) ( $response['generated_at'] ?? '' ) );
		if ( ! $generated || $generated > time() + DAY_IN_SECONDS || $generated < time() - self::MAX_SNAPSHOT_AGE ) { return new WP_Error( 'file26_snapshot_stale', __( 'Ranking snapshot is stale or has an invalid timestamp.', DDD_TEXT_DOMAIN ) ); }
		if ( empty( $response['nested_tiers'] ) ) { return new WP_Error( 'file26_nested_tiers_unproven', __( 'File 26 did not attest nested Top 10/100/1000 tiers.', DDD_TEXT_DOMAIN ) ); }
		$bias = is_array( $response['bias_audit'] ?? null ) ? $response['bias_audit'] : array();
		if ( 'pass' !== sanitize_key( (string) ( $bias['status'] ?? '' ) ) ) { return new WP_Error( 'file26_bias_audit_missing', __( 'Ranking bias audit has not passed.', DDD_TEXT_DOMAIN ) ); }
		$blocked = array_map( 'sanitize_key', (array) ( $bias['prohibited_signals'] ?? array() ) );
		foreach ( self::prohibited() as $signal ) { if ( ! in_array( $signal, $blocked, true ) ) { return new WP_Error( 'file26_bias_guard_incomplete', __( 'Ranking policy does not attest every prohibited paid/donor influence.', DDD_TEXT_DOMAIN ) ); } }
		if ( ! empty( $bias['paid_boost'] ) || ! empty( $bias['donor_boost'] ) ) { return new WP_Error( 'file26_paid_bias_detected', __( 'Paid or donor ranking advantage is forbidden.', DDD_TEXT_DOMAIN ) ); }
		$cap = 'top10' === $request['tier'] ? 10 : ( 'top100' === $request['tier'] ? 100 : ( 'top1000' === $request['tier'] ? 1000 : PHP_INT_MAX ) );
		$seen = array(); $items = array();
		foreach ( (array) ( $response['items'] ?? array() ) as $item ) {
			$id = strtolower( sanitize_text_field( (string) ( $item['public_id'] ?? '' ) ) ); $rank = absint( $item['rank'] ?? 0 );
			$why = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $item['explanation'] ?? array() ) ) ) );
			if ( ! DDD_Helpers::valid_public_id( $id ) || ! $rank || $rank > $cap || isset( $seen[ $id ] ) || ! $why ) { continue; }
			$seen[ $id ] = true; $items[] = array( 'public_id' => $id, 'rank' => $rank, 'explanation' => array_slice( $why, 0, 8 ) );
		}
		usort( $items, static function ( $a, $b ) { return $a['rank'] <=> $b['rank']; } );
		$assurance = null;
		if ( has_filter( self::ASSURANCE_FILTER ) ) {
			$assurance = self::public_assurance( apply_filters( self::ASSURANCE_FILTER, null, $bias, array( 'policy_version' => $policy, 'monthly_version' => $monthly, 'snapshot_id' => sanitize_text_field( (string) ( $response['snapshot_id'] ?? '' ) ) ) ) );
		}
		return array( 'source' => 'file26', 'ready' => true, 'policy_version' => $policy, 'monthly_version' => $monthly, 'generated_at' => gmdate( 'Y-m-d H:i:s', $generated ), 'items' => $items, 'next_cursor' => sanitize_text_field( substr( (string) ( $response['next_cursor'] ?? '' ), 0, self::MAX_CURSOR ) ), 'assurance' => $assurance );
	}

	private static function neutral( $f ) {
		global $wpdb; $table = DDD_Repository::table( 'projection' );
		if ( ! $table ) { return new WP_Error( 'projection_unavailable', __( 'Directory projection is unavailable.', DDD_TEXT_DOMAIN ) ); }
		$where = array( 'eligible=1' ); $p = array(); $q = DDD_Helpers::normalize_token( $f['q'] );
		if ( $q ) { $like = '%' . $wpdb->esc_like( $q ) . '%'; $where[] = '(display_name_norm LIKE %s OR specialty_norm LIKE %s OR search_text_norm LIKE %s)'; array_push( $p, $like, $like, $like ); }
		foreach ( array( 'country'=>'country_norm', 'city'=>'city_norm', 'specialty'=>'specialty_norm' ) as $k=>$c ) { if ( '' !== (string) $f[$k] ) { $where[] = "$c=%s"; $p[] = DDD_Repository::taxonomy_normalize( $k, $f[$k] ); } }
		if ( $f['language'] ) { $where[]='languages_norm LIKE %s'; $p[]='%'.$wpdb->esc_like( DDD_Repository::taxonomy_normalize( 'language', $f['language'] ) ).'%'; }
		if ( $f['qualification'] ) { $where[]='qualification_norm LIKE %s'; $p[]='%'.$wpdb->esc_like( DDD_Helpers::normalize_token( $f['qualification'] ) ).'%'; }
		if ( $f['min_experience'] ) { $where[]='experience_years>=%d'; $p[]=absint($f['min_experience']); }
		if ( $f['mode'] ) { $where[]='consultation_modes_json LIKE %s'; $p[]='%"'.$wpdb->esc_like($f['mode']).'"%'; }
		if ( $f['accepting'] ) { $where[]='accepting_patients=1'; }
		if ( $f['currency'] ) { $where[]='currency=%s'; $p[]=strtoupper(substr($f['currency'],0,3)); }
		if ( null !== $f['fee_min'] ) { $where[]='(fee_max IS NULL OR fee_max>=%f)'; $p[]=(float)$f['fee_min']; }
		if ( null !== $f['fee_max'] ) { $where[]='(fee_min IS NULL OR fee_min<=%f)'; $p[]=(float)$f['fee_max']; }
		$hash = DDD_Helpers::filter_hash( $f ); $raw = isset($_GET['doctor_rank_cursor']) ? sanitize_text_field(wp_unslash($_GET['doctor_rank_cursor'])) : ''; $cursor = $raw ? DDD_Helpers::cursor_decode($raw,$hash) : array();
		if ( $raw && ! $cursor ) { return new WP_Error( 'neutral_cursor_invalid', __( 'The All Verified cursor expired or does not match these filters. Restart the view.', DDD_TEXT_DOMAIN ) ); }
		if ( $cursor ) { $where[]='(display_name_norm>%s OR (display_name_norm=%s AND public_id>%s))'; $p[]=(string)($cursor['n']??''); $p[]=(string)($cursor['n']??''); $p[]=(string)($cursor['p']??''); }
		$p[] = self::LIMIT + 1; $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$table} WHERE ".implode(' AND ',$where).' ORDER BY display_name_norm ASC, public_id ASC LIMIT %d',$p), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$more = count($rows)>self::LIMIT; if($more){array_pop($rows);} $items=array();
		foreach($rows as $row){ $dto=DDD_Repository::get_by_public_id($row['public_id']); if($dto){$items[]=array('public_id'=>$dto['public_id'],'rank'=>0,'explanation'=>array(__( 'Verified and publicly eligible', DDD_TEXT_DOMAIN ),__( 'Neutral alphabetical fallback; no merit rank is asserted while File 26 is unavailable.', DDD_TEXT_DOMAIN )));}}
		$next=''; if($more&&$rows){$last=end($rows);$next=DDD_Helpers::cursor_encode(array('fh'=>$hash,'n'=>(string)$last['display_name_norm'],'p'=>(string)$last['public_id']));}
		return array('source'=>'neutral','ready'=>true,'items'=>$items,'next_cursor'=>$next,'policy_version'=>'','monthly_version'=>'','generated_at'=>'');
	}

	public static function public_items( $snapshot ) {
		$items = array();
		foreach ( (array) ( $snapshot['items'] ?? array() ) as $ranked ) {
			$doctor = DDD_Repository::get_by_public_id( $ranked['public_id'] );
			if ( ! $doctor ) { continue; }
			$doctor['global_rank'] = 'file26' === ( $snapshot['source'] ?? '' ) ? absint( $ranked['rank'] ) : 0;
			$doctor['ranking_explanation'] = $ranked['explanation']; $items[] = $doctor;
		}
		return $items;
	}

	public static function rest_routes() {
		register_rest_route( DDD_REST::NS, '/ranking', array( 'methods'=>WP_REST_Server::READABLE, 'callback'=>array(__CLASS__,'rest_ranking'), 'permission_callback'=>'__return_true', 'args'=>array('tier'=>array('sanitize_callback'=>'sanitize_key','default'=>'all')) ) );
	}
	public static function rest_ranking( WP_REST_Request $request ) {
		$tier=sanitize_key((string)$request->get_param('tier')); if(!in_array($tier,array('top10','top100','top1000','all'),true)){$tier='all';}
		$s=self::snapshot($tier,self::filters()); if(is_wp_error($s)){return $s;}
		return rest_ensure_response(array('source'=>$s['source'],'policy_version'=>(string)$s['policy_version'],'monthly_version'=>(string)$s['monthly_version'],'generated_at'=>(string)$s['generated_at'],'items'=>self::public_items($s),'next_cursor'=>(string)$s['next_cursor']));
	}
}
add_action( 'plugins_loaded', array( 'DDD_Central_Ranking', 'register' ), 31 );
