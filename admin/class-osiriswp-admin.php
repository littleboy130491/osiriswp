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
		if ( false === strpos( (string) $hook, 'osiriswp' ) ) {
			return;
		}

		wp_enqueue_style( 'osiriswp-admin', OSIRISWP_URL . 'assets/css/admin.css', [], OSIRISWP_VERSION );
		wp_enqueue_script( 'osiriswp-admin', OSIRISWP_URL . 'assets/js/admin.js', [ 'jquery' ], OSIRISWP_VERSION, [ 'in_footer' => true ] );

		wp_localize_script( 'osiriswp-admin', 'osiriswpAdmin', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'pruneNonce' => current_user_can( 'manage_options' ) ? wp_create_nonce( 'osiriswp_prune_nonce' ) : '',
			'i18n'       => [
				'copied'       => __( 'Copied!', 'osiriswp' ),
				'confirmPrune' => __( 'Are you sure you want to delete old event data? This action cannot be undone.', 'osiriswp' ),
				'pruning'      => __( 'Pruning...', 'osiriswp' ),
				'pruneSuccess' => __( 'Data pruned successfully!', 'osiriswp' ),
				'pruneError'   => __( 'Error pruning data:', 'osiriswp' ),
			],
		] );
	}
}
