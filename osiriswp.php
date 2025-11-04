<?php
/**
 * Plugin Name:       OsirisWP Event Tracking
 * Plugin URI:        https://example.com/osiriswp
 * Description:       Track all events triggered in your WordPress site with visitor UUID management and comprehensive analytics dashboard.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            You
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       osiriswp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin constants.
if ( ! defined( 'OSIRISWP_VERSION' ) ) {
	define( 'OSIRISWP_VERSION', '0.1.0' );
}
if ( ! defined( 'OSIRISWP_FILE' ) ) {
	define( 'OSIRISWP_FILE', __FILE__ );
}
if ( ! defined( 'OSIRISWP_DIR' ) ) {
	define( 'OSIRISWP_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'OSIRISWP_URL' ) ) {
	define( 'OSIRISWP_URL', plugin_dir_url( __FILE__ ) );
}

// Includes.
require_once OSIRISWP_DIR . 'includes/class-osiriswp-activator.php';
require_once OSIRISWP_DIR . 'includes/class-osiriswp-deactivator.php';
require_once OSIRISWP_DIR . 'includes/class-osiriswp.php';
require_once OSIRISWP_DIR . 'admin/class-osiriswp-admin.php';
require_once OSIRISWP_DIR . 'public/class-osiriswp-public.php';

/**
 * Activation hook.
 */
function osiriswp_activate() {
	OsirisWP_Activator::activate();
}
register_activation_hook( __FILE__, 'osiriswp_activate' );

/**
 * Deactivation hook.
 */
function osiriswp_deactivate() {
	OsirisWP_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'osiriswp_deactivate' );

/**
 * Boot the plugin.
 */
function osiriswp_run() {
	$plugin = new OsirisWP();
	$plugin->run();
}
add_action( 'plugins_loaded', 'osiriswp_run' );

