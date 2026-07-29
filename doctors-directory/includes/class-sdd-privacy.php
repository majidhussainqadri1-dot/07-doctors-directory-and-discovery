<?php
defined( 'ABSPATH' ) || exit;

final class SDD_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['doctors-directory'] = array( 'exporter_friendly_name' => 'Doctors Directory', 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'data' => array(), 'done' => true ); }
		$data = array();
		foreach ( array( 'headline', 'website', 'linkedin', 'facebook', 'online_available', 'in_person_available', 'accepting_patients', 'public_phone', 'public_whatsapp', 'discoverable', 'featured', 'hidden' ) as $key ) {
			$value = SDD_Helpers::get( $user->ID, $key, '' );
			if ( '' !== $value ) { $data[] = array( 'name' => ucwords( str_replace( '_', ' ', $key ) ), 'value' => $value ); }
		}
		return array( 'data' => $data ? array( array( 'group_id' => 'doctors-directory', 'group_label' => 'Doctors Directory', 'item_id' => 'doctor-' . $user->ID, 'data' => $data ) ) : array(), 'done' => true );
	}

	public function erasers( $erasers ) {
		$erasers['doctors-directory'] = array( 'eraser_friendly_name' => 'Doctors Directory', 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		foreach ( array( 'headline', 'website', 'linkedin', 'facebook', 'online_available', 'in_person_available', 'accepting_patients', 'public_phone', 'public_whatsapp', 'discoverable' ) as $key ) { delete_user_meta( $user->ID, '_sdd_' . $key ); }
		global $wpdb; $wpdb->update( $wpdb->prefix . 'sdd_reports', array( 'details' => '[Removed through privacy request]', 'reporter_id' => 0 ), array( 'reporter_id' => $user->ID ), array( '%s', '%d' ), array( '%d' ) );
		return array( 'items_removed' => true, 'items_retained' => true, 'messages' => array( 'Administrative visibility decisions may be retained for platform integrity.' ), 'done' => true );
	}
}

