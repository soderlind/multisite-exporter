# Changelog
All notable changes to the Multisite Exporter plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2025-05-14
### Fixed
- Minor version update

## [1.2.0] - 2025-05-14 
### Added
- WP CLI command for exporting content from multisite installations
- Command line progress bar during export
- Export content filtering by post types (posts, pages, media)
- Option to select specific site IDs for export
- Automatic creation of zip file when exporting multiple sites

## [1.1.8] - 2025-05-14
### Fixed
- Fixed: Only show "across all pages" text when there are multiple pages of results.

## [1.1.7] - 2025-05-14
### Added
- Conditional display of pagination text for selected exports: now shows "across all pages" only when there is more than one page of results.

## [1.1.6] - 2025-05-14
### Added
- Alternating row colors in export tables for improved readability
- CSS styling enhancements for better visual hierarchy

### Improved
- Table display with consistent background colors for even rows
- Visual distinction between rows in export history tables

## [1.1.5] - 2025-05-08
### Removed
- Export progress tracking functionality to simplify the codebase
- Progress-related JavaScript and AJAX handlers
- scheduler-progress.js file that was responsible for frontend progress tracking

### Improved
- Streamlined export process with reduced complexity
- Plugin performance by removing unnecessary AJAX calls
- Memory usage during export processing

### Added
- In debug mode, validation of export files after creation


## [1.1.4] - 2025-05-07
### Fixed
- Redundant code in admin views for better maintainability
- Code structure in scheduled actions page
- PHP 8.2+ deprecation warning: "ZipArchive::open(): Using empty file as ZipArchive is deprecated"

### Improved
- Refactored duplicate code in pagination handling
- Consolidated progress tracking logic for better efficiency
- Extracted duplicate code in `view-scheduled-actions-page.php` into reusable helper function
- Applied DRY (Don't Repeat Yourself) principle to eliminate code duplication in admin classes

## [1.1.3] - 2025-05-07
### Fixed
- "The link you followed has expired" error when performing bulk delete operations in scheduled actions
- PHP Fatal error "Cannot use auto-global as lexical variable" in scheduled actions page
- "Headers already sent" warning by adding headers_sent() check before redirects

### Improved
- Error handling for bulk operations in scheduled actions
- Added proper checkbox handling for "select all" functionality in bulk operations
- Implemented a fallback for displaying success messages when headers are already sent

## [1.1.2] - 2025-05-07
### Fixed
- Added proper error handling with WP_DEBUG checks for all error_log calls in class-init.php
- Implemented `if ( defined( 'WP_DEBUG' ) && WP_DEBUG )` checks around all 7 instances of error logging

### Added
- Added automatic plugin updates using YahnisElsts\PluginUpdateChecker\v5\PucFactory
- Added GitHub Actions workflow to automatically create the plugin zip file on release
- Improved installation guide in readme files for easier setup
- Comprehensive debugging section to README.md with:
  - Documentation of error logging behavior with WP_DEBUG
  - Examples of common log messages
  - Instructions for enabling debug mode in WordPress

## [1.1.1] - 2025-05-07
### Fixed
- Network admin URL links in view-history-page.php and view-main-page.php to resolve "Sorry, you are not allowed to access this page" errors

### Changed
- Added conditional WP_DEBUG check for all error logging functions to follow WordPress best practices
- Updated documentation in README.md with information about debug logging

## [1.1.0] - 2025-05-07
### Changed
- Refactored plugin architecture to follow single responsibility principle
  - Moved admin UI functionality to dedicated `class-admin.php`
  - Separated export processing logic into `class-export.php`
  - Created core initialization class in `class-init.php`
- Refactored JavaScript: Moved all inline JavaScript to separate files
  - Created `history-page.js` for export history page functionality
  - Added proper namespacing through IIFE pattern
- Improved script loading using WordPress enqueuing API
  - Renamed `enqueue_admin_styles()` to `enqueue_admin_assets()` for better semantics
  - Implemented conditional asset loading based on current admin page
- Moved plugin constants from class-multisite-exporter.php to main plugin file
  - Better initialization and earlier constant availability
- Enhanced UI: Added disable/enable functionality for selection buttons when "select all across pages" is active

## [1.0.1] - 2025-05-07
### Changed
- Updated documentation to clarify the location of the Action Scheduler library
- Improved README.md with more detailed information about Action Scheduler integration

## [1.0.0] - 2025-05-07
### Added
- Initial plugin release
- Background export processing for all subsites using Action Scheduler
- Export filtering by content type, custom post type, and date range
- Centralized export storage in uploads directory
- Export history page showing all completed exports
- Download functionality for individual export files
- Multiple selection and bulk download of export files as a zip archive
- Full internationalization support with POT file