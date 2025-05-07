=== Multisite Exporter ===
Contributors: persoderlind
Tags: multisite, export, background processing, action scheduler
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.0
Stable tag: 1.0.1
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
* **Customizable Export Directory**: Change where your exports are stored using a simple filter hook

= Requirements =

* WordPress Multisite installation
* PHP 7.0 or higher
* Action Scheduler library (included via Composer in vendor/woocommerce/action-scheduler/)

== Installation ==

1. Upload the `multisite-exporter` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress Network Admin
3. Make sure the Action Scheduler library is installed (included via Composer in vendor/woocommerce/action-scheduler/)
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

= Customizing Export Directory =

You can change the default export directory location by adding this code to your theme's functions.php file or a custom plugin:

`
// Change the directory where exports are stored
add_filter( 'multisite_exporter_directory', 'my_custom_export_directory' );
function my_custom_export_directory( $default_export_dir ) {
    return WP_CONTENT_DIR . '/my-custom-exports';
}
`

== Frequently Asked Questions ==

= Where are the export files stored? =

By default, all export files are stored in a centralized location within your WordPress uploads directory:
`wp-content/uploads/multisite-exports/`

You can change this location using the `multisite_exporter_directory` filter.

= Where is the Action Scheduler library located? =

The Action Scheduler library is included with the plugin via Composer and can be found in the following location:
`/vendor/woocommerce/action-scheduler/`

All Action Scheduler related files are loaded automatically when the plugin initializes.

= Can I export only certain content types? =

Yes, you can filter exports by content type (all content, posts, pages, or media), custom post type, and date range.

= Is this plugin compatible with very large multisite networks? =

Yes, the plugin uses Action Scheduler to process exports in the background, making it suitable for networks with many subsites.

= How can I translate this plugin? =

The plugin is fully translatable. A POT file is included in the `languages` directory. You can create translations using a tool like Poedit.

= Can I change where the export files are stored? =

Yes, you can use the `multisite_exporter_directory` filter to specify a custom directory location for your export files.

== Screenshots ==

1. Main export configuration screen
2. Export history page with download options
3. Multiple file selection for bulk downloads

== Changelog ==

= 1.0.1 =
* Updated documentation to clarify the location of the Action Scheduler library
* Improved documentation with more detailed information about Action Scheduler integration

= 1.0.0 =
* Initial release
* Background export processing for all subsites
* Export filtering by content type, custom post type, and date range
* Export history page with individual and bulk download options
* Full internationalization support

== Upgrade Notice ==

= 1.0.1 =
Documentation update to clarify the location of the Action Scheduler library.

= 1.0.0 =
Initial release of the Multisite Exporter plugin.