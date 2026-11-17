<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Events_Renderer {
	const PER_PAGE = 50;

	private $data_handler;

	public function __construct() {
		$this->data_handler = new OsirisWP_Events_Data();
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		
		$filters = OsirisWP_Events_Data::get_filters_from_request();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$event_count_mode = isset( $_GET['event_count_mode'] ) && 'once' === $_GET['event_count_mode'] ? 'once' : 'each';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_num = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = self::PER_PAGE;

		$events                = $this->data_handler->get_events( $filters, $page_num, $per_page );
		$total_events          = $this->data_handler->get_events_count( $filters );
		$total_visitors        = $this->data_handler->get_unique_visitors_count( array_merge( $filters, [ 'event_name' => 'page_view' ] ) );
		$event_unique_visitors = $this->data_handler->get_unique_visitors_count( $filters );
		$gclid_visitors        = $this->data_handler->get_unique_visitors_count( array_merge( $filters, [ 'has_gclid' => '1' ] ) );
		$event_triggered       = 'once' === $event_count_mode ? $event_unique_visitors : $total_events;
		$event_rate            = $total_visitors > 0 ? $event_triggered / $total_visitors : 0;
		$total_pages           = (int) ceil( $total_events / $per_page );

		$unique_pages  = $this->data_handler->get_unique_pages();
		$unique_events = $this->data_handler->get_unique_event_names();

		echo '<div class="wrap osiriswp-wrap">';
		echo '<h1>' . esc_html__( 'Event Tracking Dashboard', 'osiriswp' ) . '</h1>';
		$this->render_filters( $filters, $event_count_mode, $unique_pages, $unique_events );
		$this->render_summary( $total_visitors, $event_triggered, $event_rate, $event_count_mode, $gclid_visitors );
		$this->render_events_table( $events, $total_events, $total_pages, $page_num, $filters, $event_count_mode );
		echo '</div>';
	}

	private function render_filters( $filters, $event_count_mode, $unique_pages, $unique_events ) {
		$visitor_uuid = $filters['visitor_uuid'];
		$page         = $filters['page'];
		$event_name   = $filters['event_name'];
		$date_from    = $filters['date_from'];
		$date_to      = $filters['date_to'];
		$cookie_name  = $filters['cookie_name'];
		?>
		<div class="osiriswp-filters">
			<h2><?php echo esc_html__( 'Filter Events', 'osiriswp' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="osiriswp_events">
				<?php wp_nonce_field( OsirisWP_CSV_Export::NONCE_ACTION, OsirisWP_CSV_Export::NONCE_FIELD, false ); ?>
				
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

					<div class="filter-field">
						<label for="gclid"><?php echo esc_html__( 'GCLID:', 'osiriswp' ); ?></label>
						<input type="text" id="gclid" name="gclid" value="<?php echo esc_attr( $filters['gclid'] ); ?>" />
					</div>

					<div class="filter-field">
						<label for="has_gclid"><?php echo esc_html__( 'Google Ads Clicks:', 'osiriswp' ); ?></label>
						<select id="has_gclid" name="has_gclid">
							<option value="" <?php selected( $filters['has_gclid'], '' ); ?>><?php echo esc_html__( 'All events', 'osiriswp' ); ?></option>
							<option value="1" <?php selected( $filters['has_gclid'], '1' ); ?>><?php echo esc_html__( 'Only events with a GCLID', 'osiriswp' ); ?></option>
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
					<input type="submit" class="button button-primary" value="<?php echo esc_attr__( 'Filter', 'osiriswp' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=osiriswp_events' ) ); ?>" class="button"><?php echo esc_html__( 'Clear', 'osiriswp' ); ?></a>
					<button type="submit" name="export_csv" value="1" class="button button-secondary">
						<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: 3px;"></span>
						<?php echo esc_html__( 'Export to CSV', 'osiriswp' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_summary( $total_visitors, $event_triggered, $event_rate, $event_count_mode, $gclid_visitors ) {
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
			<div class="osiriswp-summary-card">
				<h3><?php echo esc_html__( 'Visitors with GCLID', 'osiriswp' ); ?></h3>
				<p><?php echo esc_html( number_format_i18n( $gclid_visitors ) ); ?></p>
			</div>
		</div>
		<?php
	}

	private function render_events_table( $events, $total_events, $total_pages, $page_num, $filters, $event_count_mode ) {
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
						<th><?php echo esc_html__( 'GCLID', 'osiriswp' ); ?></th>
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
								<td><code><a href="#" class="visitor-uuid-link" data-uuid="<?php echo esc_attr( $event->visitor_uuid ); ?>"><?php echo esc_html( $event->visitor_uuid ); ?></a></code></td>
								<td><strong><?php echo esc_html( $event->event_name ); ?></strong></td>
								<td><a href="<?php echo esc_url( $event->page ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( substr( $event->page, 0, 50 ) . ( strlen( $event->page ) > 50 ? '...' : '' ) ); ?></a></td>
								<td>
									<?php if ( ! empty( $event->gclid ) ) : ?>
										<code class="osiriswp-gclid" title="<?php echo esc_attr( $event->gclid ); ?>"><?php echo esc_html( strlen( $event->gclid ) > 16 ? substr( $event->gclid, 0, 16 ) . '…' : $event->gclid ); ?></code>
										<button type="button" class="button-link osiriswp-copy-inline copy-btn-inline" data-copy="<?php echo esc_attr( $event->gclid ); ?>"><?php echo esc_html__( 'Copy', 'osiriswp' ); ?></button>
									<?php else : ?>
										<em>&mdash;</em>
									<?php endif; ?>
								</td>
								<td>
									<?php 
									if ( ! empty( $event->query_strings ) ) {
										$params = [];
										parse_str( $event->query_strings, $params );
										if ( ! empty( $params ) ) {
											$count = count( $params );
											echo '<div class="collapsible-data">';
											echo '<span class="data-summary clickable" title="Click to expand">' . sprintf( esc_html__( '%d parameters', 'osiriswp' ), $count ) . '</span>';
											echo '<div class="data-content" style="display:none;">';
											echo '<div class="data-full">';
											foreach ( $params as $key => $value ) {
												echo '<div><strong>' . esc_html( $key ) . ':</strong> ' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</div>';
											}
											echo '</div>';
											echo '<button type="button" class="copy-btn" data-copy="' . esc_attr( $event->query_strings ) . '">' . esc_html__( 'Copy', 'osiriswp' ) . '</button>';
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
											echo '<div><strong>' . esc_html( $key ) . ':</strong> ' . esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) . '</div>';
										}
										echo '</div>';
										echo '<button type="button" class="copy-btn" data-copy="' . esc_attr( $event->cookies ) . '">' . esc_html__( 'Copy', 'osiriswp' ) . '</button>';
										echo '</div>';
										echo '</div>';
									} else {
										echo '<em>' . esc_html__( 'No cookies', 'osiriswp' ) . '</em>';
									}
									?>
								</td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $event->triggered_at ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="8"><?php echo esc_html__( 'No events found.', 'osiriswp' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			
			<?php if ( $total_pages > 1 ): ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php 
							$start_item = ( $page_num - 1 ) * self::PER_PAGE + 1;
							$end_item = min( $page_num * self::PER_PAGE, $total_events );
							echo sprintf( 
								esc_html__( 'Showing %1$d–%2$d of %3$d events', 'osiriswp' ),
								$start_item,
								$end_item,
								$total_events
							);
							?>
						</span>
						<?php
						// Build URL with all current (non-empty) filters.
						$url_params = [ 'page' => 'osiriswp_events' ];
						$param_map  = [ 'visitor_uuid' => 'visitor_uuid', 'page' => 'page_url', 'event_name' => 'event_name', 'date_from' => 'date_from', 'date_to' => 'date_to', 'cookie_name' => 'cookie_name', 'gclid' => 'gclid', 'has_gclid' => 'has_gclid' ];
						foreach ( $param_map as $key => $param ) {
							if ( '' !== (string) $filters[ $key ] ) {
								$url_params[ $param ] = $filters[ $key ];
							}
						}
						$url_params['event_count_mode'] = $event_count_mode;

						$base_url = add_query_arg( rawurlencode_deep( $url_params ), admin_url( 'admin.php' ) );
						
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
}
