<?php
/**
 * Pricing rules (editable)
 * Devuelve un array de reglas para BB_Pricing.
 * Cambia estos valores sin tocar la lógica del plugin.
 */

if (!defined('ABSPATH')) exit;

return [
    // Precio base por CLASE y PAQUETE
    // Puedes agregar clases (ej. 'luxury', 'exotic') o paquetes extra.
    'base' => [
        'compact' =>   ['basic' => 99,  'standard' => 149, 'premium' => 199],
        'midsize' =>   ['basic' => 109, 'standard' => 159, 'premium' => 219],
        'suv'     =>   ['basic' => 129, 'standard' => 189, 'premium' => 259],
        'truck'   =>   ['basic' => 139, 'standard' => 199, 'premium' => 279],
        'van'     =>   ['basic' => 139, 'standard' => 199, 'premium' => 279],
        'luxury'  =>   ['basic' => 169, 'standard' => 239, 'premium' => 329],
    ],

    // Multiplicadores por marca (opcionales)
    // Ej: marcas premium/luxury pueden subir 10–20%
    'brand_multipliers' => [
        // premium
        'bmw' => 1.12, 'mercedes-benz' => 1.12, 'audi' => 1.10, 'lexus' => 1.10, 'infiniti' => 1.08, 'acura' => 1.06,
        'porsche' => 1.20, 'land rover' => 1.18, 'jaguar' => 1.15, 'tesla' => 1.12,

        // mainstream (1.00 = sin cambios, puedes omitirlos)
        'toyota' => 1.00, 'honda' => 1.00, 'ford' => 1.00, 'chevrolet' => 1.00, 'nissan' => 1.00, 'hyundai' => 1.00, 'kia' => 1.00,
    ],

    // Ajustes por año (opcionales)
    // Puedes usar “sumas” o “multiplicadores” por rangos.
    'year_adjustments' => [
        // Sumas fijas por rango
        'add' => [
            '>=2023' => 15,     // modelos recientes: +$15
            '2008-2014' => 10,  // más detalle: +$10
            '<=2007' => 20,     // autos antiguos: +$20
        ],
        // Multiplicadores por rango (aplican después de base)
        'mul' => [
            '>=2024' => 1.04,   // 4% extra a lo nuevo
            '<=2005' => 1.05,   // 5% extra a muy antiguos
        ],
    ],

    // Sobrescrituras directas (si el modelo contiene estas palabras)
    // Útil para pick-up grandes, vans comerciales, etc.
    'model_overrides' => [
        'F-250' => 'truck',
        'F-350' => 'truck',
        'Silverado 2500' => 'truck',
        'Ram 2500' => 'truck',
        'Econoline' => 'van',
        'Sprinter' => 'van',
        'Transit' => 'van',
        'Escalade' => 'luxury',
        'Range Rover' => 'luxury',
        'G-Class' => 'luxury',
        'Model X' => 'suv',
        'Model Y' => 'suv',
    ],

    // Paquetes visibles (etiquetas)
    'packages_labels' => [
        'basic'    => 'Basic',
        'standard' => 'Standard',
        'premium'  => 'Premium',
    ],
];
