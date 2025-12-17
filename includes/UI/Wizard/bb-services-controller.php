<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BB_Services_Controller' ) ) {

    class BB_Services_Controller {

        /** @var BB_Services_Service */
        protected $services_service;

        public function __construct( BB_Services_Service $services_service ) {
            $this->services_service = $services_service;
        }

        public function handle_post( array $post, array &$errors, array &$state ) {

            // Solo si estamos en este step
            $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';
            if ( $step !== 'package' ) {
                return;
            }

            $selected = isset( $post['bb_package'] )
                ? sanitize_text_field( wp_unslash( $post['bb_package'] ) )
                : '';

            if ( isset( $post['bb_continue'] ) && $selected === '' ) {
                $errors[] = __( 'Please select a package.', 'bubbles-booking' );
                return;
            }

           $selected_id = (int) $selected;

$state['selected_package']   = $selected_id; // compat
$state['bb_package']         = $selected_id; // key principal
$state['vehicle_bb_package'] = $selected_id; // compat

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
                    $state['package'] = array(
                        'id'               => (string) ( $found['id'] ?? $selected ),
                        'name'             => (string) ( $found['name'] ?? '' ),
                        'price'            => (float)  ( $found['price'] ?? 0 ),
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

        public function get_view_data( array $state, array $errors = array() ) {

            $vehicle = array( 'year'=>'', 'make'=>'', 'model'=>'' );
            if ( isset( $state['vehicle'] ) && is_array( $state['vehicle'] ) ) {
                $vehicle = array_merge( $vehicle, $state['vehicle'] );
            }

            list( $pricing_packages, $meta_all ) = $this->build_pricing_for_vehicle( $vehicle );

            // ✅ opcional pero útil: guardar para Job_Builder fallback si lo necesitas
            // (si no quieres esto, lo puedes quitar)
            // $state['pricing_packages'] = $pricing_packages; // NO por valor aquí; state entra como copia
            // $state['meta_all'] = $meta_all;

            $selected_pkg = '';
            if ( isset( $state['bb_package'] ) ) $selected_pkg = (string) $state['bb_package'];
            if ( $selected_pkg === '' && isset( $state['selected_package'] ) ) $selected_pkg = (string) $state['selected_package'];

            return array(
                'pricing_packages' => $pricing_packages,
                'meta_all'         => $meta_all,
                'selected_pkg'     => $selected_pkg,
                'errors'           => $errors,
            );
        }

        protected function build_pricing_for_vehicle( array $vehicle ) {

            $services = $this->services_service->get_active_for_wizard();

            $legacy_by_id = array();
            if ( function_exists( 'bb_custom_price_quote' ) ) {
                $legacy_list = bb_custom_price_quote( $vehicle );
                if ( is_array( $legacy_list ) ) {
                    foreach ( $legacy_list as $item ) {
                        if ( empty( $item['id'] ) ) continue;
                        $legacy_by_id[ $item['id'] ] = $item;
                    }
                }
            }

            $pricing_packages = array();
            $meta_all         = array();

            foreach ( (array) $services as $svc ) {

                $id = isset( $svc['id'] ) ? (string) $svc['id'] : '';
                if ( $id === '' ) continue;

                $base_price       = isset( $svc['price'] ) ? (float) $svc['price'] : 0.0;
                $duration_minutes = isset( $svc['duration_minutes'] ) ? (int) $svc['duration_minutes'] : 0;
                $duration_hours   = $duration_minutes > 0 ? ( $duration_minutes / 60.0 ) : 0;

                $legacy         = isset( $legacy_by_id[ $id ] ) ? $legacy_by_id[ $id ] : null;
                $legacy_price   = ( $legacy && isset( $legacy['price'] ) ) ? (float) $legacy['price'] : null;
                $class_detected = ( $legacy && isset( $legacy['class_detected'] ) ) ? $legacy['class_detected'] : '';

                $final_price = ( $legacy_price !== null ) ? $legacy_price : $base_price;

                $pricing_packages[] = array(
                    'id'             => $id,
                    'price'          => $final_price,
                    'label'          => $svc['name'] ?? '',
                    'class_detected' => $class_detected,
                );

                $meta_all[ $id ] = array(
                    'name'           => $svc['name']        ?? '',
                    'description'    => $svc['description'] ?? '',
                    'duration_hours' => $duration_hours > 0 ? $duration_hours : 2,
                    'icon'           => '',
                );
            }

            return array( $pricing_packages, $meta_all );
        }
    }
}
