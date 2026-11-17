<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_CSV_Export {
	const NONCE_ACTION = 'osiriswp_export_csv';
	const NONCE_FIELD  = '_osiriswp_export_nonce';

	private $data_handler;

	public function __construct() {
		$this->data_handler = new OsirisWP_Events_Data();
	}

	public function hooks() {
		add_action( 'admin_init', [ $this, 'handle_csv_export' ] );
	}

	/**
	 * Neutralise spreadsheet formulas (CSV injection) in visitor-controlled values.
	 */
	public static function escape_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	public function handle_csv_export() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		if ( ! isset( $_GET['page'] ) || 'osiriswp_events' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['export_csv'] ) || '1' !== $_GET['export_csv'] ) {
			return;
		}
		// phpcs:enable

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'osiriswp' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$filters = OsirisWP_Events_Data::get_filters_from_request();

		// Large exports: don't time out, and stream without output buffering.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		$filename = 'osiriswp-events-' . wp_date( 'Y-m-d-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// Add BOM for Excel UTF-8 support
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv( $output, [ 'ID', 'Visitor UUID', 'Event Name', 'Page URL', 'GCLID', 'Query Strings', 'Cookies', 'Triggered At' ], ',', '"', '\\' );

		$this->data_handler->each_event_batch_for_export(
			static function ( $rows ) use ( $output ) {
				foreach ( $rows as $event ) {
					fputcsv(
						$output,
						[
							$event->id,
							self::escape_cell( $event->visitor_uuid ),
							self::escape_cell( $event->event_name ),
							self::escape_cell( $event->page ),
							self::escape_cell( $event->gclid ),
							self::escape_cell( $event->query_strings ),
							self::escape_cell( $event->cookies ),
							$event->triggered_at,
						],
						',',
						'"',
						'\\'
					);
				}
				flush();
			},
			$filters
		);

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
