(function($){
	// OsirisWP Event Tracking - Settings-based event tracking
	window.OsirisWP = {
		trackEvent: function(eventName, data) {
			if (!eventName) return;
			
			// Check if this event should be tracked
			var trackedEvents = osiriswp_tracked_events.events || [];
			var ga4Events = osiriswp_tracked_events.ga4_events || [];
			var additionalEvents = osiriswp_tracked_events.additional_events || [];
			
			// Only track if the event is in the allowed list (GA4 + additional)
			if (trackedEvents.indexOf(eventName) === -1) {
				if (osiriswp_debug && osiriswp_debug.debug_enabled) {
					console.log('OsirisWP Debug: Event "' + eventName + '" not tracked (not in GA4 or additional events list)');
				}
				return;
			}
			
			// Debug logging
			if (osiriswp_debug && osiriswp_debug.debug_enabled) {
				var eventType = ga4Events.indexOf(eventName) !== -1 ? 'GA4' : 'Additional';
				console.log('OsirisWP Debug: ' + eventName + ' is triggered (' + eventType + ' event)', data);
			}
			
			// Check if current page is an asset file (should not be tracked)
			var currentPath = window.location.pathname;
			var assetExtensions = ['.css', '.js', '.map', '.css.map', '.js.map', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.pdf', '.zip'];
			var isAsset = assetExtensions.some(function(ext) {
				return currentPath.endsWith(ext);
			});
			
			if (isAsset) {
				if (osiriswp_debug && osiriswp_debug.debug_enabled) {
					console.log('OsirisWP Debug: Skipping asset file - ' + currentPath);
				}
				return;
			}
			
			// Extract query string from current URL
			var queryString = window.location.search.substring(1); // Remove the '?'
			
			// Get cookies
			var cookies = {};
			if (document.cookie) {
				document.cookie.split(';').forEach(function(cookie) {
					var parts = cookie.trim().split('=');
					if (parts.length === 2) {
						cookies[parts[0]] = parts[1];
					}
				});
			}
			
			// Add query string and cookies to data
			if (!data) data = {};
			data.query_string = queryString;
			data.cookies = cookies;
			
			// Track the event
			$.post(osiriswp_ajax.ajax_url, {
				action: 'osiriswp_track_event',
				event_name: eventName,
				data: JSON.stringify(data),
				nonce: osiriswp_ajax.nonce
			});
		},
		
		init: function() {
			this.trackConfiguredEvents();
			if (osiriswp_debug && osiriswp_debug.debug_enabled) {
				console.log('OsirisWP: Event tracking initialized');
			}
		},
		
		trackConfiguredEvents: function() {
			var self = this;
			var trackedEvents = osiriswp_tracked_events.events || [];
			var ga4Events = osiriswp_tracked_events.ga4_events || [];
			var additionalEvents = osiriswp_tracked_events.additional_events || [];
			
			// Set up click tracking for GA4 click events + additional click events
			if (trackedEvents.some(function(event) { 
				return ga4Events.indexOf(event) !== -1 || additionalEvents.indexOf(event) !== -1;
			})) {
				$(document).on('click', '*', function(e) {
					// Only track the actual clicked element, not bubbled events
					if (e.target !== this) {
						return;
					}
					
					var $target = $(e.target);
					var tagName = e.target.tagName.toLowerCase();
					var className = $target.attr('class') || '';
					var id = $target.attr('id') || '';
					var text = $target.text().trim().substring(0, 100);
					var href = $target.attr('href') || '';
					
					// Skip joinchat elements to avoid duplicate events
					if (id.includes('joinchat') || className.includes('joinchat') || $target.closest('[class*="joinchat"]').length > 0) {
						return;
					}
					
					// Only track meaningful elements (links, buttons, interactive elements)
					var meaningfulTags = ['a', 'button', 'input', 'select', 'textarea'];
					var isInteractive = meaningfulTags.indexOf(tagName) !== -1 || 
					                   $target.attr('onclick') || 
					                   $target.attr('role') === 'button' ||
					                   className.includes('button') || 
					                   className.includes('btn');
					
					if (!isInteractive) {
						return; // Skip non-interactive elements
					}
					
					// GA4 standard event detection
					var eventType = 'click';
					if (tagName === 'a') {
						if (href.includes('mailto:')) {
							eventType = 'generate_lead'; // GA4: lead generation
						} else if (href.includes('tel:')) {
							eventType = 'generate_lead'; // GA4: lead generation
						} else if (href.includes('.pdf') || href.includes('.doc') || href.includes('.zip')) {
							eventType = 'view_item'; // GA4: viewing downloadable item
						} else if (href.startsWith('http') && !href.includes(window.location.hostname)) {
							eventType = 'click'; // GA4: outbound click
						} else {
							eventType = 'click'; // GA4: internal click
						}
					} else if (tagName === 'button') {
						// GA4 e-commerce events
						if (className.includes('add-to-cart') || id.includes('add-to-cart') || text.includes('add to cart')) {
							eventType = 'add_to_cart';
						} else if (className.includes('buy') || id.includes('buy') || text.includes('buy now')) {
							eventType = 'purchase';
						} else if (className.includes('checkout') || id.includes('checkout') || text.includes('checkout')) {
							eventType = 'begin_checkout';
						} else if (className.includes('wishlist') || id.includes('wishlist') || text.includes('wishlist')) {
							eventType = 'add_to_wishlist';
						} else if (className.includes('remove') || id.includes('remove') || text.includes('remove')) {
							eventType = 'remove_from_cart';
						} else {
							eventType = 'button_click'; // GA4 standard
						}
					} else if (className.includes('product') || id.includes('product')) {
						eventType = 'select_item'; // GA4 standard
					}
					
					// Only track if this specific event type is enabled (GA4 or additional)
					if (trackedEvents.indexOf(eventType) !== -1) {
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
			
			// Set up form submission tracking for GA4 events + additional events
			if (trackedEvents.some(function(event) { 
				return event === 'form_submit' || event === 'generate_lead' || event === 'search' ||
				       event === 'begin_checkout' || event === 'add_payment_info' || event === 'add_shipping_info' ||
				       additionalEvents.indexOf(event) !== -1;
			})) {
				$(document).on('submit', 'form', function(e) {
					var $form = $(e.target);
					var action = $form.attr('action') || '';
					var method = $form.attr('method') || 'GET';
					var className = $form.attr('class') || '';
					var id = $form.attr('id') || '';
					
					// GA4 standard event detection for forms
					var eventType = 'form_submit';
					if (action.includes('search') || $form.hasClass('search') || $form.find('[type="search"]').length) {
						eventType = 'search'; // GA4 standard
					} else if (action.includes('contact') || $form.hasClass('contact') || id.includes('contact')) {
						eventType = 'generate_lead'; // GA4 standard
					} else if (action.includes('newsletter') || $form.hasClass('newsletter') || id.includes('newsletter')) {
						eventType = 'generate_lead'; // GA4 standard
					} else if (action.includes('checkout') || $form.hasClass('checkout') || id.includes('checkout')) {
						eventType = 'begin_checkout'; // GA4 standard
					} else if (action.includes('payment') || $form.hasClass('payment') || id.includes('payment')) {
						eventType = 'add_payment_info'; // GA4 standard
					} else if (action.includes('shipping') || $form.hasClass('shipping') || id.includes('shipping')) {
						eventType = 'add_shipping_info'; // GA4 standard
					} else {
						eventType = 'form_submit'; // GA4 standard
					}
					
					// Only track if this specific event type is enabled (GA4 or additional)
					if (trackedEvents.indexOf(eventType) !== -1) {
						self.trackEvent(eventType, {
							action: action,
							method: method,
							class: className,
							id: id,
							page_url: window.location.href
						});
					}
				});
			}
			
			// Set up custom event tracking for additional events
			if (additionalEvents.indexOf('joinchat') !== -1) {
				$(document).on('joinchat:open joinchat:close', function(e) {
					self.trackEvent('joinchat', {
						action: e.type.replace('joinchat:', ''),
						page_url: window.location.href
					});
				});
			}
			
			// GA4 view_item_list - track product listings
			if (ga4Events.indexOf('view_item_list') !== -1) {
				var productListSelectors = ['.products', '.product-list', '.items', '[data-product-list]'];
				productListSelectors.forEach(function(selector) {
					if ($(selector).length > 0) {
						self.trackEvent('view_item_list', {
							container: selector,
							page_url: window.location.href
						});
					}
				});
			}
		}
	};
	
	$(document).ready(function(){
		OsirisWP.init();
	});
	
})(jQuery);
