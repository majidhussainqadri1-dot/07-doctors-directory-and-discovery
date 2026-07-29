<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Directory {
	const PER_PAGE = 24;

	public function hooks() {
		add_shortcode( 'sdd_doctors_directory', array( $this, 'render' ) );
	}

	public function render() {
		$filters = $this->filters();
		$result  = $this->query_doctors( $filters, $filters['page'], self::PER_PAGE, 'ranked' );
		ob_start();
		?>
		<main class="sdd-shell" id="doctors-directory">
			<?php do_action( 'sdd_before_directory' ); ?>
			<header class="sdd-hero">
				<div><span>Global Professional Directory</span><h1>Doctors</h1><p>Find verified homeopathic practitioners by location, language, experience, and consultation availability. Always confirm local licensing and professional suitability directly.</p></div>
				<div class="sdd-hero-stat"><strong><?php echo absint( $result['total'] ); ?></strong><span>verified profiles found</span></div>
			</header>
			<?php echo $this->search_form( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( ! $this->has_filters( $filters ) ) : ?>
				<?php $founder = $this->founder_card(); if ( $founder ) : ?><section class="sdd-section"><div class="sdd-section-head"><div><span>Platform Leadership</span><h2>Founder</h2></div></div><?php echo $founder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section><?php endif; ?>
				<?php $featured = $this->query_doctors( array(), 1, 6, 'featured' ); if ( $featured['items'] ) : ?><section class="sdd-section"><div class="sdd-section-head"><div><span>Selected Profiles</span><h2>Featured Doctors</h2></div></div><div class="sdd-grid"><?php foreach ( $featured['items'] as $doctor ) { echo $this->card( $doctor ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endif; ?>
				<?php $recent = $this->query_doctors( array(), 1, 6, 'recent' ); if ( $recent['items'] ) : ?><section class="sdd-section"><div class="sdd-section-head"><div><span>New to the Directory</span><h2>Recently Joined Doctors</h2></div></div><div class="sdd-grid"><?php foreach ( $recent['items'] as $doctor ) { echo $this->card( $doctor ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endif; ?>
			<?php endif; ?>
			<section class="sdd-section" aria-labelledby="sdd-results-title">
				<div class="sdd-section-head"><div><span>Verified Directory</span><h2 id="sdd-results-title"><?php echo $this->has_filters( $filters ) ? esc_html__( 'Search Results', 'doctors-directory' ) : esc_html__( 'All Doctors', 'doctors-directory' ); ?></h2></div><small>Results are ordered deterministically by featured status, profile completion, educational contributions, name, and account ID.</small></div>
				<div class="sdd-grid"><?php if ( $result['items'] ) : foreach ( $result['items'] as $doctor ) { echo $this->card( $doctor ); } else : ?><div class="sdd-empty"><h3>No doctors matched your search</h3><p>Remove one or more filters and try again.</p></div><?php endif; ?></div>
				<?php echo $this->pagination( $result, $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>
			<p class="sdd-disclaimer">Directory information is supplied by practitioners and reviewed at a foundation level. Verification is not an endorsement, treatment guarantee, or substitute for checking professional credentials in your jurisdiction.</p>
			<?php do_action( 'sdd_after_directory' ); ?>
		</main>
		<?php
		return ob_get_clean();
	}

	private function filters() {
		$mode = isset( $_GET['doctor_mode'] ) ? sanitize_key( wp_unslash( $_GET['doctor_mode'] ) ) : '';
		if ( ! in_array( $mode, array( '', 'online', 'in-person' ), true ) ) {
			$mode = '';
		}
		return array(
			'search'        => isset( $_GET['doctor_search'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_search'] ) ) : '',
			'country'       => isset( $_GET['doctor_country'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_country'] ) ) : '',
			'city'          => isset( $_GET['doctor_city'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_city'] ) ) : '',
			'language'      => isset( $_GET['doctor_language'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_language'] ) ) : '',
			'qualification' => isset( $_GET['doctor_qualification'] ) ? sanitize_text_field( wp_unslash( $_GET['doctor_qualification'] ) ) : '',
			'experience'    => isset( $_GET['doctor_experience'] ) ? min( 80, absint( $_GET['doctor_experience'] ) ) : 0,
			'mode'          => $mode,
			'accepting'     => ! empty( $_GET['doctor_accepting'] ) ? 1 : 0,
			'page'          => isset( $_GET['doctor_page'] ) ? max( 1, absint( $_GET['doctor_page'] ) ) : 1,
		);
	}

	private function has_filters( $filters ) {
		$copy = $filters;
		unset( $copy['page'] );
		return (bool) array_filter( $copy );
	}

	private function query_doctors( $filters, $page, $per_page, $order_mode ) {
		global $wpdb;
		$page       = max( 1, absint( $page ) );
		$per_page   = max( 1, min( 60, absint( $per_page ) ) );
		$conditions = array();
		$params     = array();
		$caps_key   = $wpdb->get_blog_prefix() . 'capabilities';
		$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} caps WHERE caps.user_id = u.ID AND caps.meta_key = %s AND caps.meta_value LIKE %s)";
		$params[] = $caps_key;
		$params[] = '%' . $wpdb->esc_like( '"sabri_doctor_verified"' ) . '%';
		$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} verify WHERE verify.user_id = u.ID AND verify.meta_key = '_spd_verification_status' AND verify.meta_value = 'verified')";
		$conditions[] = "NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} hidden WHERE hidden.user_id = u.ID AND hidden.meta_key = '_sdd_hidden' AND hidden.meta_value = '1')";
		$conditions[] = "NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} discoverable WHERE discoverable.user_id = u.ID AND discoverable.meta_key = '_sdd_discoverable' AND discoverable.meta_value = '0')";
		$founder_id = SDD_Helpers::founder_id();
		if ( $founder_id ) {
			$conditions[] = 'u.ID <> %d';
			$params[] = $founder_id;
		}
		if ( 'featured' === $order_mode ) {
			$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} featured_only WHERE featured_only.user_id = u.ID AND featured_only.meta_key = '_sdd_featured' AND featured_only.meta_value = '1')";
		}
		$filters = wp_parse_args( $filters, array( 'search' => '', 'country' => '', 'city' => '', 'language' => '', 'qualification' => '', 'experience' => 0, 'mode' => '', 'accepting' => 0 ) );
		if ( $filters['search'] ) {
			$like = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$conditions[] = "(u.display_name LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} specialty WHERE specialty.user_id = u.ID AND specialty.meta_key = '_spd_specialty' AND specialty.meta_value LIKE %s))";
			$params[] = $like;
			$params[] = $like;
		}
		foreach ( array( 'country' => '_spd_country', 'city' => '_spd_city', 'language' => '_spd_languages', 'qualification' => '_spd_qualification' ) as $field => $meta_key ) {
			if ( $filters[ $field ] ) {
				$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} filter_{$field} WHERE filter_{$field}.user_id = u.ID AND filter_{$field}.meta_key = %s AND filter_{$field}.meta_value LIKE %s)";
				$params[] = $meta_key;
				$params[] = '%' . $wpdb->esc_like( $filters[ $field ] ) . '%';
			}
		}
		if ( $filters['experience'] ) {
			$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} experience WHERE experience.user_id = u.ID AND experience.meta_key = '_spd_experience_years' AND CAST(experience.meta_value AS UNSIGNED) >= %d)";
			$params[] = absint( $filters['experience'] );
		}
		if ( 'online' === $filters['mode'] || 'in-person' === $filters['mode'] ) {
			$mode_key = 'online' === $filters['mode'] ? '_sdd_online_available' : '_sdd_in_person_available';
			$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} consultation_mode WHERE consultation_mode.user_id = u.ID AND consultation_mode.meta_key = %s AND consultation_mode.meta_value = '1')";
			$params[] = $mode_key;
		}
		if ( $filters['accepting'] ) {
			$conditions[] = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} accepting WHERE accepting.user_id = u.ID AND accepting.meta_key = '_sdd_accepting_patients' AND accepting.meta_value = '1')";
		}
		$where = implode( ' AND ', $conditions );
		$count_sql = "SELECT COUNT(*) FROM {$wpdb->users} u WHERE {$where}";
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$pages = max( 1, (int) ceil( $total / $per_page ) );
		$page  = min( $page, $pages );
		$completion = $this->completion_score_sql();
		$contributions = "(SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_author = u.ID AND p.post_status = 'publish' AND p.post_type IN ('snp_publication','slc_lesson','he_entry'))";
		if ( 'recent' === $order_mode ) {
			$order = 'u.user_registered DESC, u.ID DESC';
		} else {
			$order = "(EXISTS (SELECT 1 FROM {$wpdb->usermeta} featured_order WHERE featured_order.user_id = u.ID AND featured_order.meta_key = '_sdd_featured' AND featured_order.meta_value = '1')) DESC, {$completion} DESC, {$contributions} DESC, u.display_name ASC, u.ID ASC";
		}
		$offset = ( $page - 1 ) * $per_page;
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$list_sql = "SELECT u.ID FROM {$wpdb->users} u WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
		$ids = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( $list_sql, $list_params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = array();
		if ( $ids ) {
			$users = get_users( array( 'include' => $ids, 'orderby' => 'include' ) );
			$indexed = array();
			foreach ( $users as $user ) {
				$indexed[ $user->ID ] = $user;
			}
			foreach ( $ids as $id ) {
				if ( isset( $indexed[ $id ] ) && SDD_Helpers::is_public( $id ) ) {
					$items[] = $indexed[ $id ];
				}
			}
		}
		return array( 'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per_page );
	}

	private function completion_score_sql() {
		global $wpdb;
		$meta_keys = array( '_spd_profile_photo_id', '_spd_bio', '_spd_qualification', '_spd_licence_number', '_sdd_licensing_authority', '_sdd_professional_address', '_spd_experience_years', '_spd_country', '_spd_city', '_spd_languages', '_spd_phone', '_spd_whatsapp', '_spd_clinic', '_spd_consultation_modes', '_sdd_consultation_timings' );
		$parts = array( "CASE WHEN TRIM(u.display_name) <> '' THEN 1 ELSE 0 END" );
		foreach ( $meta_keys as $index => $key ) {
			$alias = 'completion_' . absint( $index );
			$parts[] = "CASE WHEN EXISTS (SELECT 1 FROM {$wpdb->usermeta} {$alias} WHERE {$alias}.user_id = u.ID AND {$alias}.meta_key = '" . esc_sql( $key ) . "' AND TRIM({$alias}.meta_value) <> '') THEN 1 ELSE 0 END";
		}
		return '(' . implode( ' + ', $parts ) . ')';
	}

	private function search_form( $f ) {
		ob_start();
		?>
		<form class="sdd-search" method="get" aria-label="Search verified doctors">
			<div class="sdd-search-grid">
				<label>Doctor’s name or specialty<input type="search" name="doctor_search" value="<?php echo esc_attr( $f['search'] ); ?>" placeholder="Search by name or specialty"></label>
				<label>Country<input name="doctor_country" value="<?php echo esc_attr( $f['country'] ); ?>"></label>
				<label>City<input name="doctor_city" value="<?php echo esc_attr( $f['city'] ); ?>"></label>
				<label>Language<input name="doctor_language" value="<?php echo esc_attr( $f['language'] ); ?>"></label>
				<label>Qualification<input name="doctor_qualification" value="<?php echo esc_attr( $f['qualification'] ); ?>"></label>
				<label>Minimum experience<select name="doctor_experience"><option value="0">Any experience</option><?php foreach ( array( 1, 3, 5, 10, 15, 20 ) as $years ) : ?><option value="<?php echo absint( $years ); ?>" <?php selected( $f['experience'], $years ); ?>><?php echo absint( $years ); ?>+ years</option><?php endforeach; ?></select></label>
				<label>Consultation<select name="doctor_mode"><option value="">Any consultation</option><option value="online" <?php selected( $f['mode'], 'online' ); ?>>Online available</option><option value="in-person" <?php selected( $f['mode'], 'in-person' ); ?>>In-person available</option></select></label>
				<label class="sdd-check"><input type="checkbox" name="doctor_accepting" value="1" <?php checked( $f['accepting'], 1 ); ?>> Accepting new patients</label>
				<button class="sdd-button" type="submit">Search Doctors</button>
			</div>
			<?php if ( $this->has_filters( $f ) ) : ?><a class="sdd-clear" href="<?php echo esc_url( remove_query_arg( array( 'doctor_search', 'doctor_country', 'doctor_city', 'doctor_language', 'doctor_qualification', 'doctor_experience', 'doctor_mode', 'doctor_accepting', 'doctor_page' ) ) ); ?>">Clear all filters</a><?php endif; ?>
		</form>
		<?php
		return ob_get_clean();
	}

	private function founder_card() {
		if ( ! SDD_Helpers::dependency_ready() ) {
			return '';
		}
		$f = SPD_Helpers::founder();
		$url = SDD_Helpers::founder_url();
		if ( ! $url ) {
			return '';
		}
		$photo = absint( $f['photo_id'] );
		ob_start();
		?><article class="sdd-card sdd-founder-card"><div class="sdd-avatar"><?php echo $photo ? wp_get_attachment_image( $photo, 'thumbnail', false, array( 'alt' => $f['name'] ) ) : esc_html( SDD_Helpers::initials( $f['name'] ) ); ?></div><div class="sdd-card-body"><span class="sdd-badge">✓ Verified Founder</span><h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $f['name'] ); ?></a></h3><p class="sdd-headline"><?php echo esc_html( $f['title'] ); ?></p><p><?php echo esc_html( $f['location'] ); ?></p><div class="sdd-meter" role="progressbar" aria-label="Founder profile completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100"><span style="width:100%"></span></div><small>Profile 100% complete</small><?php echo $this->contacts( 0, $f['phone'], $f['whatsapp'], true, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></article><?php
		return ob_get_clean();
	}

	public function card( $user ) {
		$id         = absint( $user->ID );
		$photo      = absint( SDD_Helpers::spd( $id, 'profile_photo_id', 0 ) );
		$completion = SDD_Helpers::completion( $id );
		$online     = '1' === SDD_Helpers::get( $id, 'online_available', '0' );
		$person     = '1' === SDD_Helpers::get( $id, 'in_person_available', '0' );
		$accepting  = '1' === SDD_Helpers::get( $id, 'accepting_patients', '0' );
		ob_start();
		?><article class="sdd-card"><div class="sdd-avatar"><?php echo $photo ? wp_get_attachment_image( $photo, 'thumbnail', false, array( 'alt' => $user->display_name, 'loading' => 'lazy' ) ) : esc_html( SDD_Helpers::initials( $user->display_name ) ); ?></div><div class="sdd-card-body"><span class="sdd-badge">✓ Verified Doctor</span><h3><a href="<?php echo esc_url( SDD_Helpers::profile_url( $id ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a></h3><p class="sdd-headline"><?php echo esc_html( SDD_Helpers::get( $id, 'headline', SDD_Helpers::spd( $id, 'specialty', 'Homeopathic practitioner' ) ) ); ?></p><p><?php echo esc_html( trim( SDD_Helpers::spd( $id, 'city' ) . ', ' . SDD_Helpers::spd( $id, 'country' ), ', ' ) ); ?></p><div class="sdd-tags"><?php if ( $online ) : ?><span>Online</span><?php endif; ?><?php if ( $person ) : ?><span>In person</span><?php endif; ?><?php if ( $accepting ) : ?><span>Accepting patients</span><?php endif; ?><?php if ( SDD_Helpers::spd( $id, 'languages' ) ) : ?><span><?php echo esc_html( SDD_Helpers::spd( $id, 'languages' ) ); ?></span><?php endif; ?></div><div class="sdd-meter" role="progressbar" aria-label="Profile completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo absint( $completion ); ?>"><span style="width:<?php echo absint( $completion ); ?>%"></span></div><small>Profile <?php echo absint( $completion ); ?>% complete · <?php echo absint( SDD_Helpers::contributions( $id ) ); ?> contributions</small><?php echo $this->contacts( $id, SDD_Helpers::spd( $id, 'phone' ), SDD_Helpers::spd( $id, 'whatsapp' ), SDD_Helpers::contact_is_public( $id, 'phone' ), SDD_Helpers::contact_is_public( $id, 'whatsapp' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><a class="sdd-profile-link" href="<?php echo esc_url( SDD_Helpers::profile_url( $id ) ); ?>">View Profile</a></div></article><?php
		return ob_get_clean();
	}

	public function contacts( $user_id, $phone, $whatsapp, $show_phone, $show_whatsapp ) {
		$out   = '<div class="sdd-contact" aria-label="Professional contact options">';
		$clean = SDD_Helpers::phone( $phone );
		$out  .= $show_phone && $clean ? '<a href="tel:' . esc_attr( $clean ) . '">Phone</a>' : '<span aria-disabled="true">Phone Private</span>';
		$wa    = SDD_Helpers::whatsapp( $whatsapp );
		$out  .= $show_whatsapp && $wa ? '<a class="is-whatsapp" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener noreferrer">WhatsApp</a>' : '<span aria-disabled="true">WhatsApp Private</span>';
		if ( $user_id ) {
			$message = SDD_Helpers::integration_url( 'message', $user_id );
			if ( $message ) {
				$out .= '<a class="is-message" href="' . esc_url( $message ) . '">Message</a>';
			}
		}
		return $out . '</div>';
	}

	private function pagination( $result, $filters ) {
		if ( $result['pages'] <= 1 ) {
			return '';
		}
		$big  = 999999999;
		$base = add_query_arg( 'doctor_page', $big, remove_query_arg( 'doctor_page' ) );
		$base = str_replace( (string) $big, '%#%', esc_url( $base ) );
		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $result['page'],
				'total'     => $result['pages'],
				'type'      => 'list',
				'prev_text' => __( 'Previous', 'doctors-directory' ),
				'next_text' => __( 'Next', 'doctors-directory' ),
			)
		);
		return $links ? '<nav class="sdd-pagination" aria-label="Doctors directory pages">' . $links . '</nav>' : '';
	}
}
