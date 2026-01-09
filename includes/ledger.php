<?php
if (!defined('ABSPATH')) exit;

add_action('woocommerce_checkout_order_processed', function($order_id){
    if (!$order_id) return;

    // zapisz date range do order meta
    if (function_exists('WC') && WC()->session) {
        $start = trim((string)WC()->session->get('lm_ev_date_start'));
        $end   = trim((string)WC()->session->get('lm_ev_date_end'));

        if ($start && $end) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_lm_ev_date_start', $start);
                $order->update_meta_data('_lm_ev_date_end', $end);
                $order->update_meta_data('_lm_ev_nights', lm_ev_nights_between($start, $end));
                $order->save();
            }
        }
    }

    if (!lm_ev_table_exists()) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $coupons = (array) $order->get_coupon_codes();
    if (empty($coupons)) return;

    global $wpdb;
    $table = lm_ev_table_name();

    foreach ($coupons as $code) {
        $code = trim((string)$code);
        if ($code === '') continue;

        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE coupon_code = %s LIMIT 1", $code));
        if (!$id) continue;

        $wpdb->update(
            $table,
            [
                'status'         => 'used',
                'used_at'        => current_time('mysql'),
                'order_id'       => (int)$order_id,
                'employee_email' => (string)$order->get_billing_email(),
            ],
            [ 'coupon_code' => $code ],
            [ '%s','%s','%d','%s' ],
            [ '%s' ]
        );
    }
}, 10, 1);
