<?php
if (!defined('ABSPATH')) exit;

function lm_ev_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'lm_ev_coupons';
}

/**
 * SHOW TABLES LIKE: '_' i '%' są wildcardami.
 * Musimy je escapować, inaczej sprawdzanie tabeli bywa fałszywe.
 */
function lm_ev_table_exists() {
    global $wpdb;
    $table = lm_ev_table_name();
    $like  = str_replace(['\\','_','%'], ['\\\\','\\_','\\%'], $table);
    $sql   = $wpdb->prepare("SHOW TABLES LIKE %s", $like);
    $res   = $wpdb->get_var($sql);
    return ($res === $table);
}

function lm_ev_ensure_table_exists() {
    global $wpdb;
    $table   = lm_ev_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_name VARCHAR(255) NOT NULL,
        company_note VARCHAR(255) NULL,
        company_nip VARCHAR(32) NULL,
        company_address VARCHAR(255) NULL,
        base_type VARCHAR(10) NOT NULL,
        base_product_id BIGINT UNSIGNED NOT NULL,
        base_price DECIMAL(10,2) NOT NULL,
        employer_percent INT NOT NULL,
        coupon_amount DECIMAL(10,2) NOT NULL,
        coupon_code VARCHAR(80) NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'unused',
        used_at DATETIME NULL,
        order_id BIGINT UNSIGNED NULL,
        employee_email VARCHAR(190) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY coupon_code (coupon_code),
        KEY company_name (company_name),
        KEY status (status)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    // dbDelta will CREATE the table if missing and can also bring an existing
    // table schema up to date (e.g. add new columns) as long as the CREATE TABLE
    // statement reflects the desired schema.
    dbDelta($sql);

    // Verify table exists; column upgrades are handled below.
    if (!lm_ev_table_exists()) return false;

    // Hardening: some hostings / older tables may miss newer columns.
    // Ensure NIP/adres exists even if the table was created before v0.1.6.
    lm_ev_db_maybe_add_column('company_nip', 'VARCHAR(32) NULL');
    lm_ev_db_maybe_add_column('company_address', 'VARCHAR(255) NULL');

    return true;
}

/**
 * Add missing column to LM vouchers table (safe idempotent).
 */
function lm_ev_db_maybe_add_column($column, $definition_sql) {
    global $wpdb;
    $table = lm_ev_table_name();
    $column = sanitize_key($column);
    if ($column === '') return;

    // If table doesn't exist, nothing to do here.
    if (!lm_ev_table_exists()) return;

    $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    if ($exists) return;

    // Best-effort ALTER; ignore failure silently (we still guard reads elsewhere).
    // NOTE: $definition_sql is controlled by code, not user input.
    $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition_sql}");
}
