<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Public {
	const COOKIE_NAME        = 'osiriswp_visitor_uuid';
	const ATTRIBUTION_COOKIE = 'osiriswp_attribution';

	// Google Ads click window: keep the click ID for 90 days after the click.
	const ATTRIBUTION_TTL = 90 * DAY_IN_SECONDS;

	const CLICK_ID_KEYS = [ 'gclid', 'gbraid', 'wbraid' ];
	const UTM_KEYS      = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];

	// Storage limits so a single anonymous request cannot bloat the table.
	const MAX_PAGE_LENGTH    = 500;
	const MAX_QUERY_LENGTH   = 2000;
	const MAX_COOKIES        = 50;
	const MAX_COOKIE_NAME    = 100;
	const MAX_COOKIE_VALUE   = 500;
	const MAX_COOKIES_LENGTH = 8000;

	private $cookie_name = self::COOKIE_NAME;

	public function hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'init', [ $this, 'set_visitor_uuid' ] );
		add_action( 'wp_ajax_osiriswp_track_event', [ $this, 'ajax_track_event' ] );
		add_action( 'wp_ajax_nopriv_osiriswp_track_event', [ $this, 'ajax_track_event' ] );
	}

	/**
	 * GA4 standard events that are always tracked.
	 */
	public static function get_ga4_events() {
		return [
			'page_view', 'click', 'form_submit', 'button_click', 'generate_lead',
			'add_to_cart', 'purchase', 'begin_checkout', 'add_payment_info',
			'add_shipping_info', 'search', 'view_item', 'view_item_list',
			'select_item', 'add_to_wishlist', 'remove_from_cart',
		];
	}

	/**
	 * Additional events configured in settings.
	 */
	public static function get_additional_events() {
		$tracked_events = (string) get_option( 'osiriswp_tracked_events', '' );
		if ( '' === trim( $tracked_events ) ) {
			return [];
		}

		$events = preg_split( '/[\s,]+/', $tracked_events );
		$events = array_map( [ __CLASS__, 'sanitize_event_name' ], $events );

		return array_values( array_unique( array_filter( $events ) ) );
	}

	/**
	 * Every event name the server accepts.
	 */
	public static function get_allowed_events() {
		$events = array_values( array_unique( array_merge( self::get_ga4_events(), self::get_additional_events() ) ) );

		/**
		 * Filter the list of event names OsirisWP accepts.
		 *
		 * @param string[] $events Allowed event names.
		 */
		return (array) apply_filters( 'osiriswp_allowed_events', $events );
	}

	/**
	 * Event names are limited to letters, digits, underscore, dash, dot and colon.
	 */
	public static function sanitize_event_name( $name ) {
		$name = preg_replace( '/[^A-Za-z0-9_\-.:]/', '', (string) $name );
		return substr( $name, 0, 100 );
	}

	/**
	 * Google click IDs are URL-safe base64-like tokens.
	 */
	public static function sanitize_click_id( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $value );
		return substr( $value, 0, 255 );
	}

	/**
	 * Parse a raw query string into attribution parameters (click IDs + UTM tags).
	 */
	private function attribution_from_query( $query_string ) {
		$params = [];
		parse_str( (string) $query_string, $params );

		$found = [];
		foreach ( self::CLICK_ID_KEYS as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$id = self::sanitize_click_id( $params[ $key ] );
				if ( '' !== $id ) {
					$found[ $key ] = $id;
				}
			}
		}
		foreach ( self::UTM_KEYS as $key ) {
			if ( isset( $params[ $key ] ) && is_scalar( $params[ $key ] ) ) {
				$val = $this->clean_string( sanitize_text_field( (string) $params[ $key ] ), 150 );
				if ( '' !== $val ) {
					$found[ $key ] = $val;
				}
			}
		}

		return $found;
	}

	/**
	 * Read the stored attribution cookie (written by PHP or by public.js).
	 */
	public function get_attribution() {
		if ( empty( $_COOKIE[ self::ATTRIBUTION_COOKIE ] ) || ! is_string( $_COOKIE[ self::ATTRIBUTION_COOKIE ] ) ) {
			return [];
		}

		$data = json_decode( wp_unslash( $_COOKIE[ self::ATTRIBUTION_COOKIE ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $data ) ) {
			return [];
		}

		$clean = [];
		foreach ( self::CLICK_ID_KEYS as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$id = self::sanitize_click_id( $data[ $key ] );
				if ( '' !== $id ) {
					$clean[ $key ] = $id;
				}
			}
		}
		foreach ( self::UTM_KEYS as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				$clean[ $key ] = $this->clean_string( sanitize_text_field( (string) $data[ $key ] ), 150 );
			}
		}
		$clean['ts'] = isset( $data['ts'] ) ? absint( $data['ts'] ) : time();

		// Expired attribution is ignored even if the browser still sends it.
		if ( $clean['ts'] + self::ATTRIBUTION_TTL < time() ) {
			return [];
		}

		return $clean;
	}

	/**
	 * Merge newly seen attribution with the stored one.
	 * A new ad click always wins (last click); UTM-only visits never erase a stored click ID.
	 */
	private function merge_attribution( array $stored, array $incoming ) {
		if ( empty( $incoming ) ) {
			return $stored;
		}

		$incoming_has_click = (bool) array_intersect_key( $incoming, array_flip( self::CLICK_ID_KEYS ) );
		$stored_has_click   = (bool) array_intersect_key( $stored, array_flip( self::CLICK_ID_KEYS ) );

		if ( $stored_has_click && ! $incoming_has_click ) {
			return $stored;
		}

		// Same click seen again (e.g. more events on the landing page): keep the original click time.
		foreach ( self::CLICK_ID_KEYS as $key ) {
			if ( isset( $incoming[ $key ], $stored[ $key ] ) && $incoming[ $key ] === $stored[ $key ] ) {
				return $stored;
			}
		}

		$incoming['ts'] = time();
		return $incoming;
	}

	/**
	 * Send the attribution cookie. Server-set cookies are not shortened by Safari ITP like JS cookies.
	 */
	private function write_attribution_cookie( array $attribution ) {
		if ( empty( $attribution ) ) {
			return;
		}

		$json    = wp_json_encode( $attribution );
		$expires = ( isset( $attribution['ts'] ) ? (int) $attribution['ts'] : time() ) + self::ATTRIBUTION_TTL;

		if ( ! headers_sent() && false !== $json && $expires > time() ) {
			// Raw cookie + rawurlencode so public.js can decode it with decodeURIComponent.
			setrawcookie(
				self::ATTRIBUTION_COOKIE,
				rawurlencode( $json ),
				[
					'expires'  => $expires,
					'path'     => self::cookie_path(),
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				]
			);
		}

		$_COOKIE[ self::ATTRIBUTION_COOKIE ] = false === $json ? '' : $json;
	}

	/**
	 * Capture click IDs / UTM tags from the current request URL into the attribution cookie.
	 */
	public function capture_attribution() {
		$query = isset( $_SERVER['QUERY_STRING'] ) ? wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' === $query ) {
			return;
		}

		$incoming = $this->attribution_from_query( $query );
		if ( empty( $incoming ) ) {
			return;
		}

		$stored = $this->get_attribution();
		$merged = $this->merge_attribution( $stored, $incoming );
		if ( $merged !== $stored ) {
			$this->write_attribution_cookie( $merged );
		}
	}

	public static function cookie_path() {
		return COOKIEPATH ? COOKIEPATH : '/';
	}

	public function enqueue() {
		wp_enqueue_script(
			'osiriswp-public',
			OSIRISWP_URL . 'assets/js/public.js',
			[ 'jquery' ],
			OSIRISWP_VERSION,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		$ga4_events        = self::get_ga4_events();
		$additional_events = self::get_additional_events();

		$config = [
			'ajax' => [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'osiriswp_track_event_nonce' ),
			],
			'cookie' => [
				'path'            => self::cookie_path(),
				'domain'          => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'attribution_ttl' => self::ATTRIBUTION_TTL,
			],
			// page_view is sent by the browser so it also works on page-cached sites.
			'track_page_view' => ! ( is_preview() || is_customize_preview() || is_embed() || is_feed() ),
			'debug' => [
				'debug_enabled' => (bool) get_option( 'osiriswp_debug', false ),
			],
			'tracked_events' => [
				'events'            => self::get_allowed_events(),
				'ga4_events'        => $ga4_events,
				'additional_events' => $additional_events,
			],
		];

		// Keep the legacy global names so existing integrations keep working.
		$inline = sprintf(
			'var osiriswp_ajax = %1$s; var osiriswp_debug = %2$s; var osiriswp_tracked_events = %3$s; var osiriswp_config = %4$s;',
			wp_json_encode( $config['ajax'] ),
			wp_json_encode( $config['debug'] ),
			wp_json_encode( $config['tracked_events'] ),
			wp_json_encode( [
				'cookie'             => $config['cookie'],
				'track_page_view'    => $config['track_page_view'],
			] )
		);
		wp_add_inline_script( 'osiriswp-public', $inline, 'before' );
	}

	/**
	 * Whether this request is one where we should touch the visitor cookie.
	 */
	private function should_handle_cookie() {
		if ( headers_sent() ) {
			return false;
		}
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		// Admin screens never track; admin-ajax (tracking endpoint) does.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		return true;
	}

	public function set_visitor_uuid() {
		if ( ! $this->should_handle_cookie() ) {
			return;
		}
		if ( null === $this->get_visitor_uuid() ) {
			$this->issue_visitor_cookie();
		}
		// Landing requests (?gclid=…) persist the click ID before anything is tracked.
		if ( ! wp_doing_ajax() ) {
			$this->capture_attribution();
		}
	}

	/**
	 * Generate a new UUID, send it as a cookie and make it available for this request.
	 */
	private function issue_visitor_cookie( $visitor_uuid = null ) {
		if ( null === $visitor_uuid ) {
			$visitor_uuid = wp_generate_uuid4();
		}

		if ( ! headers_sent() ) {
			setcookie(
				$this->cookie_name,
				$visitor_uuid,
				[
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => self::cookie_path(),
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => false, // Readable by front-end integrations (it is an identifier, not a credential).
					'samesite' => 'Lax',
				]
			);
		}

		$_COOKIE[ $this->cookie_name ] = $visitor_uuid;
		return $visitor_uuid;
	}

	/**
	 * Return the visitor UUID from the cookie, or null when missing/invalid.
	 */
	public function get_visitor_uuid() {
		if ( ! isset( $_COOKIE[ $this->cookie_name ] ) || ! is_string( $_COOKIE[ $this->cookie_name ] ) ) {
			return null;
		}

		$uuid = strtolower( sanitize_text_field( wp_unslash( $_COOKIE[ $this->cookie_name ] ) ) );

		return wp_is_uuid( $uuid ) ? $uuid : null;
	}

	/**
	 * Whether a cookie must never be stored (authentication, sessions, etc.).
	 */
	private function is_sensitive_cookie( $name ) {
		$name = strtolower( (string) $name );

		$prefixes = [
			'wordpress_',          // wordpress_logged_in_*, wordpress_sec_*, wordpress_test_cookie.
			'wp-settings',
			'wp_woocommerce_session_',
			'woocommerce_',
			'wp-postpass_',
			'comment_author',
			'phpsessid',
			'wp_lang',
		];
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}

		$needles = [ 'session', 'token', 'auth', 'csrf', 'xsrf', 'password', 'passwd', 'secret', 'jwt' ];
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $name, $needle ) ) {
				return true;
			}
		}

		/**
		 * Filter whether a cookie is excluded from storage.
		 *
		 * @param bool   $sensitive Whether the cookie is treated as sensitive.
		 * @param string $name      Cookie name (lowercased).
		 */
		return (bool) apply_filters( 'osiriswp_is_sensitive_cookie', false, $name );
	}

	/**
	 * Strip control characters and cap the length of a free-form string.
	 */
	private function clean_string( $value, $max_length ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = wp_check_invalid_utf8( (string) $value, true );
		$value = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );
		$value = trim( (string) $value );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	/**
	 * Filter, sanitize and encode cookies for storage.
	 */
	private function prepare_cookies( $cookies ) {
		if ( ! is_array( $cookies ) ) {
			return '';
		}

		$clean = [];
		foreach ( $cookies as $name => $value ) {
			if ( count( $clean ) >= self::MAX_COOKIES ) {
				break;
			}
			$name = $this->clean_string( $name, self::MAX_COOKIE_NAME );
			if ( '' === $name || $this->is_sensitive_cookie( $name ) ) {
				continue;
			}
			$clean[ $name ] = $this->clean_string( $value, self::MAX_COOKIE_VALUE );
		}

		if ( empty( $clean ) ) {
			return '';
		}

		$json = wp_json_encode( $clean );

		// Drop trailing cookies until the payload fits the column budget.
		while ( false !== $json && strlen( $json ) > self::MAX_COOKIES_LENGTH && ! empty( $clean ) ) {
			array_pop( $clean );
			$json = wp_json_encode( $clean );
		}

		return ( false === $json || empty( $clean ) ) ? '' : $json;
	}

	/**
	 * Build the absolute URL of the current request from the site's own host
	 * (never from the spoofable Host header, and without duplicating subdirectories).
	 */
	private function current_request_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parts       = wp_parse_url( home_url() );

		$origin = ( is_ssl() ? 'https' : ( $parts['scheme'] ?? 'http' ) ) . '://' . ( $parts['host'] ?? '' );
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		return esc_url_raw( $origin . '/' . ltrim( (string) $request_uri, '/' ) );
	}

	public function track_event( $event_name, $data = [] ) {
		global $wpdb;

		$event_name = self::sanitize_event_name( $event_name );
		if ( '' === $event_name ) {
			return false;
		}

		$visitor_uuid = $this->get_visitor_uuid();
		if ( ! $visitor_uuid ) {
			$visitor_uuid = $this->issue_visitor_cookie();
		}

		$debug_enabled = (bool) get_option( 'osiriswp_debug', false );
		if ( $debug_enabled ) {
			error_log( "OsirisWP Debug: {$event_name} is triggered" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Page URL from data (AJAX) when it is a valid http(s) URL, otherwise from the request.
		$page = '';
		if ( isset( $data['page_url'] ) && is_string( $data['page_url'] ) ) {
			$page = esc_url_raw( $data['page_url'], [ 'http', 'https' ] );
		}
		if ( '' === $page ) {
			$page = $this->current_request_url();
		}
		$page = $this->clean_string( $page, self::MAX_PAGE_LENGTH );

		// Query string from data (JavaScript) or from the request URL.
		if ( isset( $data['query_string'] ) && is_string( $data['query_string'] ) && '' !== $data['query_string'] ) {
			$query_strings = $data['query_string'];
		} else {
			$query_strings = (string) wp_parse_url( $this->current_request_url(), PHP_URL_QUERY );
		}
		$query_strings = $this->clean_string( ltrim( $query_strings, '?' ), self::MAX_QUERY_LENGTH );

		// GCLID: from the current URL first (a fresh ad click), otherwise the remembered one.
		$current_attribution = $this->attribution_from_query( $query_strings );
		$attribution         = $this->merge_attribution( $this->get_attribution(), $current_attribution );
		$gclid               = isset( $attribution['gclid'] ) ? $attribution['gclid'] : '';
		if ( ! empty( $current_attribution['gclid'] ) ) {
			$gclid = $current_attribution['gclid'];
		}
		if ( wp_doing_ajax() && ! empty( $current_attribution ) ) {
			// Cached landing page: PHP never saw the URL, so persist it from the AJAX call.
			$this->write_attribution_cookie( $attribution );
		}

		// Cookies from data (JavaScript) or from the request, minus sensitive ones.
		if ( isset( $data['cookies'] ) && is_array( $data['cookies'] ) ) {
			$cookies_data = $this->prepare_cookies( $data['cookies'] );
		} else {
			$cookies_data = $this->prepare_cookies( wp_unslash( $_COOKIE ) );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'osiriswp_events',
			[
				'visitor_uuid'  => $visitor_uuid,
				'event_name'    => $event_name,
				'page'          => $page,
				'gclid'         => $gclid,
				'query_strings' => $query_strings,
				'cookies'       => $cookies_data,
				'triggered_at'  => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $result && $debug_enabled ) {
			error_log( 'OsirisWP: Database insert failed - ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return false !== $result;
	}

	public function ajax_track_event() {
		$debug_enabled = (bool) get_option( 'osiriswp_debug', false );

		// Nonces for logged-out visitors are shared by everyone and expire on cached
		// pages, so they only add protection for logged-in users.
		if ( is_user_logged_in() ) {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'osiriswp_track_event_nonce' ) ) {
				if ( $debug_enabled ) {
					error_log( 'OsirisWP AJAX: Nonce verification failed' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
			}
		}

		$event_name = isset( $_POST['event_name'] ) ? self::sanitize_event_name( wp_unslash( $_POST['event_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $event_name ) {
			wp_send_json_error( [ 'message' => 'Event name required' ], 400 );
		}

		// Reject events that are not configured, so the public endpoint cannot be used to spam arbitrary rows.
		if ( ! in_array( $event_name, self::get_allowed_events(), true ) ) {
			if ( $debug_enabled ) {
				error_log( 'OsirisWP AJAX: Rejected unknown event - ' . $event_name ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error( [ 'message' => 'Event not allowed' ], 400 );
		}

		$data = [];
		if ( isset( $_POST['data'] ) && is_string( $_POST['data'] ) && strlen( $_POST['data'] ) <= 65536 ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$json_data = json_decode( wp_unslash( $_POST['data'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_array( $json_data ) ) {
				$data = $json_data;
			}
		}

		if ( $debug_enabled ) {
			error_log( 'OsirisWP AJAX: Tracking event - ' . $event_name ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Renew server-side cookies so browsers (Safari ITP) keep them for their full lifetime.
		$existing_uuid = $this->get_visitor_uuid();
		if ( $existing_uuid ) {
			$this->issue_visitor_cookie( $existing_uuid );
		}
		$stored_attribution = $this->get_attribution();
		if ( ! empty( $stored_attribution ) ) {
			$this->write_attribution_cookie( $stored_attribution );
		}

		try {
			$result = $this->track_event( $event_name, $data );
			wp_send_json_success( [ 'tracked' => $result ] );
		} catch ( Throwable $e ) {
			if ( $debug_enabled ) {
				error_log( 'OsirisWP AJAX: Exception - ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error( [ 'message' => 'Tracking failed' ], 500 );
		}
	}
}
