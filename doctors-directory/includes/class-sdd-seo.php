<?php
defined( 'ABSPATH' ) || exit;

final class DDD_SEO {
	public function hooks() {
		add_action( 'wp_head', array( $this, 'schema' ), 40 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'get_canonical_url', array( $this, 'canonical_url' ), 10, 2 );
		add_action( 'wp_sitemaps_init', array( $this, 'register_sitemap' ) );
	}

	private function is_directory_page() {
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		return ! empty( $map['directory'] ) && is_page( absint( $map['directory'] ) );
	}

	private function is_status_page() {
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		return ! empty( $map['status'] ) && is_page( absint( $map['status'] ) );
	}

	public function schema() {
		if ( ! $this->is_directory_page() ) {
			return;
		}
		$result = DDD_Repository::search( array( 'limit' => 12 ) );
		if ( is_wp_error( $result ) ) { return; }
		$items = array();
		$position = 1;
		foreach ( $result['items'] as $doctor ) {
			$person = array(
				'@type'    => 'Person',
				'@id'      => $doctor['public_directory_url'] . '#person',
				'name'     => $doctor['display_name'],
				'jobTitle' => $doctor['professional_title'] ? $doctor['professional_title'] : $doctor['specialty'],
				'url'      => $doctor['profile_url'] ? $doctor['profile_url'] : $doctor['public_directory_url'],
			);
			if ( $doctor['languages'] ) {
				$person['knowsLanguage'] = $doctor['languages'];
			}
			if ( $doctor['city'] || $doctor['country'] ) {
				$person['homeLocation'] = array( '@type' => 'Place', 'name' => trim( $doctor['city'] . ', ' . $doctor['country'], ', ' ) );
			}
			if ( $doctor['qualification'] ) {
				$person['hasCredential'] = array( '@type' => 'EducationalOccupationalCredential', 'name' => $doctor['qualification'] );
			}
			$items[] = array( '@type' => 'ListItem', 'position' => $position++, 'item' => $person );
		}
		$data = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'CollectionPage',
			'@id'         => $this->directory_url() . '#directory',
			'url'         => $this->directory_url(),
			'name'        => __( 'Doctors Directory and Discovery', DDD_TEXT_DOMAIN ),
			'description' => __( 'Publicly eligible verified homeopathic doctors. Verification is not an endorsement or treatment guarantee.', DDD_TEXT_DOMAIN ),
			'mainEntity'  => array( '@type' => 'ItemList', 'itemListElement' => $items ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
	}

	public function robots( $robots ) {
		if ( $this->is_status_page() ) {
			$robots['noindex'] = true;
			$robots['noarchive'] = true;
			$robots['nosnippet'] = true;
		}
		if ( $this->is_directory_page() && $this->has_filters() ) {
			$robots['noindex'] = true;
			$robots['follow'] = true;
		}
		return $robots;
	}

	private function has_filters() {
		foreach ( array( 'doctor_search','doctor_specialty','doctor_country','doctor_city','doctor_language','doctor_qualification','doctor_experience','doctor_mode','doctor_currency','doctor_fee_min','doctor_fee_max','doctor_accepting','doctor_cursor' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== (string) $_GET[ $key ] ) {
				return true;
			}
		}
		return false;
	}

	private function directory_url() {
		$map = (array) get_option( DDD_Activator::PAGE_MAP_OPTION, array() );
		return ! empty( $map['directory'] ) && 'publish' === get_post_status( absint( $map['directory'] ) ) ? get_permalink( absint( $map['directory'] ) ) : home_url( '/doctors/' );
	}

	public function canonical_url( $canonical, $post ) {
		if ( $this->is_directory_page() ) {
			return $this->directory_url();
		}
		if ( get_query_var( 'ddd_doctor_public_id' ) ) {
			return DDD_Helpers::public_profile_url( get_query_var( 'ddd_doctor_public_id' ) );
		}
		return $canonical;
	}

	public function register_sitemap( $sitemaps ) {
		if ( class_exists( 'WP_Sitemaps_Provider' ) ) {
			$sitemaps->registry->add_provider( 'ddd-doctors', new DDD_Doctor_Sitemap_Provider() );
		}
	}
}

if ( class_exists( 'WP_Sitemaps_Provider' ) ) {
	final class DDD_Doctor_Sitemap_Provider extends WP_Sitemaps_Provider {
		const PER_PAGE = 1000;

		public function __construct() {
			$this->name = 'ddd-doctors';
			$this->object_type = 'user';
		}

		public function get_url_list( $page_num, $object_subtype = '' ) {
			global $wpdb;
			$page_num = max( 1, absint( $page_num ) );
			$offset = ( $page_num - 1 ) * self::PER_PAGE;
			$table = DDD_Repository::table( 'projection' );
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT public_id,profile_url,updated_at FROM {$table} WHERE eligible=1 ORDER BY doctor_id ASC LIMIT %d OFFSET %d", self::PER_PAGE, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$urls = array();
			foreach ( $rows as $row ) {
				$entry = array( 'loc' => ! empty( $row['profile_url'] ) ? esc_url_raw( $row['profile_url'] ) : DDD_Helpers::public_profile_url( $row['public_id'] ) );
				if ( $row['updated_at'] && '0000-00-00 00:00:00' !== $row['updated_at'] ) {
					$entry['lastmod'] = gmdate( DATE_W3C, strtotime( $row['updated_at'] . ' UTC' ) );
				}
				$urls[] = $entry;
			}
			return $urls;
		}

		public function get_max_num_pages( $object_subtype = '' ) {
			global $wpdb;
			$table = DDD_Repository::table( 'projection' );
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE eligible=1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return max( 1, (int) ceil( $total / self::PER_PAGE ) );
		}
	}
}

if ( ! class_exists( 'SDD_SEO' ) ) {
	class_alias( 'DDD_SEO', 'SDD_SEO' );
}
