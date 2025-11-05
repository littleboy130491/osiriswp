<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Admin {
	private $menu;
	private $settings;
	private $csv_export;

	public function __construct() {
		$this->menu = new OsirisWP_Admin_Menu();
		$this->settings = new OsirisWP_Settings();
		$this->csv_export = new OsirisWP_CSV_Export();
	}

	public function hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		
		// Hook all components
		$this->menu->hooks();
		$this->settings->hooks();
		$this->csv_export->hooks();
	}

	public function enqueue( $hook ) {
		if ( strpos( $hook, 'osiriswp' ) !== false ) {
			wp_enqueue_style( 'osiriswp-admin', OSIRISWP_URL . 'assets/css/admin.css', [], OSIRISWP_VERSION );
			wp_enqueue_script( 'osiriswp-admin', OSIRISWP_URL . 'assets/js/admin.js', [ 'jquery' ], OSIRISWP_VERSION, true );
		}
	}
}
