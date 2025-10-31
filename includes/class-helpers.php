<?php
/*
 * File: includes/class-helpers.php
 * Description: Utility and normalization helpers for Steeljaw importer
 * Plugin: Steeljaw — TikTok CSV → Woo Orders
 * Author: Eric Kowalewski
 * Last Updated: 2025-10-28 (EDT)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Steeljaw_Helpers {

    /* -----------------------------------------------------------
     * Numeric parsing (cleans $, commas, etc.)
     * ----------------------------------------------------------- */
    public static function num( $val ) {
        $s = is_string( $val ) ? $val : strval( $val );
        $s = preg_replace( '/[^\d\.\-\,]/', '', $s );
        $s = str_replace( ',', '', $s );
        if ( $s === '' || strtolower( $s ) === 'nan' ) return 0.0;
        return floatval( $s );
    }

    /* -----------------------------------------------------------
     * Normalize SKU — trims invisible characters
     * ----------------------------------------------------------- */
    public static function normalize_sku( $sku ) {
        $sku = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u','',(string)$sku);
        $sku = trim($sku);
        $sku = preg_replace('/\s+/', ' ', $sku);
        return $sku;
    }

    /* -----------------------------------------------------------
     * Pick header column by fuzzy name
     * ----------------------------------------------------------- */
    public static function pick( $headers, $cands ) {
        foreach ( $cands as $c ) {
            $c = strtolower( $c );
            if ( in_array( $c, $headers, true ) ) return $c;
        }
        foreach ( $headers as $h ) {
            foreach ( $cands as $c ) {
                $c = strtolower( $c );
                if ( strpos( $h, $c ) !== false ) return $h;
            }
        }
        return null;
    }

    /* -----------------------------------------------------------
     * Find product ID by SKU (case-insensitive)
     * ----------------------------------------------------------- */
    public static function find_product_id_by_sku_relaxed( $sku ) {
        global $wpdb;
        $sku = self::normalize_sku( $sku );
        if ( $sku === '' ) return 0;

        $pid = wc_get_product_id_by_sku( $sku );
        if ( $pid ) return $pid;

        $pid = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
             WHERE m.meta_key = '_sku'
               AND LOWER(m.meta_value) = LOWER(%s)
               AND p.post_type IN ('product','product_variation')
             LIMIT 1", $sku
        ));
        return intval( $pid );
    }

    /* -----------------------------------------------------------
     * Normalize US state codes
     * ----------------------------------------------------------- */
    public static function normalize_us_state( $s ) {
        $s = trim((string)$s);
        if ( $s === '' ) return '';
        $countries = new WC_Countries();
        $states = $countries->get_states('US');
        if ( ! is_array($states) ) return $s;

        if ( isset( $states[strtoupper($s)] ) ) return strtoupper($s);
        $lower = strtolower($s);
        foreach ( $states as $code => $name ) {
            if ( strtolower($name) === $lower ) return $code;
        }
        $extra = ['washington dc'=>'DC','district of columbia'=>'DC'];
        if ( isset( $extra[$lower] ) ) return $extra[$lower];
        return $s;
    }

    /* -----------------------------------------------------------
     * Extract phone digits only
     * ----------------------------------------------------------- */
    public static function phone_digits( $p ) {
        $d = preg_replace( '/\D+/', '', (string)$p );
        if ( strlen($d) == 11 && substr($d,0,1) === '1' ) $d = substr($d,1);
        return $d;
    }

    /* -----------------------------------------------------------
     * Sanitize or fallback email
     * ----------------------------------------------------------- */
    public static function safe_email( $maybe, $username, $oid ) {
        $maybe = trim((string)$maybe);
        if ( $maybe && is_email($maybe) ) return $maybe;
        $handle = $username ? preg_replace('/[^a-z0-9\.\-\_]+/i','', strtolower($username)) : ('tiktok+' . $oid);
        return $handle . '@tiktok.local';
    }
}
