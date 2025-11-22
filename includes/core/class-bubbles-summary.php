<?php
if (!defined('ABSPATH')) exit;

/**
 * Bubbles_Summary
 *
 * Clase pequeña que toma el $state del wizard y prepara
 * TODO lo necesario para el resumen lateral:
 *
 *  - Lista de vehículos (incluyendo el vehículo actual)
 *  - Paquete y add-ons por vehículo
 *  - Subtotal por vehículo
 *  - Total general
 *  - Dirección + fecha/hora
 */
class Bubbles_Summary {

    /** @var array */
    protected $state = array();

    /** @var array lista base de vehículos (year/make/model/package/addons) */
    protected $vehicles = array();

    /** @var array addons catalog (slug => [name, price]) */
    protected $addons_price_map = array();

    /** @var array vehículos enriquecidos con precios, labels, etc. */
    protected $detailed_vehicles = array();

    /** @var float total general */
    protected $grand_total = 0.0;

    public function __construct($state) {
        $this->state = is_array($state) ? $state : array();

        $this->build_base_vehicles();
        $this->build_addons_price_map();
        $this->build_detailed_vehicles();
    }

    /**
     * Reconstruye lista base de vehículos desde $state:
     *  - $state['vehicles'] viene de bb_vehicles (JSON)
     *  - $state['vehicle'] es el vehículo actual en el formulario
     */
    protected function build_base_vehicles() {
        $vehicles = array();

        // 1) Vehículos ya confirmados en bb_vehicles (JSON)
        if (!empty($this->state['vehicles']) && is_array($this->state['vehicles'])) {
            foreach ($this->state['vehicles'] as $v) {
                if (!is_array($v)) {
                    continue;
                }

                $vehicles[] = array(
                    'year'    => isset($v['year'])    ? (string) $v['year']    : '',
                    'make'    => isset($v['make'])    ? (string) $v['make']    : '',
                    'model'   => isset($v['model'])   ? (string) $v['model']   : '',
                    'package' => isset($v['package']) ? (string) $v['package'] : '',
                    'addons'  => (isset($v['addons']) && is_array($v['addons']))
                        ? array_values($v['addons'])
                        : array(),
                );
            }
        }

        // 2) Vehículo actual (el del formulario)
        $current = isset($this->state['vehicle']) && is_array($this->state['vehicle'])
            ? $this->state['vehicle']
            : array();

        $has_current_vehicle =
            !empty($current['year']) ||
            !empty($current['make']) ||
            !empty($current['model']);

        if ($has_current_vehicle) {
            $current_entry = array(
                'year'    => isset($current['year'])    ? (string) $current['year']    : '',
                'make'    => isset($current['make'])    ? (string) $current['make']    : '',
                'model'   => isset($current['model'])   ? (string) $current['model']   : '',
                // si por alguna razón no estuviera en vehicle, toma package/addons del state "global"
                'package' => isset($current['package'])
                    ? (string) $current['package']
                    : (isset($this->state['package']) ? (string) $this->state['package'] : ''),
                'addons'  => (isset($current['addons']) && is_array($current['addons']))
                    ? array_values($current['addons'])
                    : (isset($this->state['addons']) && is_array($this->state['addons'])
                        ? array_values($this->state['addons'])
                        : array()),
            );

            // evitar duplicados exactos
            $already = false;
            foreach ($vehicles as $v) {
                if (
                    $v['year']    === $current_entry['year'] &&
                    $v['make']    === $current_entry['make'] &&
                    $v['model']   === $current_entry['model'] &&
                    $v['package'] === $current_entry['package'] &&
                    implode(',', $v['addons']) === implode(',', $current_entry['addons'])
                ) {
                    $already = true;
                    break;
                }
            }

            if (!$already) {
                $vehicles[] = $current_entry;
            }
        }

        $this->vehicles = $vehicles;
    }

    /**
     * Construye mapa de precios de add-ons: slug => [name, price]
     */
    protected function build_addons_price_map() {
        $catalog = apply_filters('bubbles_addons_catalog', array(
            array(
                'slug'  => 'heavy_pet_hair',
                'name'  => 'Heavy Pet Hair Removal',
                'desc'  => 'Intensive pet-hair removal from seats, carpets, and hard-to-reach areas.',
                'price' => 45,
            ),
            array(
                'slug'  => 'light_pet_hair',
                'name'  => 'Light Pet Hair Removal',
                'desc'  => 'Light pet-hair removal in visible areas.',
                'price' => 25,
            ),
            array(
                'slug'  => 'car_baby_seat',
                'name'  => 'Car Baby Seat',
                'desc'  => 'Detailed cleaning of the child car seat (accessible areas).',
                'price' => 25,
            ),
        ));

        $map = array();
        if (is_array($catalog)) {
            foreach ($catalog as $item) {
                if (empty($item['slug'])) {
                    continue;
                }
                $slug = (string) $item['slug'];
                $map[$slug] = array(
                    'name'  => isset($item['name'])  ? (string) $item['name']  : $slug,
                    'price' => isset($item['price']) ? (float)  $item['price'] : 0.0,
                );
            }
        }

        $this->addons_price_map = $map;
    }

    /**
     * Para cada vehículo base, calcula:
     *  - título
     *  - paquete (label + price)
     *  - add-ons (con nombre + precio)
     *  - subtotal
     * y acumula el total general.
     */
    protected function build_detailed_vehicles() {
        $this->detailed_vehicles = array();
        $this->grand_total       = 0.0;

        foreach ($this->vehicles as $v) {
            $year    = isset($v['year'])    ? (string) $v['year']    : '';
            $make    = isset($v['make'])    ? (string) $v['make']    : '';
            $model   = isset($v['model'])   ? (string) $v['model']   : '';
            $pkg_id  = isset($v['package']) ? (string) $v['package'] : '';
            $addons  = (isset($v['addons']) && is_array($v['addons'])) ? $v['addons'] : array();

            $title = trim($year . ' ' . $make . ' ' . $model);

            // --- Paquete ---
            $pkg_label = $pkg_id;
            $pkg_price = 0.0;

            if ($pkg_id && function_exists('bb_custom_price_quote')) {
                $vehicle_for_price = array(
                    'year'  => $year,
                    'make'  => $make,
                    'model' => $model,
                );

                $quote = bb_custom_price_quote($vehicle_for_price);
                if (is_array($quote)) {
                    foreach ($quote as $p) {
                        if (!empty($p['id']) && (string)$p['id'] === $pkg_id) {
                            $pkg_label = !empty($p['label']) ? (string) $p['label'] : $pkg_id;
                            if (isset($p['price'])) {
                                $pkg_price = (float) $p['price'];
                            }
                            break;
                        }
                    }
                }
            }

            // --- Add-ons de este vehículo ---
            $addons_detail = array();
            $addons_total  = 0.0;

            foreach ($addons as $slug) {
                $slug = (string) $slug;
                if (!isset($this->addons_price_map[$slug])) {
                    continue;
                }
                $info = $this->addons_price_map[$slug];
                $addons_detail[] = array(
                    'slug'  => $slug,
                    'name'  => $info['name'],
                    'price' => $info['price'],
                );
                $addons_total += (float) $info['price'];
            }

            // --- Subtotal por vehículo ---
            $subtotal = $pkg_price + $addons_total;
            $this->grand_total += $subtotal;

            $this->detailed_vehicles[] = array(
                'title'          => $title,
                'year'           => $year,
                'make'           => $make,
                'model'          => $model,
                'package_id'     => $pkg_id,
                'package_label'  => $pkg_label,
                'package_price'  => $pkg_price,
                'addons'         => $addons,
                'addons_detail'  => $addons_detail,
                'addons_total'   => $addons_total,
                'subtotal'       => $subtotal,
            );
        }
    }

    /* --------------------- Getters usados en la plantilla --------------------- */

    /** Lista BASE (por si la necesitas en otro lado) */
    public function get_vehicles() {
        return $this->vehicles;
    }

    /** Lista DETALLADA para el resumen */
    public function get_detailed_vehicles() {
        return $this->detailed_vehicles;
    }

    /** Totales (por ahora solo el general) */
    public function get_totals() {
        return array(
            'grand_total' => $this->grand_total,
        );
    }

    public function get_address() {
        return isset($this->state['address']) && is_array($this->state['address'])
            ? $this->state['address']
            : array();
    }

    public function get_date() {
        return isset($this->state['date']) ? (string) $this->state['date'] : '';
    }

    public function get_time() {
        return isset($this->state['time']) ? (string) $this->state['time'] : '';
    }
}
