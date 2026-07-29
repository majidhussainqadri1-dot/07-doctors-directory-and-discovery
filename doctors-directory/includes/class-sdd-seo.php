<?php
defined( 'ABSPATH' ) || exit;

final class SDD_SEO {
	public function hooks() {
		add_action( 'wp_head', array( $this, 'schema' ), 40 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'document_title_parts', array( $this, 'title' ) );
		add_filter( 'get_canonical_url', array( $this, 'canonical_url' ), 10, 2 );
		add_action( 'wp_sitemaps_init', array( $this, 'register_sitemap' ) );
	}

	private function requested_user() {
		$value = isset( $_GET['user'] ) ? sanitize_user( wp_unslash( $_GET['user'] ), true ) : '';
		return $value ? ( ctype_digit( $value ) ? get_userdata( absint( $value ) ) : get_user_by( 'slug', $value ) ) : false;
	}

	private function is_profile_page() {
		$map = (array) get_option( 'sdd_page_map', array() );
		return ! empty( $map['profile'] ) && is_page( absint( $map['profile'] ) );
	}

	public function schema() {
		if ( ! $this->is_profile_page() ) {
			return;
		}
		$user = $this->requested_user();
		if ( ! $user || ! SDD_Helpers::is_public( $user->ID ) ) {
			return;
		}
		$url      = SDD_Helpers::profile_url( $user->ID );
		$photo    = absint( SDD_Helpers::spd( $user->ID, 'profile_photo_id', 0 ) );
		$languages = array_values( array_filter( array_map( 'trim', preg_split( '/[,;\n]+/', (string) SDD_Helpers::spd( $user->ID, 'languages' ) ) ) ) );
		$person = array(
			'@type'       => 'Person',
			'@id'         => $url . '#person',
			'name'        => $user->display_name,
			'jobTitle'    => SDD_Helpers::get( $user->ID, 'headline', SDD_Helpers::spd( $user->ID, 'specialty', 'Homeopathic practitioner' ) ),
			'description' => wp_strip_all_tags( SDD_Helpers::spd( $user->ID, 'bio' ) ),
		);
		if ( $languages ) {
			$person['knowsLanguage'] = $languages;
		}
		if ( $photo ) {
			$person['image'] = wp_get_attachment_image_url( $photo, 'large' );
		}
		$location = trim( SDD_Helpers::spd( $user->ID, 'city' ) . ', ' . SDD_Helpers::spd( $user->ID, 'country' ), ', ' );
		if ( $location ) {
			$person['homeLocation'] = array( '@type' => 'Place', 'name' => $location );
		}
		$qualification = trim( (string) SDD_Helpers::spd( $user->ID, 'qualification' ) );
		$license       = trim( (string) SDD_Helpers::spd( $user->ID, 'licence_number' ) );
		$authority     = trim( (string) SDD_Helpers::get( $user->ID, 'licensing_authority' ) );
		if ( $qualification || $license || $authority ) {
			$credential = array( '@type' => 'EducationalOccupationalCredential' );
			if ( $qualification ) {
				$credential['name'] = $qualification;
			}
			if ( $license ) {
				$credential['identifier'] = $license;
			}
			if ( $authority ) {
				$credential['recognizedBy'] = array( '@type' => 'Organization', 'name' => $authority );
			}
			$person['hasCredential'] = $credential;
		}
		$data = array( '@context' => 'https://schema.org', '@type' => 'ProfilePage', '@id' => $url . '#profile', 'url' => $url, 'name' => $user->display_name . ' — Verified Doctor Profile', 'mainEntity' => $person );
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
	}

	public function canonical_url( $canonical, $post ) {
		if ( $this->is_profile_page() ) {
			$user = $this->requested_user();
			if ( $user && SDD_Helpers::is_public( $user->ID ) ) {
				return SDD_Helpers::profile_url( $user->ID );
			}
		}
		return $canonical;
	}

	public function robots( $robots ) {
		$map = (array) get_option( 'sdd_page_map', array() );
		if ( ! empty( $map['settings'] ) && is_page( absint( $map['settings'] ) ) ) {
			$robots['noindex']   = true;
			$robots['noarchive'] = true;
			$robots['nosnippet'] = true;
		}
		if ( $this->is_profile_page() ) {
			$user = $this->requested_user();
			if ( ! $user || ! SDD_Helpers::is_public( $user->ID ) ) {
				$robots['noindex']   = true;
				$robots['noarchive'] = true;
				$robots['nosnippet'] = true;
			}
		}
		return $robots;
	}

	public function title( $parts ) {
		if ( $this->is_profile_page() ) {
			$user = $this->requested_user();
			if ( $user && SDD_Helpers::is_public( $user->ID ) ) {
				$parts['title'] = $user->display_name . ' — Verified Doctor';
			}
		}
		return $parts;
	}

	public function register_sitemap( $sitemaps ) {
		if ( class_exists( 'SDD_Doctor_Sitemap_Provider' ) ) {
			$sitemaps->registry->add_provider( 'doctors', new SDD_Doctor_Sitemap_Provider() );
		}
	}

}


if ( class_exists( 'WP_Sitemaps_Provider' ) ) {
	final class SDD_Doctor_Sitemap_Provider extends WP_Sitemaps_Provider {
		const PER_PAGE = 1000;

		public function __construct() {
			$this->name        = 'doctors';
			$this->object_type = 'user';
		}

		public function get_url_list( $page_num, $object_subtype = '' ) {
			global $wpdb;
			$page_num = max( 1, absint( $page_num ) );
			$offset   = ( $page_num - 1 ) * self::PER_PAGE;
			$query    = $this->base_sql();
			$params   = $query['params'];
			$params[] = self::PER_PAGE;
			$params[] = $offset;
			$sql = "SELECT u.ID,u.user_registered FROM {$wpdb->users} u WHERE {$query['where']} ORDER BY u.ID ASC LIMIT %d OFFSET %d";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$urls = array();
			foreach ( $rows as $row ) {
				if ( ! SDD_Helpers::is_public( $row->ID ) ) {
					continue;
				}
				$entry = array( 'loc' => SDD_Helpers::profile_url( $row->ID ) );
				if ( $row->user_registered && '0000-00-00 00:00:00' !== $row->user_registered ) {
					$entry['lastmod'] = gmdate( DATE_W3C, strtotime( $row->user_registered . ' UTC' ) );
				}
				$urls[] = $entry;
			}
			return $urls;
		}

		public function get_max_num_pages( $object_subtype = '' ) {
			global $wpdb;
			$query = $this->base_sql();
			$sql   = "SELECT COUNT(*) FROM {$wpdb->users} u WHERE {$query['where']}";
			$total = (int) $wpdb->get_var( $wpdb->prepare( $sql, $query['params'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return max( 1, (int) ceil( $total / self::PER_PAGE ) );
		}

		private function base_sql() {
			global $wpdb;
			$caps_key = $wpdb->get_blog_prefix() . 'capabilities';
			$where = array(
				"EXISTS (SELECT 1 FROM {$wpdb->usermeta} caps WHERE caps.user_id=u.ID AND caps.meta_key=%s AND caps.meta_value LIKE %s)",
				"EXISTS (SELECT 1 FROM {$wpdb->usermeta} verify WHERE verify.user_id=u.ID AND verify.meta_key='_spd_verification_status' AND verify.meta_value='verified')",
				"NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} hidden WHERE hidden.user_id=u.ID AND hidden.meta_key='_sdd_hidden' AND hidden.meta_value='1')",
				"NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} discoverable WHERE discoverable.user_id=u.ID AND discoverable.meta_key='_sdd_discoverable' AND discoverable.meta_value='0')",
			);
			$params = array( $caps_key, '%' . $wpdb->esc_like( '"sabri_doctor_verified"' ) . '%' );
			$founder = SDD_Helpers::founder_id();
			if ( $founder ) {
				$where[]  = 'u.ID<>%d';
				$params[] = $founder;
			}
			return array( 'where' => implode( ' AND ', $where ), 'params' => $params );
		}
	}
}
