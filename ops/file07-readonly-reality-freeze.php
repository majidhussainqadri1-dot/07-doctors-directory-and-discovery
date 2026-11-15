<?php
/**
 * Sabri File07 V12.3 — read-only WordPress/Hostinger reality freeze.
 *
 * Run ONLY by an authorized operator on an ISOLATED RESTORED STAGING COPY:
 *   wp --skip-plugins --skip-themes eval-file ops/file07-readonly-reality-freeze.php
 *
 * WARNING: WP-CLI bootstraps WordPress before this file executes. A normal
 * bootstrap may run plugins_loaded upgrade/migration hooks; mu-plugins can
 * still load even with --skip-plugins. Do NOT run on unverified LIVE as the
 * first reality freeze. Obtain Hostinger filesystem/DB read-only snapshots
 * through separate authorized controls before allowing any WP bootstrap.
 *
 * This file is NOT part of the installable File07 plugin; build-release.sh
 * copies doctors-directory/ only. Never execute through an HTTP route.
 * No private records, tokens, filenames of user uploads, or log content emitted.
 *
 * ATTENTION: This script reads the WordPress installation, not staging/live
 * interchangeably. Label the exact environment and preserve the generated
 * report confidentially in your governed incident register. The script itself
 * issues no writes, but WordPress bootstrap side effects are NOT attested.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

global $wpdb;
if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	WP_CLI::error( 'WordPress database context is unavailable.' );
}

if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$plugin_key = 'doctors-directory-and-discovery/doctors-directory.php';
$plugin_dir = WP_PLUGIN_DIR . '/doctors-directory-and-discovery';
$main_file  = WP_PLUGIN_DIR . '/' . $plugin_key;
$installed  = get_plugins();
$active     = (array) get_option( 'active_plugins', array() );
$known      = array(
	'00' => array( 'membership', 'identity' ),
	'03' => array( 'profiles', 'doctors' ),
	'07' => array( 'directory', 'discovery' ),
	'08' => array( 'clinic', 'appointments' ),
	'09' => array( 'verification', 'onboarding' ),
	'19' => array( 'notifications', 'unified' ),
	'20' => array( 'shell', 'navigation' ),
	'24' => array( 'security', 'resilience' ),
	'25' => array( 'visual', 'experience' ),
	'26' => array( 'search', 'knowledge' ),
);

$companions = array();
foreach ( $installed as $key => $header ) {
	$name = strtolower( sanitize_text_field( (string) ( $header['Name'] ?? '' ) ) );
	$slug = strtolower( (string) $key );
	$matches = array();
	foreach ( $known as $file_id => $hints ) {
		$hit = true;
		foreach ( $hints as $hint ) {
			if ( false === strpos( $name . ' ' . $slug, $hint ) ) {
				$hit = false;
				break;
			}
		}
		if ( $hit ) {
			$matches[] = $file_id;
		}
	}
	if ( $matches || $key === $plugin_key ) {
		$companions[] = array(
			'plugin_key'       => sanitize_text_field( (string) $key ),
			'declared_version' => sanitize_text_field( (string) ( $header['Version'] ?? '' ) ),
			'active'           => in_array( $key, $active, true ),
			'file_hint'        => $matches,
		);
	}
}

$tree = array(
	'complete'            => false,
	'file_count'          => 0,
	'tree_sha256'         => null,
	'main_header_sha256'  => is_file( $main_file ) ? hash_file( 'sha256', $main_file ) : null,
	'zip_sha256'          => null, // Installed source bytes are NOT an installable package ZIP.
	'note'                => 'Tree hash is SHA256 of sorted relative-file-name + per-file SHA256 pairs, not package ZIP parity.',
);
if ( is_dir( $plugin_dir ) && is_file( $main_file ) ) {
	$entries = array();
	$complete = true;
	$count = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $entry ) {
		++$count;
		if ( $count > 500 || $entry->isLink() || ! $entry->isFile() || $entry->getSize() > 20 * 1024 * 1024 ) {
			$complete = false;
			continue;
		}
		$real = $entry->getRealPath();
		$root = realpath( $plugin_dir );
		if ( ! $real || ! $root || 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
			$complete = false;
			continue;
		}
		$sha = hash_file( 'sha256', $real );
		if ( ! is_string( $sha ) ) {
			$complete = false;
			continue;
		}
		$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $real, strlen( $root ) + 1 ) );
		$entries[] = $relative . '  ' . $sha;
	}
	sort( $entries, SORT_STRING );
	$tree['complete'] = $complete;
	$tree['file_count'] = count( $entries );
	$tree['tree_sha256'] = count( $entries ) ? hash( 'sha256', implode( "\n", $entries ) . "\n" ) : null;
	if ( ! $complete ) {
		$tree['note'] .= ' Limits or skipped files make this an INCOMPLETE source inventory.';
	}
}

$option_values = array();
foreach ( array(
	'ddd_version',
	'ddd_db_version',
	'ddd_contract_version',
	'ddd_projection_schema',
	'ddd_legacy_reports_migrated',
	'ddd_legacy_reports_cursor',
	'ddd_reconcile_cursor',
	'ddd_safe_mode',
) as $key ) {
	$value = get_option( $key, null );
	$option_values[ $key ] = is_scalar( $value ) && strlen( (string) $value ) < 100
		? sanitize_text_field( (string) $value )
		: ( null === $value ? null : '[present-unreported]' );
}
// Never output the activation-lock token or any authentication/secret option.
$option_values['ddd_activation_lock_present'] = false !== get_option( 'ddd_activation_lock', false );

$table_names = array(
	'projection', 'taxonomy', 'saved_refs', 'reports', 'report_audit', 'feature_audit',
	'admin_audit', 'outbox', 'inbox', 'search_metrics', 'rate_limits', 'health_log',
);
$schema = array();
foreach ( $table_names as $suffix ) {
	$table = $wpdb->prefix . 'ddd_' . $suffix;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	$item = array( 'exists' => ( $found === $table ), 'columns' => array(), 'row_count' => null );
	if ( $item['exists'] ) {
		// Table name has only trusted WordPress prefix and hard-coded ASCII suffix.
		$safe_table = str_replace( '`', '``', $table );
		$columns = $wpdb->get_results( 'SHOW COLUMNS FROM `' . $safe_table . '`', ARRAY_A );
		if ( is_array( $columns ) ) {
			foreach ( $columns as $column ) {
				$item['columns'][] = array(
					'field' => sanitize_key( (string) ( $column['Field'] ?? '' ) ),
					'type'  => sanitize_text_field( (string) ( $column['Type'] ?? '' ) ),
					'null'  => 'YES' === ( $column['Null'] ?? '' ),
				);
			}
		}
		// Aggregate count only: no individual doctor, report, IP, patient or user data.
		$count = $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $safe_table . '`' );
		$item['row_count'] = is_numeric( $count ) ? (int) $count : null;
	}
	$schema[ $suffix ] = $item;
}

$cron = array();
foreach ( array( 'ddd_reconcile_tick', 'ddd_expire_features_tick', 'ddd_process_outbox_tick', 'ddd_continue_legacy_migration' ) as $hook ) {
	$next = wp_next_scheduled( $hook );
	$cron[ $hook ] = $next ? gmdate( 'c', (int) $next ) : null;
}

$report = array(
	'report_type'             => 'file07-v123-readonly-reality-freeze',
	'captured_at_utc'         => gmdate( 'c' ),
	'environment'             => 'OPERATOR_MUST_LABEL_ISOLATED_RESTORED_STAGING',
	'wordpress_version'      => get_bloginfo( 'version' ),
	'php_version'            => PHP_VERSION,
	'database_server_version'=> method_exists( $wpdb, 'db_version' ) ? $wpdb->db_version() : null,
	'file07_plugin_key'      => $plugin_key,
	'file07_installed'       => is_file( $main_file ),
	'file07_active'          => in_array( $plugin_key, $active, true ),
	'file07_declared_version'=> is_array( $installed[ $plugin_key ] ?? null ) ? (string) ( $installed[ $plugin_key ]['Version'] ?? '' ) : null,
	'plugin_tree'            => $tree,
	'selected_companions'    => $companions,
	'selected_ddd_options'   => $option_values,
	'ddd_tables'             => $schema,
	'ddd_cron'               => $cron,
	'deployed_zip_parity'    => 'UNVERIFIED: installed tree hash is not a ZIP hash',
	'live_behavior_retested' => false,
	'script_write_operations'=> 0,
	'bootstrap_side_effects' => 'NOT ATTESTED — WP-CLI boot may run mu-plugin hooks; use only on isolated restored staging with skip flags',
	'privacy_notice'         => 'Contains only aggregate counts, schema names, plugin versions, and hashes; do not publish publicly.',
);
WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
