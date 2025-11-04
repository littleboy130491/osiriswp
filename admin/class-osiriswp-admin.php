<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OsirisWP_Admin {
	public function hooks() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function add_menu() {
		add_options_page(
			__( 'OsirisWP Settings', 'osiriswp' ),
			'OsirisWP',
			'manage_options',
			'osiriswp',
			[ $this, 'render' ]
		);
	}

	public function register_settings() {
		register_setting( 'osiriswp_settings', 'osiriswp_option_name', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'Hello from OsirisWP',
		] );

		add_settings_section( 'osiriswp_main', __( 'Main Settings', 'osiriswp' ), function() {
			echo '<p>' . esc_html__( 'Configure OsirisWP behavior.', 'osiriswp' ) . '</p>';
		}, 'osiriswp' );

		add_settings_field( 'osiriswp_option_name', __( 'Greeting Text', 'osiriswp' ), function() {
			$value = get_option( 'osiriswp_option_name', '' );
			echo '<input type="text" class="regular-text" name="osiriswp_option_name" value="' . esc_attr( $value ) . '" />';
		}, 'osiriswp', 'osiriswp_main' );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OsirisWP Settings', 'osiriswp' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'osiriswp_settings' ); ?>
				<?php do_settings_sections( 'osiriswp' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function enqueue( $hook ) {
		if ( 'settings_page_osiriswp' !== $hook ) { return; }
		wp_enqueue_style( 'osiriswp-admin', OSIRISWP_URL . 'assets/css/admin.css', [], OSIRISWP_VERSION );
		wp_enqueue_script( 'osiriswp-admin', OSIRISWP_URL . 'assets/js/admin.js', [ 'jquery' ], OSIRISWP_VERSION, true );
	}
}
