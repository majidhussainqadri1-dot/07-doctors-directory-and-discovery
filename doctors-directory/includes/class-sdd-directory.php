<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Directory {
	public function hooks() {
		add_shortcode( 'sdd_doctors_directory', array( $this, 'render' ) );
	}

	public function render() {
		$filters = $this->filters();
		$doctors = $this->doctors( $filters );
		$featured = array_values( array_filter( $doctors, function( $user ) { return '1' === SDD_Helpers::get( $user->ID, 'featured', '0' ); } ) );
		$recent = $doctors;
		usort( $recent, function( $a, $b ) { return strcmp( $b->user_registered, $a->user_registered ); } );
		ob_start(); ?>
		<main class="sdd-shell" id="doctors-directory">
			<?php echo SDD_Helpers::navigation(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<header class="sdd-hero"><div><span>Global Professional Directory</span><h1>Doctors</h1><p>Find verified homeopathic practitioners by location, language, experience, and consultation availability. Always confirm local licensing and suitability directly.</p></div><div class="sdd-hero-stat"><strong><?php echo absint( count( $doctors ) ); ?></strong><span>verified profiles found</span></div></header>
			<?php echo $this->search_form( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( ! $this->has_filters( $filters ) ) : ?>
				<section class="sdd-section"><div class="sdd-section-head"><div><span>Platform Leadership</span><h2>Founder</h2></div></div><?php echo $this->founder_card(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
				<?php if ( $featured ) : ?><section class="sdd-section"><div class="sdd-section-head"><div><span>Selected Profiles</span><h2>Featured Doctors</h2></div></div><div class="sdd-grid"><?php foreach ( array_slice( $featured, 0, 6 ) as $doctor ) { echo $this->card( $doctor ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endif; ?>
				<?php if ( $recent ) : ?><section class="sdd-section"><div class="sdd-section-head"><div><span>New to the Directory</span><h2>Recently Joined Doctors</h2></div></div><div class="sdd-grid"><?php foreach ( array_slice( $recent, 0, 6 ) as $doctor ) { echo $this->card( $doctor ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endif; ?>
			<?php endif; ?>
			<section class="sdd-section"><div class="sdd-section-head"><div><span>Verified Directory</span><h2><?php echo $this->has_filters( $filters ) ? 'Search Results' : 'All Doctors'; ?></h2></div><small>Profiles are ordered by featured status, profile completion, educational contributions, and name.</small></div><div class="sdd-grid"><?php if ( $doctors ) : foreach ( $doctors as $doctor ) { echo $this->card( $doctor ); } else : ?><div class="sdd-empty"><h3>No doctors matched your search</h3><p>Remove one or more filters and try again.</p></div><?php endif; ?></div></section>
			<p class="sdd-disclaimer">Directory information is supplied by practitioners and reviewed at a foundation level. Verification is not an endorsement, treatment guarantee, or substitute for checking professional credentials in your jurisdiction.</p>
		</main>
		<?php return ob_get_clean();
	}

	private function filters() {
		return array(
			'search' => isset( $_GET['doctor_search'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_search'] ) ) : '',
			'country' => isset( $_GET['doctor_country'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_country'] ) ) : '',
			'city' => isset( $_GET['doctor_city'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_city'] ) ) : '',
			'language' => isset( $_GET['doctor_language'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_language'] ) ) : '',
			'qualification' => isset( $_GET['doctor_qualification'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_qualification'] ) ) : '',
			'experience' => isset( $_GET['doctor_experience'] ) ? absint( $_GET['doctor_experience'] ) : 0,
			'mode' => isset( $_GET['doctor_mode'] ) ? sanitize_key( wp_unslash( $_GET['doctor_mode'] ) ) : '',
		);
	}

	private function has_filters( $filters ) {
		return (bool) array_filter( $filters );
	}

	private function doctors( $filters ) {
		$users = get_users( array( 'role' => 'sabri_doctor_verified', 'number' => 250, 'orderby' => 'display_name', 'order' => 'ASC' ) );
		$users = array_values( array_filter( $users, function( $user ) use ( $filters ) {
			if ( ! SDD_Helpers::is_public( $user->ID ) ) { return false; }
			$haystack = strtolower( $user->display_name . ' ' . SDD_Helpers::spd( $user->ID, 'specialty' ) );
			if ( $filters['search'] && false === strpos( $haystack, strtolower( $filters['search'] ) ) ) { return false; }
			foreach ( array( 'country', 'city', 'language', 'qualification' ) as $field ) {
				if ( $filters[ $field ] && false === stripos( (string) SDD_Helpers::spd( $user->ID, 'language' === $field ? 'languages' : $field ), $filters[ $field ] ) ) { return false; }
			}
			if ( $filters['experience'] && absint( SDD_Helpers::spd( $user->ID, 'experience_years', 0 ) ) < $filters['experience'] ) { return false; }
			if ( 'online' === $filters['mode'] && '1' !== SDD_Helpers::get( $user->ID, 'online_available', '0' ) ) { return false; }
			if ( 'in-person' === $filters['mode'] && '1' !== SDD_Helpers::get( $user->ID, 'in_person_available', '0' ) ) { return false; }
			return true;
		} ) );
		usort( $users, function( $a, $b ) {
			$score_a = ( '1' === SDD_Helpers::get( $a->ID, 'featured', '0' ) ? 10000 : 0 ) + SDD_Helpers::completion( $a->ID ) * 10 + SDD_Helpers::contributions( $a->ID );
			$score_b = ( '1' === SDD_Helpers::get( $b->ID, 'featured', '0' ) ? 10000 : 0 ) + SDD_Helpers::completion( $b->ID ) * 10 + SDD_Helpers::contributions( $b->ID );
			return $score_a === $score_b ? strcasecmp( $a->display_name, $b->display_name ) : $score_b - $score_a;
		} );
		return $users;
	}

	private function search_form( $f ) {
		ob_start(); ?><form class="sdd-search" method="get" aria-label="Search verified doctors"><div class="sdd-search-grid"><label>Doctor’s name<input type="search" name="doctor_search" value="<?php echo esc_attr( $f['search'] ); ?>" placeholder="Search by name or specialty"></label><label>Country<input name="doctor_country" value="<?php echo esc_attr( $f['country'] ); ?>"></label><label>City<input name="doctor_city" value="<?php echo esc_attr( $f['city'] ); ?>"></label><label>Language<input name="doctor_language" value="<?php echo esc_attr( $f['language'] ); ?>"></label><label>Qualification<input name="doctor_qualification" value="<?php echo esc_attr( $f['qualification'] ); ?>"></label><label>Minimum experience<select name="doctor_experience"><option value="0">Any experience</option><?php foreach ( array( 1, 3, 5, 10, 15, 20 ) as $years ) : ?><option value="<?php echo absint( $years ); ?>" <?php selected( $f['experience'], $years ); ?>><?php echo absint( $years ); ?>+ years</option><?php endforeach; ?></select></label><label>Consultation<select name="doctor_mode"><option value="">Any consultation</option><option value="online" <?php selected( $f['mode'], 'online' ); ?>>Online available</option><option value="in-person" <?php selected( $f['mode'], 'in-person' ); ?>>In-person available</option></select></label><button class="sdd-button" type="submit">Search Doctors</button></div><?php if ( $this->has_filters( $f ) ) : ?><a class="sdd-clear" href="<?php echo esc_url( remove_query_arg( array( 'doctor_search', 'doctor_country', 'doctor_city', 'doctor_language', 'doctor_qualification', 'doctor_experience', 'doctor_mode' ) ) ); ?>">Clear all filters</a><?php endif; ?></form><?php return ob_get_clean();
	}

	private function founder_card() {
		$f = SPD_Helpers::founder();
		$photo = absint( $f['photo_id'] );
		ob_start(); ?><article class="sdd-card sdd-founder-card"><div class="sdd-avatar"><?php echo $photo ? wp_get_attachment_image( $photo, 'thumbnail', false, array( 'alt' => $f['name'] ) ) : esc_html( SDD_Helpers::initials( $f['name'] ) ); ?></div><div class="sdd-card-body"><span class="sdd-badge">✓ Verified Founder</span><h3><a href="<?php echo esc_url( SDD_Helpers::founder_url() ); ?>"><?php echo esc_html( $f['name'] ); ?></a></h3><p class="sdd-headline"><?php echo esc_html( $f['title'] ); ?></p><p><?php echo esc_html( $f['location'] ); ?></p><div class="sdd-meter"><span style="width:100%"></span></div><small>Profile 100% complete</small><?php echo $this->contacts( $f['phone'], $f['whatsapp'], true, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></article><?php return ob_get_clean();
	}

	public function card( $user ) {
		$id = $user->ID;
		$photo = absint( SDD_Helpers::spd( $id, 'profile_photo_id', 0 ) );
		$completion = SDD_Helpers::completion( $id );
		$online = '1' === SDD_Helpers::get( $id, 'online_available', '0' );
		$person = '1' === SDD_Helpers::get( $id, 'in_person_available', '0' );
		ob_start(); ?><article class="sdd-card"><div class="sdd-avatar"><?php echo $photo ? wp_get_attachment_image( $photo, 'thumbnail', false, array( 'alt' => $user->display_name, 'loading' => 'lazy' ) ) : esc_html( SDD_Helpers::initials( $user->display_name ) ); ?></div><div class="sdd-card-body"><span class="sdd-badge">✓ Verified Doctor</span><h3><a href="<?php echo esc_url( SDD_Helpers::profile_url( $id ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a></h3><p class="sdd-headline"><?php echo esc_html( SDD_Helpers::get( $id, 'headline', SDD_Helpers::spd( $id, 'specialty', 'Homeopathic practitioner' ) ) ); ?></p><p><?php echo esc_html( trim( SDD_Helpers::spd( $id, 'city' ) . ', ' . SDD_Helpers::spd( $id, 'country' ), ', ' ) ); ?></p><div class="sdd-tags"><?php if ( $online ) : ?><span>Online</span><?php endif; ?><?php if ( $person ) : ?><span>In person</span><?php endif; ?><?php if ( SDD_Helpers::spd( $id, 'languages' ) ) : ?><span><?php echo esc_html( SDD_Helpers::spd( $id, 'languages' ) ); ?></span><?php endif; ?></div><div class="sdd-meter" aria-label="Profile <?php echo absint( $completion ); ?> percent complete"><span style="width:<?php echo absint( $completion ); ?>%"></span></div><small>Profile <?php echo absint( $completion ); ?>% complete · <?php echo absint( SDD_Helpers::contributions( $id ) ); ?> contributions</small><?php echo $this->contacts( SDD_Helpers::spd( $id, 'phone' ), SDD_Helpers::spd( $id, 'whatsapp' ), SDD_Helpers::contact_is_public( $id, 'phone' ), SDD_Helpers::contact_is_public( $id, 'whatsapp' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a class="sdd-profile-link" href="<?php echo esc_url( SDD_Helpers::profile_url( $id ) ); ?>">View Profile</a></div></article><?php return ob_get_clean();
	}

	public function contacts( $phone, $whatsapp, $show_phone, $show_whatsapp ) {
		$out = '<div class="sdd-contact">';
		$clean = SDD_Helpers::phone( $phone );
		$out .= $show_phone && $clean ? '<a href="tel:' . esc_attr( $clean ) . '">Phone</a>' : '<span aria-disabled="true">Phone Private</span>';
		$wa = SDD_Helpers::whatsapp( $whatsapp );
		$out .= $show_whatsapp && $wa ? '<a class="is-whatsapp" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener noreferrer">WhatsApp</a>' : '<span aria-disabled="true">WhatsApp Private</span>';
		return $out . '</div>';
	}
}

