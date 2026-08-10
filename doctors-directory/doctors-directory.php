<?php
/**
 * Plugin Name: Doctors Directory and Discovery
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: Canonical verified-doctor directory, discovery, eligibility projection, search, moderation, SEO and operational controls for the Sabri Social Homeopathy Platform.
 * Version: 1.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * License: GPL-2.0-or-later
 * Text Domain: doctors-directory-discovery
 */

defined( 'ABSPATH' ) || exit;

define( 'DDD_VERSION', '1.1.0' );
define( 'DDD_DB_VERSION', '1.1.0' );
define( 'DDD_CONTRACT_VERSION', '1.1.0' );
define( 'DDD_FILE', __FILE__ );
define( 'DDD_DIR', plugin_dir_path( __FILE__ ) );
define( 'DDD_URL', plugin_dir_url( __FILE__ ) );
define( 'DDD_TEXT_DOMAIN', 'doctors-directory-discovery' );
define( 'DDD_SLUG', 'doctors-directory-discovery' );
define( 'DDD_PROJECTION_SCHEMA', 2 );
define( 'DDD_MIN_FILE03_VERSION', '0.1.0' );
define( 'DDD_SAFE_MODE_OPTION', 'ddd_safe_mode' );

require_once DDD_DIR . 'includes/class-sdd-helpers.php';
require_once DDD_DIR . 'includes/class-sdd-activator.php';
require_once DDD_DIR . 'includes/class-sdd-directory.php';
require_once DDD_DIR . 'includes/class-sdd-profile.php';
require_once DDD_DIR . 'includes/class-sdd-admin.php';
require_once DDD_DIR . 'includes/class-sdd-privacy.php';
require_once DDD_DIR . 'includes/class-sdd-seo.php';
require_once DDD_DIR . 'includes/class-sdd-plugin.php';
require_once DDD_DIR . 'includes/class-ddd-review-hardening.php';
require_once DDD_DIR . 'includes/class-ddd-central-ranking.php';
require_once DDD_DIR . 'includes/class-ddd-ranking-ui.php';
require_once DDD_DIR . 'includes/class-ddd-ranking-appeal.php';

register_activation_hook( DDD_FILE, array( 'DDD_Activator', 'activate' ) );
register_deactivation_hook( DDD_FILE, array( 'DDD_Activator', 'deactivate' ) );

/**
 * Backward-compatible constants for legacy 0.2.x integrations.
 */
if ( ! defined( 'SDD_VERSION' ) ) {
	define( 'SDD_VERSION', DDD_VERSION );
}
if ( ! defined( 'SDD_DB_VERSION' ) ) {
	define( 'SDD_DB_VERSION', DDD_DB_VERSION );
}
if ( ! defined( 'SDD_FILE' ) ) {
	define( 'SDD_FILE', DDD_FILE );
}
if ( ! defined( 'SDD_DIR' ) ) {
	define( 'SDD_DIR', DDD_DIR );
}
if ( ! defined( 'SDD_URL' ) ) {
	define( 'SDD_URL', DDD_URL );
}

function ddd_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$health = DDD_Contracts::dependency_health();
	printf(
		'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <code>%3$s</code></p></div>',
		esc_html__( 'Doctors Directory and Discovery:', DDD_TEXT_DOMAIN ),
		esc_html( $health['message'] ),
		esc_html( $health['code'] )
	);
}

function ddd_start_plugin() {
	load_plugin_textdomain( DDD_TEXT_DOMAIN, false, dirname( plugin_basename( DDD_FILE ) ) . '/languages' );
	DDD_Activator::maybe_upgrade();
	$health = DDD_Contracts::dependency_health();
	if ( ! $health['ready'] ) {
		add_action( 'admin_notices', 'ddd_dependency_notice' );
		DDD_Observability::record_health( 'dependency', 'degraded', $health['code'] );
	}
	( new DDD_Plugin() )->run();
}

add_action( 'plugins_loaded', array( 'DDD_Review_Hardening', 'register' ), 29 );
add_action( 'plugins_loaded', 'ddd_start_plugin', 30 );
