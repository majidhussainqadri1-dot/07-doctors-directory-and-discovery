<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_3 {
	private static function completeness( $profile, $clinic ) {
		$checks = array(
			! empty( $profile['display_name'] ),
			! empty( $profile['professional_title'] ),
			! empty( $profile['specialty'] ),
			! empty( $profile['country'] ),
			! empty( $profile['city'] ),
			! empty( $profile['languages'] ),
			! empty( $profile['qualification'] ),
			! empty( $profile['experience_years'] ),
			! empty( $profile['avatar_id'] ),
			! empty( $profile['profile_url'] ),
			! empty( $clinic['consultation_modes'] ),
			! empty( $clinic['clinic_url'] ) || ! empty( $clinic['appointment_url'] ),
		);
		return (int) round( 100 * count( array_filter( $checks ) ) / count( $checks ) );
	}
	private static function quality_score( $eligibility, $completeness, $featured ) {
		$score = $completeness * 0.55;
		$score += ! empty( $eligibility['clinic']['accepting_patients'] ) ? 10 : 0;
		$score += ! empty( $eligibility['clinic']['consultation_modes'] ) ? 8 : 0;
		$score += ! empty( $eligibility['verification']['effective_at'] ) ? 7 : 0;
		$score += $featured ? 3 : 0; // Featured is bounded and never dominates relevance.
		return min( 100, round( $score, 3 ) );
	}
	public static function taxonomy_normalize( $type, $value ) {
		global $wpdb;
		$normalized = DDD_Helpers::normalize_token( $value );
		if ( '' === $normalized ) {
			return '';
		}
		$table = self::table( 'taxonomy' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT canonical_key,aliases_json FROM {$table} WHERE type=%s AND status='active'", sanitize_key( $type ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $row ) {
			$aliases = json_decode( (string) $row['aliases_json'], true );
			$aliases = array_merge( array( $row['canonical_key'] ), is_array( $aliases ) ? $aliases : array() );
			foreach ( $aliases as $alias ) {
				if ( $normalized === DDD_Helpers::normalize_token( $alias ) ) {
					return sanitize_title( $row['canonical_key'] );
				}
		}
		}
		return $normalized;
	}
}
