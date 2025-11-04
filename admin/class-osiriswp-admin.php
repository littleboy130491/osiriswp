<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Admin {
	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'osiriswp_events';
	}

	public function hooks() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_ajax_osiriswp_prune_data', [ $this, 'ajax_prune_data' ] );
		add_action( 'wp_ajax_nopriv_osiriswp_prune_data', [ $this, 'ajax_prune_data' ] );
	}

	public function add_menu() {
		add_menu_page(
			__( 'OsirisWP Event Tracking', 'osiriswp' ),
			'OsirisWP',
			'manage_options',
			'osiriswp_events',
			[ $this, 'render_events_page' ],
			'dashicons-chart-bar',
			25
		);
		
		add_submenu_page(
			'osiriswp_events',
			__( 'Event Tracking Dashboard', 'osiriswp' ),
			__( 'Events', 'osiriswp' ),
			'manage_options',
			'osiriswp_events',
			[ $this, 'render_events_page' ]
		);
		
		add_submenu_page(
			'osiriswp_events',
			__( 'OsirisWP Settings', 'osiriswp' ),
			__( 'Settings', 'osiriswp' ),
			'manage_options',
			'osiriswp_settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'osiriswp_settings', 'osiriswp_debug', [
			'type' => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default' => false,
		] );

		register_setting( 'osiriswp_settings', 'osiriswp_prune_days', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 30,
		] );

		register_setting( 'osiriswp_settings', 'osiriswp_tracked_events', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		] );

		register_setting( 'osiriswp_settings', 'osiriswp_cleanup_on_deactivation', [
			'type' => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default' => false,
		] );

		add_settings_section( 'osiriswp_main', __( 'Tracking Settings', 'osiriswp' ), function() {
			echo '<p>' . esc_html__( 'Configure event tracking behavior.', 'osiriswp' ) . '</p>';
		}, 'osiriswp_settings' );

		add_settings_field( 'osiriswp_debug', __( 'Debug Mode', 'osiriswp' ), function() {
			$debug = get_option( 'osiriswp_debug', false );
			echo '<label><input type="checkbox" name="osiriswp_debug" value="1" ' . checked( $debug, true, false ) . ' /> ' . esc_html__( 'Enable debug logging to console', 'osiriswp' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'When enabled, all events will be logged to browser console.', 'osiriswp' ) . '</p>';
		}, 'osiriswp_settings', 'osiriswp_main' );

		add_settings_field( 'osiriswp_tracked_events', __( 'Additional Events', 'osiriswp' ), function() {
			$events = get_option( 'osiriswp_tracked_events', '' );
			echo '<textarea name="osiriswp_tracked_events" rows="4" class="large-text" placeholder="joinchat, popup_click, custom_event, button_specific">' . esc_textarea( $events ) . '</textarea>';
			echo '<p class="description">' . esc_html__( 'GA4 standard events are tracked by default. Enter additional custom events separated by commas for specific plugins or custom functionality.', 'osiriswp' ) . '</p>';
			echo '<p class="description"><strong>' . esc_html__( 'GA4 events included by default:', 'osiriswp' ) . '</strong> page_view, click, form_submit, button_click, generate_lead, add_to_cart, purchase, begin_checkout, add_payment_info, add_shipping_info, search, view_item, view_item_list, select_item, add_to_wishlist, remove_from_cart</p>';
		}, 'osiriswp_settings', 'osiriswp_main' );

		add_settings_field( 'osiriswp_prune_days', __( 'Data Pruning', 'osiriswp' ), function() {
			$days = get_option( 'osiriswp_prune_days', 30 );
			echo '<input type="number" name="osiriswp_prune_days" value="' . esc_attr( $days ) . '" min="0" max="365" class="small-text" /> ' . esc_html__( 'days', 'osiriswp' );
			echo '<p class="description">' . esc_html__( 'Specify the age threshold for manual data pruning. When you click "Prune Old Data", all records older than this many days will be deleted.', 'osiriswp' ) . '</p>';
			echo '<p class="notice notice-warning" style="margin: 10px 0; padding: 10px;"><strong>' . esc_html__( 'Notice:', 'osiriswp' ) . '</strong> ' . esc_html__( 'Setting this to 0 will delete ALL event data when you prune.', 'osiriswp' ) . '</p>';
		}, 'osiriswp_settings', 'osiriswp_main' );

		add_settings_field( 'osiriswp_prune_now', __( 'Prune Data Now', 'osiriswp' ), function() {
			echo '<button type="button" id="osiriswp-prune-now" class="button">' . esc_html__( 'Prune Old Data', 'osiriswp' ) . '</button>';
			echo '<p class="description">' . esc_html__( 'Immediately delete event data older than the specified days.', 'osiriswp' ) . '</p>';
		}, 'osiriswp_settings', 'osiriswp_main' );

		add_settings_field( 'osiriswp_cleanup_on_deactivation', __( 'Database Cleanup', 'osiriswp' ), function() {
			$cleanup = get_option( 'osiriswp_cleanup_on_deactivation', false );
			echo '<label><input type="checkbox" name="osiriswp_cleanup_on_deactivation" value="1" ' . checked( $cleanup, true, false ) . ' /> ' . esc_html__( 'Clean database on plugin deactivation', 'osiriswp' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'When enabled, all event data and plugin settings will be permanently deleted when you deactivate the plugin. This cannot be undone.', 'osiriswp' ) . '</p>';
			echo '<p class="notice notice-error" style="margin: 10px 0; padding: 10px;"><strong>' . esc_html__( 'Warning:', 'osiriswp' ) . '</strong> ' . esc_html__( 'This will permanently delete ALL event data and settings if you deactivate the plugin.', 'osiriswp' ) . '</p>';
		}, 'osiriswp_settings', 'osiriswp_main' );
	}

	public function render_events_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		
		$visitor_uuid = isset( $_GET['visitor_uuid'] ) ? sanitize_text_field( $_GET['visitor_uuid'] ) : '';
		$page = isset( $_GET['page_url'] ) ? sanitize_text_field( $_GET['page_url'] ) : '';
		$event_name = isset( $_GET['event_name'] ) ? sanitize_text_field( $_GET['event_name'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		$cookie_name = isset( $_GET['cookie_name'] ) ? sanitize_text_field( $_GET['cookie_name'] ) : '';
		
		$page_num = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		
		$events = $this->get_events( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name, $page_num, $per_page );
		$total_events = $this->get_events_count( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name );
		$total_pages = ceil( $total_events / $per_page );
		
		$unique_pages = $this->get_unique_pages();
		$unique_events = $this->get_unique_event_names();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OsirisWP Event Tracking', 'osiriswp' ); ?></h1>
			
			<div class="osiriswp-filters">
				<h2><?php echo esc_html__( 'Filter Events', 'osiriswp' ); ?></h2>
				<form method="get" action="">
					<input type="hidden" name="page" value="osiriswp_events">
					
					<div class="filter-row">
						<div class="filter-field">
							<label for="visitor_uuid"><?php echo esc_html__( 'Visitor UUID:', 'osiriswp' ); ?></label>
							<input type="text" id="visitor_uuid" name="visitor_uuid" value="<?php echo esc_attr( $visitor_uuid ); ?>" />
						</div>
						
						<div class="filter-field">
							<label for="event_name"><?php echo esc_html__( 'Event Name:', 'osiriswp' ); ?></label>
							<select id="event_name" name="event_name">
								<option value=""><?php echo esc_html__( 'All Events', 'osiriswp' ); ?></option>
								<?php foreach ( $unique_events as $evt_name ): ?>
									<option value="<?php echo esc_attr( $evt_name ); ?>" <?php selected( $event_name, $evt_name ); ?>><?php echo esc_html( $evt_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						
						<div class="filter-field">
							<label for="page_url"><?php echo esc_html__( 'Page URL:', 'osiriswp' ); ?></label>
							<select id="page_url" name="page_url">
								<option value=""><?php echo esc_html__( 'All Pages', 'osiriswp' ); ?></option>
								<?php foreach ( $unique_pages as $page_url ): ?>
									<option value="<?php echo esc_attr( $page_url ); ?>" <?php selected( $page, $page_url ); ?>><?php echo esc_html( substr( $page_url, 0, 60 ) . ( strlen( $page_url ) > 60 ? '...' : '' ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					
					<div class="filter-row">
						<div class="filter-field">
							<label for="date_from"><?php echo esc_html__( 'Date From:', 'osiriswp' ); ?></label>
							<input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
						</div>
						
						<div class="filter-field">
							<label for="date_to"><?php echo esc_html__( 'Date To:', 'osiriswp' ); ?></label>
							<input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
						</div>
						
						<div class="filter-field">
							<label for="cookie_name"><?php echo esc_html__( 'Cookie Name:', 'osiriswp' ); ?></label>
							<input type="text" id="cookie_name" name="cookie_name" value="<?php echo esc_attr( $cookie_name ); ?>" />
						</div>
					</div>
					
					<div class="filter-actions">
						<input type="submit" class="button button-primary" value="<?php echo esc_html__( 'Filter', 'osiriswp' ); ?>">
						<a href="?page=osiriswp_events" class="button"><?php echo esc_html__( 'Clear', 'osiriswp' ); ?></a>
					</div>
				</form>
			</div>
			
			<div class="osiriswp-events-table">
				<h2><?php echo esc_html__( 'Recent Events', 'osiriswp' ); ?></h2>
				
				<?php if ( $total_pages > 1 ): ?>
					<div class="tablenav top">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php 
								echo sprintf( 
									esc_html__( '%d total events', 'osiriswp' ),
									$total_events
								);
								?>
							</span>
						</div>
					</div>
				<?php endif; ?>
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'ID', 'osiriswp' ); ?></th>
							<th><?php echo esc_html__( 'Visitor UUID', 'osiriswp' ); ?></th>
							<th><?php echo esc_html__( 'Event Name', 'osiriswp' ); ?></th>
							<th><?php echo esc_html__( 'Page', 'osiriswp' ); ?></th>
							<th><?php echo esc_html__( 'Query Strings', 'osiriswp' ); ?></th>
							<th><?php echo esc_html__( 'Cookies', 'osiriswp' ); ?></th>
							<th><?php echo esc_html__( 'Triggered At', 'osiriswp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $events ) ): ?>
							<?php foreach ( $events as $event ): ?>
								<tr>
									<td><?php echo esc_html( $event->id ); ?></td>
									<td><code><a href="#" class="visitor-uuid-link" data-uuid="<?php echo esc_attr( $event->visitor_uuid ); ?>" onclick="return false;"><?php echo esc_html( $event->visitor_uuid ); ?></a></code></td>
									<td><strong><?php echo esc_html( $event->event_name ); ?></strong></td>
									<td><a href="<?php echo esc_url( $event->page ); ?>" target="_blank"><?php echo esc_html( substr( $event->page, 0, 50 ) . ( strlen( $event->page ) > 50 ? '...' : '' ) ); ?></a></td>
									<td>
										<?php 
										if ( ! empty( $event->query_strings ) ) {
											parse_str( $event->query_strings, $params );
											if ( ! empty( $params ) ) {
												$count = count( $params );
												echo '<div class="collapsible-data">';
												echo '<span class="data-summary clickable" title="Click to expand">' . sprintf( esc_html__( '%d parameters', 'osiriswp' ), $count ) . '</span>';
												echo '<div class="data-content" style="display:none;">';
												echo '<div class="data-full">';
												foreach ( $params as $key => $value ) {
													echo '<div><strong>' . esc_html( $key ) . ':</strong> ' . esc_html( $value ) . '</div>';
												}
												echo '</div>';
												echo '<button class="copy-btn" data-copy="' . esc_attr( $event->query_strings ) . '">' . esc_html__( 'Copy', 'osiriswp' ) . '</button>';
												echo '</div>';
												echo '</div>';
											} else {
												echo '<em>' . esc_html__( 'No parameters', 'osiriswp' ) . '</em>';
											}
										} else {
											echo '<em>' . esc_html__( 'No query string', 'osiriswp' ) . '</em>';
										}
										?>
									</td>
									<td>
										<?php 
										$cookies_data = json_decode( $event->cookies, true );
										if ( $cookies_data && is_array( $cookies_data ) ) {
											$count = count( $cookies_data );
											echo '<div class="collapsible-data">';
											echo '<span class="data-summary clickable" title="Click to expand">' . sprintf( esc_html__( '%d cookies', 'osiriswp' ), $count ) . '</span>';
											echo '<div class="data-content" style="display:none;">';
											echo '<div class="data-full">';
											foreach ( $cookies_data as $key => $value ) {
												echo '<div><strong>' . esc_html( $key ) . ':</strong> ' . esc_html( $value ) . '</div>';
											}
											echo '</div>';
											echo '<button class="copy-btn" data-copy="' . esc_attr( $event->cookies ) . '">' . esc_html__( 'Copy', 'osiriswp' ) . '</button>';
											echo '</div>';
											echo '</div>';
										} else {
											echo '<em>' . esc_html__( 'No cookies', 'osiriswp' ) . '</em>';
										}
										?>
									</td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $event->triggered_at ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="7"><?php echo esc_html__( 'No events found.', 'osiriswp' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
				
				<?php if ( $total_pages > 1 ): ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php 
								$start_item = ( $page_num - 1 ) * $per_page + 1;
								$end_item = min( $page_num * $per_page, $total_events );
								echo sprintf( 
									esc_html__( 'Showing %1$d–%2$d of %3$d events', 'osiriswp' ),
									$start_item,
									$end_item,
									$total_events
								);
								?>
							</span>
							<?php
							// Build URL with all current parameters
							$url_params = [
								'page' => 'osiriswp_events',
								'paged' => '%#%',
							];
							
							if ( ! empty( $visitor_uuid ) ) {
								$url_params['visitor_uuid'] = $visitor_uuid;
							}
							if ( ! empty( $page ) ) {
								$url_params['page_url'] = $page;
							}
							if ( ! empty( $event_name ) ) {
								$url_params['event_name'] = $event_name;
							}
							if ( ! empty( $date_from ) ) {
								$url_params['date_from'] = $date_from;
							}
							if ( ! empty( $date_to ) ) {
								$url_params['date_to'] = $date_to;
							}
							if ( ! empty( $cookie_name ) ) {
								$url_params['cookie_name'] = $cookie_name;
							}
							
							$current_url = add_query_arg( $url_params, admin_url( 'admin.php' ) );
							$current_url = str_replace( '%#%', '%#%', $current_url );
							
							echo paginate_links( [
								'base' => str_replace( '%#%', '%#%', esc_url( $current_url ) ),
								'format' => '',
								'prev_text' => __( '&laquo;', 'osiriswp' ),
								'next_text' => __( '&raquo;', 'osiriswp' ),
								'total' => $total_pages,
								'current' => $page_num,
								'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'osiriswp' ) . ' </span>',
							] );
							?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		
		<style>
		.osiriswp-filters {
			background: #fff;
			border: 1px solid #ccd0d4;
			padding: 20px;
			margin: 20px 0;
		}
		.filter-row {
			display: flex;
			gap: 20px;
			margin-bottom: 15px;
		}
		.filter-field {
			flex: 1;
		}
		.filter-field label {
			display: block;
			margin-bottom: 5px;
			font-weight: bold;
		}
		.filter-field input,
		.filter-field select {
			width: 100%;
		}
		.filter-actions {
			margin-top: 15px;
		}
		.filter-actions .button {
			margin-right: 10px;
		}
		
		/* Pagination styling */
		.osiriswp-events-table .tablenav {
			margin: 10px 0;
		}
		.osiriswp-events-table .tablenav-pages {
			float: right;
		}
		.osiriswp-events-table .displaying-num {
			margin-right: 10px;
			color: #50575e;
		}
		.osiriswp-events-table .pagination-links {
			margin: 0;
		}
		.osiriswp-events-table .pagination-links a,
		.osiriswp-events-table .pagination-links span {
			display: inline-block;
			min-width: 30px;
			height: 30px;
			line-height: 28px;
			text-align: center;
			border: 1px solid #ddd;
			margin: 0 1px;
			text-decoration: none;
			color: #0073aa;
		}
		.osiriswp-events-table .pagination-links a:hover,
		.osiriswp-events-table .pagination-links a:focus {
			background: #f3f3f3;
			color: #0073aa;
		}
		.osiriswp-events-table .pagination-links .current {
			background: #0073aa;
			color: #fff;
			border-color: #0073aa;
		}
		.osiriswp-events-table .pagination-links .prev,
		.osiriswp-events-table .pagination-links .next {
			padding: 0 10px;
		}
		
		/* Table styling */
		.osiriswp-events-table .wp-list-table {
			margin-top: 10px;
		}
		.osiriswp-events-table .column-id {
			width: 60px;
		}
		.osiriswp-events-table .column-visitor_uuid {
			width: 120px;
		}
		.osiriswp-events-table .column-event_name {
			width: 120px;
		}
		.osiriswp-events-table .column-triggered_at {
			width: 150px;
		}
		
		/* Visitor UUID link styling */
		.visitor-uuid-link {
			color: #0073aa;
			text-decoration: none;
			cursor: pointer;
		}
		.visitor-uuid-link:hover {
			color: #005a87;
			text-decoration: underline;
		}
		
		/* Highlight effect for visitor UUID field */
		#visitor_uuid.highlight {
			border-color: #0073aa;
			box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.3);
			transition: all 0.3s ease;
		}
		
		/* Collapsible data styling */
		.collapsible-data {
			position: relative;
		}
		.data-summary {
			color: #0073aa;
			cursor: pointer;
			font-size: 12px;
			padding: 2px 4px;
			border-radius: 3px;
			display: inline-block;
		}
		.data-summary:hover {
			background: #f0f6fc;
			color: #005a87;
		}
		.data-content {
			position: absolute;
			top: 100%;
			left: 0;
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 4px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
			z-index: 1000;
			min-width: 250px;
			max-width: 400px;
		}
		.data-full {
			padding: 10px;
			max-height: 200px;
			overflow-y: auto;
			font-size: 11px;
			line-height: 1.4;
		}
		.data-full div {
			margin-bottom: 3px;
			word-break: break-all;
		}
		.copy-btn {
			background: #0073aa;
			color: #fff;
			border: none;
			padding: 5px 10px;
			font-size: 11px;
			cursor: pointer;
			width: 100%;
			border-radius: 0 0 3px 3px;
		}
		.copy-btn:hover {
			background: #005a87;
		}
		.copy-btn.copied {
			background: #46b450;
		}
		</style>
		
		<script>
		jQuery(document).ready(function($) {
			// Handle visitor UUID click to auto-fill filter
			$('.visitor-uuid-link').on('click', function(e) {
				e.preventDefault();
				var uuid = $(this).data('uuid');
				
				// Fill the visitor UUID filter
				$('#visitor_uuid').val(uuid);
				
				// Scroll to the filter section
				$('.osiriswp-filters')[0].scrollIntoView({ behavior: 'smooth' });
				
				// Highlight the field briefly
				$('#visitor_uuid').addClass('highlight');
				setTimeout(function() {
					$('#visitor_uuid').removeClass('highlight');
				}, 2000);
			});
			
			// Handle collapsible data display
			$('.data-summary').on('click', function(e) {
				e.stopPropagation();
				var $content = $(this).siblings('.data-content');
				var $summary = $(this);
				
				// Close other open popups
				$('.data-content').not($content).hide();
				
				// Toggle current popup
				$content.toggle();
				
				// Position popup to avoid going off screen
				if ($content.is(':visible')) {
					var popupRect = $content[0].getBoundingClientRect();
					var windowWidth = $(window).width();
					
					if (popupRect.right > windowWidth) {
						$content.css('left', 'auto');
						$content.css('right', '0');
					} else {
						$content.css('left', '0');
						$content.css('right', 'auto');
					}
				}
			});
			
			// Close popups when clicking outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.collapsible-data').length) {
					$('.data-content').hide();
				}
			});
			
			// Handle copy to clipboard
			$('.copy-btn').on('click', function(e) {
				e.stopPropagation();
				var textToCopy = $(this).data('copy');
				var $btn = $(this);
				
				// Create temporary textarea for copying
				var $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(textToCopy).select();
				document.execCommand('copy');
				$temp.remove();
				
				// Show feedback
				var originalText = $btn.text();
				$btn.text('Copied!').addClass('copied');
				setTimeout(function() {
					$btn.text(originalText).removeClass('copied');
				}, 2000);
			});
		});
		</script>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OsirisWP Settings', 'osiriswp' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'osiriswp_settings' ); ?>
				<?php do_settings_sections( 'osiriswp_settings' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#osiriswp-prune-now').on('click', function(e) {
				e.preventDefault();
				
				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete old event data? This action cannot be undone.', 'osiriswp' ) ); ?>')) {
					return;
				}
				
				var $button = $(this);
				$button.prop('disabled', true).text('<?php echo esc_js( __( 'Pruning...', 'osiriswp' ) ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'osiriswp_prune_data',
						nonce: '<?php echo wp_create_nonce( 'osiriswp_prune_nonce' ); ?>'
					},
					success: function(response) {
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Prune Old Data', 'osiriswp' ) ); ?>');
						
						console.log('AJAX Response:', response);
						
						if (response.success) {
							alert('<?php echo esc_js( __( 'Data pruned successfully!', 'osiriswp' ) ); ?> ' + response.data.message);
							// Reload the page to refresh the event list
							location.reload();
						} else {
							alert('<?php echo esc_js( __( 'Error pruning data:', 'osiriswp' ) ); ?> ' + response.data.message);
						}
					},
					error: function(xhr, status, error) {
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Prune Old Data', 'osiriswp' ) ); ?>');
						console.log('AJAX Error:', status, error);
						console.log('Response Text:', xhr.responseText);
						alert('<?php echo esc_js( __( 'AJAX Error:', 'osiriswp' ) ); ?> ' + status + ' - ' + error + '\n\nResponse: ' + xhr.responseText);
					}
				});
			});
		});
		</script>
		<?php
	}

	public function ajax_prune_data() {
		// Remove any BOM or output buffering
		if (ob_get_length()) ob_clean();
		
		// Set clean JSON headers
		header('Content-Type: application/json; charset=utf-8');
		
		// Debug logging
		error_log('OsirisWP: ajax_prune_data called');
		
		check_ajax_referer( 'osiriswp_prune_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			error_log('OsirisWP: Insufficient permissions');
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'osiriswp' ) ] );
		}
		
		$days = get_option( 'osiriswp_prune_days', 30 );
		error_log('OsirisWP: Pruning days retrieved from DB: ' . var_export($days, true));
		error_log('OsirisWP: All osiriswp options: ' . print_r(get_option('osiriswp_settings'), true));
		
		global $wpdb;
		
		if ( $days == 0 ) {
			// Delete ALL data
			error_log('OsirisWP: Deleting ALL data');
			$deleted = $wpdb->query( "DELETE FROM {$this->table_name}" );
			if ( $deleted !== false ) {
				wp_send_json_success( [ 
					'message' => __( 'ALL event data has been deleted.', 'osiriswp' ) 
				] );
			} else {
				error_log('OsirisWP: Database error deleting all data');
				wp_send_json_error( [ 'message' => __( 'Database error occurred.', 'osiriswp' ) ] );
			}
		} else {
			// Delete data older than specified days
			$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			error_log('OsirisWP: Deleting data older than: ' . $cutoff_date);
			
			$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$this->table_name} WHERE triggered_at < %s",
				$cutoff_date
			) );
			
			if ( $deleted !== false ) {
				wp_send_json_success( [ 
					'message' => sprintf( 
						__( '%d records deleted (older than %d days).', 'osiriswp' ), 
						$deleted,
						$days
					) 
				] );
			} else {
				error_log('OsirisWP: Database error deleting old data');
				wp_send_json_error( [ 'message' => __( 'Database error occurred.', 'osiriswp' ) ] );
			}
		}
	}

	private function get_events( $visitor_uuid = '', $page = '', $event_name = '', $date_from = '', $date_to = '', $cookie_name = '', $page_num = 1, $per_page = 50 ) {
		global $wpdb;
		
		$where = [];
		$table_name = $wpdb->prefix . 'osiriswp_events';
		$sql = "SELECT * FROM {$table_name}";
		
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

	private function get_events_count( $visitor_uuid = '', $page = '', $event_name = '', $date_from = '', $date_to = '', $cookie_name = '' ) {
		global $wpdb;
		
		$where = [];
		$table_name = $wpdb->prefix . 'osiriswp_events';
		$sql = "SELECT COUNT(*) FROM {$table_name}";
		
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
		
		if ( ! empty( $visitor_uuid ) ) {
			$where[] = $wpdb->prepare( "visitor_uuid = %s", $visitor_uuid );
		}
		
		if ( ! empty( $page ) ) {
			$where[] = $wpdb->prepare( "page = %s", $page );
		}
		
		if ( ! empty( $date_from ) ) {
			$where[] = $wpdb->prepare( "DATE(triggered_at) >= %s", $date_from );
		}
		
		if ( ! empty( $date_to ) ) {
			$where[] = $wpdb->prepare( "DATE(triggered_at) <= %s", $date_to );
		}
		
		if ( ! empty( $cookie_name ) ) {
			$where[] = $wpdb->prepare( "cookies LIKE %s", '%' . $wpdb->esc_like( $cookie_name ) . '%' );
		}
		
		if ( ! empty( $where ) ) {
			$sql .= " WHERE " . implode( " AND ", $where );
		}
		
		return (int) $wpdb->get_var( $sql );
	}

	private function get_unique_pages() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osiriswp_events';
		return $wpdb->get_col( "SELECT DISTINCT page FROM {$table_name} ORDER BY page ASC" );
	}
	
	private function get_unique_event_names() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'osiriswp_events';
		return $wpdb->get_col( "SELECT DISTINCT event_name FROM {$table_name} ORDER BY event_name ASC" );
	}

	public function enqueue( $hook ) {
		if ( strpos( $hook, 'osiriswp' ) !== false ) {
			wp_enqueue_style( 'osiriswp-admin', OSIRISWP_URL . 'assets/css/admin.css', [], OSIRISWP_VERSION );
			wp_enqueue_script( 'osiriswp-admin', OSIRISWP_URL . 'assets/js/admin.js', [ 'jquery' ], OSIRISWP_VERSION, true );
		}
	}
}
