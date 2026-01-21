<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BB_Vehicle_Controller' ) ) {

class BB_Vehicle_Controller {

    public function __construct() {}

    /**
     * Devuelve lista de tipos desde la BD (bb_job_targets) para industry=auto.
     * Cada item: [id, slug, label]
     */
    private function get_vehicle_types_db(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'bb_job_targets';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, slug, label
                 FROM {$table}
                 WHERE industry = %s AND is_active = 1
                 ORDER BY sort_order ASC, label ASC",
                'auto'
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) return array();

        $out = array();
        foreach ( $rows as $r ) {
            $id    = (int) ( $r['id'] ?? 0 );
            $slug  = sanitize_key( (string) ( $r['slug'] ?? '' ) );
            $label = (string) ( $r['label'] ?? $slug );

            if ( $id <= 0 || $slug === '' ) continue;

            $out[] = array(
                'id'    => $id,
                'slug'  => $slug,
                'label' => $label,
            );
        }

        return $out;
    }

    /**
     * Busca un job target activo por slug e industry.
     * Retorna array con id/slug/label o null.
     */
    private function find_active_job_target_by_slug( string $industry, string $slug ): ?array {
        global $wpdb;

        $industry = sanitize_key( $industry );
        $slug     = sanitize_key( $slug );

        if ( $industry === '' || $slug === '' ) return null;

        $table = $wpdb->prefix . 'bb_job_targets';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, industry, slug, label, is_active
                 FROM {$table}
                 WHERE industry = %s AND slug = %s AND is_active = 1
                 LIMIT 1",
                $industry,
                $slug
            ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) return null;

        $id = (int) ( $row['id'] ?? 0 );
        if ( $id <= 0 ) return null;

        return array(
            'id'       => $id,
            'industry' => (string) ( $row['industry'] ?? $industry ),
            'slug'     => (string) ( $row['slug'] ?? $slug ),
            'label'    => (string) ( $row['label'] ?? $slug ),
        );
    }

    /**
     * Busca un job target activo por ID.
     * Retorna array o null.
     */
    private function find_active_job_target_by_id( int $id ): ?array {
        global $wpdb;

        if ( $id <= 0 ) return null;

        $table = $wpdb->prefix . 'bb_job_targets';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, industry, slug, label, is_active
                 FROM {$table}
                 WHERE id = %d AND is_active = 1
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if ( ! is_array( $row ) ) return null;

        $rid = (int) ( $row['id'] ?? 0 );
        if ( $rid <= 0 ) return null;

        return array(
            'id'       => $rid,
            'industry' => (string) ( $row['industry'] ?? 'auto' ),
            'slug'     => (string) ( $row['slug'] ?? '' ),
            'label'    => (string) ( $row['label'] ?? '' ),
        );
    }

    public function handle_post( array $post, array &$errors, array &$state ) : void {

        $step = isset( $post['bb_step'] ) ? sanitize_key( wp_unslash( $post['bb_step'] ) ) : '';

        $state['_dbg_vehicle'] = array(
            'bb_step'        => $step,
            'has_continue'   => isset( $post['bb_continue'] ) ? 1 : 0,
            'has_submit_btn' => isset( $post['bb_vehicle_submit'] ) ? 1 : 0,
            'keys'           => array_keys( $post ),
        );

        if ( $step !== 'vehicle' ) return;

        $submit = ( isset( $post['bb_continue'] ) || isset( $post['bb_vehicle_submit'] ) );
        if ( ! $submit ) return;

        // Nonce
        if ( isset( $post['bb_vehicle_nonce'] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $post['bb_vehicle_nonce'] ) );
            if ( ! wp_verify_nonce( $nonce, 'bb_vehicle_form' ) ) {
                $errors[] = __( 'Security check failed. Please try again.', 'bubbles-booking' );
                return;
            }
        }

        // Fields
        $year  = isset( $post['car_year'] )  ? sanitize_text_field( wp_unslash( $post['car_year'] ) )  : '';
        $make  = isset( $post['car_make'] )  ? sanitize_text_field( wp_unslash( $post['car_make'] ) )  : '';
        $model = isset( $post['car_model'] ) ? sanitize_text_field( wp_unslash( $post['car_model'] ) ) : '';
        $color = isset( $post['car_color'] ) ? sanitize_text_field( wp_unslash( $post['car_color'] ) ) : '';

        // ✅ Vehicle type slug (viene del select poblado desde BD)
        $type = isset( $post['car_type'] ) ? sanitize_key( wp_unslash( $post['car_type'] ) ) : '';

        // ✅ job_target_id hidden (si JS lo resolvió)
        $job_target_id = isset( $post['job_target_id'] ) ? (int) $post['job_target_id'] : 0;
        if ( $job_target_id < 0 ) $job_target_id = 0;

        // Debug input
        $state['_dbg_vehicle_in'] = array(
            'year'          => $year,
            'make'          => $make,
            'model'         => $model,
            'color'         => $color,
            'type'          => $type,
            'job_target_id' => $job_target_id,
        );

        /**
         * ✅ Validación/Normalización de type y job_target_id
         *
         * Regla:
         * - Si viene type, debe existir activo en BD.
         * - Si viene job_target_id, debe existir activo.
         * - Si falta job_target_id pero hay type, lo reconstruimos por slug.
         * - Si type falta pero hay job_target_id, reconstruimos type por ID.
         */

        // 1) Si hay type, validar que exista activo
        $jt_from_slug = null;
        if ( $type !== '' ) {
            $jt_from_slug = $this->find_active_job_target_by_slug( 'auto', $type );
            if ( ! $jt_from_slug ) {
                // type inválido
                $type = '';
            }
        }

        // 2) Si hay job_target_id, validar que exista activo
        $jt_from_id = null;
        if ( $job_target_id > 0 ) {
            $jt_from_id = $this->find_active_job_target_by_id( $job_target_id );
            if ( ! $jt_from_id ) {
                $job_target_id = 0;
            }
        }

        // 3) Reconstrucción (si JS falló)
        if ( $job_target_id <= 0 && $type !== '' ) {
            if ( ! $jt_from_slug ) {
                $jt_from_slug = $this->find_active_job_target_by_slug( 'auto', $type );
            }
            if ( $jt_from_slug && (int)$jt_from_slug['id'] > 0 ) {
                $job_target_id = (int) $jt_from_slug['id'];
            }
        }

        if ( $type === '' && $job_target_id > 0 ) {
            if ( ! $jt_from_id ) {
                $jt_from_id = $this->find_active_job_target_by_id( $job_target_id );
            }
            if ( $jt_from_id && ! empty( $jt_from_id['slug'] ) ) {
                $type = sanitize_key( (string) $jt_from_id['slug'] );
            }
        }

        // ✅ Validación mínima: Year/Make/Model/Color/Type requerido
        if ( $year === '' || $make === '' || $model === '' || $color === '' || $type === '' ) {

            $state['vehicle_form'] = array(
                'year'          => $year,
                'make'          => $make,
                'model'         => $model,
                'color'         => $color,
                'type'          => $type,
                'job_target_id' => $job_target_id,
            );

            $errors[] = __( 'Please complete Year, Make, Model, Vehicle type and Color.', 'bubbles-booking' );
            return;
        }

        $label = trim( $year . ' ' . $make . ' ' . $model ) . ' (' . $color . ')';

        // Guardar en state
        $state['vehicle_form'] = array(
            'year'          => $year,
            'make'          => $make,
            'model'         => $model,
            'color'         => $color,
            'type'          => $type,
            'label'         => $label,
            'job_target_id' => $job_target_id,
        );

        $state['vehicle'] = $state['vehicle_form'];

        // Top-level (pricing engine)
        $state['job_target_id'] = $job_target_id;

        $state['_dbg_vehicle_saved'] = array(
            'vehicle_form'  => $state['vehicle_form'],
            'vehicle'       => $state['vehicle'],
            'job_target_id' => $state['job_target_id'],
        );
    }

    public function get_view_data( array $state, array $errors = array() ) : array {

        $prev = array(
            'year'          => '',
            'make'          => '',
            'model'         => '',
            'color'         => '',
            'type'          => '',
            'job_target_id' => 0,
        );

        if ( isset( $state['vehicle_form'] ) && is_array( $state['vehicle_form'] ) ) {
            $prev = array_merge( $prev, $state['vehicle_form'] );
        } elseif ( isset( $state['vehicle'] ) && is_array( $state['vehicle'] ) ) {
            $prev = array_merge( $prev, $state['vehicle'] );
        }

        // ✅ Tipos desde BD para renderizar select
        $vehicle_types = $this->get_vehicle_types_db();

        return array(
            'prev'          => $prev,
            'errors'        => $errors,
            'message'       => '',
            'bb_vehicles'   => '',
            'vehicle_types' => $vehicle_types,
        );
    }
}

} // class_exists
