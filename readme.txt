=== Multisite Exporter ===
Contributors: persoderlind
Tags: multisite, export, background processing, action scheduler
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Runs WordPress Exporter on each subsite in a multisite, in the background.

== Description ==

Multisite Exporter is a powerful tool for WordPress multisite administrators who need to export content from multiple subsites. Instead of manually exporting each site one by one, this plugin automates the entire process by running exports in the background using Action Scheduler.

= Features =

* **Batch Processing**: Exports are processed in the background, so you can export content from dozens or hundreds of sites without timeout issues
* **Content Filtering**: Filter exports by content type, custom post type, and date range
* **Centralized Storage**: All export files are stored in a common location for easy access
* **Bulk Downloads**: Select multiple export files and download them as a single zip file
* **User-Friendly Interface**: Simple, intuitive interface for configuring exports and accessing files
* **Fully Translatable**: The plugin is fully internationalized and ready for translation

= Requirements =

* WordPress Multisite installation
* PHP 7.0 or higher
* Action Scheduler library (included)

== Installation ==

1. Upload the `multisite-exporter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress Network Admin
3. Make sure the Action Scheduler library is installed (included via Composer)
4. Go to MS Exporter → Multisite Exporter to start using the plugin

== Usage ==

= Running an Export =

1. Navigate to MS Exporter → Multisite Exporter in your Network Admin dashboard
2. Configure your export settings:
   * Content: Choose to export all content, posts, pages, or media
   * Post Type: Optionally specify a custom post type
   * Date Range: Optionally filter by start and end date
3. Click "Run Export for All Subsites"
4. Exports will be processed in the background using Action Scheduler

= Accessing Export Files =

1. Navigate to MS Exporter → Export History
2. View a list of all completed exports from all subsites
3. Download options:
   * Click the "Download" button next to an individual export
   * Select multiple exports using checkboxes and click "Download Selected" to get a zip file
   * Use "Select All" and "Download Selected" to download all exports at once

== Frequently Asked Questions ==

= Where are the export files stored? =

All export files are stored in a centralized location within your WordPress uploads directory:
`wp-content/uploads/multisite-exports/`

= Can I export only certain content types? =

Yes, you can filter exports by content type (all content, posts, pages, or media), custom post type, and date range.

= Is this plugin compatible with very large multisite networks? =

Yes, the plugin uses Action Scheduler to process exports in the background, making it suitable for networks with many subsites.

= How can I translate this plugin? =

The plugin is fully translatable. A POT file is included in the `languages` directory. You can create translations using a tool like Poedit.

== Screenshots ==

1. Main export configuration screen
2. Export history page with download options
3. Multiple file selection for bulk downloads

== Changelog ==

= 1.0.0 =
* Initial release
* Background export processing for all subsites
* Export filtering by content type, custom post type, and date range
* Export history page with individual and bulk download options
* Full internationalization support

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Multisite Exporter plugin.