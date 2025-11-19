<?php
if (!defined('ABSPATH')) exit;

add_filter('bubbles_addons_catalog', function ($addons) {

    // 1) Catálogo por defecto (fallback)
    $default_addons = array(
        array(
            'slug'  => 'heavy_pet_hair',
            'name'  => 'Heavy Pet Hair Removal',
            'desc'  => 'Intensive pet-hair removal from seats, carpets, and hard-to-reach areas.',
            'price' => 45,
            'group' => 'interior',
            'badge' => 'Popular',
        ),
        array(
            'slug'  => 'light_pet_hair',
            'name'  => 'Light Pet Hair Removal',
            'desc'  => 'Light pet-hair removal in visible areas.',
            'price' => 25,
            'group' => 'interior',
        ),
        array(
            'slug'  => 'car_baby_seat',
            'name'  => 'Car Baby Seat',
            'desc'  => 'Detailed cleaning of the child car seat (accessible areas).',
            'price' => 25,
            'group' => 'interior',
        ),
    );

    // 2) Intentar leer catálogo personalizado desde la DB
    $stored = get_option('bb_addons_catalog', array());

    if (is_array($stored) && !empty($stored)) {
        // Si existe un catálogo guardado en opciones, usamos ese
        $addons = $stored;
    } else {
        // Si no hay nada guardado, usamos el catálogo por defecto
        $addons = $default_addons;
    }

    return $addons;
});
