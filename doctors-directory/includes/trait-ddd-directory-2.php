<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Directory_Trait_2 {
	private function search_form( $f ) {
		ob_start();
		?>
		<form class="ddd-search" method="get" aria-label="<?php esc_attr_e( 'Search verified doctors', DDD_TEXT_DOMAIN ); ?>">
			<div class="ddd-search-grid">
				<label><?php esc_html_e( 'Name, title or specialty', DDD_TEXT_DOMAIN ); ?><input type="search" name="doctor_search" value="<?php echo esc_attr( $f['q'] ); ?>" autocomplete="off"></label>
				<label><?php esc_html_e( 'Specialty', DDD_TEXT_DOMAIN ); ?><input name="doctor_specialty" value="<?php echo esc_attr( $f['specialty'] ); ?>"></label>
				<label><?php esc_html_e( 'Country', DDD_TEXT_DOMAIN ); ?><input name="doctor_country" value="<?php echo esc_attr( $f['country'] ); ?>"></label>
				<label><?php esc_html_e( 'City', DDD_TEXT_DOMAIN ); ?><input name="doctor_city" value="<?php echo esc_attr( $f['city'] ); ?>"></label>
				<label><?php esc_html_e( 'Language', DDD_TEXT_DOMAIN ); ?><input name="doctor_language" value="<?php echo esc_attr( $f['language'] ); ?>"></label>
				<label><?php esc_html_e( 'Qualification', DDD_TEXT_DOMAIN ); ?><input name="doctor_qualification" value="<?php echo esc_attr( $f['qualification'] ); ?>"></label>
				<label><?php esc_html_e( 'Minimum experience', DDD_TEXT_DOMAIN ); ?><select name="doctor_experience"><option value="0"><?php esc_html_e( 'Any experience', DDD_TEXT_DOMAIN ); ?></option><?php foreach ( array( 1,3,5,10,15,20,30 ) as $years ) : ?><option value="<?php echo absint( $years ); ?>" <?php selected( $f['min_experience'], $years ); ?>><?php echo absint( $years ); ?>+ <?php esc_html_e( 'years', DDD_TEXT_DOMAIN ); ?></option><?php endforeach; ?></select></label>
				<label><?php esc_html_e( 'Consultation mode', DDD_TEXT_DOMAIN ); ?><select name="doctor_mode"><option value=""><?php esc_html_e( 'Any mode', DDD_TEXT_DOMAIN ); ?></option><option value="online" <?php selected( $f['mode'], 'online' ); ?>><?php esc_html_e( 'Online', DDD_TEXT_DOMAIN ); ?></option><option value="in-person" <?php selected( $f['mode'], 'in-person' ); ?>><?php esc_html_e( 'In person', DDD_TEXT_DOMAIN ); ?></option></select></label>
				<label><?php esc_html_e( 'Currency', DDD_TEXT_DOMAIN ); ?><input name="doctor_currency" maxlength="3" value="<?php echo esc_attr( $f['currency'] ); ?>" placeholder="PKR"></label>
				<label><?php esc_html_e( 'Minimum fee', DDD_TEXT_DOMAIN ); ?><input type="number" min="0" step="0.01" name="doctor_fee_min" value="<?php echo esc_attr( null === $f['fee_min'] ? '' : $f['fee_min'] ); ?>"></label>
				<label><?php esc_html_e( 'Maximum fee', DDD_TEXT_DOMAIN ); ?><input type="number" min="0" step="0.01" name="doctor_fee_max" value="<?php echo esc_attr( null === $f['fee_max'] ? '' : $f['fee_max'] ); ?>"></label>
				<label class="ddd-check"><input type="checkbox" name="doctor_accepting" value="1" <?php checked( $f['accepting'], 1 ); ?>> <?php esc_html_e( 'Accepting new patients', DDD_TEXT_DOMAIN ); ?></label>
				<button class="ddd-button" type="submit"><?php esc_html_e( 'Search Doctors', DDD_TEXT_DOMAIN ); ?></button>
			</div>
			<a class="ddd-clear" href="<?php echo esc_url( remove_query_arg( array( 'doctor_search','doctor_specialty','doctor_country','doctor_city','doctor_language','doctor_qualification','doctor_experience','doctor_mode','doctor_currency','doctor_fee_min','doctor_fee_max','doctor_accepting','doctor_cursor' ) ) ); ?>"><?php esc_html_e( 'Clear all filters', DDD_TEXT_DOMAIN ); ?></a>
		</form>
		<?php
		return ob_get_clean();
	}
	private function founder_section() {
		$founder = DDD_Contracts::founder();
		if ( ! $founder || empty( $founder['display_name'] ) ) {
			return '';
		}
		$card = array(
			'public_id' => isset( $founder['public_id'] ) ? $founder['public_id'] : '',
			'display_name' => $founder['display_name'],
			'professional_title' => isset( $founder['professional_title'] ) ? $founder['professional_title'] : '',
			'specialty' => isset( $founder['specialty'] ) ? $founder['specialty'] : '',
			'country' => isset( $founder['country'] ) ? $founder['country'] : '',
			'city' => isset( $founder['city'] ) ? $founder['city'] : '',
			'languages' => isset( $founder['languages'] ) ? DDD_Helpers::list_value( $founder['languages'] ) : array(),
			'qualification' => isset( $founder['qualification'] ) ? $founder['qualification'] : '',
			'experience_years' => isset( $founder['experience_years'] ) ? absint( $founder['experience_years'] ) : 0,
			'consultation_modes' => array(),
			'accepting_patients' => false,
			'fee' => null,
			'avatar_id' => isset( $founder['avatar_id'] ) ? absint( $founder['avatar_id'] ) : 0,
			'profile_url' => isset( $founder['profile_url'] ) ? $founder['profile_url'] : '',
			'clinic_url' => '',
			'appointment_url' => '',
			'public_directory_url' => isset( $founder['profile_url'] ) ? $founder['profile_url'] : '',
			'completeness' => 100,
			'verified_at' => '',
			'featured' => true,
			'feature_label' => __( 'Verified Founder', DDD_TEXT_DOMAIN ),
			'ranking_explanation' => array( __( 'Institutional Founder identity', DDD_TEXT_DOMAIN ) ),
		);
		return $this->section( __( 'Founder', DDD_TEXT_DOMAIN ), __( 'The official Founder is institutionally pinned and never mixed into ordinary or recent-doctor groups.', DDD_TEXT_DOMAIN ), array( $card ) );
	}
	private function section( $title, $description, $items, $empty_state = false ) {
		if ( ! $items && ! $empty_state ) {
			return '';
		}
		ob_start();
		?>
		<section class="ddd-section" aria-labelledby="ddd-<?php echo esc_attr( sanitize_title( $title ) ); ?>">
			<div class="ddd-section-head"><div><h2 id="ddd-<?php echo esc_attr( sanitize_title( $title ) ); ?>"><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div></div>
			<div class="ddd-grid">
				<?php if ( $items ) : foreach ( $items as $item ) : echo $this->card( $item ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?><div class="ddd-empty" role="status"><h3><?php esc_html_e( 'No eligible doctors matched this search', DDD_TEXT_DOMAIN ); ?></h3><p><?php esc_html_e( 'Remove one or more filters, check spelling or try a broader location or specialty.', DDD_TEXT_DOMAIN ); ?></p></div><?php endif; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
