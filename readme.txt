=== OsirisWP Event Tracker ===
Contributors: Henry
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful event tracking plugin for WordPress that captures user interactions, page views, and custom events with detailed analytics.

== Description ==
OsirisWP Event Tracker is a comprehensive solution for tracking user interactions on your WordPress site. It captures page views, clicks, form submissions, and custom events, storing them in your WordPress database for analysis.

### Key Features:
- **Real-time Event Tracking**: Tracks page views, clicks, and form submissions
- **Visitor Identification**: Unique visitor tracking with UUIDs
- **Query String & Cookie Capture**: Automatically captures URL parameters and cookies
- **Custom Event Support**: Track custom events with flexible data payloads
- **Admin Dashboard**: View and filter events with a clean, intuitive interface
- **Summary Metrics**: Filter-aware Visitors (unique `page_view` users), Events Triggered, and Rate (events/visitors)
- **Configurable Event Count Mode**: Count events per trigger or once per visitor
- **CSV Export**: Export filtered events directly from the events screen
- **Responsive Design**: Works on all device sizes
- **Developer Friendly**: Extensible API for custom event tracking

== Installation ==
1. Upload the `osiriswp` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to OsirisWP → Events to view the tracking dashboard

== Usage ==
### Basic Tracking
No configuration needed! The plugin automatically tracks:
- Page views
- Link clicks
- Form submissions
- Query string parameters
- Cookies

### Viewing Events
1. Navigate to OsirisWP → Events in your WordPress admin
2. Use the filters to narrow down events by:
   - Date range
   - Event type
   - Visitor UUID
   - Page URL
   - Cookie name

### Event Tracking Methods

#### Method 1: Settings-Based Tracking (Recommended for most users)
Configure event names in the plugin settings and let OsirisWP handle the tracking automatically:

1. Go to **OsirisWP → Settings**
2. Add event names (one per line) such as:
   - `button_click`
   - `form_submit`
   - `video_play`
   - `download_file`
3. Save settings

The plugin will automatically track these events when they occur on your site.

#### Method 2: Custom JavaScript Tracking (Advanced)
Use the `trackEvent()` function in your theme or plugins for custom tracking:

```javascript
// Basic custom event
trackEvent('video_play', { category: 'engagement', label: 'product_demo' });

// Event with additional data
trackEvent('form_submission', {
    form_id: 'contact-form',
    user_type: 'premium',
    conversion_value: 150
});
```

**When to use custom tracking:**
- Tracking complex JavaScript applications
- Events with specific data requirements
- Custom dashboard interactions
- Third-party integrations

**For most websites, the settings-based tracking is all you need!**

## Frequently Asked Questions

### How do I track events?
You have two options:
1. **Settings-based tracking** (recommended): Add event names in OsirisWP → Settings
2. **Custom tracking**: Use `trackEvent('event_name', data)` in your JavaScript code

### What's the difference between settings and custom tracking?
Settings-based tracking automatically captures standard interactions. Custom tracking gives you control over what data to send with each event.

### Where is the event data stored?
All events are stored in the `wp_osiriswp_events` table in your WordPress database.

### How can I export the event data?
Use the **Export to CSV** button on the OsirisWP Events screen. The export follows your active filters.

### What happens when I deactivate the plugin?
By default, your event data is preserved. If you want to clean up the database on deactivation, check the "Clean database on deactivation" option in the plugin settings.

## Screenshots
1. Events overview with filtering options
2. Detailed event view with query parameters and cookies
3. Event type breakdown chart

== Changelog ==

= 1.0.4 =
* Updated Visitors summary metric to use unique visitors from `page_view` events (counted once per visitor).
* Kept Events Triggered mode behavior (once per visitor or each trigger) for event metrics and rate calculation.

= 1.0.3 =
* Added filter-aware summary cards above the events table (Visitors, Events Triggered, Rate).
* Added configurable event trigger counting mode (count each trigger or once per visitor).
* Kept event count mode across pagination links.
* Updated version metadata for release consistency.

= 1.0.0 =
* Initial release with core tracking functionality
* Admin dashboard for event management
* Advanced filtering and search capabilities
* Clickable UUIDs for easy visitor tracking
* Collapsible data display for better UX
* Settings-based and custom event tracking methods
* Database cleanup option on plugin deactivation
* Comprehensive documentation and usage guides
