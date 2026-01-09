<?php
/**
 * Plugin Name: LM Employee Voucher Page (MVP)
 * Description: Kod -> baza -> wybór dat -> dodatki -> podsumowanie -> checkout + generator + ewidencja
 * Version: 0.2.1
 */

if (!defined('ABSPATH')) exit;

define('LM_EV_PATH', plugin_dir_path(__FILE__));
define('LM_EV_URL',  plugin_dir_url(__FILE__));

require_once LM_EV_PATH . 'includes/config.php';
require_once LM_EV_PATH . 'includes/db.php';
require_once LM_EV_PATH . 'includes/cart.php';
require_once LM_EV_PATH . 'includes/dates.php';
require_once LM_EV_PATH . 'includes/fees.php';
require_once LM_EV_PATH . 'includes/ajax.php';
require_once LM_EV_PATH . 'includes/shortcode.php';
require_once LM_EV_PATH . 'includes/admin.php';
require_once LM_EV_PATH . 'includes/ledger.php';

register_activation_hook(__FILE__, function(){
    lm_ev_ensure_table_exists();
});
