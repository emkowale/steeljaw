<?php
/* 
 * File: includes/address-map.php
 * Purpose: Map TikTok CSV row → WooCommerce billing/shipping (HPOS-safe)
 */

if ( ! defined('ABSPATH') ) exit;

if ( ! function_exists('steeljaw_apply_addresses') ) {
function steeljaw_apply_addresses( WC_Order $order, array $row, string $fallback_email = '' ){
    // Extract + normalize from CSV headers
    $recipient = trim((string)($row['Recipient'] ?? ''));
    $phone     = trim((string)($row['Phone #'] ?? ''));
    $country   = strtoupper(trim((string)($row['Country'] ?? 'US')));
    $state     = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($row['State'] ?? '')), 0, 2));
    $city      = trim((string)($row['City'] ?? ''));
    $postcode  = preg_replace('/\s+/', '', (string)($row['Zipcode'] ?? ''));
    $addr1     = trim((string)($row['Address Line 1'] ?? ''));
    $addr2     = trim((string)($row['Address Line 2'] ?? ''));
    $email     = $fallback_email ?: (string) get_post_meta($order->get_id(), '_billing_email', true);

    // Split recipient → first/last (basic)
    $first_name = $recipient;
    $last_name  = '';
    if ($recipient && strpos($recipient, ' ') !== false){
        $parts = preg_split('/\s+/', $recipient, 2);
        $first_name = $parts[0] ?? '';
        $last_name  = $parts[1] ?? '';
    }

    // Set BILLING (explicit setters)
    $order->set_billing_first_name($first_name);
    $order->set_billing_last_name($last_name);
    $order->set_billing_address_1($addr1);
    $order->set_billing_address_2($addr2);
    $order->set_billing_city($city);
    $order->set_billing_state($state);
    $order->set_billing_postcode($postcode);
    $order->set_billing_country($country);
    if ($email) $order->set_billing_email($email);
    if ($phone) $order->set_billing_phone($phone);

    // SHIPPING mirrors billing (TikTok feed ships to recipient)
    $order->set_shipping_first_name($first_name);
    $order->set_shipping_last_name($last_name);
    $order->set_shipping_address_1($addr1);
    $order->set_shipping_address_2($addr2);
    $order->set_shipping_city($city);
    $order->set_shipping_state($state);
    $order->set_shipping_postcode($postcode);
    $order->set_shipping_country($country);

    // Ensure admin "Address:" blocks render even if theme/plugins expect meta
    $pairs = [
        'first_name' => $first_name, 'last_name' => $last_name,
        'address_1'  => $addr1, 'address_2' => $addr2,
        'city'       => $city,  'state' => $state,
        'postcode'   => $postcode, 'country' => $country,
        'email'      => $email, 'phone' => $phone,
    ];
    foreach ($pairs as $k => $v){
        if ($v === '' || $v === null) continue;
        $order->update_meta_data('_billing_'.$k, $v);
        $order->update_meta_data('_shipping_'.$k, $v);
    }
}}
