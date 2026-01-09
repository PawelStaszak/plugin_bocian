<?php
if (!defined('ABSPATH')) exit;

/** ====== KONFIG ====== */
function lm_ev_config() {
    return [
        'base_3_id' => 317, // 3 dni (600)
        'base_5_id' => 309, // 5 dni (1000)

        // dodatki
        'addon_weekend_id' => 378, // odblokowuje weekendy w wyborze dat

        // pozostałe dodatki
        'addons' => [
            379, // Zmiana z 3 dni na 5 dni (ma znikać gdy baza = 5d)
        ],

        // opłaty obligatoryjne
        'fee_service'   => 200,
        'fee_climate_3' => 9,
        'fee_climate_5' => 15,
        'fee_parking_3' => 30,
        'fee_parking_5' => 50,

        // ID upgrade (dla logiki ukrywania/blokowania)
        'upgrade_3_to_5_id' => 379,

        // “produkt” wirtualny do faktury (na później)
        'invoice_item_name' => 'Vouchery pracownicze (dopłata pracodawcy)',
    ];
}

/** nonce do AJAX */
function lm_ev_ajax_nonce_action() {
    return 'lm_ev_ajax';
}

/** seller / dane firmy */
function lm_ev_seller_company_data() {
    return [
        'krs'   => '0000608521',
        'nip'   => '9552391813',
        'regon' => '363982602',
        'name'  => 'Linea Mare (spółka komandytowa)',
        'addr'  => 'Pomorska 112, 70-812 Szczecin, Polska',
        'legal' => 'SPÓŁKA KOMANDYTOWA',
        'reg_date' => '2016-03-17',
    ];
}
