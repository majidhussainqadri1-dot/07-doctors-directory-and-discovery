<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Directory_Trait_3 {
	public function card( $doctor ) {
		$name = $doctor['display_name'];
		$location = trim( $doctor['city'] . ', ' . $doctor['country'], ', ' );
		$destination = $doctor['profile_url'] ? $doctor['profile_url'] : ( $doctor['clinic_url'] ? $doctor['clinic_url'] : $doctor['public_directory_url'] );
		ob_start();
		?>
		<article class="ddd-card" data-public-id="<?php echo esc_attr( $doctor['public_id'] ); ?>">
			<div class="ddd-avatar"><?php echo $doctor['avatar_id'] ? wp_get_attachment_image( $doctor['avatar_id'], 'thumbnail', false, array( 'alt' => $name, 'loading' => 'lazy', 'decoding' => 'async' ) ) : esc_html( DDD_Helpers::initials( $name ) ); ?></div>
			<div class="ddd-card-body">
				<div class="ddd-badges"><span class="ddd-badge"><?php esc_html_e( 'Verified Doctor', DDD_TEXT_DOMAIN ); ?></span><?php if ( $doctor['featured'] ) : ?><span class="ddd-badge ddd-badge-featured"><?php echo esc_html( $doctor['feature_label'] ? $doctor['feature_label'] : __( 'Featured', DDD_TEXT_DOMAIN ) ); ?></span><?php endif; ?></div>
				<h3><a href="<?php echo esc_url( $destination ); ?>"><?php echo esc_html( $name ); ?></a></h3>
				<p class="ddd-headline"><?php echo esc_html( $doctor['professional_title'] ? $doctor['professional_title'] : $doctor['specialty'] ); ?></p>
				<?php if ( $location ) : ?><p><?php echo esc_html( $location ); ?></p><?php endif; ?>
				<div class="ddd-tags"><?php foreach ( array_slice( $doctor['consultation_modes'], 0, 3 ) as $mode ) : ?><span><?php echo esc_html( ucwords( str_replace( '-', ' ', $mode ) ) ); ?></span><?php endforeach; ?><?php if ( $doctor['accepting_patients'] ) : ?><span><?php esc_html_e( 'Accepting patients', DDD_TEXT_DOMAIN ); ?></span><?php endif; ?><?php foreach ( array_slice( $doctor['languages'], 0, 3 ) as $language ) : ?><span><?php echo esc_html( $language ); ?></span><?php endforeach; ?></div>
				<?php if ( $doctor['fee'] ) : ?><p><strong><?php esc_html_e( 'Fee:', DDD_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( $doctor['fee']['currency'] . ' ' . number_format_i18n( $doctor['fee']['min'], 2 ) ); ?></p><?php endif; ?>
				<details class="ddd-ranking"><summary><?php esc_html_e( 'Why this result appears', DDD_TEXT_DOMAIN ); ?></summary><ul><?php foreach ( $doctor['ranking_explanation'] as $label ) : ?><li><?php echo esc_html( $label ); ?></li><?php endforeach; ?></ul></details>
				<nav class="ddd-actions" aria-label="<?php echo esc_attr( sprintf( __( 'Actions for %s', DDD_TEXT_DOMAIN ), $name ) ); ?>"><a class="ddd-button" href="<?php echo esc_url( $destination ); ?>"><?php esc_html_e( 'View Profile', DDD_TEXT_DOMAIN ); ?></a><?php if ( $doctor['clinic_url'] ) : ?><a class="ddd-button ddd-button-light" href="<?php echo esc_url( $doctor['clinic_url'] ); ?>"><?php esc_html_e( 'Clinic', DDD_TEXT_DOMAIN ); ?></a><?php endif; ?><?php if ( $doctor['appointment_url'] ) : ?><a class="ddd-button ddd-button-light" href="<?php echo esc_url( is_user_logged_in() ? $doctor['appointment_url'] : wp_login_url( $doctor['appointment_url'] ) ); ?>"><?php esc_html_e( 'Appointment', DDD_TEXT_DOMAIN ); ?></a><?php endif; ?></nav>
				<?php if ( is_user_logged_in() && $doctor['public_id'] ) : ?>
				<div class="ddd-secondary-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_save_doctor"><input type="hidden" name="public_id" value="<?php echo esc_attr( $doctor['public_id'] ); ?>"><input type="hidden" name="save" value="1"><?php wp_nonce_field( 'ddd_save_doctor_' . $doctor['public_id'] ); ?><button class="ddd-clear" type="submit"><?php esc_html_e( 'Save doctor', DDD_TEXT_DOMAIN ); ?></button></form><details class="ddd-report"><summary><?php esc_html_e( 'Report listing concern', DDD_TEXT_DOMAIN ); ?></summary><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ddd_report_listing"><input type="hidden" name="public_id" value="<?php echo esc_attr( $doctor['public_id'] ); ?>"><input type="hidden" name="idempotency_key" value="<?php echo esc_attr( 'listing-' . wp_generate_uuid4() ); ?>"><?php wp_nonce_field( 'ddd_report_listing_' . $doctor['public_id'] ); ?><label><?php esc_html_e( 'Reason', DDD_TEXT_DOMAIN ); ?><select name="reason" required><option value=""><?php esc_html_e( 'Choose a reason', DDD_TEXT_DOMAIN ); ?></option><option value="credentials"><?php esc_html_e( 'Credentials concern', DDD_TEXT_DOMAIN ); ?></option><option value="incorrect-information"><?php esc_html_e( 'Incorrect information', DDD_TEXT_DOMAIN ); ?></option><option value="medical-safety"><?php esc_html_e( 'Medical safety concern', DDD_TEXT_DOMAIN ); ?></option><option value="impersonation"><?php esc_html_e( 'Impersonation', DDD_TEXT_DOMAIN ); ?></option><option value="spam"><?php esc_html_e( 'Spam', DDD_TEXT_DOMAIN ); ?></option><option value="harassment"><?php esc_html_e( 'Harassment', DDD_TEXT_DOMAIN ); ?></option><option value="copyright"><?php esc_html_e( 'Copyright', DDD_TEXT_DOMAIN ); ?></option><option value="other"><?php esc_html_e( 'Other', DDD_TEXT_DOMAIN ); ?></option></select></label><label><?php esc_html_e( 'Details', DDD_TEXT_DOMAIN ); ?><textarea name="details" minlength="10" maxlength="2000" required></textarea></label><label><?php esc_html_e( 'Optional evidence URL', DDD_TEXT_DOMAIN ); ?><input type="url" name="evidence_url"></label><button class="ddd-button" type="submit"><?php esc_html_e( 'Submit report', DDD_TEXT_DOMAIN ); ?></button></form></details></div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}
}
