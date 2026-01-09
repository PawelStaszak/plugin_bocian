<?php
if (!defined('ABSPATH')) exit;

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

            $ok = $wpdb->insert($table, [
                'company_name'     => '—',
                'company_note'     => 'repair',
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

    wp_redirect(add_query_arg(['page'=>'lm-ev-generator','repaired'=>$added], admin_url('admin.php')));
    exit;
}
