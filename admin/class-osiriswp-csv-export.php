<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_CSV_Export {
	private $data_handler;

	public function __construct() {
		$this->data_handler = new OsirisWP_Events_Data();
	}

	public function hooks() {
		add_action( 'admin_init', [ $this, 'handle_csv_export' ] );
	}

	public function handle_csv_export() {
		// Check if export is requested
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'osiriswp_events' ) {
			return;
		}
		
		if ( ! isset( $_GET['export_csv'] ) || $_GET['export_csv'] !== '1' ) {
			return;
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions.', 'osiriswp' ) );
		}
		
		// Get filter parameters
		$visitor_uuid = isset( $_GET['visitor_uuid'] ) ? sanitize_text_field( $_GET['visitor_uuid'] ) : '';
		$page = isset( $_GET['page_url'] ) ? sanitize_text_field( $_GET['page_url'] ) : '';
		$event_name = isset( $_GET['event_name'] ) ? sanitize_text_field( $_GET['event_name'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		$cookie_name = isset( $_GET['cookie_name'] ) ? sanitize_text_field( $_GET['cookie_name'] ) : '';
		
		// Get all events (no pagination)
		$events = $this->data_handler->get_all_events_for_export( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name );
		
		// Set headers for CSV download
		$filename = 'osiriswp-events-' . date( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		// Open output stream
		$output = fopen( 'php://output', 'w' );
		
		// Add BOM for Excel UTF-8 support
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );
		
		// Add CSV headers
		fputcsv( $output, [
			'ID',
			'Visitor UUID',
			'Event Name',
			'Page URL',
			'Query Strings',
			'Cookies',
			'Triggered At'
		] );
		
		// Add data rows
		foreach ( $events as $event ) {
			fputcsv( $output, [
				$event->id,
				$event->visitor_uuid,
				$event->event_name,
				$event->page,
				$event->query_strings,
				$event->cookies,
				$event->triggered_at
			] );
		}
		
		fclose( $output );
		exit;
	}
}
