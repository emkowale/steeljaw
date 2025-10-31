<?php
/*
 * File: includes/class-finalizer.php
 * Description: Ensures imported orders meet ShipStation requirements.
 * Plugin: Steeljaw — TikTok CSV → Woo Orders
 * Author: Eric Kowalewski
 * Last Updated: 2025-10-28 (EDT)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Steeljaw_Finalizer {

    /**
     * Fix order meta, totals, and line items to ensure ShipStation imports properly.
     * Called automatically after each create/repair.
     */
    public static function finalize_for_shipstation( $order_id ) {
        global $wpdb;
        $order_id = intval( $order_id );
        if ( ! $order_id ) return;

        /* -------------------------
         * Ensure proper order status and timestamps
         * ------------------------- */
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->posts}
             SET post_status = 'wc-processing',
                 post_modified = NOW(),
                 post_modified_gmt = UTC_TIMESTAMP()
             WHERE ID = %d",
            $order_id
        ));

        /* -------------------------
         * Ensure _paid_date exists
         * ------------------------- */
        $has_paid = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key = '_paid_date'", $order_id
        ));
        if ( ! $has_paid ) {
            $wpdb->insert( $wpdb->postmeta, [
                'post_id'    => $order_id,
                'meta_key'   => '_paid_date',
                'meta_value' => current_time('mysql', true)
            ]);
        }

        /* -------------------------
         * Ensure line items are valid
         * ------------------------- */
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT oi.order_item_id, m1.meta_value AS product_id,
                    m2.meta_value AS qty, m3.meta_value AS line_total
             FROM {$wpdb->prefix}woocommerce_order_items oi
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta m1
                ON oi.order_item_id = m1.order_item_id AND m1.meta_key = '_product_id'
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta m2
                ON oi.order_item_id = m2.order_item_id AND m2.meta_key = '_qty'
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta m3
                ON oi.order_item_id = m3.order_item_id AND m3.meta_key = '_line_total'
             WHERE oi.order_id = %d AND oi.order_item_type = 'line_item'",
             $order_id
        ));

        $sum = 0;
        foreach ( $items as $row ) {
            $pid  = intval( $row->product_id );
            $qty  = max( 1, floatval( $row->qty ) );
            $line = max( 0.01, floatval( $row->line_total ) );
            $sum += $line;

            if ( ! $pid ) {
                $wpdb->update(
                    $wpdb->prefix.'woocommerce_order_itemmeta',
                    [ 'meta_value' => STEELJAW_PLACEHOLDER_ID ],
                    [ 'order_item_id' => $row->order_item_id, 'meta_key' => '_product_id' ]
                );
            }
            $wpdb->update(
                $wpdb->prefix.'woocommerce_order_itemmeta',
                [ 'meta_value' => $qty ],
                [ 'order_item_id' => $row->order_item_id, 'meta_key' => '_qty' ]
            );
            $wpdb->update(
                $wpdb->prefix.'woocommerce_order_itemmeta',
                [ 'meta_value' => $line ],
                [ 'order_item_id' => $row->order_item_id, 'meta_key' => '_line_total' ]
            );
        }

        /* -------------------------
         * Update _order_total
         * ------------------------- */
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key = '_order_total'", $order_id
        ));
        $wpdb->insert( $wpdb->postmeta, [
            'post_id'    => $order_id,
            'meta_key'   => '_order_total',
            'meta_value' => number_format( $sum, 2, '.', '' )
        ]);

        /* -------------------------
         * Ensure required shipping/billing fields
         * ------------------------- */
        $required_keys = [
            '_shipping_address_1', '_shipping_city', '_shipping_postcode',
            '_shipping_country', '_billing_email'
        ];
        foreach ( $required_keys as $key ) {
            $val = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta}
                 WHERE post_id = %d AND meta_key = %s LIMIT 1",
                $order_id, $key
            ));
            if ( ! $val || trim($val) === '' ) {
                $wpdb->replace( $wpdb->postmeta, [
                    'post_id'    => $order_id,
                    'meta_key'   => $key,
                    'meta_value' => 'Unknown'
                ]);
            }
        }
    }
}
