<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Helpers {
	const META = '_sdd_';

	public static function dependency_ready() {
		$methods = array( 'get', 'is_doctor', 'verification_status', 'status_label', 'profile_url', 'founder', 'audit' );
		if ( ! class_exists( 'SPD_Helpers' ) ) {
			return false;
		}
		foreach ( $methods as $method ) {
			if ( ! is_callable( array( 'SPD_Helpers', $method ) ) ) {
				return false;
			}
		}
		return ! defined( 'SPD_VERSION' ) || version_compare( SPD_VERSION, SDD_MIN_SPD_VERSION, '>=' );
	}

	public static function dependency_message() {
		if ( ! class_exists( 'SPD_Helpers' ) ) {
			return __( 'Activate File 03 — Sabri Profiles and Doctors before using this module.', 'doctors-directory' );
		}
		if ( defined( 'SPD_VERSION' ) && version_compare( SPD_VERSION, SDD_MIN_SPD_VERSION, '<' ) ) {
			return sprintf( __( 'Update File 03 to version %s or later.', 'doctors-directory' ), SDD_MIN_SPD_VERSION );
		}
		return __( 'File 03 is active but its required public API is incomplete or incompatible.', 'doctors-directory' );
	}

	public static function get( $user_id, $key, $default = '' ) {
		$value = get_user_meta( absint( $user_id ), self::META . $key, true );
		return '' === $value ? $default : $value;
	}

	public static function spd( $user_id, $key, $default = '' ) {
		return self::dependency_ready() ? SPD_Helpers::get( absint( $user_id ), $key, $default ) : $default;
	}

	public static function founder_id() {
		return absint( get_option( 'spf_founder_user_id', 0 ) );
	}

	public static function is_founder( $user_id ) {
		return absint( $user_id ) && absint( $user_id ) === self::founder_id();
	}

	public static function verification_status( $user_id ) {
		return self::dependency_ready() ? SPD_Helpers::verification_status( absint( $user_id ) ) : 'pending';
	}

	public static function is_verified( $user_id ) {
		return self::dependency_ready() && SPD_Helpers::is_doctor( $user_id ) && 'verified' === self::verification_status( $user_id );
	}

	public static function is_public( $user_id ) {
		return self::is_verified( $user_id )
			&& ! self::is_founder( $user_id )
			&& '1' !== self::get( $user_id, 'hidden', '0' )
			&& '0' !== self::get( $user_id, 'discoverable', '1' );
	}

	public static function status_label( $user_id ) {
		$status = self::verification_status( $user_id );
		return self::dependency_ready() ? SPD_Helpers::status_label( $status ) : ucfirst( str_replace( '_', ' ', $status ) );
	}

	public static function profile_url( $user_id ) {
		return self::dependency_ready() ? SPD_Helpers::profile_url( $user_id ) : get_author_posts_url( absint( $user_id ) );
	}

	public static function founder_url() {
		$map = (array) get_option( 'spd_page_map', array() );
		return ! empty( $map['founder'] ) && 'publish' === get_post_status( absint( $map['founder'] ) ) ? get_permalink( absint( $map['founder'] ) ) : '';
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
			(bool) trim( self::spd( $user_id, 'licence_number' ) ),
			(bool) trim( self::get( $user_id, 'licensing_authority' ) ),
			(bool) trim( self::get( $user_id, 'professional_address' ) ),
			(bool) trim( self::spd( $user_id, 'experience_years' ) ),
			(bool) trim( self::spd( $user_id, 'country' ) ),
			(bool) trim( self::spd( $user_id, 'city' ) ),
			(bool) trim( self::spd( $user_id, 'languages' ) ),
			(bool) trim( self::spd( $user_id, 'phone' ) ),
			(bool) trim( self::spd( $user_id, 'whatsapp' ) ),
			(bool) trim( self::spd( $user_id, 'clinic' ) ),
			(bool) trim( self::spd( $user_id, 'consultation_modes' ) ),
			(bool) trim( self::get( $user_id, 'consultation_timings' ) ),
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
		return self::dependency_ready() ? SPD_Helpers::clean_phone( $number ) : preg_replace( '/[^0-9+]/', '', (string) $number );
	}

	public static function whatsapp( $number ) {
		return self::dependency_ready() ? SPD_Helpers::whatsapp_url( $number ) : '';
	}

	public static function initials( $name ) {
		$words = preg_split( '/\s+/u', trim( (string) $name ) );
		$out   = '';
		foreach ( array_slice( array_filter( $words ), 0, 2 ) as $word ) {
			$out .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1, 'UTF-8' ) : substr( $word, 0, 1 );
		}
		return $out ? ( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out, 'UTF-8' ) : strtoupper( $out ) ) : 'DR';
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

	public static function published_page_url( $page_id, $args = array() ) {
		$page_id = absint( $page_id );
		if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}
		$url = get_permalink( $page_id );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	public static function integration_url( $type, $user_id ) {
		$user_id = absint( $user_id );
		$url     = '';
		if ( 'message' === $type ) {
			$url = self::published_page_url( get_option( 'sn_network_page_id', 0 ), array( 'recipient' => $user_id, 'sdd_action' => 'message' ) );
		} elseif ( 'appointment' === $type ) {
			$map = (array) get_option( 'swc_page_map', array() );
			$url = self::published_page_url( ! empty( $map['request'] ) ? $map['request'] : 0, array( 'doctor_id' => $user_id ) );
		} elseif ( 'clinic' === $type ) {
			$map = (array) get_option( 'swc_page_map', array() );
			$url = self::published_page_url( ! empty( $map['clinic'] ) ? $map['clinic'] : 0, array( 'doctor_id' => $user_id ) );
		}
		$url = (string) apply_filters( 'sdd_integration_url', $url, $type, $user_id );
		if ( $url && ! is_user_logged_in() && in_array( $type, array( 'message', 'appointment' ), true ) ) {
			$url = wp_login_url( $url );
		}
		return $url;
	}

	public static function safe_page_url( $map, $key ) {
		return ! empty( $map[ $key ] ) ? self::published_page_url( $map[ $key ] ) : '';
	}

	public static function maybe_audit( $user_id, $old, $new, $reason ) {
		if ( $old === $new || ! self::dependency_ready() ) {
			return;
		}
		SPD_Helpers::audit( $user_id, $old, $new, $reason );
	}
}
