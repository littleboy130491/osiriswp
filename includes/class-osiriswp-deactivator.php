<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Deactivator {
	public static function deactivate() {
		// Only wipe data when the admin explicitly opted in.
		if ( ! get_option( 'osiriswp_cleanup_on_deactivation', false ) ) {
			return;
		}

		self::remove_data();
	}

	/**
	 * Drop the events table and delete all plugin options for the current site.
	 */
	public static function remove_data() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'osiriswp_events';
		$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( self::option_names() as $option ) {
			delete_option( $option );
		}
	}

	public static function option_names() {
		return [
			'osiriswp_option_name',
			'osiriswp_db_version',
			'osiriswp_debug',
			'osiriswp_prune_days',
			'osiriswp_tracked_events',
			'osiriswp_cleanup_on_deactivation',
		];
	}
}
