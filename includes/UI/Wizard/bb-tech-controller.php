<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('BB_Tech_Controller') ) {

class BB_Tech_Controller {

    /** @var BB_Draft_Steps_Service */
    private $draft;

    /** @var TechProfileService */
    private $profiles;

    public function __construct( BB_Draft_Steps_Service $draft, TechProfileService $profiles ) {
        $this->draft    = $draft;
        $this->profiles = $profiles;
    }

    public function handle_post( array $post, array &$errors, array &$state ): void {

        $step = isset($post['bb_step']) ? sanitize_key( wp_unslash($post['bb_step']) ) : '';
        if ( $step !== 'tech' ) return;

        $choice = isset($post['bb_tech_choice']) ? sanitize_text_field( wp_unslash($post['bb_tech_choice']) ) : '';

        if ( $choice === '' || $choice === 'auto' ) {
            $state['tech'] = array('mode' => 'auto', 'tech_id' => null);
        } elseif ( strpos($choice, 'tech:') === 0 ) {
            $id = (int) substr($choice, 5);
            $state['tech'] = array('mode' => 'manual', 'tech_id' => ($id > 0 ? $id : null));
        } else {
            $state['tech'] = array('mode' => 'auto', 'tech_id' => null);
        }

        // Guardar SOLO slice tech (simple)
        $this->draft->set_slice('tech', $state['tech']);
        $this->draft->save('draft');
    }

    public function get_view_data( array $state, array $errors = array() ): array {

        // Techs ya filtrados por slot (disponibles)
        $slot_tech_ids = array_map('intval', (array)($state['date']['slot']['tech_ids'] ?? array()));

        // Distancias ya calculadas por coverage (gratis)
        $distances = (array)($state['coverage']['distances'] ?? array());

        $techs = array();

        foreach ( $slot_tech_ids as $tech_id ) {

            // Perfil canonico (con fallbacks)
            $p = $this->profiles->get_profile_for_view( (int)$tech_id );

            // Nombre display (simple y limpio)
            $first = trim((string)($p['first_name'] ?? ''));
            $last  = trim((string)($p['last_name'] ?? ''));
            $display_name = trim($first . ' ' . $last);
            if ( $display_name === '' ) $display_name = 'Tech #' . (int)$tech_id;

            // Avatar por attachment ID
            $avatar_url = '';
            $avatar_id  = (int)($p['avatar_id'] ?? 0);
            if ( $avatar_id > 0 ) {
                $avatar_url = (string) wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
            }

            // Portfolio: en tu repo ya viene como array de URLs
            $portfolio = isset($p['portfolio']) && is_array($p['portfolio']) ? $p['portfolio'] : array();

            // Distance desde coverage.distances
            $distance_mi = null;
            $k = (string)$tech_id;
            if ( isset($distances[$k]) ) $distance_mi = (float)$distances[$k];

            // Languages
            $langs = isset($p['languages']) && is_array($p['languages']) ? $p['languages'] : array();
            $langs = array_values(array_filter(array_map('strval', $langs)));

            // Bio (ya viene en bb_tech_bio o fallback)
            $bio = (string)($p['bio'] ?? '');
            $bio = wp_strip_all_tags($bio);

            // Status (por si quieres ocultar pending, etc.)
            $status = (string)($p['status'] ?? 'pending');

            $techs[] = array(
                'id'           => (int)$tech_id,
                'display_name' => $display_name,
                'bio'          => $bio,
                'languages'    => $langs,
                'avatar_url'   => $avatar_url,
                'portfolio'    => array_values(array_filter(array_map('strval', $portfolio))),
                'distance_mi'  => $distance_mi,
                'status'       => $status,
                'address'      => $p['address']['formatted'] ?? '', // opcional
            );
        }

        return array(
            'techs'       => $techs,
            'mode'        => (string)($state['tech']['mode'] ?? 'auto'),
            'selected_id' => $state['tech']['tech_id'] ?? null,
            'errors'      => $errors,
        );
    }
}

}
