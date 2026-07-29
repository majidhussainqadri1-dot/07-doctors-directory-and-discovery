<?php
/**
 * Plugin Name: Doctors Directory and Discovery
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: An American English directory, discovery, profile-completion, contact, reporting, and management layer for verified doctors.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: doctors-directory
 */

defined( 'ABSPATH' ) || exit;

define( 'SDD_VERSION', '0.1.0' );
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

function sdd_start_plugin() {
	if ( class_exists( 'SPD_Helpers' ) ) {
		( new SDD_Plugin() )->run();
	} else {
		add_action( 'admin_notices', function() {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>Doctors Directory:</strong> Activate File 03 — Sabri Profiles and Doctors first.</p></div>';
			}
		} );
	}
}
add_action( 'plugins_loaded', 'sdd_start_plugin', 20 );

