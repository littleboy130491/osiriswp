<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Events_Data {
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'osiriswp_events';
	}

	public function get_events( $visitor_uuid = '', $page = '', $event_name = '', $date_from = '', $date_to = '', $cookie_name = '', $page_num = 1, $per_page = 50 ) {
		global $wpdb;
		
		$where = [];
		$sql = "SELECT * FROM {$this->table_name}";
		
		if ( ! empty( $visitor_uuid ) ) {
			$where[] = $wpdb->prepare( 'visitor_uuid = %s', $visitor_uuid );
		}
		
		if ( ! empty( $page ) ) {
			$where[] = $wpdb->prepare( 'page = %s', $page );
		}
		
		if ( ! empty( $event_name ) ) {
			$where[] = $wpdb->prepare( 'event_name = %s', $event_name );
		}
		
		if ( ! empty( $date_from ) ) {
			$where[] = $wpdb->prepare( 'DATE(triggered_at) >= %s', $date_from );
		}
		
		if ( ! empty( $date_to ) ) {
			$where[] = $wpdb->prepare( 'DATE(triggered_at) <= %s', $date_to );
		}
		
		if ( ! empty( $cookie_name ) ) {
			$where[] = $wpdb->prepare( 'cookies LIKE %s', '%' . $wpdb->esc_like( $cookie_name ) . '%' );
		}
		
		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		
		$offset = ( $page_num - 1 ) * $per_page;
		$sql .= " {$where_clause} ORDER BY triggered_at DESC LIMIT %d OFFSET %d";
		
		return $wpdb->get_results( $wpdb->prepare( $sql, $per_page, $offset ) );
	}

	public function get_events_count( $visitor_uuid = '', $page = '', $event_name = '', $date_from = '', $date_to = '', $cookie_name = '' ) {
		global $wpdb;
		
		$where = [];
		$sql = "SELECT COUNT(*) FROM {$this->table_name}";
		
		if ( ! empty( $visitor_uuid ) ) {
			$where[] = $wpdb->prepare( 'visitor_uuid = %s', $visitor_uuid );
		}
		
		if ( ! empty( $page ) ) {
			$where[] = $wpdb->prepare( 'page = %s', $page );
		}
		
		if ( ! empty( $event_name ) ) {
			$where[] = $wpdb->prepare( 'event_name = %s', $event_name );
		}
		
		if ( ! empty( $date_from ) ) {
			$where[] = $wpdb->prepare( 'DATE(triggered_at) >= %s', $date_from );
		}
		
		if ( ! empty( $date_to ) ) {
			$where[] = $wpdb->prepare( 'DATE(triggered_at) <= %s', $date_to );
		}
		
		if ( ! empty( $cookie_name ) ) {
			$where[] = $wpdb->prepare( 'cookies LIKE %s', '%' . $wpdb->esc_like( $cookie_name ) . '%' );
		}
		
		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql .= " {$where_clause}";
		
		return (int) $wpdb->get_var( $sql );
	}

	public function get_all_events_for_export( $visitor_uuid = '', $page = '', $event_name = '', $date_from = '', $date_to = '', $cookie_name = '' ) {
		global $wpdb;
		
		$where = [];
		$sql = "SELECT * FROM {$this->table_name}";
		
		if ( ! empty( $visitor_uuid ) ) {
			$where[] = $wpdb->prepare( 'visitor_uuid = %s', $visitor_uuid );
		}
		
		if ( ! empty( $page ) ) {
			$where[] = $wpdb->prepare( 'page = %s', $page );
		}
		
		if ( ! empty( $event_name ) ) {
			$where[] = $wpdb->prepare( 'event_name = %s', $event_name );
		}
		
		if ( ! empty( $date_from ) ) {
			$where[] = $wpdb->prepare( 'DATE(triggered_at) >= %s', $date_from );
		}
		
		if ( ! empty( $date_to ) ) {
			$where[] = $wpdb->prepare( 'DATE(triggered_at) <= %s', $date_to );
		}
		
		if ( ! empty( $cookie_name ) ) {
			$where[] = $wpdb->prepare( 'cookies LIKE %s', '%' . $wpdb->esc_like( $cookie_name ) . '%' );
		}
		
		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql .= " {$where_clause} ORDER BY triggered_at DESC";
		
		return $wpdb->get_results( $sql );
	}

	public function get_unique_pages() {
		global $wpdb;
		return $wpdb->get_col( "SELECT DISTINCT page FROM {$this->table_name} ORDER BY page ASC" );
	}
	
	public function get_unique_event_names() {
		global $wpdb;
		return $wpdb->get_col( "SELECT DISTINCT event_name FROM {$this->table_name} ORDER BY event_name ASC" );
	}
}
