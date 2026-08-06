<?php
defined( 'ABSPATH' ) || exit;

trait DDD_Repository_Trait_8 {
	public static function expire_features() {
		global $wpdb;
		$table = self::table( 'projection' );
		$now = current_time( 'mysql', true );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT doctor_id FROM {$table} WHERE featured=1 AND feature_end IS NOT NULL AND feature_end<=%s LIMIT 500", $now ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $ids as $id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET featured=0,feature_label='',version=version+1,updated_at=%s WHERE doctor_id=%d AND featured=1",
					$now,
					absint( $id )
				)
			); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::outbox_add( 'DoctorDirectoryFeatured.v1', (string) absint( $id ), array( 'doctor_id' => absint( $id ), 'featured' => false, 'reason' => 'expired' ) );
			self::invalidate_cache( $id );
		}
		return count( $ids );
	}
	public static function invalidate_cache( $doctor_id = 0 ) {
		$version = absint( get_option( 'ddd_cache_version', 1 ) ) + 1;
		update_option( 'ddd_cache_version', $version, false );
		if ( $doctor_id ) {
			clean_user_cache( absint( $doctor_id ) );
		}
		do_action( 'ddd_directory_cache_invalidated', absint( $doctor_id ), $version );
	}
}
