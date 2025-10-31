<?php
/*
 * Plugin Name: Steeljaw — TikTok CSV → Woo Orders
 * Description: Manual importer that converts TikTok Shop CSV exports into WooCommerce orders. Adds a Repair mode and auto-finalizes ShipStation-ready orders.
 * Plugin URI: https://thebeartraxs.com/
 * Author: The Bear Traxs
 * Version: 1.2.1
 * Last Updated: 2025-10-28 (EDT)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* -----------------------------------------------------------
 *  PATH CONSTANTS
 * ----------------------------------------------------------- */
define( 'STEELJAW_DIR', plugin_dir_path( __FILE__ ) );
define( 'STEELJAW_URL', plugin_dir_url( __FILE__ ) );

/* -----------------------------------------------------------
 *  LOAD MODULES
 * ----------------------------------------------------------- */
require_once STEELJAW_DIR . 'includes/version.php';
require_once STEELJAW_DIR . 'includes/class-helpers.php';
require_once STEELJAW_DIR . 'includes/class-finalizer.php';
require_once STEELJAW_DIR . 'includes/ui-admin.php';
require_once STEELJAW_DIR . 'includes/class-importer.php';
require_once STEELJAW_DIR . '/includes/address-map.php';


/* -----------------------------------------------------------
 *  INIT MAIN CLASS
 * ----------------------------------------------------------- */
add_action('plugins_loaded', function(){
    if ( class_exists('WooCommerce') ) {
        new Steeljaw_TikTok_Importer();
    }
});
