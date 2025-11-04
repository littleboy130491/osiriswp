<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Public {
	private $cookie_name = 'osiriswp_visitor_uuid';

	public function hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'init', [ $this, 'set_visitor_uuid' ] );
		add_action( 'wp', [ $this, 'track_page_view' ] );
		add_action( 'wp_ajax_osiriswp_track_event', [ $this, 'ajax_track_event' ] );
		add_action( 'wp_ajax_nopriv_osiriswp_track_event', [ $this, 'ajax_track_event' ] );
	}

	public function enqueue() {
		wp_enqueue_style( 'osiriswp-public', OSIRISWP_URL . 'assets/css/public.css', [], OSIRISWP_VERSION );
		wp_enqueue_script( 'osiriswp-public', OSIRISWP_URL . 'assets/js/public.js', [ 'jquery' ], OSIRISWP_VERSION, true );
		
		wp_localize_script( 'osiriswp-public', 'osiriswp_ajax', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'osiriswp_track_event_nonce' )
		]);
		
		// Pass debug setting to JavaScript
		$debug_enabled = get_option( 'osiriswp_debug', false );
		wp_localize_script( 'osiriswp-public', 'osiriswp_debug', [
			'debug_enabled' => $debug_enabled
		] );
		
		// Pass tracked events configuration to JavaScript
		$tracked_events = get_option( 'osiriswp_tracked_events', '' );
		
		// Handle comma-separated values
		if ( ! empty( $tracked_events ) ) {
			$events_array = array_map( 'trim', explode( ',', $tracked_events ) );
			$events_array = array_filter( $events_array ); // Remove empty values
			$events_array = array_unique( $events_array ); // Remove duplicates
		} else {
			$events_array = [];
		}
		
		// GA4 standard events are always included by default
		$ga4_events = [
			'page_view', 'click', 'form_submit', 'button_click', 'generate_lead', 
			'add_to_cart', 'purchase', 'begin_checkout', 'add_payment_info', 
			'add_shipping_info', 'search', 'view_item', 'view_item_list', 
			'select_item', 'add_to_wishlist', 'remove_from_cart'
		];
		
		// Merge GA4 events with additional custom events
		$all_events = array_merge( $ga4_events, $events_array );
		
		wp_localize_script( 'osiriswp-public', 'osiriswp_tracked_events', [
			'events' => $all_events,
			'ga4_events' => $ga4_events,
			'additional_events' => $events_array
		] );
	}

	public function set_visitor_uuid() {
		if ( ! isset( $_COOKIE[$this->cookie_name] ) ) {
			$visitor_uuid = $this->generate_uuid();
			setcookie( $this->cookie_name, $visitor_uuid, time() + (365 * 24 * 60 * 60), "/" );
			$_COOKIE[$this->cookie_name] = $visitor_uuid;
		}
	}

	private function generate_uuid() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	public function get_visitor_uuid() {
		return isset( $_COOKIE[$this->cookie_name] ) ? sanitize_text_field( $_COOKIE[$this->cookie_name] ) : null;
	}

	public function track_event( $event_name, $data = [] ) {
		global $wpdb;
		
		$visitor_uuid = $this->get_visitor_uuid();
		if ( ! $visitor_uuid ) {
			// If no cookie, generate one for AJAX requests
			$visitor_uuid = $this->generate_uuid();
			setcookie( $this->cookie_name, $visitor_uuid, time() + (365 * 24 * 60 * 60), "/" );
			$_COOKIE[$this->cookie_name] = $visitor_uuid;
		}

		// Debug logging
		$debug_enabled = get_option( 'osiriswp_debug', false );
		if ( $debug_enabled ) {
			error_log( "OsirisWP Debug: {$event_name} is triggered" );
		}

		$table_name = $wpdb->prefix . 'osiriswp_events';
		
		// Get page URL from data if available (AJAX), otherwise from server
		if ( isset( $data['page_url'] ) ) {
			$page = sanitize_text_field( $data['page_url'] );
		} else {
			$current_url = home_url( add_query_arg( NULL, NULL ) );
			$parsed_url = parse_url( $current_url );
			$page = home_url( $_SERVER['REQUEST_URI'] ?? '/' );
		}
		
		// Get query string from data (JavaScript) or from server
		if ( isset( $data['query_string'] ) && ! empty( $data['query_string'] ) ) {
			$query_strings = sanitize_text_field( $data['query_string'] );
		} else {
			$current_url = home_url( add_query_arg( NULL, NULL ) );
			$parsed_url = parse_url( $current_url );
			$query_strings = isset( $parsed_url['query'] ) ? sanitize_text_field( $parsed_url['query'] ) : '';
		}
		
		// Get cookies from data (JavaScript) or from server
		if ( isset( $data['cookies'] ) && is_array( $data['cookies'] ) ) {
			$cookies_data = json_encode( $data['cookies'] );
		} else {
			$cookies_data = isset( $_COOKIE ) ? json_encode( $_COOKIE ) : '';
		}

		$result = $wpdb->insert(
			$table_name,
			[
				'visitor_uuid' => $visitor_uuid,
				'event_name' => $event_name,
				'page' => sanitize_text_field( $page ),
				'query_strings' => $query_strings,
				'cookies' => $cookies_data,
				'triggered_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
		
		if ( $result === false && $debug_enabled ) {
			error_log( "OsirisWP: Database insert failed - " . $wpdb->last_error );
		}

		return $result !== false;
	}

	public function track_page_view() {
		if ( ! is_admin() ) {
			// Only track actual WordPress page requests, not system/asset requests
			$request_uri = $_SERVER['REQUEST_URI'] ?? '';
			
			// First check if it's a valid WordPress page request
			if ( ! ( is_page() || is_single() || is_home() || is_front_page() || is_category() || is_tag() || is_archive() || is_search() || is_404() ) ) {
				return;
			}
			
			// Then check for excluded patterns
			$excluded_patterns = [
				'/favicon.ico',
				'/.css',
				'/.js',
				'/.css.map',
				'/.js.map',
				'/.map',
				'/.jpg',
				'/.jpeg',
				'/.png',
				'/.gif',
				'/.svg',
				'/.ico',
				'/.woff',
				'/.woff2',
				'/.ttf',
				'/.eot',
				'/wp-admin/',
				'/wp-includes/',
				'/wp-content/',
				'/xmlrpc.php',
				'/wp-json/',
				'/.well-known/',
				'/robots.txt',
				'/sitemap',
				'/feed/',
				'/trackback/',
				'/comment/',
				'/wp-login.php',
				'/wp-register.php',
				'/wp-cron.php',
				'/wp-mail.php',
				'/wp-links-opml.php',
				'/wp-tar.php'
			];
			
			foreach ( $excluded_patterns as $pattern ) {
				if ( strpos( $request_uri, $pattern ) !== false ) {
					return;
				}
			}
			
			// Only track if it passes both checks
			$this->track_event( 'page_view' );
		}
	}

	public function ajax_track_event() {
		// Remove any BOM or output buffering
		if (ob_get_length()) ob_clean();
		
		// Set clean JSON headers
		header('Content-Type: application/json; charset=utf-8');
		
		// Debug logging
		$debug_enabled = get_option( 'osiriswp_debug', false );
		
		// Check nonce without dying
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'osiriswp_track_event_nonce' ) ) {
			if ( $debug_enabled ) {
				error_log( "OsirisWP AJAX: Nonce verification failed" );
			}
			wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
			return;
		}
		
		if ( ! isset( $_POST['event_name'] ) ) {
			if ( $debug_enabled ) {
				error_log( "OsirisWP AJAX: No event name provided" );
			}
			wp_send_json_error( [ 'message' => 'Event name required' ] );
			return;
		}
		
		$event_name = sanitize_text_field( $_POST['event_name'] );
		
		// Parse JSON data from JavaScript
		$data = [];
		if ( isset( $_POST['data'] ) ) {
			$json_data = json_decode( stripslashes( $_POST['data'] ), true );
			if ( is_array( $json_data ) ) {
				$data = $json_data;
			}
		}
		
		if ( $debug_enabled ) {
			error_log( "OsirisWP AJAX: Tracking event - " . $event_name );
			error_log( "OsirisWP AJAX: Event data - " . print_r( $data, true ) );
		}
		
		try {
			$result = $this->track_event( $event_name, $data );
			
			if ( $debug_enabled ) {
				error_log( "OsirisWP AJAX: Track result - " . ( $result ? 'success' : 'failed' ) );
			}
			
			wp_send_json_success( [ 'tracked' => $result ] );
		} catch ( Exception $e ) {
			if ( $debug_enabled ) {
				error_log( "OsirisWP AJAX: Exception - " . $e->getMessage() );
				error_log( "OsirisWP AJAX: Stack trace - " . $e->getTraceAsString() );
			}
			wp_send_json_error( [ 'message' => 'Tracking failed: ' . $e->getMessage() ] );
		}
	}
}
