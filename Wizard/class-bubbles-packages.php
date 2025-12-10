<?php
if (!defined('ABSPATH')) exit;

class Bubbles_Packages { 
    /**
     * Definición extendida de cada paquete.
     * NO DEFINE PRECIOS. Eso es trabajo de BB_Pricing.
     */
    public static function get_all() {
        $meta = [
            'basic' => [
                'id'             => 'basic',
                'name'           => 'Basic Wash',
                'description'    => 'Exterior wash and light interior vacuum.',
                'duration_hours' => 1.5,
                'icon'           => '🚗',
                'sort_order'     => 10,
                'is_active'      => true,
            ],

            'standard' => [
                'id'             => 'standard',
                'name'           => 'Plus Detail',
                'description'    => 'Interior detail + wax protection.',
                'duration_hours' => 2,
                'icon'           => '✨',
                'sort_order'     => 20,
                'is_active'      => true,
            ],

            'premium' => [
                'id'             => 'premium',
                'name'           => 'Pro Deep Detail',
                'description'    => 'Deep interior detail + polish.',
                'duration_hours' => 3,
                'icon'           => '🏆',
                'sort_order'     => 30,
                'is_active'      => true,
            ],
        ];

        return apply_filters('bubbles_package_metadata', $meta);
    }

    public static function get($id) {
        $all = self::get_all();
        return $all[$id] ?? null;
    }

    public static function get_duration($id) {
        $m = self::get($id);
        return $m['duration_hours'] ?? 2;
    }
}
