<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BB_Admin_Target_Job_Controller {

    protected BB_Job_Targets_Service $service;

    public function __construct( BB_Job_Targets_Service $service ) {
        $this->service = $service;
    }

    public function handle_request(): array {

        if ( ! current_user_can( 'manage_options' ) ) {
            return array('error' => __( 'No permission.', 'bubbles-booking' ), 'items' => array());
        }

        $industry = isset($_GET['industry']) ? sanitize_key( wp_unslash($_GET['industry']) ) : '';
        $notice   = '';

        // ✅ Delete por GET con nonce (evita forms anidados en la tabla)
        if ( isset($_GET['bb_action']) && $_GET['bb_action'] === 'delete' ) {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

            if ( $id > 0 && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bb_job_targets_delete_' . $id ) ) {
                $notice = ( $this->service->delete($id) )
                    ? __( 'Deleted.', 'bubbles-booking' )
                    : __( 'Could not delete.', 'bubbles-booking' );
            } else {
                // nonce inválido o id inválido
                if ( $id > 0 ) {
                    $notice = __( 'Invalid delete request.', 'bubbles-booking' );
                }
            }
        }

        $action = isset($_POST['bb_action']) ? sanitize_key( wp_unslash($_POST['bb_action']) ) : '';

        if ( $action !== '' ) {

            check_admin_referer( 'bb_job_targets_save', 'bb_job_targets_nonce' );

            switch ( $action ) {

                case 'save': {
                    $data = array(
                        'id'          => isset($_POST['id']) ? (int) $_POST['id'] : 0,
                        'industry'    => isset($_POST['industry']) ? sanitize_key($_POST['industry']) : 'auto',
                        'label'       => isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '',
                        'tier_id'     => isset($_POST['tier_id']) ? (int) $_POST['tier_id'] : 0,
                        'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
                        'sort_order'  => isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0,
                        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
                    );

                    $id = $this->service->upsert( $data );

                    $notice = $id > 0
                        ? __( 'Job Target saved.', 'bubbles-booking' )
                        : __( 'Could not save. Check required fields.', 'bubbles-booking' );
                    break;
                }

                case 'bulk_update': {
                    $rows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : array();
                    $updated = 0;

                    foreach ( $rows as $id_str => $row ) {
                        $id = (int) $id_str;
                        if ( $id <= 0 ) continue;

                        // ✅ NO pisar tier_id con 0 por defecto
                        $fields = array();

                        // sort_order solo si viene
                        if ( isset($row['sort_order']) && $row['sort_order'] !== '' ) {
                            $fields['sort_order'] = (int) $row['sort_order'];
                        }

                        // checkbox: si no viene, es OFF
                        $fields['is_active'] = isset($row['is_active']) ? 1 : 0;

                        // tier_id: solo si viene y > 0
                        if ( isset($row['tier_id']) ) {
                            $tid = (int) $row['tier_id'];
                            if ( $tid > 0 ) {
                                $fields['tier_id'] = $tid;
                            }
                        }

                        if ( $this->service->update_fields( $id, $fields ) ) {
                            $updated++;
                        }
                    }

                    $notice = sprintf( __( 'Updated %d row(s).', 'bubbles-booking' ), $updated );
                    break;
                }

                case 'seed': {
                    $this->service->seed_defaults_if_empty();
                    $notice = __( 'Defaults seeded (only if empty).', 'bubbles-booking' );
                    break;
                }
            }
        }

        $items = $this->service->get_all( $industry );

        $edit_id = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
        $edit_item = $edit_id > 0 ? $this->service->get_by_id( $edit_id ) : null;

        return array(
            'notice'    => $notice,
            'industry'  => $industry,
            'items'     => $items,
            'edit_item' => $edit_item,
        );
    }
}
