<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Events_Data {
	const DROPDOWN_LIMIT = 1000;

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'osiriswp_events';
	}

	/**
	 * Empty filter set (every key build_where() understands).
	 */
	public static function default_filters() {
		return [
			'visitor_uuid' => '',
			'page'         => '',
			'event_name'   => '',
			'date_from'    => '',
			'date_to'      => '',
			'cookie_name'  => '',
			'gclid'        => '',
			'has_gclid'    => '',
		];
	}

	public function get_events( array $filters, $page_num = 1, $per_page = 50 ) {
		global $wpdb;

		list( $where_clause, $args ) = $this->build_where( $filters );

		$page_num = max( 1, (int) $page_num );
		$per_page = max( 1, (int) $per_page );
		$args[]   = $per_page;
		$args[]   = ( $page_num - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY triggered_at DESC, id DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function get_events_count( array $filters ) {
		return $this->count( 'COUNT(*)', $filters );
	}

	public function get_unique_visitors_count( array $filters ) {
		return $this->count( 'COUNT(DISTINCT visitor_uuid)', $filters );
	}

	/**
	 * Fetch export rows in batches (keyset pagination on id) to keep memory flat.
	 *
	 * @param callable $callback Called with each batch (array of row objects).
	 * @param array    $filters  Filters (see default_filters()).
	 * @param int      $batch    Rows per batch.
	 */
	public function each_event_batch_for_export( callable $callback, array $filters, $batch = 1000 ) {
		global $wpdb;

		list( $where_clause, $args ) = $this->build_where( $filters );

		$last_id = PHP_INT_MAX;
		$prefix  = $where_clause ? $where_clause . ' AND' : 'WHERE';

		do {
			$batch_args = array_merge( $args, [ $last_id, (int) $batch ] );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql  = "SELECT id, visitor_uuid, event_name, page, gclid, query_strings, cookies, triggered_at FROM {$this->table_name} {$prefix} id < %d ORDER BY id DESC LIMIT %d";
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $batch_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( empty( $rows ) ) {
				break;
			}

			call_user_func( $callback, $rows );

			$last_id = (int) end( $rows )->id;
			$count   = count( $rows );
			unset( $rows );
			$wpdb->flush();
		} while ( $count === (int) $batch );
	}

	public function get_unique_pages() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT page FROM {$this->table_name} ORDER BY page ASC LIMIT %d", self::DROPDOWN_LIMIT ) );
	}

	public function get_unique_event_names() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT event_name FROM {$this->table_name} ORDER BY event_name ASC LIMIT %d", self::DROPDOWN_LIMIT ) );
	}

	private function count( $expression, array $filters ) {
		global $wpdb;

		list( $where_clause, $args ) = $this->build_where( $filters );

		// $expression is a fixed internal string, never user input.
		$sql = "SELECT {$expression} FROM {$this->table_name} {$where_clause}";

		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Read the dashboard filters from the current request.
	 *
	 * @return array See default_filters().
	 */
	public static function get_filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$get = static function ( $key ) {
			return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};

		$filters = [
			'visitor_uuid' => $get( 'visitor_uuid' ),
			// Exact match against stored values: only used as a prepared parameter and escaped on output.
			'page'         => isset( $_GET['page_url'] ) && is_string( $_GET['page_url'] ) ? trim( wp_check_invalid_utf8( wp_unslash( $_GET['page_url'] ), true ) ) : '',
			'event_name'   => $get( 'event_name' ),
			'date_from'    => self::sanitize_date( $get( 'date_from' ) ),
			'date_to'      => self::sanitize_date( $get( 'date_to' ) ),
			'cookie_name'  => $get( 'cookie_name' ),
			'gclid'        => OsirisWP_Public::sanitize_click_id( $get( 'gclid' ) ),
			'has_gclid'    => '1' === $get( 'has_gclid' ) ? '1' : '',
		];
		// phpcs:enable

		return $filters;
	}

	/**
	 * Validate a Y-m-d date string.
	 */
	public static function sanitize_date( $date ) {
		$date = (string) $date;
		$dt   = DateTime::createFromFormat( '!Y-m-d', $date );
		return ( $dt && $dt->format( 'Y-m-d' ) === $date ) ? $date : '';
	}

	/**
	 * Build a WHERE clause with placeholders plus its arguments, to be prepared once.
	 *
	 * @return array{0:string,1:array}
	 */
	private function build_where( array $filters ) {
		global $wpdb;

		$f     = array_merge( self::default_filters(), $filters );
		$where = [];
		$args  = [];

		foreach ( [ 'visitor_uuid' => 'visitor_uuid', 'page' => 'page', 'event_name' => 'event_name', 'gclid' => 'gclid' ] as $key => $column ) {
			if ( '' !== (string) $f[ $key ] ) {
				$where[] = "{$column} = %s";
				$args[]  = $f[ $key ];
			}
		}

		if ( '1' === (string) $f['has_gclid'] ) {
			$where[] = "gclid <> ''";
		}

		// Range comparisons on the raw column so the triggered_at index can be used.
		$date_from = self::sanitize_date( $f['date_from'] );
		if ( '' !== $date_from ) {
			$where[] = 'triggered_at >= %s';
			$args[]  = $date_from . ' 00:00:00';
		}

		$date_to = self::sanitize_date( $f['date_to'] );
		if ( '' !== $date_to ) {
			$where[] = 'triggered_at <= %s';
			$args[]  = $date_to . ' 23:59:59';
		}

		if ( '' !== (string) $f['cookie_name'] ) {
			$where[] = 'cookies LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $f['cookie_name'] ) . '%';
		}

		return [ $where ? 'WHERE ' . implode( ' AND ', $where ) : '', $args ];
	}
}
