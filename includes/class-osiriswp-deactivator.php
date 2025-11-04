<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Deactivator {
	public static function deactivate() {
		// Check if cleanup is enabled in settings
		$cleanup_on_deactivation = get_option( 'osiriswp_cleanup_on_deactivation', false );
		
		if ( $cleanup_on_deactivation ) {
			global $wpdb;
			
			// Drop the events table
			$table_name = $wpdb->prefix . 'osiriswp_events';
			$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
			
			// Remove plugin options
			delete_option( 'osiriswp_tracked_events' );
			delete_option( 'osiriswp_cleanup_on_deactivation' );
			
			// Log cleanup
			error_log( 'OsirisWP: Database cleanup completed on deactivation' );
		}
	}
}
