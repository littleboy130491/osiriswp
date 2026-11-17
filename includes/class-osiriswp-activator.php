<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}

		self::install();
	}

	/**
	 * Create/upgrade the schema when the stored DB version is outdated.
	 * Also covers sites added to a network after network activation.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'osiriswp_db_version' ) !== OSIRISWP_DB_VERSION ) {
			self::install();
		}
	}

	private static function install() {
		self::create_database_table();

		if ( false === get_option( 'osiriswp_option_name', false ) ) {
			add_option( 'osiriswp_option_name', 'Hello from OsirisWP' );
		}

		update_option( 'osiriswp_db_version', OSIRISWP_DB_VERSION );
	}

	private static function create_database_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'osiriswp_events';
		$charset_collate = $wpdb->get_charset_collate();

		// Index prefix lengths keep keys within the 767/3072-byte InnoDB limits on utf8mb4.
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_uuid varchar(36) NOT NULL,
			event_name varchar(255) NOT NULL,
			page varchar(500) NOT NULL,
			query_strings text,
			cookies text,
			triggered_at datetime DEFAULT CURRENT_TIMESTAMP,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY visitor_uuid (visitor_uuid),
			KEY event_name (event_name(191)),
			KEY page (page(191)),
			KEY triggered_at (triggered_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
