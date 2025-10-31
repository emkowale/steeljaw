<?php
/*
 * File: includes/version.php
 * Description: Core version and constants for Steeljaw
 * Plugin: Steeljaw — TikTok CSV → Woo Orders
 * Author: Eric Kowalewski
 * Last Updated: 2025-10-28 (EDT)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -----------------------------------------------------------
 *  VERSION CONSTANTS
 * ----------------------------------------------------------- */

if ( ! defined( 'STEELJAW_VERSION' ) ) {
    define( 'STEELJAW_VERSION', '1.2.1' );
}

/* -----------------------------------------------------------
 *  PLACEHOLDER PRODUCT ID
 *  Used when TikTok items don’t map to real products.
 * ----------------------------------------------------------- */

if ( ! defined( 'STEELJAW_PLACEHOLDER_ID' ) ) {
    define( 'STEELJAW_PLACEHOLDER_ID', 40158 );
}

/* -----------------------------------------------------------
 *  META KEY HELPERS
 * ----------------------------------------------------------- */

if ( ! defined( 'STEELJAW_META_TIKTOK_ID' ) ) {
    define( 'STEELJAW_META_TIKTOK_ID', '_tiktok_order_id' );
}

if ( ! defined( 'STEELJAW_META_USERNAME' ) ) {
    define( 'STEELJAW_META_USERNAME', '_tiktok_username' );
}

if ( ! defined( 'STEELJAW_META_PHONE' ) ) {
    define( 'STEELJAW_META_PHONE', '_tiktok_original_phone' );
}

/* -----------------------------------------------------------
 *  UTILITY
 * ----------------------------------------------------------- */

if ( ! function_exists( 'steeljaw_version_info' ) ) {
    function steeljaw_version_info() {
        return sprintf(
            'Steeljaw v%s | Placeholder Product ID %s',
            STEELJAW_VERSION,
            STEELJAW_PLACEHOLDER_ID
        );
    }
}
