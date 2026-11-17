<?php
if (!defined('ABSPATH')) {
	exit;
}

class OsirisWP
{
	public function run()
	{
		// WP 6.7+ warns when translations load before `init`.
		add_action('init', [$this, 'load_textdomain']);

		// Admin area.
		if (is_admin()) {
			$admin = new OsirisWP_Admin();
			$admin->hooks();
		}

		// Public site.
		$public = new OsirisWP_Public();
		$public->hooks();

		// Example shortcode.
		add_shortcode('osiriswp_hello', [$this, 'shortcode_hello']);
	}

	public function load_textdomain()
	{
		load_plugin_textdomain('osiriswp', false, dirname(plugin_basename(OSIRISWP_FILE)) . '/languages');
	}

	public function shortcode_hello($atts)
	{
		$atts = shortcode_atts(['name' => 'world'], $atts, 'osiriswp_hello');
		$val = get_option('osiriswp_option_name', '');
		/* translators: 1: name, 2: configured greeting. */
		return sprintf(esc_html__('Hello %1$s! %2$s', 'osiriswp'), esc_html($atts['name']), esc_html($val));
	}
}
