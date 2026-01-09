<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
    add_menu_page(
        'LM Vouchery',
        'LM Vouchery',
        'manage_options',
        'lm-ev-generator',
        'lm_ev_admin_page',
        'dashicons-tickets',
        56
    );
});

add_action('admin_post_lm_ev_generate_coupons', 'lm_ev_handle_generate_coupons');
add_action('admin_post_lm_ev_export_csv', 'lm_ev_handle_export_csv');
add_action('admin_post_lm_ev_repair', 'lm_ev_handle_repair');

/**
 * Zakładki:
 * - Generator
 * - Terminy
 */
function lm_ev_admin_page() {
    if (!current_user_can('manage_options')) return;

    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'generator';
    $tab = in_array($tab, ['generator','dates'], true) ? $tab : 'generator';

    echo '<div class="wrap"><h1>LM — Vouchery</h1>';

    echo '<nav class="nav-tab-wrapper" style="margin-bottom:12px;">';
    echo '<a class="nav-tab ' . ($tab==='generator'?'nav-tab-active':'') . '" href="'.esc_url(add_query_arg(['page'=>'lm-ev-generator','tab'=>'generator'], admin_url('admin.php'))).'">Generator + ewidencja</a>';
    echo '<a class="nav-tab ' . ($tab==='dates'?'nav-tab-active':'') . '" href="'.esc_url(add_query_arg(['page'=>'lm-ev-generator','tab'=>'dates'], admin_url('admin.php'))).'">Terminy</a>';
    echo '</nav>';

    if ($tab === 'dates') {
        lm_ev_admin_dates_panel();
        echo '</div>';
        return;
    }

    // generator tab
    $cfg   = lm_ev_config();
    $base3 = (int)$cfg['base_3_id'];
    $base5 = (int)$cfg['base_5_id'];

    $price3 = 600;
    $price5 = 1000;

    if (!lm_ev_ensure_table_exists()) {
        echo '<div class="notice notice-error"><p><strong>LM Vouchery:</strong> Nie udało się utworzyć tabeli ewidencji <code>'.esc_html(lm_ev_table_name()).'</code>.</p></div>';
    }

    $gen = isset($_GET['gen']) ? (int)$_GET['gen'] : 0;
    if ($gen > 0) {
        echo '<div class="notice notice-success"><p>Wygenerowano kodów: <strong>' . esc_html($gen) . '</strong></p></div>';
    }

    $repaired = isset($_GET['repaired']) ? (int)$_GET['repaired'] : 0;
    if ($repaired > 0) {
        echo '<div class="notice notice-success"><p>Naprawa ewidencji: dopisano brakujących rekordów: <strong>' . esc_html($repaired) . '</strong></p></div>';
    }

    global $wpdb;
    $count = lm_ev_table_exists() ? (int)$wpdb->get_var("SELECT COUNT(*) FROM ".lm_ev_table_name()) : 0;

    ?>
        <p><strong>Produkty bazowe:</strong> 3 dni (ID <?php echo esc_html($base3); ?>) / 5 dni (ID <?php echo esc_html($base5); ?>)</p>
        <p><strong>Ewidencja:</strong> tabela <code><?php echo esc_html(lm_ev_table_name()); ?></code>, rekordy: <strong><?php echo esc_html($count); ?></strong></p>

        <h2>Wygeneruj kody</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('lm_ev_generate_coupons'); ?>
            <input type="hidden" name="action" value="lm_ev_generate_coupons" />

            <table class="form-table">
                <tr>
                    <th scope="row">Firma</th>
                    <td><input type="text" name="company_name" required style="width:360px"></td>
                </tr>
                <tr>
                    <th scope="row">NIP</th>
                    <td><input type="text" name="company_nip" style="width:360px" placeholder="np. 1234567890"></td>
                </tr>
                <tr>
                    <th scope="row">Adres firmy</th>
                    <td><input type="text" name="company_address" style="width:560px" placeholder="ulica, kod, miasto"></td>
                </tr>
                <tr>
                    <th scope="row">Notatka (opcjonalnie)</th>
                    <td><input type="text" name="company_note" style="width:360px" placeholder="np. FV / kontakt"></td>
                </tr>
                <tr>
                    <th scope="row">Voucher bazowy</th>
                    <td>
                        <select name="base_type" required>
                            <option value="3d">3 dni — <?php echo esc_html($price3); ?> zł (produkt <?php echo esc_html($base3); ?>)</option>
                            <option value="5d">5 dni — <?php echo esc_html($price5); ?> zł (produkt <?php echo esc_html($base5); ?>)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">% opłaca pracodawca</th>
                    <td>
                        <input type="number" name="percent" min="10" max="99" required>
                        <p class="description">Min. 10%, max 99%</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Ilość kodów</th>
                    <td><input type="number" name="qty" min="1" max="500" required value="10"></td>
                </tr>
                <tr>
                    <th scope="row">Ważność (miesiące)</th>
                    <td><input type="number" name="months" min="1" max="36" required value="24"></td>
                </tr>
            </table>

            <p><button class="button button-primary">Generuj</button></p>
        </form>

        <hr>

        <h2>Ewidencja</h2>

        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('lm_ev_export_csv'); ?>
                <input type="hidden" name="action" value="lm_ev_export_csv">
                <button class="button">Pobierz CSV</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('lm_ev_repair'); ?>
                <input type="hidden" name="action" value="lm_ev_repair">
                <button class="button button-secondary">Repair ewidencji (dopisz brakujące z Woo kuponów)</button>
            </form>
        </div>

        <?php lm_ev_render_admin_table(); ?>
    </div>
    <?php
}

function lm_ev_handle_generate_coupons() {
    if (!current_user_can('manage_options')) wp_die('Brak uprawnień');
    check_admin_referer('lm_ev_generate_coupons');

    if (!lm_ev_ensure_table_exists()) {
        wp_die('Nie udało się utworzyć tabeli ewidencji (lm_ev_coupons).');
    }

    $cfg = lm_ev_config();

    $company   = sanitize_text_field($_POST['company_name'] ?? '');
    $note      = sanitize_text_field($_POST['company_note'] ?? '');
    $nip       = sanitize_text_field($_POST['company_nip'] ?? '');
    $addr      = sanitize_text_field($_POST['company_address'] ?? '');

    $base_type = sanitize_text_field($_POST['base_type'] ?? '');
    $percent   = (int)($_POST['percent'] ?? 0);
    $qty       = (int)($_POST['qty'] ?? 0);
    $months    = (int)($_POST['months'] ?? 24);

    if (!$company || !in_array($base_type, ['3d','5d'], true) || $percent < 10 || $percent > 99 || $qty < 1 || $qty > 500) {
        wp_die('Nieprawidłowe dane.');
    }

    $base_product_id = ($base_type === '3d') ? (int)$cfg['base_3_id'] : (int)$cfg['base_5_id'];
    $base_price      = ($base_type === '3d') ? 600 : 1000;
    $coupon_amount   = round($base_price * ($percent / 100), 2);
    $expires_at      = date('Y-m-d H:i:s', strtotime('+' . $months . ' months'));

    global $wpdb;
    $table   = lm_ev_table_name();
    $created = 0;

    for ($i=0; $i<$qty; $i++) {
        $code = lm_ev_generate_code($base_type, $percent);

        $coupon_id = wp_insert_post([
            'post_title'   => $code,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'shop_coupon',
        ]);

        if (is_wp_error($coupon_id) || !$coupon_id) continue;

        update_post_meta($coupon_id, 'discount_type', 'fixed_product');
        update_post_meta($coupon_id, 'coupon_amount', (string)$coupon_amount);
        update_post_meta($coupon_id, 'individual_use', 'yes');
        update_post_meta($coupon_id, 'usage_limit', '1');
        update_post_meta($coupon_id, 'usage_limit_per_user', '1');
        update_post_meta($coupon_id, 'product_ids', (string)$base_product_id);
        update_post_meta($coupon_id, 'date_expires', strtotime($expires_at));

        // zapis danych firmy też do kuponu (na przyszłość do faktur)
        update_post_meta($coupon_id, '_lm_ev_company_name', $company);
        update_post_meta($coupon_id, '_lm_ev_company_nip', $nip);
        update_post_meta($coupon_id, '_lm_ev_company_address', $addr);
        update_post_meta($coupon_id, '_lm_ev_company_note', $note);

        $ok = $wpdb->insert($table, [
            'company_name'     => $company,
            'company_note'     => $note,
            'company_nip'      => $nip,
            'company_address'  => $addr,
            'base_type'        => $base_type,
            'base_product_id'  => $base_product_id,
            'base_price'       => $base_price,
            'employer_percent' => $percent,
            'coupon_amount'    => $coupon_amount,
            'coupon_code'      => $code,
            'created_at'       => current_time('mysql'),
            'expires_at'       => $expires_at,
            'status'           => 'unused',
        ]);

        if ($ok !== false) $created++;
    }

    wp_redirect(add_query_arg(['page'=>'lm-ev-generator','tab'=>'generator','gen'=>$created], admin_url('admin.php')));
    exit;
}

function lm_ev_generate_code($base_type, $percent) {
    $prefix = ($base_type === '3d') ? 'MRZ3' : 'MRZ5';
    $rand   = strtoupper(wp_generate_password(6, false, false));
    $code   = "{$prefix}-{$percent}-{$rand}";
    if (wc_get_coupon_id_by_code($code)) return lm_ev_generate_code($base_type, $percent);
    return $code;
}

function lm_ev_render_admin_table() {
    if (!lm_ev_table_exists()) {
        echo '<div class="notice notice-error"><p><strong>Brak tabeli ewidencji:</strong> ' . esc_html(lm_ev_table_name()) . '</p></div>';
        return;
    }

    global $wpdb;
    $table = lm_ev_table_name();
    $rows  = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 200");

    echo '<table class="widefat striped"><thead><tr>
        <th>ID</th><th>Firma</th><th>NIP</th><th>Adres</th><th>Typ</th><th>%</th><th>Kwota</th><th>Kod</th><th>Status</th><th>Ważny do</th><th>Order</th><th>Email</th>
    </tr></thead><tbody>';

    if (!$rows) {
        echo '<tr><td colspan="12">Brak rekordów.</td></tr>';
    } else {
        foreach ($rows as $r) {
            $nip = isset($r->company_nip) ? (string)$r->company_nip : '';
            $addr = isset($r->company_address) ? (string)$r->company_address : '';
            echo '<tr>';
            echo '<td>'.(int)$r->id.'</td>';
            echo '<td>'.esc_html($r->company_name).(!empty($r->company_note)?'<br><small>'.esc_html($r->company_note).'</small>':'').'</td>';
            echo '<td>'.esc_html($nip !== '' ? $nip : '-').'</td>';
            echo '<td>'.esc_html($addr !== '' ? $addr : '-').'</td>';
            echo '<td>'.esc_html($r->base_type).'</td>';
            echo '<td>'.(int)$r->employer_percent.'%</td>';
            echo '<td>'.wc_price((float)$r->coupon_amount).'</td>';
            echo '<td><code>'.esc_html($r->coupon_code).'</code></td>';
            echo '<td>'.esc_html($r->status).'</td>';
            echo '<td>'.esc_html($r->expires_at ?: '-').'</td>';
            echo '<td>'.esc_html($r->order_id ?: '-').'</td>';
            echo '<td>'.esc_html($r->employee_email ?: '-').'</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '<p><small>Pokazuje 200 ostatnich rekordów (MVP).</small></p>';
}

function lm_ev_handle_export_csv() {
    if (!current_user_can('manage_options')) wp_die('Brak uprawnień');
    check_admin_referer('lm_ev_export_csv');

    if (!lm_ev_ensure_table_exists()) wp_die('Nie udało się utworzyć tabeli ewidencji (lm_ev_coupons).');

    global $wpdb;
    $table = lm_ev_table_name();
    $rows  = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lm_vouchery_' . date('Y-m-d_His') . '.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','company_name','company_note','company_nip','company_address','base_type','base_product_id','base_price','employer_percent','coupon_amount','coupon_code','status','created_at','expires_at','used_at','order_id','employee_email'], ';');

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],$r['company_name'],$r['company_note'],$r['company_nip'],$r['company_address'],$r['base_type'],$r['base_product_id'],$r['base_price'],
            $r['employer_percent'],$r['coupon_amount'],$r['coupon_code'],$r['status'],$r['created_at'],$r['expires_at'],
            $r['used_at'],$r['order_id'],$r['employee_email']
        ], ';');
    }
    fclose($out);
    exit;
}

function lm_ev_handle_repair() {
    if (!current_user_can('manage_options')) wp_die('Brak uprawnień');
    check_admin_referer('lm_ev_repair');

    if (!lm_ev_ensure_table_exists()) wp_die('Nie udało się utworzyć tabeli ewidencji.');

    $cfg   = lm_ev_config();
    $base3 = (int)$cfg['base_3_id'];
    $base5 = (int)$cfg['base_5_id'];

    $args = [
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'posts_per_page' => 2000,
        'fields'         => 'ids',
        's'              => 'MRZ',
    ];
    $q = new WP_Query($args);

    global $wpdb;
    $table = lm_ev_table_name();
    $added = 0;

    if ($q->have_posts()) {
        foreach ($q->posts as $cid) {
            $code = get_the_title($cid);
            if (!is_string($code) || $code === '') continue;

            if (!preg_match('~^(MRZ3|MRZ5)\-(\d{2})\-([A-Z0-9]{6})$~', $code, $m)) continue;

            $base_type = ($m[1] === 'MRZ3') ? '3d' : '5d';
            $percent   = (int)$m[2];

            $base_product_id = ($base_type === '3d') ? $base3 : $base5;
            $base_price      = ($base_type === '3d') ? 600 : 1000;

            $amount_meta = get_post_meta($cid, 'coupon_amount', true);
            $coupon_amount = is_numeric($amount_meta) ? (float)$amount_meta : round($base_price * ($percent / 100), 2);

            $expires_ts = (int)get_post_meta($cid, 'date_expires', true);
            $expires_at = $expires_ts ? date('Y-m-d H:i:s', $expires_ts) : null;

            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE coupon_code = %s LIMIT 1", $code));
            if ($exists) continue;

            $company = (string)get_post_meta($cid, '_lm_ev_company_name', true);
            $nip     = (string)get_post_meta($cid, '_lm_ev_company_nip', true);
            $addr    = (string)get_post_meta($cid, '_lm_ev_company_address', true);
            $note    = (string)get_post_meta($cid, '_lm_ev_company_note', true);

            $ok = $wpdb->insert($table, [
                'company_name'     => $company ?: '—',
                'company_note'     => $note ?: 'repair',
                'company_nip'      => $nip ?: null,
                'company_address'  => $addr ?: null,
                'base_type'        => $base_type,
                'base_product_id'  => $base_product_id,
                'base_price'       => $base_price,
                'employer_percent' => $percent,
                'coupon_amount'    => $coupon_amount,
                'coupon_code'      => $code,
                'created_at'       => current_time('mysql'),
                'expires_at'       => $expires_at,
                'status'           => 'unused',
            ]);

            if ($ok !== false) $added++;
        }
    }

    wp_redirect(add_query_arg(['page'=>'lm-ev-generator','tab'=>'generator','repaired'=>$added], admin_url('admin.php')));
    exit;
}
