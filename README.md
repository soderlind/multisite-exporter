# Multisite Exporter
Multisite Exporter is a WordPress plugin that allows you to export content from all subsites in a WordPress multisite installation. The plugin generates WordPress XML (WXR) files by running the WordPress exporter on each subsite in the background using the Action Scheduler library, making it efficient even for large multisite networks.

[Features](#features) | [Requirements](#requirements) | [Installation](#installation) | [Usage](#usage) | [Action Scheduler](#how-action-scheduler-works) | [Export File Storage](#export-file-storage) | [Translation](#translation) | [Debugging](#debugging)

https://github.com/user-attachments/assets/1e8ce516-1b0e-47f4-9128-e615bebadd89



## Features

- Export content from all subsites in a WordPress multisite network as XML (WXR) files.
- Background processing using Action Scheduler to handle large networks efficiently
- Filter exports by content type, post type, and date range
- Centralized export file storage for easy access to all exports
- Select and download multiple export files as a zip archive
- Fully translatable with .pot file included
- Customizable export directory location via filter

## Requirements

- WordPress Multisite installation
- PHP 8.2 or higher
- Action Scheduler library (included with the plugin)

## Installation

- **Quick Install**

   - Download [`multisite-exporter.zip`](https://github.com/soderlind/multisite-exporter/releases/latest/download/multisite-exporter.zip)
   - Upload via WordPress Network > Plugins > Add New > Upload Plugin
   - Network activate the plugin.

- **Composer Install**

   ```bash
   composer require soderlind/multisite-exporter
   ```

- **Updates**
   * Plugin updates are handled automatically via GitHub. No need to manually download and install updates.

## Usage

### Running an Export

1. Log in to your WordPress Network Admin dashboard
2. Navigate to MS Exporter → Multisite Exporter
3. Configure your export settings:
   - Content: Choose to export all content, posts, pages, or media
   - Post Type: Optionally specify a custom post type
   - Date Range: Optionally filter by start and end date
4. Click "Run Export for All Subsites"
5. Exports will be processed in the background using Action Scheduler

### Accessing Export Files

1. Navigate to MS Exporter → Exports
2. View a list of all completed exports from all subsites
3. Download options:
   - Click the "Download" button next to an individual export
   - Select multiple exports using checkboxes and click "Download Selected" to get a zip file
   - Use "Select All" and "Download Selected" to download all exports at once in a zip file.

## How Action Scheduler Works

The Multisite Exporter plugin uses Action Scheduler, a robust background processing library for WordPress, to handle export operations efficiently across multiple sites. Here's how it works:

### Overview

1. **Task Scheduling**: When you click "Run Export for All Subsites," the plugin creates an individual task for each subsite in your network.

2. **Background Processing**: These tasks are processed in the background using WordPress cron, without blocking user interactions or causing timeout issues.

3. **Failure Handling**: If an export fails, Action Scheduler will automatically retry it based on its built-in retry logic, ensuring robust operation even in challenging environments.

### Technical Details

- **Storage**: Action Scheduler stores tasks in a custom table, ensuring they don't get lost even in case of server interruptions.
  
- **Queue Processing**: Tasks are executed in a controlled manner, with rate limiting to prevent server overload.

- **Admin Interface**: The plugin provides a filtered view of Action Scheduler's interface under MS Exporter → Scheduled Actions, where you can monitor the progress of exports.

- **Scaling**: The architecture can handle thousands of tasks, making it perfect for large multisite networks with hundreds of subsites.

### Action Scheduler Location

The Action Scheduler library is included in this plugin via Composer and can be found at:

```
/vendor/woocommerce/action-scheduler/
```

All Action Scheduler related files are loaded automatically when the plugin initializes.

### Benefits for Multisite Exports

- **No Timeout Issues**: Export processes run in the background, avoiding PHP timeout restrictions.
  
- **Server-Friendly**: Tasks are processed in batches, preventing server resource exhaustion.
  
- **Monitoring**: You can track the progress of exports and identify any issues that may occur.

- **Reliability**: The persistent storage mechanism ensures that even if a server restarts, your export tasks will continue where they left off.

## Export File Storage

All export files are stored in a centralized location within your WordPress uploads directory:

```
wp-content/uploads/multisite-exports/
```

This makes it easy to find and manage exports from all your subsites in one place.

### Customizing Export Directory

You can change the default export directory by using the `multisite_exporter_directory` filter:

```php
/**
 * Change the directory where Multisite Exporter stores exported files
 */
add_filter( 'multisite_exporter_directory', 'my_custom_export_directory' );
function my_custom_export_directory( $default_export_dir ) {
    // Define a custom location for your export files
    return WP_CONTENT_DIR . '/my-custom-exports';
}
```

Add this code to your theme's functions.php file or a custom plugin.

## Translation

The plugin is fully translatable. A POT file is included in the `languages` directory to help with translations.

To create a translation:

1. Copy the `languages/multisite-exporter.pot` file
2. Rename it to `multisite-exporter-{locale}.po` (e.g., `multisite-exporter-fr_FR.po`)
3. Translate using a tool like Poedit
4. Save both .po and .mo files to the languages directory


## Debugging

### Error Logging

Multisite Exporter follows WordPress best practices for error logging. All error messages are conditionally logged based on the WP_DEBUG constant setting:

```php
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Multisite Exporter: [error message]' );
}
```

This ensures that:
- In development environments with WP_DEBUG enabled, you'll see detailed logs about the export process
- In production environments with WP_DEBUG disabled, no error messages will be written to the log files

### WXR Validation

Multisite Exporter includes a WXR (WordPress eXtended RSS) validator that checks the integrity of export files:

- **Automatic Validation**: Each export file is automatically validated after creation
- **XML Structure Check**: Ensures the export file has valid XML structure
- **WXR Schema Validation**: Validates against WordPress WXR format requirements
- **Error Detection**: Identifies and logs specific issues in malformed export files

The validator helps troubleshoot problematic exports and ensures your export files will be compatible with WordPress import tools.

### Common Log Messages

When WP_DEBUG is enabled, you may see the following messages in your WordPress debug log:

- Filesystem initialization: Messages about WordPress filesystem initialization
- Export progress: Notifications when exports start and complete for each blog
- File operations: Information about file cleanup and management
- Error handling: Details about any issues encountered during the export process
- WXR validation: Results of export file validation checks

### Enabling Debug Mode

To enable debug logging for troubleshooting, add the following to your wp-config.php file:

```php
// Enable WP_DEBUG mode
define( 'WP_DEBUG', true );

// Enable Debug logging to the /wp-content/debug.log file
define( 'WP_DEBUG_LOG', true );

// Disable display of errors and warnings
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

This configuration will log all errors to the debug.log file without displaying them on screen.

## Copyright and License

Multisite Exporter is copyright © 2025 [Per Søderlind](http://github.com/soderlind).

Multisite Exporter is open-source software; you can redistribute it and/or modify it under the terms of the GNU General Public License, version 2, as published by the Free Software Foundation.

Multisite Exporter is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See [LICENSE](LICENSE) for more information.

