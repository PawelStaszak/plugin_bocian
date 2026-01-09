<?php
if (!defined('ABSPATH')) exit;

/**
 * TERMINY POBYTU (ARRIVAL + RANGE)
 *
 * ADMIN:
 * - ustawiasz DOZWOLONE DNI PRZYJAZDU (YYYY-MM-DD) przez kalendarz
 * - domyślnie weekend zablokowany
 *
 * FRONT:
 * - pracownik wybiera START w kalendarzu
 * - JS automatycznie zaznacza zakres 3 lub 5 nocy (zależnie od vouchera)
 * - jeśli weekend zablokowany i nie ma dodatku "weekend" → walidacja FAIL
 */

/* ======================================================
 * OPTIONS
 * ====================================================== */

function lm_ev_dates_option_key() {
    return 'lm_ev_allowed_arrival_dates_csv';
}

function lm_ev_weekend_lock_option_key() {
    return 'lm_ev_weekend_lock_default'; // '1' / '0'
}

/* ======================================================
 * DATA HELPERS
 * ====================================================== */

function lm_ev_get_allowed_arrival_dates(): array {
    $csv = trim((string) get_option(lm_ev_dates_option_key(), ''));
    if ($csv === '') return [];

    $dates = [];
    foreach (explode(',', $csv) as $d) {
        $d = trim($d);
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $d)) {
            $dates[] = $d;
        }
    }

    $dates = array_values(array_unique($dates));
    sort($dates);
    return $dates;
}

function lm_ev_is_allowed_arrival_date(string $date): bool {
    return in_array($date, lm_ev_get_allowed_arrival_dates(), true);
}

function lm_ev_is_weekend(string $date): bool {
    $ts = strtotime($date . ' 12:00:00');
    if (!$ts) return false;
    $dow = (int) date('N', $ts); // 6 = sob, 7 = nd
    return ($dow >= 6);
}

function lm_ev_is_weekend_locked_by_default(): bool {
    return (string) get_option(lm_ev_weekend_lock_option_key(), '1') === '1';
}

/**
 * Ilość nocy pomiędzy datami (end - start)
 * 2026-01-10 → 2026-01-13 = 3 noce
 */
function lm_ev_nights_between(string $start, string $end): int {
    $a = strtotime($start . ' 12:00:00');
    $b = strtotime($end . ' 12:00:00');
    if (!$a || !$b) return 0;
    return max(0, (int)(($b - $a) / DAY_IN_SECONDS));
}

/* ======================================================
 * CART CONTEXT
 * ====================================================== */

function lm_ev_required_nights_from_cart(): int {
    if (!function_exists('WC') || !WC()->cart) return 3;

    $cfg = lm_ev_config();

    if (lm_ev_cart_has_product_or_parent((int)$cfg['base_5_id'])) return 5;
    if (lm_ev_cart_has_product_or_parent((int)$cfg['base_3_id'])) return 3;

    return 3;
}

function lm_ev_weekend_unlocked_by_cart(): bool {
    if (!function_exists('WC') || !WC()->cart) return false;

    $cfg = lm_ev_config();
    $weekend_id = (int) $cfg['addon_weekend_id'];

    return $weekend_id && lm_ev_cart_has_product_or_parent($weekend_id);
}

/* ======================================================
 * WALIDACJA ZAKRESU DAT (BACKEND – OSTATECZNA)
 * ====================================================== */

function lm_ev_validate_date_range(
    string $start,
    string $end,
    bool $weekend_unlocked,
    ?int $required_nights = null
): array {

    if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $start) ||
        !preg_match('~^\d{4}-\d{2}-\d{2}$~', $end)) {
        return [false, 'Nieprawidłowy format daty.'];
    }

    if (!lm_ev_is_allowed_arrival_date($start)) {
        return [false, 'Wybrany dzień przyjazdu nie jest dostępny.'];
    }

    $nights = lm_ev_nights_between($start, $end);
    $required = $required_nights ?? lm_ev_required_nights_from_cart();

    if ($nights !== $required) {
        return [false, 'Nieprawidłowa długość pobytu. Wymagane nocy: ' . $required . '.'];
    }

    if (lm_ev_is_weekend_locked_by_default() && !$weekend_unlocked) {
        $cur = strtotime($start . ' 12:00:00');
        $end_ts = strtotime($end . ' 12:00:00');

        while ($cur < $end_ts) {
            $d = date('Y-m-d', $cur);
            if (lm_ev_is_weekend($d)) {
                return [false, 'Weekend jest domyślnie wyłączony. Dodaj opcję weekend.'];
            }
            $cur += DAY_IN_SECONDS;
        }
    }

    return [true, 'OK'];
}

/* ======================================================
 * ADMIN PANEL – TAB "TERMINY"
 * ====================================================== */

function lm_ev_admin_dates_panel() {
    if (!current_user_can('manage_options')) return;

    $saved = false;

    if (!empty($_POST['lm_ev_save_dates'])) {
        check_admin_referer('lm_ev_save_dates');

        $raw = (string) ($_POST['lm_ev_dates_csv'] ?? '');
        $dates = [];

        foreach (explode(',', $raw) as $d) {
            $d = trim($d);
            if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $d)) {
                $dates[] = $d;
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        update_option(lm_ev_dates_option_key(), implode(',', $dates));
        update_option(
            lm_ev_weekend_lock_option_key(),
            !empty($_POST['lm_ev_weekend_lock']) ? '1' : '0'
        );

        $saved = true;
    }

    $csv = implode(',', lm_ev_get_allowed_arrival_dates());
    $weekend_locked = lm_ev_is_weekend_locked_by_default();
    ?>

    <div class="wrap">
        <h2>Dostępne terminy (dni przyjazdu)</h2>

        <?php if ($saved): ?>
            <div class="notice notice-success"><p>Zapisano terminy.</p></div>
        <?php endif; ?>

        <p>
            Ustawiasz <strong>dni przyjazdu</strong>.  
            Na froncie pracownik wybiera <strong>start</strong>, a zakres (3 / 5 nocy)
            dobierany jest automatycznie.
        </p>

        <form method="post">
            <?php wp_nonce_field('lm_ev_save_dates'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Kalendarz (dni przyjazdu)</th>
                    <td>
                        <input type="text" id="lm-ev-calendar" style="width:360px" placeholder="Kliknij i wybierz daty">
                        <p class="description">Multi-select dni przyjazdu.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Blokuj weekendy domyślnie</th>
                    <td>
                        <label>
                            <input type="checkbox" name="lm_ev_weekend_lock" value="1" <?php checked($weekend_locked); ?>>
                            Weekend (sob/nd) domyślnie wyłączony
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Zapisane daty (CSV)</th>
                    <td>
                        <textarea name="lm_ev_dates_csv"
                                  id="lm-ev-dates-csv"
                                  rows="4"
                                  style="width:760px;max-width:100%;"><?php
                            echo esc_textarea($csv);
                        ?></textarea>
                        <p class="description">YYYY-MM-DD, oddzielone przecinkami.</p>
                    </td>
                </tr>
            </table>

            <p>
                <button class="button button-primary" name="lm_ev_save_dates" value="1">
                    Zapisz
                </button>
            </p>
        </form>
    </div>

    <script>
    (function(){
        document.addEventListener('DOMContentLoaded', function(){
            if (typeof flatpickr === 'undefined') return;

            var csvEl = document.getElementById('lm-ev-dates-csv');
            var init = csvEl.value ? csvEl.value.split(',') : [];

            flatpickr('#lm-ev-calendar', {
                mode: 'multiple',
                dateFormat: 'Y-m-d',
                defaultDate: init,
                onChange: function(_, str){
                    csvEl.value = str || '';
                }
            });
        });
    })();
    </script>

    <?php
}

/* ======================================================
 * ADMIN ASSETS (flatpickr tylko na TAB=dates)
 * ====================================================== */

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'toplevel_page_lm-ev-generator') return;
    if (($_GET['tab'] ?? '') !== 'dates') return;

    wp_enqueue_style(
        'flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
        [],
        null
    );

    wp_enqueue_script(
        'flatpickr',
        'https://cdn.jsdelivr.net/npm/flatpickr',
        [],
        null,
        true
    );
});
