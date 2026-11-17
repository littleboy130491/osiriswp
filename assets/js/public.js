(function($){
	'use strict';

	var cfgEvents = window.osiriswp_tracked_events || {};
	var cfgAjax = window.osiriswp_ajax || {};
	var cfgDebug = window.osiriswp_debug || {};

	function isDebug() {
		return !!cfgDebug.debug_enabled;
	}

	function log() {
		if (isDebug() && window.console) {
			console.log.apply(console, arguments);
		}
	}

	// Cookies that must never leave the browser for tracking purposes.
	var SENSITIVE_COOKIE = /^(wordpress_|wp-settings|wp_woocommerce_session_|woocommerce_|wp-postpass_|comment_author|phpsessid)|session|token|auth|csrf|xsrf|password|passwd|secret|jwt/i;

	function readCookies() {
		var cookies = {};
		if (!document.cookie) {
			return cookies;
		}
		document.cookie.split(';').forEach(function(cookie) {
			var idx = cookie.indexOf('=');
			if (idx === -1) {
				return;
			}
			var name = cookie.slice(0, idx).trim();
			if (!name || SENSITIVE_COOKIE.test(name)) {
				return;
			}
			cookies[name] = cookie.slice(idx + 1).trim();
		});
		return cookies;
	}

	function send(eventName, data) {
		if (!cfgAjax.ajax_url) {
			return;
		}

		var payload = {
			action: 'osiriswp_track_event',
			event_name: eventName,
			data: JSON.stringify(data),
			nonce: cfgAjax.nonce || ''
		};

		// sendBeacon survives page unloads (e.g. clicking a link that navigates away).
		if (navigator.sendBeacon && window.FormData) {
			var form = new FormData();
			Object.keys(payload).forEach(function(key) {
				form.append(key, payload[key]);
			});
			try {
				if (navigator.sendBeacon(cfgAjax.ajax_url, form)) {
					return;
				}
			} catch (err) {
				// Fall back to XHR below.
			}
		}

		$.post(cfgAjax.ajax_url, payload);
	}

	var INTERACTIVE_SELECTOR = 'a, button, input, select, textarea, [role="button"], [onclick], [class*="button"], [class*="btn"]';

	// OsirisWP Event Tracking - Settings-based event tracking
	window.OsirisWP = {
		trackEvent: function(eventName, data) {
			if (!eventName) return;

			var trackedEvents = cfgEvents.events || [];
			var ga4Events = cfgEvents.ga4_events || [];

			// Only track if the event is in the allowed list (GA4 + additional)
			if (trackedEvents.indexOf(eventName) === -1) {
				log('OsirisWP Debug: Event "' + eventName + '" not tracked (not in GA4 or additional events list)');
				return;
			}

			log('OsirisWP Debug: ' + eventName + ' is triggered (' + (ga4Events.indexOf(eventName) !== -1 ? 'GA4' : 'Additional') + ' event)', data);

			if (!data || typeof data !== 'object') data = {};
			if (!data.page_url) data.page_url = window.location.href;
			data.query_string = window.location.search.substring(1);
			data.cookies = readCookies();

			send(eventName, data);
		},

		init: function() {
			this.trackConfiguredEvents();
			log('OsirisWP: Event tracking initialized');
		},

		trackConfiguredEvents: function() {
			var self = this;
			var trackedEvents = cfgEvents.events || [];
			var ga4Events = cfgEvents.ga4_events || [];
			var additionalEvents = cfgEvents.additional_events || [];

			function enabled(eventType) {
				return trackedEvents.indexOf(eventType) !== -1;
			}

			// One delegated handler: resolve the nearest interactive element once per click.
			if (trackedEvents.length) {
				$(document).on('click.osiriswp', function(e) {
					var el = e.target && e.target.closest ? e.target.closest(INTERACTIVE_SELECTOR) : null;
					if (!el) {
						return;
					}

					var $target = $(el);
					var tagName = el.tagName.toLowerCase();
					var className = (typeof el.className === 'string' ? el.className : ($target.attr('class') || ''));
					var id = el.id || '';
					var text = ($target.text() || $target.val() || '').toString().trim().substring(0, 100);
					var lowerText = text.toLowerCase();
					var lowerClass = className.toLowerCase();
					var lowerId = id.toLowerCase();
					var href = $target.attr('href') || '';

					// Skip joinchat elements to avoid duplicate events
					if (lowerId.indexOf('joinchat') !== -1 || lowerClass.indexOf('joinchat') !== -1 || $target.closest('[class*="joinchat"]').length > 0) {
						return;
					}

					function has(word) {
						return lowerClass.indexOf(word) !== -1 || lowerId.indexOf(word) !== -1 || lowerText.indexOf(word) !== -1;
					}

					// GA4 standard event detection
					var eventType = 'click';
					if (tagName === 'a') {
						var lowerHref = href.toLowerCase();
						if (lowerHref.indexOf('mailto:') === 0 || lowerHref.indexOf('tel:') === 0) {
							eventType = 'generate_lead';
						} else if (/\.(pdf|docx?|zip)(\?|#|$)/.test(lowerHref)) {
							eventType = 'view_item';
						}
					} else if (tagName === 'button') {
						if (has('add-to-cart') || has('add to cart')) {
							eventType = 'add_to_cart';
						} else if (has('buy now') || lowerClass.indexOf('buy') !== -1 || lowerId.indexOf('buy') !== -1) {
							eventType = 'purchase';
						} else if (has('checkout')) {
							eventType = 'begin_checkout';
						} else if (has('wishlist')) {
							eventType = 'add_to_wishlist';
						} else if (has('remove')) {
							eventType = 'remove_from_cart';
						} else {
							eventType = 'button_click';
						}
					} else if (lowerClass.indexOf('product') !== -1 || lowerId.indexOf('product') !== -1) {
						eventType = 'select_item';
					}

					if (enabled(eventType)) {
						self.trackEvent(eventType, {
							tag: tagName,
							class: className,
							id: id,
							text: text,
							href: href,
							page_url: window.location.href
						});
					}
				});
			}

			// Form submission tracking
			if (trackedEvents.length) {
				$(document).on('submit.osiriswp', 'form', function(e) {
					var $form = $(e.target);
					var action = ($form.attr('action') || '').toLowerCase();
					var method = $form.attr('method') || 'GET';
					var className = $form.attr('class') || '';
					var id = $form.attr('id') || '';
					var lowerId = id.toLowerCase();

					function match(word) {
						return action.indexOf(word) !== -1 || $form.hasClass(word) || lowerId.indexOf(word) !== -1;
					}

					var eventType = 'form_submit';
					if (match('search') || $form.find('[type="search"]').length) {
						eventType = 'search';
					} else if (match('contact') || match('newsletter')) {
						eventType = 'generate_lead';
					} else if (match('checkout')) {
						eventType = 'begin_checkout';
					} else if (match('payment')) {
						eventType = 'add_payment_info';
					} else if (match('shipping')) {
						eventType = 'add_shipping_info';
					}

					if (enabled(eventType)) {
						self.trackEvent(eventType, {
							action: $form.attr('action') || '',
							method: method,
							class: className,
							id: id,
							page_url: window.location.href
						});
					}
				});
			}

			// Custom event tracking for additional events
			if (additionalEvents.indexOf('joinchat') !== -1) {
				$(document).on('joinchat:open.osiriswp joinchat:close.osiriswp', function(e) {
					self.trackEvent('joinchat', {
						action: e.type.replace('joinchat:', ''),
						page_url: window.location.href
					});
				});
			}

			// GA4 view_item_list - track product listings (once per page load)
			if (ga4Events.indexOf('view_item_list') !== -1) {
				var productListSelectors = ['.products', '.product-list', '.items', '[data-product-list]'];
				var found = productListSelectors.filter(function(selector) {
					return document.querySelector(selector) !== null;
				});
				if (found.length) {
					self.trackEvent('view_item_list', {
						container: found.join(', '),
						page_url: window.location.href
					});
				}
			}
		}
	};

	// Documented shorthand: trackEvent('event_name', data). Never overwrite an existing global.
	if (typeof window.trackEvent === 'undefined') {
		window.trackEvent = function(eventName, data) {
			window.OsirisWP.trackEvent(eventName, data);
		};
	}

	$(function(){
		window.OsirisWP.init();
	});

})(jQuery);
