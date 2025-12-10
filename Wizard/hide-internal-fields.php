<?php
// Ocultar campos internos (solo para el cliente: checkout, emails, My Account)
add_filter('woocommerce_order_item_get_formatted_meta_data', function($formatted_meta, $item) {

    // En el admin NO ocultamos nada, para que puedas ver toda la info completa
    if (is_admin() && ! wp_doing_ajax()) {
        return $formatted_meta;
    }

    // Claves que NO queremos que el cliente vea
    $hidden_keys = array(
        'Bb place id',
        'Bb address street',
        'Bb address city',
        'Bb address state',
        'Bb address zip',
        'Bb address lat',
        'Bb address lng',
    );

    foreach ($formatted_meta as $id => $meta) {
        if (in_array($meta->key, $hidden_keys, true)) {
            unset($formatted_meta[$id]);
        }
    }

    return $formatted_meta;
}, 10, 2);
