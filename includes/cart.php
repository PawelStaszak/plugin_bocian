<?php
if (!defined('ABSPATH')) exit;

/** CART HELPERS */
function lm_ev_cart_has_product_or_parent($target_id) {
    if (!function_exists('WC') || !WC()->cart) return false;

    $target_id = (int)$target_id;
    if (!$target_id) return false;

    foreach (WC()->cart->get_cart() as $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $vid = (int)($item['variation_id'] ?? 0);

        if ($pid === $target_id || $vid === $target_id) return true;

        if ($vid) {
            $v = wc_get_product($vid);
            if ($v && (int)$v->get_parent_id() === $target_id) return true;
        }
        if ($pid) {
            $p = wc_get_product($pid);
            if ($p && $p->is_type('variation') && (int)$p->get_parent_id() === $target_id) return true;
        }
    }
    return false;
}

function lm_ev_has_active_voucher() {
    if (!function_exists('WC') || !WC()->session || !WC()->cart) return false;

    $code = trim((string) WC()->session->get('lm_employee_coupon'));
    if ($code === '') return false;

    $applied = (array) WC()->cart->get_applied_coupons();
    $applied_lc = array_map('strtolower', $applied);

    if (in_array(strtolower($code), $applied_lc, true)) return true;

    $coupon_id = wc_get_coupon_id_by_code($code);
    if (!$coupon_id) {
        WC()->session->set('lm_employee_coupon', null);
        return false;
    }

    if (!WC()->cart->is_empty()) {
        WC()->cart->add_discount($code);
        WC()->cart->calculate_totals();

        $applied2 = (array) WC()->cart->get_applied_coupons();
        $applied2_lc = array_map('strtolower', $applied2);
        if (in_array(strtolower($code), $applied2_lc, true)) return true;
    }

    return false;
}

function lm_ev_cart_add_base_product($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return false;

    if (!$product->is_type('variable')) {
        return (bool) WC()->cart->add_to_cart($product_id, 1);
    }

    $variations = $product->get_available_variations();
    if (empty($variations)) return false;

    foreach ($variations as $v) {
        if (empty($v['variation_id'])) continue;
        $variation_id = (int) $v['variation_id'];
        $attributes   = !empty($v['attributes']) ? (array) $v['attributes'] : [];
        $added = WC()->cart->add_to_cart($product_id, 1, $variation_id, $attributes);
        if ($added) return true;
    }

    return false;
}

function lm_ev_try_apply_coupon($code) {
    $ok = WC()->cart->add_discount($code);
    WC()->cart->calculate_totals();
    return $ok;
}

function lm_ev_render_totals_html() {
    if (!function_exists('WC') || !WC()->cart) {
        return '<p style="margin:0;color:#b00020;font-weight:700;">Koszyk niedostępny.</p>';
    }

    WC()->cart->calculate_totals();
    $cart = WC()->cart;

    $subtotal_products = (float) $cart->get_cart_contents_total();
    $employer_paid     = (float) $cart->get_discount_total();
    $fees              = $cart->get_fees();
    $total_to_pay      = $cart->get_total('edit');

    ob_start(); ?>
    <div style="display:grid;gap:10px;">
        <div>
            <p style="margin:0 0 6px;color:#666;">Rozliczenie:</p>
            <div style="border:1px solid #eee;border-radius:10px;overflow:hidden;">
                <div style="display:flex;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid #eee;">
                    <div style="font-weight:700;">Pobyt + dodatki (produkty)</div>
                    <div style="font-weight:800;"><?php echo wp_kses_post(wc_price($subtotal_products)); ?></div>
                </div>

                <div style="display:flex;justify-content:space-between;gap:10px;padding:10px 12px;border-bottom:1px solid #eee;">
                    <div style="font-weight:700;">Dopłata pracodawcy (voucher)</div>
                    <div style="font-weight:900;color:#0a7a2f;">-<?php echo wp_kses_post(wc_price($employer_paid)); ?></div>
                </div>

                <?php if (!empty($fees)) : ?>
                    <div style="padding:10px 12px;border-bottom:1px solid #eee;background:#fafafa;">
                        <div style="font-weight:800;margin-bottom:6px;">Obligo</div>
                        <?php foreach ($fees as $fee) : ?>
                            <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;">
                                <div><?php echo esc_html($fee->name); ?></div>
                                <div style="font-weight:800;"><?php echo wp_kses_post(wc_price((float)$fee->amount)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="display:flex;justify-content:space-between;gap:10px;padding:12px;">
                    <div style="font-size:14px;color:#666;font-weight:700;">Do zapłaty przez pracownika</div>
                    <div style="font-size:22px;font-weight:900;"><?php echo wp_kses_post(wc_price($total_to_pay)); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
