<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Activator {
	public static function activate() {
		self::create_database_table();
		
		if ( ! get_option( 'osiriswp_option_name', false ) ) {
			add_option( 'osiriswp_option_name', 'Hello from OsirisWP' );
		}
	}
	private static function create_database_table() {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'osiriswp_events';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_uuid varchar(36) NOT NULL,
			event_name varchar(255) NOT NULL,
			page varchar(500) NOT NULL,
			query_strings text,
			cookies text,
			triggered_at datetime DEFAULT CURRENT_TIMESTAMP,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY visitor_uuid (visitor_uuid),
			KEY event_name (event_name),
			KEY page (page),
			KEY triggered_at (triggered_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
}
