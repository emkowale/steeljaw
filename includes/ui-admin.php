<?php
/*
 * File: includes/ui-admin.php
 * Description: Admin page UI for Steeljaw importer (upload form, logs)
 * Plugin: Steeljaw — TikTok CSV → Woo Orders
 * Author: Eric Kowalewski
 * Last Updated: 2025-10-28 (EDT)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Steeljaw_Admin_UI {

    public static function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Insufficient permissions.' );
        $log = get_option( 'steeljaw_last_log', '' );
        ?>
        <div class="wrap" style="max-width:960px">
            <h1>Steeljaw — TikTok CSV → Woo Orders</h1>
            <p>Upload a TikTok Shop CSV export. Use <strong>Dry Run</strong> first to validate.</p>

            <form method="post"
                  enctype="multipart/form-data"
                  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                  style="margin-top:16px">
                <?php wp_nonce_field( 'steeljaw_import_nonce', '_wpnonce' ); ?>
                <input type="hidden" name="action" value="steeljaw_import">

                <table class="form-table">
                    <tr>
                        <th><label for="csv">CSV file</label></th>
                        <td><input type="file" name="csv" id="csv" accept=".csv,.tsv,.txt" required></td>
                    </tr>
                    <tr>
                        <th>Dry Run</th>
                        <td>
                            <label>
                                <input type="checkbox" name="dry_run" value="1" checked>
                                Parse & validate only (no orders created)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Repair Existing Orders</th>
                        <td>
                            <label>
                                <input type="checkbox" name="repair_mode" value="1">
                                If a TikTok order already exists, rebuild its product line items
                                from this CSV (keeps shipping / tax)
                            </label>
                            <div style="color:#666;font-size:12px">
                                Use the same CSV you originally imported.
                                Matching is by TikTok Order ID.
                            </div>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Upload & Process' ); ?>
            </form>

            <?php if ( $log ) : ?>
                <h2>Last Run Log</h2>
                <pre style="background:#0b0b0b;color:#a7f3d0;padding:12px;
                            overflow:auto;max-height:460px;border-radius:6px;">
<?php echo esc_html( $log ); ?></pre>
            <?php endif; ?>

            <p style="color:#666;margin-top:20px;">
                <?php echo esc_html( steeljaw_version_info() ); ?>
            </p>
        </div>
        <?php
    }

    /** Hook the menu item */
    public static function register_menu() {
        add_menu_page(
            'Steeljaw',
            'Steeljaw',
            'manage_woocommerce',
            'steeljaw',
            [ __CLASS__, 'render_page' ],
            'dashicons-upload',
            56
        );
    }
}

add_action( 'admin_menu', [ 'Steeljaw_Admin_UI', 'register_menu' ] );
