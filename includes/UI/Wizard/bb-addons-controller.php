<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Bubbles_Wizard_Addons_Controller' ) ) {

    class Bubbles_Wizard_Addons_Controller {

        /** @var BB_Addons_Service */
        protected $addons_service;

        public function __construct( BB_Addons_Service $addons_service ) {
            $this->addons_service = $addons_service;
        }

        public function handle_post( array $post, array &$errors, array &$state ) {

            $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';
            if ( $step !== 'addons' ) {
                return;
            }

            // Soportar ambos nombres
            $raw = null;
            if ( isset( $post['bb_addons'] ) && is_array( $post['bb_addons'] ) ) {
                $raw = $post['bb_addons'];
            } elseif ( isset( $post['addons'] ) && is_array( $post['addons'] ) ) {
                $raw = $post['addons'];
            }

            // Si no viene nada, no pisar (evita borrar estado)
            if ( $raw === null ) {
                return;
            }

            // IDs seleccionados
            $selected_ids = array_map( function( $v ) {
                $v = is_string( $v ) ? sanitize_text_field( wp_unslash( $v ) ) : $v;
                return (int) $v;
            }, (array) $raw );

            $selected_ids = array_filter( $selected_ids, function( $id ) { return $id > 0; } );
            $selected_ids = array_values( array_unique( $selected_ids ) );

            // Guardar canónico
            $state['addons'] = $selected_ids;
            $state['vehicle_addons_current'] = $selected_ids;

            // ✅ Resolver meta (name/price) desde catálogo activo
            $rows = $this->addons_service->get_active_for_wizard();

            // Index por ID para lookup rápido
            $by_id = array();
            foreach ( (array) $rows as $row ) {
                $id = isset( $row['id'] ) ? (int) $row['id'] : 0;
                if ( $id <= 0 ) continue;
                $by_id[ (string) $id ] = $row;
            }

            $items = array();
            foreach ( $selected_ids as $id ) {
                $key = (string) $id;
                $row = isset( $by_id[ $key ] ) ? $by_id[ $key ] : null;

                if ( is_array( $row ) ) {
                    $items[] = array(
                        'id'               => $id,
                        'name'             => (string) ( $row['name'] ?? '' ),
                        'price'            => (float)  ( $row['price'] ?? 0 ),
                        'duration_minutes' => (int)    ( $row['duration_minutes'] ?? 0 ),
                        'description'      => (string) ( $row['description'] ?? '' ),
                    );
                } else {
                    // fallback mínimo si no aparece en catálogo
                    $items[] = array(
                        'id'    => $id,
                        'name'  => '',
                        'price' => 0,
                    );
                }
            }

            $state['addons_items'] = $items;
        }

        public function get_view_data( array $state, array $errors = array() ): array {

            $rows = $this->addons_service->get_active_for_wizard();

            $addons = array();
            foreach ( (array) $rows as $row ) {

                $id   = isset( $row['id'] ) ? (int) $row['id'] : 0;
                $name = isset( $row['name'] ) ? (string) $row['name'] : '';

                if ( $id <= 0 || $name === '' ) continue;

                $addons[] = array(
                    'id'          => $id,
                    'label'       => $name,
                    'description' => (string) ( $row['description'] ?? '' ),
                    'price'       => (float)  ( $row['price'] ?? 0 ),
                );
            }

            $selected_addons = isset( $state['addons'] ) && is_array( $state['addons'] )
                ? array_values( array_unique( array_map( 'intval', $state['addons'] ) ) )
                : array();

            return array(
                'addons'          => $addons,
                'selected_addons' => $selected_addons,
                'errors'          => $errors,
            );
        }
    }
}
