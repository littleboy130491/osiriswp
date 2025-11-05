<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Settings {
	
	public function hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_osiriswp_prune_data', [ $this, 'ajax_prune_data' ] );
		add_action( 'wp_ajax_nopriv_osiriswp_prune_data', [ $this, 'ajax_prune_data' ] );
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
		$table_name = $wpdb->prefix . 'osiriswp_events';
		
		if ( $days == 0 ) {
			// Delete ALL data
			error_log('OsirisWP: Deleting ALL data');
			$deleted = $wpdb->query( "DELETE FROM {$table_name}" );
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
				"DELETE FROM {$table_name} WHERE triggered_at < %s",
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
}
