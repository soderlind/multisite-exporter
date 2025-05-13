=== Multisite Exporter ===
Contributors: persoderlind
Tags: multisite, export, background processing, action scheduler
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.1.6
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
* PHP 8.2 or higher
* Action Scheduler library (included with the plugin)

== Installation ==

1. **Quick Install**

   * Download [`multisite-exporter.zip`](https://github.com/soderlind/multisite-exporter/releases/latest/download/multisite-exporter.zip)
   * Upload via WordPress Network > Plugins > Add New > Upload Plugin
   * Network activate the plugin.

2. **Updates**
   * Plugin updates are handled automatically via GitHub. No need to manually download and install updates.

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


== Changelog ==

= 1.1.6 =
* Added: Alternating row colors in export tables for improved readability
* Added: CSS styling enhancements for better visual hierarchy
* Improved: Table display with consistent background colors for even rows
* Improved: Visual distinction between rows in export history tables

= 1.1.5 =
* Removed: Export progress tracking functionality to simplify the codebase
* Removed: Progress-related JavaScript and AJAX handlers
* Removed: scheduler-progress.js file that was responsible for frontend progress tracking
* Improved: Streamlined export process with reduced complexity
* Improved: Plugin performance by removing unnecessary AJAX calls
* Improved: Memory usage during export processing
* Added: In debug mode, validation of export files after creation


= 1.1.4 =
* Fixed: Redundant code in admin views for better maintainability
* Fixed: Code structure in scheduled actions page
* Fixed: PHP 8.2+ deprecation warning: "ZipArchive::open(): Using empty file as ZipArchive is deprecated"
* Improved: Refactored duplicate code in pagination handling
* Enhanced: Consolidated progress tracking logic for better efficiency

= 1.1.3 =
* Fixed: "The link you followed has expired" error when performing bulk delete operations
* Fixed: PHP Fatal error "Cannot use auto-global as lexical variable" in scheduled actions page
* Fixed: "Headers already sent" warning by adding headers_sent() check before redirects
* Improved: Error handling for bulk operations in scheduled actions

= 1.1.2 =
* Added: Automatic plugin updates using YahnisElsts\PluginUpdateChecker
* Added: GitHub Actions workflow to automatically create the plugin zip file on release
* Added: Improved installation guide for easier setup
* Fixed: Added proper error handling with WP_DEBUG checks for all error_log calls
* Added: Comprehensive debugging section to README.md

= 1.1.1 =
* Fixed network admin URL links in view-history-page.php and view-main-page.php
* Added conditional WP_DEBUG check for all error logging functions
* Updated documentation in README.md with information about debug logging

= 1.1.0 =
* Refactored plugin architecture to follow single responsibility principle
  * Moved admin UI functionality to dedicated class-admin.php
  * Separated export processing logic into class-export.php
  * Created core initialization class in class-init.php
* Refactored JavaScript: Moved all inline JavaScript to separate files
  * Created history-page.js for export history page functionality
  * Added proper namespacing through IIFE pattern
* Improved script loading using WordPress enqueuing API
  * Renamed enqueue_admin_styles() to enqueue_admin_assets() for better semantics
  * Implemented conditional asset loading based on current admin page
* Moved plugin constants from class-multisite-exporter.php to main plugin file
  * Better initialization and earlier constant availability
* Enhanced UI: Added disable/enable functionality for selection buttons when "select all across pages" is active

= 1.0.1 =
* Updated documentation to clarify the location of the Action Scheduler library
* Improved documentation with more detailed information about Action Scheduler integration

= 1.0.0 =
* Initial release
* Background export processing for all subsites using Action Scheduler
* Export filtering by content type, custom post type, and date range
* Centralized export storage in uploads directory
* Export history page showing all completed exports
* Download functionality for individual export files
* Multiple selection and bulk download of export files as a zip archive
* Full internationalization support with POT file

== Upgrade Notice ==

= 1.1.0 =
Improved code organization and UI enhancements for export selection.

= 1.0.1 =
Documentation update to clarify the location of the Action Scheduler library.

= 1.0.0 =
Initial release of the Multisite Exporter plugin.