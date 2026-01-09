<?php
if (!defined('ABSPATH')) exit;

function lm_ev_render_totals_html() {
    if (!function_exists('WC') || !WC()->cart) {
        return '<p class="lm-ev-error">Koszyk niedostępny.</p>';
    }

    WC()->cart->calculate_totals();
    $cart = WC()->cart;

    $subtotal_products = (float)$cart->get_cart_contents_total();
    $employer_paid     = (float)$cart->get_discount_total();
    $fees              = $cart->get_fees();
    $total_to_pay      = $cart->get_total('edit');

    ob_start(); ?>
    <div class="lm-ev-totals">
        <p class="lm-ev-muted">Rozliczenie:</p>

        <div class="lm-ev-card">
            <div class="lm-ev-row">
                <div class="lm-ev-row__label">Pobyt + dodatki (produkty)</div>
                <div class="lm-ev-row__value"><?php echo wp_kses_post(wc_price($subtotal_products)); ?></div>
            </div>

            <div class="lm-ev-row">
                <div class="lm-ev-row__label">Dopłata pracodawcy (voucher)</div>
                <div class="lm-ev-row__value lm-ev-green">-<?php echo wp_kses_post(wc_price($employer_paid)); ?></div>
            </div>

            <?php if (!empty($fees)) : ?>
                <div class="lm-ev-fees">
                    <div class="lm-ev-fees__title">Obligo</div>
                    <?php foreach ($fees as $fee) : ?>
                        <div class="lm-ev-fee">
                            <div><?php echo esc_html($fee->name); ?></div>
                            <div class="lm-ev-fee__value"><?php echo wp_kses_post(wc_price((float)$fee->amount)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="lm-ev-total">
                <div class="lm-ev-total__label">Do zapłaty przez pracownika</div>
                <div class="lm-ev-total__value"><?php echo wp_kses_post(wc_price($total_to_pay)); ?></div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
