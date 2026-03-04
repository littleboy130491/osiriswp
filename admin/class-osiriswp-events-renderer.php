<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Events_Renderer {
	private $data_handler;

	public function __construct() {
		$this->data_handler = new OsirisWP_Events_Data();
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		
		$visitor_uuid = isset( $_GET['visitor_uuid'] ) ? sanitize_text_field( $_GET['visitor_uuid'] ) : '';
		$page = isset( $_GET['page_url'] ) ? sanitize_text_field( $_GET['page_url'] ) : '';
		$event_name = isset( $_GET['event_name'] ) ? sanitize_text_field( $_GET['event_name'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		$cookie_name = isset( $_GET['cookie_name'] ) ? sanitize_text_field( $_GET['cookie_name'] ) : '';
		$event_count_mode = isset( $_GET['event_count_mode'] ) && 'once' === $_GET['event_count_mode'] ? 'once' : 'each';
		
		$page_num = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		
		$events = $this->data_handler->get_events( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name, $page_num, $per_page );
		$total_events = $this->data_handler->get_events_count( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name );
		$total_visitors = $this->data_handler->get_unique_visitors_count( $visitor_uuid, $page, 'page_view', $date_from, $date_to, $cookie_name );
		$event_unique_visitors = $this->data_handler->get_unique_visitors_count( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name );
		$event_triggered = 'once' === $event_count_mode ? $event_unique_visitors : $total_events;
		$event_rate = $total_visitors > 0 ? $event_triggered / $total_visitors : 0;
		$total_pages = ceil( $total_events / $per_page );
		
		$unique_pages = $this->data_handler->get_unique_pages();
		$unique_events = $this->data_handler->get_unique_event_names();
		
		$this->render_filters( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name, $event_count_mode, $unique_pages, $unique_events );
		$this->render_summary( $total_visitors, $event_triggered, $event_rate, $event_count_mode );
		$this->render_events_table( $events, $total_events, $total_pages, $page_num, $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name, $event_count_mode );
		$this->render_assets();
	}

	private function render_filters( $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name, $event_count_mode, $unique_pages, $unique_events ) {
		?>
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
						<label for="event_count_mode"><?php echo esc_html__( 'Event Trigger Count:', 'osiriswp' ); ?></label>
						<select id="event_count_mode" name="event_count_mode">
							<option value="each" <?php selected( $event_count_mode, 'each' ); ?>><?php echo esc_html__( 'Count each trigger', 'osiriswp' ); ?></option>
							<option value="once" <?php selected( $event_count_mode, 'once' ); ?>><?php echo esc_html__( 'Count once per visitor', 'osiriswp' ); ?></option>
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
					<button type="submit" name="export_csv" value="1" class="button button-secondary">
						<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: 3px;"></span>
						<?php echo esc_html__( 'Export to CSV', 'osiriswp' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_summary( $total_visitors, $event_triggered, $event_rate, $event_count_mode ) {
		?>
		<div class="osiriswp-summary">
			<div class="osiriswp-summary-card">
				<h3><?php echo esc_html__( 'Visitors (from page_view)', 'osiriswp' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $total_visitors ) ); ?></p>
			</div>
			<div class="osiriswp-summary-card">
				<h3>
					<?php
					echo esc_html(
						'once' === $event_count_mode
							? __( 'Events Triggered (Once per Visitor)', 'osiriswp' )
							: __( 'Events Triggered (Each Trigger)', 'osiriswp' )
					);
					?>
				</h3>
				<p><?php echo esc_html( number_format_i18n( $event_triggered ) ); ?></p>
			</div>
			<div class="osiriswp-summary-card">
				<h3><?php echo esc_html__( 'Rate (Events / Visitors)', 'osiriswp' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $event_rate, 2 ) ); ?></p>
			</div>
		</div>
		<?php
	}

	private function render_events_table( $events, $total_events, $total_pages, $page_num, $visitor_uuid, $page, $event_name, $date_from, $date_to, $cookie_name, $event_count_mode ) {
		?>
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
							$start_item = ( $page_num - 1 ) * 50 + 1;
							$end_item = min( $page_num * 50, $total_events );
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
						if ( ! empty( $event_count_mode ) ) {
							$url_params['event_count_mode'] = $event_count_mode;
						}
						
						$base_url = add_query_arg( $url_params, admin_url( 'admin.php' ) );
						
						echo paginate_links( [
							'base' => add_query_arg( 'paged', '%#%', $base_url ),
							'format' => '',
							'prev_text' => __( '&laquo;', 'osiriswp' ),
							'next_text' => __( '&raquo;', 'osiriswp' ),
							'total' => $total_pages,
							'current' => $page_num,
							'before_page_number' => '<span class="screen-reader-text">' . __( 'Page', 'osiriswp' ) . ' </span>',
							'add_args' => false,
						] );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_assets() {
		?>
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
		.osiriswp-summary {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
			gap: 15px;
			margin: 20px 0;
		}
		.osiriswp-summary-card {
			background: #fff;
			border: 1px solid #ccd0d4;
			padding: 18px;
			border-radius: 4px;
		}
		.osiriswp-summary-card h3 {
			margin: 0 0 8px;
			font-size: 14px;
			color: #50575e;
		}
		.osiriswp-summary-card p {
			margin: 0;
			font-size: 24px;
			line-height: 1.2;
			font-weight: 600;
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
}
