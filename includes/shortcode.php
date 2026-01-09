<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('lm_employee_voucher', function () {

    if (is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST)) {
        return '
            <div style="padding:12px;border:1px solid #eee;border-radius:8px;background:#fff;">
                <strong>LM Voucher (MVP)</strong><br>
                Shortcode działa na froncie strony. W edytorze jest podgląd zastępczy.
            </div>
        ';
    }

    if (!function_exists('WC')) {
        return '<p>WooCommerce nieaktywny.</p>';
    }

    if (null === WC()->cart && function_exists('wc_load_cart')) {
        wc_load_cart();
    }

    if (null === WC()->session || null === WC()->cart) {
        return '<p>Nie udało się zainicjalizować sesji WooCommerce.</p>';
    }

    /**
     * Assets
     */
    wp_enqueue_style(
        'lm-ev',
        LM_EV_URL . 'assets/css/lm-ev.css',
        [],
        '0.2.2'
    );

    wp_enqueue_script(
        'lm-ev',
        LM_EV_URL . 'assets/js/lm-ev.js',
        [],
        '0.2.2',
        true
    );

    wp_localize_script('lm-ev', 'LM_EV', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce(lm_ev_ajax_nonce_action()),
    ]);

    $cfg   = lm_ev_config();
    $base3 = (int) $cfg['base_3_id'];
    $base5 = (int) $cfg['base_5_id'];

    $msg = '';

    /**
     * Obsługa wpisania kodu
     */
    if (!empty($_POST['lm_voucher_code'])) {

        $code      = sanitize_text_field($_POST['lm_voucher_code']);
        $coupon_id = wc_get_coupon_id_by_code($code);

        if (!$coupon_id) {

            $msg = '<div class="lm-ev-msg lm-ev-msg--err">Nie znaleziono kodu.</div>';

        } else {

            $coupon      = new WC_Coupon($code);
            $product_ids = (array) $coupon->get_product_ids();

            $base_to_add = 0;

            if (in_array($base3, $product_ids, true)) {
                $base_to_add = $base3;
            }

            if (in_array($base5, $product_ids, true)) {
                $base_to_add = $base5;
            }

            if (!$base_to_add) {

                $msg = '<div class="lm-ev-msg lm-ev-msg--err">
                    Kod nie jest przypięty do produktu bazowego.
                </div>';

            } else {

                WC()->cart->empty_cart();
                WC()->cart->remove_coupons();
                wc_clear_notices();

                WC()->session->set('lm_ev_date_start', null);
                WC()->session->set('lm_ev_date_end', null);

                $added = lm_ev_cart_add_base_product($base_to_add);

                if (!$added) {

                    $msg = '<div class="lm-ev-msg lm-ev-msg--err">
                        Nie udało się dodać produktu bazowego.
                    </div>';

                } else {

                    if (!lm_ev_try_apply_coupon($code)) {

                        $msg = '<div class="lm-ev-msg lm-ev-msg--err">
                            Nie udało się zastosować kodu.
                        </div>';

                        WC()->cart->empty_cart();
                        WC()->cart->remove_coupons();
                        WC()->session->set('lm_employee_coupon', null);

                    } else {

                        WC()->session->set('lm_employee_coupon', $code);

                        $msg = '<div class="lm-ev-msg lm-ev-msg--ok">
                            Kod zaakceptowany. Wybierz dodatki i termin pobytu.
                        </div>';
                    }
                }
            }
        }
    }

    $has_active = lm_ev_has_active_voucher();

    ob_start();
    ?>

    <div class="lm-ev-wrap">

        <h2 class="lm-ev-title">Pobyt pracowniczy</h2>

        <?php echo $msg; ?>

        <?php if (!$has_active): ?>

            <form method="post" class="lm-ev-form">

                <div class="lm-ev-field">
                    <label class="lm-ev-label">Kod od pracodawcy</label>
                    <input
                        type="text"
                        name="lm_voucher_code"
                        required
                        class="lm-ev-input"
                    >
                </div>

                <button type="submit" class="lm-ev-btn lm-ev-btn--primary">
                    Aktywuj kod
                </button>

            </form>

        <?php else: ?>

            <?php
                $required_nights         = lm_ev_required_nights_from_cart();
                $weekend_unlocked        = lm_ev_weekend_unlocked_by_cart();
                $weekend_locked_default  = lm_ev_is_weekend_locked_by_default();
                $allowed_dates           = lm_ev_get_allowed_arrival_dates();
            ?>

            <!-- ================= TERMIN ================= -->

            <div class="lm-ev-section">

                <h3 class="lm-ev-h3">Termin pobytu</h3>

                <?php if (!$allowed_dates): ?>

                    <p class="lm-ev-muted">
                        Terminy nie są jeszcze ustawione.
                    </p>

                <?php else: ?>

                    <label class="lm-ev-label">
                        Wybierz dzień przyjazdu
                    </label>

                    <input
                        type="text"
                        id="lm-ev-calendar-front"
                        class="lm-ev-input"
                        placeholder="Kliknij i wybierz termin"
                        readonly
                    >

                    <input type="hidden" id="lm-ev-date-start">
                    <input type="hidden" id="lm-ev-date-end">

                    <div id="lm-ev-date-info" class="lm-ev-muted" style="margin-top:8px;"></div>

                    <div class="lm-ev-muted" style="margin-top:10px;">
                        Wymagana długość:
                        <strong><?php echo (int) $required_nights; ?> nocy</strong>
                        <?php if ($weekend_locked_default && !$weekend_unlocked): ?>
                            <br>Weekend domyślnie wyłączony (odblokuje się po dodatku).
                        <?php endif; ?>
                    </div>

                <?php endif; ?>

            </div>

            <!-- ================= DODATKI ================= -->

            <?php
                $addon_ids = [];

                if (!empty($cfg['addon_weekend_id'])) {
                    $addon_ids[] = (int) $cfg['addon_weekend_id'];
                }

                foreach ((array) $cfg['addons'] as $id) {
                    if ($id) {
                        $addon_ids[] = (int) $id;
                    }
                }

                $in_cart = [];

                foreach (WC()->cart->get_cart() as $item) {
                    if (!empty($item['product_id'])) {
                        $in_cart[(int) $item['product_id']] = true;
                    }
                }
            ?>

            <div class="lm-ev-section">

                <h3 class="lm-ev-h3">Dodatki</h3>

                <div class="lm-ev-addons">

                    <?php foreach ($addon_ids as $pid): ?>

                        <?php
                            $p = wc_get_product($pid);
                            if (!$p) continue;

                            $checked = !empty($in_cart[$pid]) ? 'checked' : '';
                            $img     = $p->get_image('thumbnail', ['class' => 'lm-ev-addon__img']);
                            $desc    = wp_trim_words(
                                wp_strip_all_tags($p->get_short_description() ?: $p->get_description()),
                                18
                            );
                        ?>

                        <label class="lm-ev-addon">

                            <span class="lm-ev-addon__left">
                                <input
                                    type="checkbox"
                                    class="lm-addon"
                                    data-product-id="<?php echo esc_attr($pid); ?>"
                                    <?php echo $checked; ?>
                                >
                                <?php echo $img; ?>

                                <span class="lm-ev-addon__meta">
                                    <strong><?php echo esc_html($p->get_name()); ?></strong>
                                    <?php if ($desc): ?>
                                        <span class="lm-ev-addon__desc">
                                            <?php echo esc_html($desc); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </span>

                            <span class="lm-ev-addon__price">
                                <?php echo wp_kses_post(wc_price($p->get_price())); ?>
                            </span>

                        </label>

                    <?php endforeach; ?>

                </div>

            </div>

            <!-- ================= PODSUMOWANIE ================= -->

            <div class="lm-ev-section lm-ev-summary">

                <h3 class="lm-ev-h3">Podsumowanie</h3>

                <div id="lm-totals">
                    <?php echo lm_ev_render_totals_html(); ?>
                </div>

                <div class="lm-ev-actions">
                    <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="lm-ev-btn lm-ev-btn--dark">
                        Przejdź do płatności
                    </a>
                    <button type="button" id="lm-reset" class="lm-ev-btn lm-ev-btn--light">
                        Zmień kod
                    </button>
                </div>

            </div>

            <script>
                window.LM_EV_DATES = {
                    allowed: <?php echo json_encode($allowed_dates); ?>,
                    requiredNights: <?php echo (int) $required_nights; ?>,
                    weekendLocked: <?php echo $weekend_locked_default ? 1 : 0; ?>,
                    weekendUnlocked: <?php echo $weekend_unlocked ? 1 : 0; ?>
                };
            </script>

        <?php endif; ?>

    </div>

    <?php
    return ob_get_clean();
});
