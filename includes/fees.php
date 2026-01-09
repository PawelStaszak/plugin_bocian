<?php
if (!defined('ABSPATH')) exit;

add_action('woocommerce_cart_calculate_fees', function($cart){
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!$cart || $cart->is_empty()) return;

    $cfg = lm_ev_config();

    $has3 = lm_ev_cart_has_product_or_parent((int)$cfg['base_3_id']);
    $has5 = lm_ev_cart_has_product_or_parent((int)$cfg['base_5_id']);
    if (!$has3 && !$has5) return;

    $cart->add_fee('Opłata serwisowa', (float)$cfg['fee_service'], false);

    if ($has5) {
        $cart->add_fee('Opłata klimatyczna', (float)$cfg['fee_climate_5'], false);
        $cart->add_fee('Parking', (float)$cfg['fee_parking_5'], false);
    } else {
        $cart->add_fee('Opłata klimatyczna', (float)$cfg['fee_climate_3'], false);
        $cart->add_fee('Parking', (float)$cfg['fee_parking_3'], false);
    }
}, 20);

add_action('woocommerce_checkout_process', function () {
    if (!function_exists('WC') || !WC()->cart || !WC()->session) return;
    if (WC()->cart->is_empty()) return;

    if (!lm_ev_has_active_voucher()) {
        wc_add_notice('Aby przejść do płatności, wpisz poprawny kod vouchera na stronie pobytu pracowniczego.', 'error');
        return;
    }

    $start = trim((string) WC()->session->get('lm_ev_date_start'));
    $end   = trim((string) WC()->session->get('lm_ev_date_end'));
    if ($start === '' || $end === '') {
        wc_add_notice('Wybierz zakres dat pobytu (start i koniec) na stronie pobytu pracowniczego.', 'error');
        return;
    }

    $required = lm_ev_required_nights_from_cart();
    $weekend_unlocked = lm_ev_weekend_unlocked_by_cart();

    [$ok, $msg] = lm_ev_validate_date_range($start, $end, $weekend_unlocked, $required);
    if (!$ok) {
        wc_add_notice('Niepoprawny wybór dat: ' . $msg, 'error');
    }
});
