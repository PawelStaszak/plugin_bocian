<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_lm_toggle_addon', 'lm_toggle_addon');
add_action('wp_ajax_nopriv_lm_toggle_addon', 'lm_toggle_addon');

add_action('wp_ajax_lm_reset_employee_flow', 'lm_reset_employee_flow');
add_action('wp_ajax_nopriv_lm_reset_employee_flow', 'lm_reset_employee_flow');

add_action('wp_ajax_lm_ev_set_date_range', 'lm_ev_set_date_range');
add_action('wp_ajax_nopriv_lm_ev_set_date_range', 'lm_ev_set_date_range');

function lm_ev_verify_ajax_nonce() {
    $nonce = (string)($_POST['nonce'] ?? '');
    if (!wp_verify_nonce($nonce, lm_ev_ajax_nonce_action())) {
        wp_send_json(['ok'=>false,'message'=>'Invalid nonce']);
    }
}

function lm_ev_remove_from_cart_by_id(int $pid): void {
    if (!function_exists('WC') || !WC()->cart) return;

    foreach (WC()->cart->get_cart() as $key => $item) {
        $p = (int)($item['product_id'] ?? 0);
        $v = (int)($item['variation_id'] ?? 0);
        if ($p === $pid || $v === $pid) {
            WC()->cart->remove_cart_item($key);
        }
    }
}

function lm_toggle_addon() {
    lm_ev_verify_ajax_nonce();

    if (!function_exists('WC')) wp_send_json(['ok'=>false,'message'=>'Woo missing']);

    $pid = (int)($_POST['product_id'] ?? 0);
    $checked = (int)($_POST['checked'] ?? 0);

    if (!$pid) wp_send_json(['ok'=>false,'message'=>'No product']);
    if (!lm_ev_has_active_voucher()) wp_send_json(['ok'=>false,'message'=>'Brak aktywnego kodu']);

    $cfg = lm_ev_config();

    $upgrade_id = (int)$cfg['upgrade_3_to_5_id'];
    $base5      = (int)$cfg['base_5_id'];

    // jeśli baza 5d -> upgrade 3->5 nie może być dodany
    if (lm_ev_cart_has_product_or_parent($base5)) {
        lm_ev_remove_from_cart_by_id($upgrade_id);
        if ($pid === $upgrade_id && $checked) {
            wp_send_json(['ok'=>false,'message'=>'Masz już pobyt 5 dni – ta opcja nie jest potrzebna.']);
        }
    }

    if ($checked) {
        WC()->cart->add_to_cart($pid, 1);
    } else {
        lm_ev_remove_from_cart_by_id($pid);
    }

    // jeśli zdjęto weekend addon, a zakres zahacza o weekend -> czyść daty
    $weekend_unlocked = lm_ev_weekend_unlocked_by_cart();
    if (!$weekend_unlocked && lm_ev_is_weekend_locked_by_default()) {
        $start = trim((string)WC()->session->get('lm_ev_date_start'));
        $end   = trim((string)WC()->session->get('lm_ev_date_end'));
        if ($start && $end) {
            [$ok,] = lm_ev_validate_date_range($start, $end, $weekend_unlocked, lm_ev_required_nights_from_cart());
            if (!$ok) {
                WC()->session->set('lm_ev_date_start', null);
                WC()->session->set('lm_ev_date_end', null);
            }
        }
    }

    WC()->cart->calculate_totals();

    wp_send_json([
        'ok' => true,
        'totals_html' => lm_ev_render_totals_html(),
        'weekend_unlocked' => $weekend_unlocked ? 1 : 0,
        'required_nights'  => lm_ev_required_nights_from_cart(),
    ]);
}

function lm_reset_employee_flow() {
    lm_ev_verify_ajax_nonce();

    if (function_exists('WC') && WC()->cart && WC()->session) {
        WC()->cart->empty_cart();
        WC()->cart->remove_coupons();
        WC()->session->set('lm_employee_coupon', null);

        WC()->session->set('lm_ev_date_start', null);
        WC()->session->set('lm_ev_date_end', null);
    }
    wp_send_json(['ok'=>true]);
}

function lm_ev_set_date_range() {
    lm_ev_verify_ajax_nonce();

    if (!function_exists('WC') || !WC()->session) wp_send_json(['ok'=>false,'message'=>'Woo missing']);
    if (!lm_ev_has_active_voucher()) wp_send_json(['ok'=>false,'message'=>'Brak aktywnego kodu']);

    $start = sanitize_text_field($_POST['start'] ?? '');
    $end   = sanitize_text_field($_POST['end'] ?? '');

    $required = lm_ev_required_nights_from_cart();
    $weekend_unlocked = lm_ev_weekend_unlocked_by_cart();

    [$ok, $msg] = lm_ev_validate_date_range($start, $end, $weekend_unlocked, $required);

    if (!$ok) {
        WC()->session->set('lm_ev_date_start', null);
        WC()->session->set('lm_ev_date_end', null);
        wp_send_json(['ok'=>false,'message'=>$msg]);
    }

    WC()->session->set('lm_ev_date_start', $start);
    WC()->session->set('lm_ev_date_end', $end);

    wp_send_json([
        'ok'=>true,
        'required_nights'=>$required,
        'weekend_unlocked'=>$weekend_unlocked ? 1 : 0,
    ]);
}
