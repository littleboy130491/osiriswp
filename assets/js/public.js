(function($){
	'use strict';

	var cfgEvents = window.osiriswp_tracked_events || {};
	var cfgAjax = window.osiriswp_ajax || {};
	var cfgDebug = window.osiriswp_debug || {};
	var cfg = window.osiriswp_config || {};
	var cfgCookie = cfg.cookie || {};

	var VISITOR_COOKIE = 'osiriswp_visitor_uuid';
	var ATTRIBUTION_COOKIE = 'osiriswp_attribution';
	var CLICK_ID_KEYS = ['gclid', 'gbraid', 'wbraid'];
	var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
	var ATTRIBUTION_TTL = cfgCookie.attribution_ttl || 90 * 24 * 60 * 60;
	var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

	// ----- Cookie helpers -----
	function getCookie(name) {
		var parts = document.cookie ? document.cookie.split(';') : [];
		for (var i = 0; i < parts.length; i++) {
			var idx = parts[i].indexOf('=');
			if (idx !== -1 && parts[i].slice(0, idx).trim() === name) {
				try {
					return decodeURIComponent(parts[i].slice(idx + 1).trim());
				} catch (err) {
					return null;
				}
			}
		}
		return null;
	}

	function setCookie(name, value, maxAgeSeconds) {
		var cookie = name + '=' + encodeURIComponent(value) +
			'; max-age=' + Math.max(0, Math.floor(maxAgeSeconds)) +
			'; path=' + (cfgCookie.path || '/') +
			'; SameSite=Lax';
		if (cfgCookie.domain) cookie += '; domain=' + cfgCookie.domain;
		if (window.location.protocol === 'https:') cookie += '; Secure';
		document.cookie = cookie;
	}

	function nowSeconds() {
		return Math.floor(Date.now() / 1000);
	}

	function uuid4() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		var b = new Uint8Array(16);
		if (window.crypto && window.crypto.getRandomValues) {
			window.crypto.getRandomValues(b);
		} else {
			for (var i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256);
		}
		b[6] = (b[6] & 0x0f) | 0x40;
		b[8] = (b[8] & 0x3f) | 0x80;
		var h = Array.prototype.map.call(b, function(x) { return ('0' + x.toString(16)).slice(-2); }).join('');
		return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
	}

	// Cached pages never run PHP, so make sure the visitor has an ID before the first event.
	function ensureVisitorId() {
		var current = getCookie(VISITOR_COOKIE);
		if (!current || !UUID_RE.test(current)) {
			setCookie(VISITOR_COOKIE, uuid4(), 365 * 24 * 60 * 60);
		}
	}

	function getAttribution() {
		var raw = getCookie(ATTRIBUTION_COOKIE);
		if (!raw) return {};
		try {
			var data = JSON.parse(raw);
			return data && typeof data === 'object' ? data : {};
		} catch (err) {
			return {};
		}
	}

	function hasClickId(obj) {
		return CLICK_ID_KEYS.some(function(k) { return !!obj[k]; });
	}

	// Remember gclid/gbraid/wbraid + UTM tags for 90 days so later pages and return visits keep them.
	function captureAttribution() {
		if (!window.URLSearchParams) return;
		var params = new URLSearchParams(window.location.search);
		var incoming = {};

		CLICK_ID_KEYS.forEach(function(k) {
			var v = (params.get(k) || '').replace(/[^A-Za-z0-9_\-]/g, '').slice(0, 255);
			if (v) incoming[k] = v;
		});
		UTM_KEYS.forEach(function(k) {
			var v = (params.get(k) || '').trim().slice(0, 150);
			if (v) incoming[k] = v;
		});
		if (!Object.keys(incoming).length) return;

		var stored = getAttribution();
		// A UTM-only visit never erases a stored ad click.
		if (hasClickId(stored) && !hasClickId(incoming)) return;
		// Same click again: keep the original click time.
		var sameClick = CLICK_ID_KEYS.some(function(k) { return incoming[k] && incoming[k] === stored[k]; });
		if (sameClick) return;

		incoming.ts = nowSeconds();
		setCookie(ATTRIBUTION_COOKIE, JSON.stringify(incoming), ATTRIBUTION_TTL);
		log('OsirisWP Debug: attribution saved', incoming);
	}

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
			this.trackPageView();
			this.trackConfiguredEvents();
			log('OsirisWP: Event tracking initialized');
		},

		// page_view comes from the browser so it is recorded on cached pages too
		// (and bots that don't run JavaScript are not counted as visitors).
		trackPageView: function() {
			if (!cfg.track_page_view) return;
			this.trackEvent('page_view', { page_url: window.location.href });
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

	// Run before anything is tracked (does not need the DOM).
	ensureVisitorId();
	captureAttribution();

	$(function(){
		window.OsirisWP.init();
	});

})(jQuery);
