<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Admin_Menu {
	
	public function hooks() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
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

	public function render_events_page() {
		$renderer = new OsirisWP_Events_Renderer();
		$renderer->render();
	}

	public function render_settings_page() {
		$renderer = new OsirisWP_Settings_Renderer();
		$renderer->render();
	}
}
