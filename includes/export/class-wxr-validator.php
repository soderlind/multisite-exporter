<?php
/**
 * WXR Validator class for validating WordPress WXR XML exports.
 *
 * @since      1.0.2
 * @package    Multisite_Exporter
 * @subpackage Multisite_Exporter/Export
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WXR Validator class.
 * 
 * @since 1.0.2
 * Handles validation of WordPress eXtended RSS (WXR) export files
 * to ensure they meet WordPress import requirements.
 */
class ME_WXR_Validator {

	/**
	 * The single instance of the class.
	 *
	 * @since  1.0.1
	 * @var    ME_WXR_Validator
	 */
	protected static $_instance = null;

	/**
	 * Main ME_WXR_Validator Instance.
	 *
	 * @return ME_WXR_Validator - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Constructor code here if needed
	}

	/**
	 * Log XML validation errors to the error log
	 * 
	 * @param array $errors Array of libxml error objects
	 */
	public function log_xml_validation_errors( $errors ) {
		foreach ( $errors as $error ) {
			error_log( sprintf(
				'Multisite Exporter XML Error: %s at line %d, column %d',
				$error->message,
				$error->line,
				$error->column
			) );
		}
	}

	/**
	 * Validate a WXR file for WordPress import compatibility
	 * 
	 * Performs comprehensive validation of a WXR export:
	 * 1. Basic XML well-formedness
	 * 2. Required WXR namespaces
	 * 3. Required WXR version element
	 * 4. Required channel elements
	 * 5. Content structure validation
	 * 
	 * @param string $xml_content The XML content to validate
	 * @return array Results with 'valid' boolean and 'errors' array
	 */
	public function validate_wxr( $xml_content ) {
		$result = array(
			'valid'  => false,
			'errors' => array(),
		);

		// Step 1: Check if XML is well-formed
		$previous_state = libxml_use_internal_errors( true );
		$dom            = new DOMDocument( '1.0', 'utf-8' );
		$xml_loaded     = @$dom->loadXML( $xml_content );

		if ( ! $xml_loaded ) {
			$errors = libxml_get_errors();
			foreach ( $errors as $error ) {
				$result[ 'errors' ][] = sprintf(
					'XML Error: %s at line %d, column %d',
					trim( $error->message ),
					$error->line,
					$error->column
				);
			}
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_state );
			return $result;
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_state );

		// Step 2: Check for required RSS and WXR namespaces
		$root = $dom->documentElement;
		if ( $root->nodeName !== 'rss' ) {
			$result[ 'errors' ][] = 'Root element must be <rss>';
			return $result;
		}

		$required_namespaces = array(
			'excerpt' => 'http://wordpress.org/export/1.2/excerpt/',
			'content' => 'http://purl.org/rss/1.0/modules/content/',
			'wfw'     => 'http://wellformedweb.org/CommentAPI/',
			'dc'      => 'http://purl.org/dc/elements/1.1/',
			'wp'      => 'http://wordpress.org/export/1.2/',
		);

		foreach ( $required_namespaces as $prefix => $namespace ) {
			$ns_attribute = $prefix === 'excerpt' ? "xmlns:$prefix" : "xmlns:$prefix";
			if ( ! $root->hasAttribute( $ns_attribute ) || $root->getAttribute( $ns_attribute ) !== $namespace ) {
				$result[ 'errors' ][] = sprintf( 'Missing or incorrect namespace: %s', $namespace );
			}
		}

		// Step 3: Check for channel element
		$channels = $root->getElementsByTagName( 'channel' );
		if ( $channels->length !== 1 ) {
			$result[ 'errors' ][] = 'WXR must contain exactly one <channel> element';
			return $result;
		}

		$channel = $channels->item( 0 );

		// Step 4: Check for required channel elements
		$required_channel_elements = array( 'title', 'link', 'description', 'pubDate', 'language' );
		foreach ( $required_channel_elements as $element_name ) {
			$elements = $channel->getElementsByTagName( $element_name );
			if ( $elements->length === 0 ) {
				$result[ 'errors' ][] = sprintf( 'Missing required channel element: %s', $element_name );
			}
		}

		// Step 5: Check for WXR version
		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'wp', 'http://wordpress.org/export/1.2/' );
		$wxr_version = $xpath->query( '//wp:wxr_version', $channel );

		if ( $wxr_version->length === 0 ) {
			$result[ 'errors' ][] = 'Missing WXR version element';
		} else {
			$version = $wxr_version->item( 0 )->textContent;
			if ( $version !== '1.2' ) {
				$result[ 'errors' ][] = sprintf( 'Unsupported WXR version: %s (expected 1.2)', $version );
			}
		}

		// Step 6: Check for site URLs
		$base_site_url_nodes = $xpath->query( '//wp:base_site_url', $channel );
		$base_blog_url_nodes = $xpath->query( '//wp:base_blog_url', $channel );

		if ( $base_site_url_nodes->length === 0 ) {
			$result[ 'errors' ][] = 'Missing base_site_url element';
		}

		if ( $base_blog_url_nodes->length === 0 ) {
			$result[ 'errors' ][] = 'Missing base_blog_url element';
		}

		// Step 7: Check for at least one item (post) element
		$items = $channel->getElementsByTagName( 'item' );
		if ( $items->length === 0 ) {
			$result[ 'warnings' ][] = 'WXR contains no items (posts)';
		} else {
			// Check structure of first item as sample validation
			$first_item             = $items->item( 0 );
			$required_item_elements = array( 'title', 'link', 'pubDate', 'guid', 'content:encoded', 'excerpt:encoded' );

			foreach ( $required_item_elements as $element_name ) {
				// Handle namespace prefix for special cases
				if ( strpos( $element_name, ':' ) !== false ) {
					list( $ns_prefix, $local_name ) = explode( ':', $element_name );
					$item_elements                  = $xpath->query( ".//$ns_prefix:$local_name", $first_item );
				} else {
					$item_elements = $first_item->getElementsByTagName( $element_name );
				}

				if ( $item_elements->length === 0 ) {
					$result[ 'errors' ][] = sprintf( 'Item missing required element: %s', $element_name );
				}
			}

			// Check for required WP elements in items
			$required_wp_elements = array( 'post_id', 'post_date', 'post_date_gmt', 'post_type' );
			foreach ( $required_wp_elements as $element_name ) {
				$wp_elements = $xpath->query( ".//wp:$element_name", $first_item );
				if ( $wp_elements->length === 0 ) {
					$result[ 'errors' ][] = sprintf( 'Item missing required WordPress element: %s', $element_name );
				}
			}
		}

		// Set result as valid if no errors found
		if ( empty( $result[ 'errors' ] ) ) {
			$result[ 'valid' ] = true;
		}

		return $result;
	}

	/**
	 * Validate the generated WXR and log any validation errors
	 *
	 * @param string $xml_content The XML content to validate
	 * @return bool True if valid, false if invalid
	 */
	public function validate_export( $xml_content ) {
		// First check basic XML well-formedness
		$previous_state = libxml_use_internal_errors( true );
		$dom            = new DOMDocument( '1.0', 'utf-8' );
		$xml_loaded     = @$dom->loadXML( $xml_content );

		if ( ! $xml_loaded ) {
			$errors = libxml_get_errors();
			$this->log_xml_validation_errors( $errors );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_state );
			return false;
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_state );

		// Then perform comprehensive WXR validation
		$validation = $this->validate_wxr( $xml_content );

		if ( ! $validation[ 'valid' ] ) {
			foreach ( $validation[ 'errors' ] as $error ) {
				error_log( "Multisite Exporter WXR Validation Error: $error" );
			}

			if ( ! empty( $validation[ 'warnings' ] ) ) {
				foreach ( $validation[ 'warnings' ] as $warning ) {
					error_log( "Multisite Exporter WXR Validation Warning: $warning" );
				}
			}

			return false;
		}

		return true;
	}
}