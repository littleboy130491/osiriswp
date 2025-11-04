<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Activator {
	public static function activate() {
		// Place to add default options, DB setup, etc.
		if ( ! get_option( 'osiriswp_option_name', false ) ) {
			add_option( 'osiriswp_option_name', 'Hello from OsirisWP' );
		}
	}
}
