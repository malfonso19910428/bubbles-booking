<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BB_Services_Controller' ) ) {

class BB_Services_Controller {

    /** @var BB_Services_Service */
    protected BB_Services_Service $services_service;

    /** @var BB_Pricing_Engine */
    protected BB_Pricing_Engine $pricing_engine;

    public function __construct(
        BB_Services_Service $services_service,
        BB_Pricing_Engine $pricing_engine
    ) {
        $this->services_service = $services_service;
        $this->pricing_engine   = $pricing_engine;
    }

    /**
     * STEP: package
     * Guarda paquete y recalcula precio usando job_target_id (CANÓNICO).
     */
    public function handle_post( array $post, array &$errors, array &$state ) : void {

        // Solo si estamos en este step
        $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';
        if ( $step !== 'package' ) return;

        $selected = isset( $post['bb_package'] )
            ? sanitize_text_field( wp_unslash( $post['bb_package'] ) )
            : '';

        if ( isset( $post['bb_continue'] ) && $selected === '' ) {
            $errors[] = __( 'Please select a package.', 'bubbles-booking' );
            return;
        }

        $selected_id = (int) $selected;

        // keys compat
        $state['selected_package']   = $selected_id;
        $state['bb_package']         = $selected_id;
        $state['vehicle_bb_package'] = $selected_id;

        // ✅ Guardar paquete completo en state['package']
        if ( $selected !== '' ) {

            $services = $this->services_service->get_active_for_wizard();

            $found = null;
            foreach ( (array) $services as $svc ) {
                if ( (string) ( $svc['id'] ?? '' ) === (string) $selected ) {
                    $found = $svc;
                    break;
                }
            }

            if ( is_array( $found ) ) {

                // ===============================
                // ✅ job_target_id CANÓNICO
                // ===============================
                $job_target_id = $this->extract_job_target_id( $post, $state );

                $service_id = (int) ( $found['id'] ?? 0 );
                $base_price = isset( $found['price'] ) ? (float) $found['price'] : 0.0;

                // ✅ Precio final calculado por engine usando job_target_id
                $final_price = $this->pricing_engine->price_for_service_and_job_target(
                    $service_id,
                    $base_price,
                    (int) $job_target_id
                );

                // (debug-friendly) vehicle_type no lo resolvemos aquí; si lo quieres mostrar, que lo haga el engine y lo exponga.
                $vehicle_type = '';
                if ( isset( $state['vehicle']['type'] ) ) {
                    $vehicle_type = sanitize_key( (string) $state['vehicle']['type'] );
                } elseif ( isset( $state['vehicle_form']['type'] ) ) {
                    $vehicle_type = sanitize_key( (string) $state['vehicle_form']['type'] );
                }

                $state['package'] = array(
                    'id'               => (string) ( $found['id'] ?? $selected ),
                    'name'             => (string) ( $found['name'] ?? '' ),
                    'price'            => (float)  $final_price,
                    'base_price'       => (float)  $base_price,
                    'job_target_id'    => (int)    $job_target_id,
                    'vehicle_type'     => (string) $vehicle_type, // opcional/debug
                    'duration_minutes' => (int)    ( $found['duration_minutes'] ?? 0 ),
                    'description'      => (string) ( $found['description'] ?? '' ),
                );

            } else {
                // fallback mínimo
                $state['package'] = array(
                    'id'    => $selected,
                    'name'  => '',
                    'price' => 0,
                );
            }
        }
    }

    public function get_view_data( array $state, array $errors = array() ) : array {

        $vehicle = array(
            'year'          => '',
            'make'          => '',
            'model'         => '',
            'type'          => '',
            'label'         => '',
            'job_target_id' => 0, // ✅ CANÓNICO
        );

        if ( isset( $state['vehicle'] ) && is_array( $state['vehicle'] ) ) {
            $vehicle = array_merge( $vehicle, $state['vehicle'] );
        }

        // fallback: si el job_target_id está arriba en state
        if ( empty( $vehicle['job_target_id'] ) && isset( $state['job_target_id'] ) ) {
            $vehicle['job_target_id'] = (int) $state['job_target_id'];
        }

        // Calcula pricing usando job_target_id
        list( $pricing_packages, $meta_all, $ctx ) = $this->build_pricing_for_vehicle( $vehicle );

        $selected_pkg = '';
        if ( isset( $state['bb_package'] ) ) $selected_pkg = (string) $state['bb_package'];
        if ( $selected_pkg === '' && isset( $state['selected_package'] ) ) $selected_pkg = (string) $state['selected_package'];

        return array(
            'pricing_packages' => $pricing_packages,
            'meta_all'         => $meta_all,
            'selected_pkg'     => $selected_pkg,
            'errors'           => $errors,

            // ctx útil para template si quieres mostrar contexto
            'vehicle_type'     => $ctx['vehicle_type'],
            'vehicle_label'    => $ctx['vehicle_label'],
            'job_target_id'    => $ctx['job_target_id'],
        );
    }

    /**
     * Retorna:
     *  - pricing_packages
     *  - meta_all
     *  - ctx (vehicle_type, vehicle_label, job_target_id)
     */
    protected function build_pricing_for_vehicle( array $vehicle ) : array {

        $services = $this->services_service->get_active_for_wizard();

        // context
        $vehicle_type   = isset( $vehicle['type'] ) ? sanitize_key( (string) $vehicle['type'] ) : '';
        $vehicle_label  = isset( $vehicle['label'] ) ? (string) $vehicle['label'] : '';
        $job_target_id  = isset( $vehicle['job_target_id'] ) ? (int) $vehicle['job_target_id'] : 0;
error_log('[BB_PACKAGE] vehicle ctx = ' . wp_json_encode(array(
  'job_target_id' => $job_target_id,
  'vehicle_type'  => $vehicle_type,
  'vehicle_label' => $vehicle_label,
)));

        // (legacy) si aún existe tu función vieja, la dejamos como fallback opcional
        $legacy_by_id = array();
        if ( function_exists( 'bb_custom_price_quote' ) ) {
            $legacy_list = bb_custom_price_quote( $vehicle );
            if ( is_array( $legacy_list ) ) {
                foreach ( $legacy_list as $item ) {
                    if ( empty( $item['id'] ) ) continue;
                    $legacy_by_id[ (string) $item['id'] ] = $item;
                }
            }
        }

        // ✅ Pre-cálculo por job_target_id (1 sola vez)
        $prices_by_service_id = array();
        if ( $job_target_id > 0 ) {
            $prices_by_service_id = $this->pricing_engine->prices_for_job_target_id( $job_target_id );
           error_log('[BB_PACKAGE] engine prices count=' . (is_array($prices_by_service_id) ? count($prices_by_service_id) : -1));
if ( is_array($prices_by_service_id) ) {
  // muestra 10 primeros para ver ids/valores
  error_log('[BB_PACKAGE] engine prices sample=' . wp_json_encode(array_slice($prices_by_service_id, 0, 10, true)));
}

            if ( ! is_array( $prices_by_service_id ) ) {
                $prices_by_service_id = array();
            }
        }

        $pricing_packages = array();
        $meta_all         = array();

        foreach ( (array) $services as $svc ) {

            $id = isset( $svc['id'] ) ? (string) $svc['id'] : '';
            if ( $id === '' ) continue;

            $service_id     = (int) $svc['id'];
            $base_price     = isset( $svc['price'] ) ? (float) $svc['price'] : 0.0;
            $duration_min   = isset( $svc['duration_minutes'] ) ? (int) $svc['duration_minutes'] : 0;
            $duration_hours = $duration_min > 0 ? ( $duration_min / 60.0 ) : 0.0;

            // ✅ Precio final: primero engine (si hay job_target), si no, base
            $final_price = $base_price;

            if ( $job_target_id > 0 && isset( $prices_by_service_id[ $service_id ] ) ) {
                $final_price = (float) $prices_by_service_id[ $service_id ];
            }

            // ===== Legacy fallback (solo si NO hay job_target válido) =====
            $legacy         = isset( $legacy_by_id[ $id ] ) ? $legacy_by_id[ $id ] : null;
            $legacy_price   = ( $legacy && isset( $legacy['price'] ) ) ? (float) $legacy['price'] : null;
            $class_detected = ( $legacy && isset( $legacy['class_detected'] ) ) ? (string) $legacy['class_detected'] : '';

            if ( $legacy_price !== null && (int) $job_target_id <= 0 ) {
                $final_price = $legacy_price;
            }
error_log('[BB_PACKAGE] svc priced = ' . wp_json_encode(array(
  'service_id'   => $service_id,
  'base_price'   => $base_price,
  'final_price'  => $final_price,
  'job_target_id'=> $job_target_id,
  'vehicle_type' => $vehicle_type,
  'engine_hit'   => ( $job_target_id > 0 && isset($prices_by_service_id[$service_id]) ) ? 1 : 0,
)));

            $pricing_packages[] = array(
                'id'             => $id,
                'price'          => $final_price,
                'label'          => $svc['name'] ?? '',
                'class_detected' => $class_detected,

                // debug opcional
                'base_price'     => $base_price,
                'job_target_id'  => $job_target_id,
                'vehicle_type'   => $vehicle_type,
            );

            $meta_all[ $id ] = array(
                'name'           => $svc['name']        ?? '',
                'description'    => $svc['description'] ?? '',
                'duration_hours' => $duration_hours > 0 ? $duration_hours : 2,
                'icon'           => '',
            );
        }

        $ctx = array(
            'vehicle_type'  => $vehicle_type,
            'vehicle_label' => $vehicle_label,
            'job_target_id' => $job_target_id,
        );

        return array( $pricing_packages, $meta_all, $ctx );
    }

    /**
     * Extrae job_target_id desde post/state con fallbacks.
     */
    protected function extract_job_target_id( array $post, array $state ) : int {

        // 1) POST directo (si el form lo manda)
        if ( isset( $post['job_target_id'] ) ) {
            return (int) sanitize_text_field( wp_unslash( $post['job_target_id'] ) );
        }
        if ( isset( $post['bb_job_target_id'] ) ) {
            return (int) sanitize_text_field( wp_unslash( $post['bb_job_target_id'] ) );
        }

        // 2) State: vehicle
        if ( isset( $state['vehicle']['job_target_id'] ) ) {
            return (int) $state['vehicle']['job_target_id'];
        }

        // 3) State top-level
        if ( isset( $state['job_target_id'] ) ) {
            return (int) $state['job_target_id'];
        }

        return 0;
    }
}

} // class_exists
