(function($){
	'use strict';

	var cfg = window.osiriswpAdmin || {};
	var i18n = cfg.i18n || {};

	$(function() {
		// ----- Events dashboard -----

		// Visitor UUID click auto-fills the filter.
		$(document).on('click', '.visitor-uuid-link', function(e) {
			e.preventDefault();
			var $field = $('#visitor_uuid');
			$field.val($(this).data('uuid'));

			var filters = $('.osiriswp-filters')[0];
			if (filters) {
				filters.scrollIntoView({ behavior: 'smooth' });
			}

			$field.addClass('highlight');
			setTimeout(function() {
				$field.removeClass('highlight');
			}, 2000);
		});

		// Collapsible data popups.
		$(document).on('click', '.data-summary', function(e) {
			e.stopPropagation();
			var $content = $(this).siblings('.data-content');

			$('.data-content').not($content).hide();
			$content.toggle();

			if ($content.is(':visible')) {
				// Reset before measuring so a previous flip doesn't skew the result.
				$content.css({ left: '0', right: 'auto' });
				if ($content[0].getBoundingClientRect().right > $(window).width()) {
					$content.css({ left: 'auto', right: '0' });
				}
			}
		});

		// Close popups when clicking outside.
		$(document).on('click', function(e) {
			if (!$(e.target).closest('.collapsible-data').length) {
				$('.data-content').hide();
			}
		});

		// Copy to clipboard.
		$(document).on('click', '.copy-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $btn = $(this);
			var text = String($btn.attr('data-copy') || '');

			function feedback() {
				var original = $btn.data('original-text') || $btn.text();
				$btn.data('original-text', original);
				$btn.text(i18n.copied || 'Copied!').addClass('copied');
				setTimeout(function() {
					$btn.text(original).removeClass('copied');
				}, 2000);
			}

			function legacyCopy() {
				var $temp = $('<textarea>').css({ position: 'fixed', top: '-1000px' }).val(text).appendTo('body');
				$temp[0].select();
				try { document.execCommand('copy'); } catch (err) {}
				$temp.remove();
				feedback();
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(feedback, legacyCopy);
			} else {
				legacyCopy();
			}
		});

		// ----- Settings: prune data -----
		$('#osiriswp-prune-now').on('click', function(e) {
			e.preventDefault();

			if (!window.confirm(i18n.confirmPrune)) {
				return;
			}

			var $button = $(this);
			var label = $button.text();
			$button.prop('disabled', true).text(i18n.pruning);

			$.ajax({
				url: cfg.ajaxUrl || window.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'osiriswp_prune_data',
					nonce: cfg.pruneNonce
				}
			}).done(function(response) {
				var message = response && response.data && response.data.message ? response.data.message : '';
				if (response && response.success) {
					window.alert(i18n.pruneSuccess + ' ' + message);
				} else {
					window.alert(i18n.pruneError + ' ' + message);
				}
			}).fail(function(xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
					? xhr.responseJSON.data.message
					: (xhr.status + ' ' + (xhr.statusText || ''));
				window.alert(i18n.pruneError + ' ' + message);
			}).always(function() {
				$button.prop('disabled', false).text(label);
			});
		});
	});
})(jQuery);
