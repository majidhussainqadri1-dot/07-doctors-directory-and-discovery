<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Directory_Trait_1 {
	public function hooks() {
		add_shortcode( 'ddd_doctors_directory', array( $this, 'render' ) );
		add_shortcode( 'sdd_doctors_directory', array( $this, 'render' ) );
		add_action( 'init', array( $this, 'rewrites' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'doctor_redirect' ), 2 );
	}
	public function rewrites() {
		add_rewrite_rule( '^doctors/search/?$', 'index.php?ddd_directory_search=1', 'top' );
		add_rewrite_rule( '^doctors/([a-f0-9-]{36})/?$', 'index.php?ddd_doctor_public_id=$matches[1]', 'top' );
	}
	public function query_vars( $vars ) {
		$vars[] = 'ddd_directory_search';
		$vars[] = 'ddd_doctor_public_id';
		return $vars;
	}
	public function doctor_redirect() {
		$public_id = get_query_var( 'ddd_doctor_public_id' );
		if ( ! $public_id ) {
			return;
		}
		$doctor = DDD_Repository::get_by_public_id( $public_id );
		if ( ! $doctor ) {
			status_header( 404 );
			nocache_headers();
			return;
		}
		$destination = $doctor['profile_url'] ? $doctor['profile_url'] : $doctor['clinic_url'];
		if ( $destination ) {
			wp_safe_redirect( $destination, 302, 'Doctors Directory and Discovery' );
			exit;
		}
	}
	private function filters() {
		$mode = isset( $_GET['doctor_mode'] ) ? sanitize_key( wp_unslash( $_GET['doctor_mode'] ) ) : '';
		if ( ! in_array( $mode, array( '', 'online', 'in-person' ), true ) ) {
			$mode = '';
		}
		return array(
			'q'              => isset( $_GET['doctor_search'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_search'] ) ) : '',
			'country'        => isset( $_GET['doctor_country'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_country'] ) ) : '',
			'city'           => isset( $_GET['doctor_city'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_city'] ) ) : '',
			'specialty'      => isset( $_GET['doctor_specialty'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_specialty'] ) ) : '',
			'language'       => isset( $_GET['doctor_language'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_language'] ) ) : '',
			'qualification'  => isset( $_GET['doctor_qualification'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_qualification'] ) ) : '',
			'min_experience' => isset( $_GET['doctor_experience'] ) ? min( 100, absint( $_GET['doctor_experience'] ) ) : 0,
			'mode'           => $mode,
			'accepting'      => ! empty( $_GET['doctor_accepting'] ) ? 1 : 0,
			'currency'       => isset( $_GET['doctor_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['doctor_currency'] ) ) ) : '',
			'fee_min'        => isset( $_GET['doctor_fee_min'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_min'] ) ) : null,
			'fee_max'        => isset( $_GET['doctor_fee_max'] ) ? DDD_Helpers::decimal_or_null( wp_unslash( $_GET['doctor_fee_max'] ) ) : null,
			'cursor'         => isset( $_GET['doctor_cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_cursor'] ) ) : '',
			'limit'          => DDD_Repository::DEFAULT_LIMIT,
		);
	}
	public function render() {
		$filters = $this->filters();
		$result = DDD_Repository::search( $filters );
		$has_filters = (bool) array_filter( array_diff_key( $filters, array( 'cursor' => true, 'limit' => true ) ) );
		$featured = $has_filters ? array( 'items' => array() ) : DDD_Repository::search( array( 'featured_only' => 1, 'limit' => 6 ) );
		$recent = $has_filters ? array( 'items' => array() ) : DDD_Repository::search( array( 'recent_only' => 90, 'limit' => 6 ) );
		ob_start();
		?>
		<main class="ddd-shell" id="doctors-directory" data-ddd-directory>
			<?php do_action( 'ddd_before_directory' ); ?>
			<header class="ddd-hero">
				<div><span><?php esc_html_e( 'Global Professional Directory', DDD_TEXT_DOMAIN ); ?></span><h1><?php esc_html_e( 'Doctors', DDD_TEXT_DOMAIN ); ?></h1><p><?php esc_html_e( 'Find publicly eligible, verified homeopathic practitioners by specialty, location, language, consultation mode, availability and fee. Verification is not an endorsement or treatment guarantee.', DDD_TEXT_DOMAIN ); ?></p></div>
			</header>
			<?php echo $this->search_form( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( ! $has_filters ) : ?>
				<?php echo $this->founder_section(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->section( __( 'Featured Doctors', DDD_TEXT_DOMAIN ), __( 'Editorially selected with a visible label, reason, expiry and audit trail; no hidden paid ranking.', DDD_TEXT_DOMAIN ), $featured['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->section( __( 'Recently Verified Doctors', DDD_TEXT_DOMAIN ), __( 'Ordered by authoritative verification effective date, never by account registration date.', DDD_TEXT_DOMAIN ), $recent['items'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<?php echo $this->section( $has_filters ? __( 'Search Results', DDD_TEXT_DOMAIN ) : __( 'All Doctors', DDD_TEXT_DOMAIN ), __( 'Stable cursor ordering uses bounded, explainable eligibility and quality signals.', DDD_TEXT_DOMAIN ), $result['items'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $result['next_cursor'] ) : ?>
				<nav class="ddd-pagination" aria-label="<?php esc_attr_e( 'Doctors directory pagination', DDD_TEXT_DOMAIN ); ?>"><a class="ddd-button ddd-button-light" href="<?php echo esc_url( add_query_arg( 'doctor_cursor', $result['next_cursor'] ) ); ?>"><?php esc_html_e( 'Next results', DDD_TEXT_DOMAIN ); ?></a></nav>
			<?php endif; ?>
			<p class="ddd-disclaimer"><?php esc_html_e( 'Always confirm professional licensing, local jurisdictional requirements and clinical suitability directly. Emergency care is outside this directory.', DDD_TEXT_DOMAIN ); ?></p>
			<?php do_action( 'ddd_after_directory' ); ?>
		</main>
		<?php
		return ob_get_clean();
	}
}
