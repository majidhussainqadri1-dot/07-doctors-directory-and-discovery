<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Helpers_Trait_1 {
	public static function meta( $user_id, $key, $default = '' ) {
		$value = get_user_meta( absint( $user_id ), self::META . $key, true );
		if ( '' === $value ) {
			$value = get_user_meta( absint( $user_id ), self::LEGACY_META . $key, true );
		}
		return '' === $value ? $default : $value;
	}
	public static function set_meta( $user_id, $key, $value ) {
		return update_user_meta( absint( $user_id ), self::META . $key, $value );
	}
	public static function founder_id() {
		$founder_id = absint( get_option( 'smc_founder_user_id', 0 ) );
		if ( ! $founder_id ) {
			$founder_id = absint( get_option( 'spf_founder_user_id', 0 ) );
		}
		return $founder_id;
	}
	public static function is_founder( $user_id ) {
		return absint( $user_id ) > 0 && absint( $user_id ) === self::founder_id();
	}
	public static function uuid_from_user( $user_id ) {
		$user_id = absint( $user_id );
		$existing = (string) get_user_meta( $user_id, '_ddd_public_id', true );
		if ( preg_match( '/^[a-f0-9-]{36}$/i', $existing ) ) {
			return strtolower( $existing );
		}
		$hash = md5( 'ddd|' . $user_id . '|' . wp_salt( 'auth' ) );
		$uuid = substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-4' . substr( $hash, 13, 3 ) . '-a' . substr( $hash, 17, 3 ) . '-' . substr( $hash, 20, 12 );
		update_user_meta( $user_id, '_ddd_public_id', $uuid );
		return $uuid;
	}
	public static function list_value( $value ) {
		if ( is_array( $value ) ) {
			$list = $value;
		} else {
			$list = preg_split( '/[,;\n|]+/u', (string) $value );
		}
		$list = array_map( 'sanitize_text_field', array_map( 'trim', (array) $list ) );
		$list = array_values( array_unique( array_filter( $list ) ) );
		return array_slice( $list, 0, 30 );
	}
	public static function decimal_or_null( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$value = preg_replace( '/[^0-9.]/', '', (string) $value );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return null;
		}
		return round( (float) $value, 2 );
	}
	public static function mysql_datetime( $value ) {
		if ( ! $value ) {
			return '';
		}
		$timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
	}
	public static function normalize_token( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = remove_accents( $value );
		if ( class_exists( 'Transliterator' ) ) {
			$converted = transliterator_transliterate( 'Any-Latin; Latin-ASCII; Lower()', $value );
			if ( is_string( $converted ) && '' !== $converted ) {
				$value .= ' ' . $converted;
			}
		}
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );
		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}
	public static function public_profile_url( $public_id ) {
		return home_url( user_trailingslashit( 'doctors/' . rawurlencode( (string) $public_id ) ) );
	}
	public static function trace_id() {
		try {
			return bin2hex( random_bytes( 8 ) );
		} catch ( Exception $e ) {
			return substr( wp_hash( microtime( true ) . '|' . wp_rand() ), 0, 16 );
		}
	}
	public static function safe_error( $code, $message, $status = 400, $extra = array() ) {
		$trace_id = self::trace_id();
		DDD_Observability::log( 'warning', $code, array( 'trace_id' => $trace_id, 'status' => $status ) );
		return new WP_Error( sanitize_key( $code ), $message, array_merge( array( 'status' => $status, 'trace_id' => $trace_id ), $extra ) );
	}
	public static function rate_limit( $scope, $actor, $limit, $window ) {
		$key = 'ddd_rl_' . md5( sanitize_key( $scope ) . '|' . sanitize_text_field( (string) $actor ) );
		$count = absint( get_transient( $key ) );
		if ( $count >= absint( $limit ) ) {
			return false;
		}
		set_transient( $key, $count + 1, max( 60, absint( $window ) ) );
		return true;
	}
	public static function cursor_encode( $payload ) {
		$json = wp_json_encode( $payload );
		$body = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
		$sig = hash_hmac( 'sha256', $body, wp_salt( 'nonce' ) );
		return $body . '.' . $sig;
	}
	public static function cursor_decode( $cursor ) {
		$parts = explode( '.', (string) $cursor, 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ), $parts[1] ) ) {
			return array();
		}
		$decoded = base64_decode( strtr( $parts[0], '-_', '+/' ), true );
		$data = json_decode( (string) $decoded, true );
		return is_array( $data ) ? $data : array();
	}
	public static function initials( $name ) {
		$words = preg_split( '/\s+/u', trim( (string) $name ) );
		$out = '';
		foreach ( array_slice( array_filter( $words ), 0, 2 ) as $word ) {
			$out .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1, 'UTF-8' ) : substr( $word, 0, 1 );
		}
		return $out ? $out : 'DR';
	}
	public static function current_ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
	public static function idempotency_key( $request = null ) {
		$key = '';
		if ( $request instanceof WP_REST_Request ) {
			$key = (string) $request->get_header( 'Idempotency-Key' );
		}
		if ( '' === $key && isset( $_SERVER['HTTP_IDEMPOTENCY_KEY'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_SERVER['HTTP_IDEMPOTENCY_KEY'] ) );
		}
		return preg_match( '/^[A-Za-z0-9._:-]{8,128}$/', $key ) ? $key : '';
	}
}
