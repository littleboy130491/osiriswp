<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-osiriswp-deactivator.php';

// Deleting the plugin removes its table and options (WordPress warns that data is deleted).
if ( is_multisite() ) {
	$osiriswp_site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
	foreach ( $osiriswp_site_ids as $osiriswp_site_id ) {
		switch_to_blog( $osiriswp_site_id );
		OsirisWP_Deactivator::remove_data();
		restore_current_blog();
	}
} else {
	OsirisWP_Deactivator::remove_data();
}
