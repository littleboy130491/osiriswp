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
		
		<script>
		jQuery(document).ready(function($) {
			$('#osiriswp-prune-now').on('click', function(e) {
				e.preventDefault();
				
				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete old event data? This action cannot be undone.', 'osiriswp' ) ); ?>')) {
					return;
				}
				
				var $button = $(this);
				$button.prop('disabled', true).text('<?php echo esc_js( __( 'Pruning...', 'osiriswp' ) ); ?>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'osiriswp_prune_data',
						nonce: '<?php echo wp_create_nonce( 'osiriswp_prune_nonce' ); ?>'
					},
					success: function(response) {
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Prune Old Data', 'osiriswp' ) ); ?>');
						
						console.log('AJAX Response:', response);
						
						if (response.success) {
							alert('<?php echo esc_js( __( 'Data pruned successfully!', 'osiriswp' ) ); ?> ' + response.data.message);
							// Reload the page to refresh the event list
							location.reload();
						} else {
							alert('<?php echo esc_js( __( 'Error pruning data:', 'osiriswp' ) ); ?> ' + response.data.message);
						}
					},
					error: function(xhr, status, error) {
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Prune Old Data', 'osiriswp' ) ); ?>');
						console.log('AJAX Error:', status, error);
						console.log('Response Text:', xhr.responseText);
						alert('<?php echo esc_js( __( 'AJAX Error:', 'osiriswp' ) ); ?> ' + status + ' - ' + error + '\n\nResponse: ' + xhr.responseText);
					}
				});
			});
		});
		</script>
		<?php
	}
}
