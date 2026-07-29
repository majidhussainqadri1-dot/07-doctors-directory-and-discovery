<?php
defined( 'ABSPATH' ) || exit;

final class SDD_SEO {
	public function hooks() {
		add_action( 'wp_head', array( $this, 'schema' ), 40 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
	}

	public function schema() {
		$map = (array) get_option( 'sdd_page_map', array() );
		if ( empty( $map['profile'] ) || ! is_page( $map['profile'] ) ) { return; }
		$value = isset( $_GET['user'] ) ? sanitize_user( wp_unslash( $_GET['user'] ) ) : '';
		$user = $value ? ( ctype_digit( $value ) ? get_userdata( absint( $value ) ) : get_user_by( 'slug', $value ) ) : false;
		if ( ! $user || ! SDD_Helpers::is_public( $user->ID ) ) { return; }
		$photo = absint( SDD_Helpers::spd( $user->ID, 'profile_photo_id', 0 ) );
		$person = array( '@type' => 'Person', '@id' => SDD_Helpers::profile_url( $user->ID ) . '#person', 'name' => $user->display_name, 'jobTitle' => SDD_Helpers::get( $user->ID, 'headline', SDD_Helpers::spd( $user->ID, 'specialty', 'Homeopathic practitioner' ) ), 'description' => wp_strip_all_tags( SDD_Helpers::spd( $user->ID, 'bio' ) ), 'knowsLanguage' => array_map( 'trim', explode( ',', SDD_Helpers::spd( $user->ID, 'languages' ) ) ) );
		if ( $photo ) { $person['image'] = wp_get_attachment_image_url( $photo, 'large' ); }
		$location = trim( SDD_Helpers::spd( $user->ID, 'city' ) . ', ' . SDD_Helpers::spd( $user->ID, 'country' ), ', ' );
		if ( $location ) { $person['homeLocation'] = array( '@type' => 'Place', 'name' => $location ); }
		$data = array( '@context' => 'https://schema.org', '@type' => 'ProfilePage', '@id' => SDD_Helpers::profile_url( $user->ID ) . '#profile', 'url' => SDD_Helpers::profile_url( $user->ID ), 'name' => $user->display_name . ' — Verified Doctor Profile', 'mainEntity' => $person );
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}

	public function robots( $robots ) {
		$map = (array) get_option( 'sdd_page_map', array() );
		if ( ! empty( $map['settings'] ) && is_page( $map['settings'] ) ) { $robots['noindex'] = true; }
		if ( ! empty( $map['profile'] ) && is_page( $map['profile'] ) ) {
			$value = isset( $_GET['user'] ) ? sanitize_user( wp_unslash( $_GET['user'] ) ) : '';
			$user = $value ? ( ctype_digit( $value ) ? get_userdata( absint( $value ) ) : get_user_by( 'slug', $value ) ) : false;
			if ( ! $user || ! SDD_Helpers::is_public( $user->ID ) ) { $robots['noindex'] = true; }
		}
		return $robots;
	}
}

