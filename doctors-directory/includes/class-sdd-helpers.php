<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Helpers {
	const META = '_sdd_';

	public static function get( $user_id, $key, $default = '' ) {
		$value = get_user_meta( absint( $user_id ), self::META . $key, true );
		return '' === $value ? $default : $value;
	}

	public static function spd( $user_id, $key, $default = '' ) {
		return class_exists( 'SPD_Helpers' ) ? SPD_Helpers::get( absint( $user_id ), $key, $default ) : $default;
	}

	public static function founder_id() {
		return absint( get_option( 'spf_founder_user_id', 0 ) );
	}

	public static function is_founder( $user_id ) {
		return absint( $user_id ) && absint( $user_id ) === self::founder_id();
	}

	public static function is_verified( $user_id ) {
		return class_exists( 'SPD_Helpers' ) && SPD_Helpers::is_doctor( $user_id ) && 'verified' === SPD_Helpers::verification_status( $user_id );
	}

	public static function is_public( $user_id ) {
		return self::is_verified( $user_id ) && '1' !== self::get( $user_id, 'hidden', '0' ) && '0' !== self::get( $user_id, 'discoverable', '1' );
	}

	public static function profile_url( $user_id ) {
		return class_exists( 'SPD_Helpers' ) ? SPD_Helpers::profile_url( $user_id ) : get_author_posts_url( absint( $user_id ) );
	}

	public static function founder_url() {
		$map = (array) get_option( 'spd_page_map', array() );
		return ! empty( $map['founder'] ) ? get_permalink( $map['founder'] ) : home_url( '/sabri-founder/' );
	}

	public static function completion( $user_id ) {
		if ( self::is_founder( $user_id ) ) {
			return 100;
		}
		$checks = array(
			(bool) absint( self::spd( $user_id, 'profile_photo_id', 0 ) ),
			(bool) trim( get_the_author_meta( 'display_name', $user_id ) ),
			(bool) trim( self::spd( $user_id, 'bio' ) ),
			(bool) trim( self::spd( $user_id, 'qualification' ) ),
			(bool) trim( self::spd( $user_id, 'experience_years' ) ),
			(bool) trim( self::spd( $user_id, 'country' ) ),
			(bool) trim( self::spd( $user_id, 'city' ) ),
			(bool) trim( self::spd( $user_id, 'languages' ) ),
			(bool) trim( self::spd( $user_id, 'phone' ) ),
			(bool) trim( self::spd( $user_id, 'whatsapp' ) ),
			(bool) trim( self::spd( $user_id, 'clinic' ) ),
			(bool) trim( self::spd( $user_id, 'consultation_modes' ) ),
		);
		return (int) round( 100 * count( array_filter( $checks ) ) / count( $checks ) );
	}

	public static function contact_is_public( $user_id, $kind ) {
		if ( self::is_founder( $user_id ) ) {
			return true;
		}
		$value = self::get( $user_id, 'public_' . $kind, '' );
		if ( '' !== $value ) {
			return '1' === $value;
		}
		return '1' === self::spd( $user_id, 'public_contact', '0' );
	}

	public static function phone( $number ) {
		return class_exists( 'SPD_Helpers' ) ? SPD_Helpers::clean_phone( $number ) : preg_replace( '/[^0-9+]/', '', (string) $number );
	}

	public static function whatsapp( $number ) {
		return class_exists( 'SPD_Helpers' ) ? SPD_Helpers::whatsapp_url( $number ) : '';
	}

	public static function initials( $name ) {
		$words = preg_split( '/\s+/', trim( $name ) );
		$out = '';
		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$out .= strtoupper( substr( $word, 0, 1 ) );
		}
		return $out ? $out : 'DR';
	}

	public static function contributions( $user_id ) {
		$total = 0;
		foreach ( array( 'snp_publication', 'slc_lesson', 'he_entry' ) as $type ) {
			if ( post_type_exists( $type ) ) {
				$total += (int) count_user_posts( $user_id, $type, true );
			}
		}
		return $total;
	}

	public static function navigation() {
		$specs = array(
			'home' => array( 'Home', 'sabri-platform-home' ), 'news' => array( 'News', 'sabri-news' ), 'founder' => array( 'Founder', 'sabri-founder' ),
			'learn' => array( 'Learn Sabri Classical Homeopathy', 'learn-sabri-classical-homeopathy' ), 'encyclopedia' => array( 'Encyclopedia', 'homeopathy-encyclopedia' ),
			'doctors' => array( 'Doctors', 'homeopathy-doctors' ), 'clinic' => array( 'Worldwide Clinic', 'worldwide-clinic' ), 'videos' => array( 'Video Wall', 'video-wall' ),
			'reels' => array( 'Reels', 'reels' ), 'pdf' => array( 'PDF Library', 'pdf-library' ), 'radar' => array( 'Radar', 'homeopathy-radar' ),
			'ai' => array( 'Sabri Classical Homeopathy AI', 'sabri-classical-homeopathy-ai' ), 'network' => array( 'Network', 'homeopathy-network' ), 'marketplace' => array( 'Marketplace', 'homeopathy-marketplace' ),
		);
		$pages = (array) get_option( 'spf_page_map', array() );
		$out = '<nav class="sdd-main-nav" aria-label="Main platform navigation">';
		foreach ( $specs as $key => $spec ) {
			$url = ! empty( $pages[ $key ] ) ? get_permalink( $pages[ $key ] ) : home_url( '/' . $spec[1] . '/' );
			$out .= '<a class="' . ( 'doctors' === $key ? 'is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $spec[0] ) . '</a>';
		}
		return $out . '</nav>';
	}
}

