<?php
/**
 * Plugin Name: Doctors Directory and Discovery
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: A secure, accessible, paginated verified-doctor directory and professional discovery layer for the Sabri Social Homeopathy Platform.
 * Version: 0.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * License: GPL-2.0-or-later
 * Text Domain: doctors-directory
 */

defined( 'ABSPATH' ) || exit;

define( 'SDD_VERSION', '0.2.0' );
define( 'SDD_DB_VERSION', '0.2.0' );
define( 'SDD_MIN_SPD_VERSION', '0.1.0' );
define( 'SDD_FILE', __FILE__ );
define( 'SDD_DIR', plugin_dir_path( __FILE__ ) );
define( 'SDD_URL', plugin_dir_url( __FILE__ ) );

require_once SDD_DIR . 'includes/class-sdd-helpers.php';
require_once SDD_DIR . 'includes/class-sdd-activator.php';
require_once SDD_DIR . 'includes/class-sdd-directory.php';
require_once SDD_DIR . 'includes/class-sdd-profile.php';
require_once SDD_DIR . 'includes/class-sdd-admin.php';
require_once SDD_DIR . 'includes/class-sdd-privacy.php';
require_once SDD_DIR . 'includes/class-sdd-seo.php';
require_once SDD_DIR . 'includes/class-sdd-plugin.php';

register_activation_hook( SDD_FILE, array( 'SDD_Activator', 'activate' ) );
register_deactivation_hook( SDD_FILE, array( 'SDD_Activator', 'deactivate' ) );

function sdd_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
		esc_html__( 'Doctors Directory:', 'doctors-directory' ),
		esc_html( SDD_Helpers::dependency_message() )
	);
}

function sdd_start_plugin() {
	if ( ! SDD_Helpers::dependency_ready() ) {
		add_action( 'admin_notices', 'sdd_dependency_notice' );
		return;
	}
	SDD_Activator::maybe_upgrade();
	( new SDD_Plugin() )->run();
}
add_action( 'plugins_loaded', 'sdd_start_plugin', 20 );
