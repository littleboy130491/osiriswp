<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Public {
	public function hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue() {
		wp_enqueue_style( 'osiriswp-public', OSIRISWP_URL . 'assets/css/public.css', [], OSIRISWP_VERSION );
		wp_enqueue_script( 'osiriswp-public', OSIRISWP_URL . 'assets/js/public.js', [ 'jquery' ], OSIRISWP_VERSION, true );
	}
}
