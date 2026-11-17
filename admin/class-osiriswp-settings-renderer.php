<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Settings_Renderer {

	public function render() {
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
		<?php
		// Prune button behaviour lives in assets/js/admin.js.
	}
}
