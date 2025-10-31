<?php
/*
 * File: includes/class-importer.php
 * Description: Main CSV import and order creation engine for Steeljaw
 * Plugin: Steeljaw — TikTok CSV → Woo Orders
 * Author: Eric Kowalewski
 * Last Updated: 2025-10-28 (EDT)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Steeljaw_TikTok_Importer {

    public function __construct() {
        add_action( 'admin_post_steeljaw_import', [ $this, 'handle_import' ] );
    }

    /* -----------------------------------------------------------
     *  MAIN IMPORT HANDLER
     * ----------------------------------------------------------- */
    public function handle_import() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Insufficient permissions.' );
        check_admin_referer( 'steeljaw_import_nonce' );
        if ( empty( $_FILES['csv']['tmp_name'] ) ) wp_die( 'No file uploaded.' );

        $dry_run     = ! empty( $_POST['dry_run'] );
        $repair_mode = ! empty( $_POST['repair_mode'] );
        $path        = $_FILES['csv']['tmp_name'];

        $fh = fopen( $path, 'r' );
        if ( ! $fh ) wp_die( 'Unable to read uploaded file.' );

        // Detect delimiter
        $first = fgets( $fh, 8192 );
        rewind( $fh );
        $delimiter = ( strpos( $first, "\t" ) !== false && substr_count( $first, "\t" ) > substr_count( $first, "," ) )
            ? "\t" : ",";

        $headers = fgetcsv( $fh, 0, $delimiter );
        if ( ! $headers ) wp_die( 'Empty file or bad delimiter.' );
        $norm = array_map( 'strtolower', array_map( 'trim', $headers ) );

        // Required fields
        $k_order_id = Steeljaw_Helpers::pick( $norm, [ 'order id', 'order_id' ] );
        $k_sku      = Steeljaw_Helpers::pick( $norm, [ 'seller sku', 'sku', 'product sku' ] );
        $k_qty      = Steeljaw_Helpers::pick( $norm, [ 'quantity', 'qty' ] );
        $k_unit     = Steeljaw_Helpers::pick( $norm, [ 'unit price', 'price' ] );
        if ( ! $k_order_id || ! $k_sku || ! $k_qty || ! $k_unit )
            wp_die( 'Missing required column(s).' );

        $orders = [];
        while ( ( $row = fgetcsv( $fh, 0, $delimiter ) ) !== false ) {
            $r = [];
            foreach ( $norm as $i => $key ) $r[$key] = isset( $row[$i] ) ? $row[$i] : '';
            $oid = trim( (string)$r[$k_order_id] );
            if ( $oid === '' ) continue;

            $sku   = Steeljaw_Helpers::normalize_sku( $r[$k_sku] );
            $qty   = max( 1, (int)Steeljaw_Helpers::num( $r[$k_qty] ) );
            $unit  = Steeljaw_Helpers::num( $r[$k_unit] );
            $pname = isset( $r['product name'] ) ? trim( $r['product name'] ) : 'TikTok Item';
            $var   = isset( $r['variation'] ) ? trim( $r['variation'] ) : '';

            if ( ! isset( $orders[$oid] ) ) {
                $orders[$oid] = [
                    'billing' => [ 'email' => Steeljaw_Helpers::safe_email( '', '', $oid ) ],
                    'items'   => [],
                    'ship'    => 0.0,
                    'tax'     => 0.0
                ];
            }
            $orders[$oid]['items'][] = compact( 'sku', 'qty', 'unit', 'pname', 'var' );
        }
        fclose( $fh );

        $log = [];
        foreach ( $orders as $oid => $data ) {
            $existing = wc_get_orders([
                'limit' => 1, 'meta_key' => STEELJAW_META_TIKTOK_ID,
                'meta_value' => $oid, 'return' => 'ids'
            ]);

            if ( $dry_run ) {
                $act = $existing ? ( $repair_mode ? 'REPAIR' : 'SKIP' ) : 'CREATE';
                $missing = [];
                foreach ( $data['items'] as $it ) {
                    if ( ! Steeljaw_Helpers::find_product_id_by_sku_relaxed( $it['sku'] ) )
                        $missing[] = $it['sku'];
                }
                $log[] = "DRY RUN {$act} | TikTok {$oid} | missing SKUs: " . implode( ',', $missing );
                continue;
            }

            if ( $existing && ! $repair_mode ) {
                $log[] = "Skip TikTok {$oid} (already imported)";
                continue;
            }

            if ( $existing && $repair_mode ) {
                $order_id = (int)$existing[0];
                $order    = wc_get_order( $order_id );
                if ( ! $order ) { $log[] = "Repair ✗ TikTok {$oid} — order missing"; continue; }
                foreach ( $order->get_items('line_item') as $item_id => $item )
                    $order->remove_item( $item_id );
                $this->add_items_from_csv( $order, $data['items'] );
                $order->calculate_totals(false);
                $order->save();
                $order->add_order_note("Repaired via Steeljaw CSV");
                Steeljaw_Finalizer::finalize_for_shipstation( $order_id );
                $log[] = "Repaired ✓ TikTok {$oid} → Woo #{$order_id}";
            } else {
                $order = new WC_Order();
                $order->set_status('processing');
                $order->set_created_via('steeljaw_csv_import');
                $order->set_currency('USD');
                $order->set_address( $data['billing'], 'billing' );
                $order->set_address( $data['billing'], 'shipping' );
                $this->add_items_from_csv( $order, $data['items'] );
                $order->calculate_totals(false);
                $order_id = $order->save();
                $order->update_meta_data( STEELJAW_META_TIKTOK_ID, $oid );
                $order->save_meta_data();
                $order->add_order_note("Imported via Steeljaw (CSV {$oid})");
                Steeljaw_Finalizer::finalize_for_shipstation( $order_id );
                $log[] = "Created ✓ TikTok {$oid} → Woo #{$order_id}";
            }
        }

        update_option( 'steeljaw_last_log', implode("\n", $log) );
        wp_safe_redirect( admin_url( 'admin.php?page=steeljaw' ) );
        exit;
    }

    /* -----------------------------------------------------------
     *  Add product line items
     * ----------------------------------------------------------- */
    private function add_items_from_csv( $order, $items ) {
        foreach ( $items as $it ) {
            $pid = Steeljaw_Helpers::find_product_id_by_sku_relaxed( $it['sku'] );
            $product = $pid ? wc_get_product( $pid ) : false;
            $item = new WC_Order_Item_Product();
            if ( $product ) $item->set_product( $product );
            $item->set_name( $it['pname'] );
            $qty = max( 1, intval( $it['qty'] ) );
            $line = round( $it['unit'] * $qty, 2 );
            $item->set_quantity( $qty );
            $item->set_total( $line );
            $order->add_item( $item );
        }
    }
}
