# Changelog
All notable changes to the Multisite Exporter plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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