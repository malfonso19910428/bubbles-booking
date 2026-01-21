<?php
if ( ! defined('ABSPATH') ) exit;

final class TechProfileService {

    private TechProfileRepo $repo;

    public function __construct( TechProfileRepo $repo ) {
        $this->repo = $repo;
    }

    public function get_profile( int $user_id ): array {
        return $this->repo->get_profile( $user_id );
    }

    /**
     * ✅ Compat con código viejo (BB_Tech_Shell)
     */
    public function profile_for_user( int $user_id ): array {
        return $this->get_profile_for_view( $user_id );
    }

    /**
     * ✅ Para la template: devuelve profile con shape garantizado.
     */
    public function get_profile_for_view( int $user_id ): array {
        $p = $this->repo->get_profile( $user_id );

        if ( ! isset($p['address']) || ! is_array($p['address']) ) $p['address'] = array();
        $p['address'] = array_merge(
            array('formatted'=>'','line1'=>'','city'=>'','state'=>'','zip'=>''),
            $p['address']
        );

        if ( ! isset($p['geo']) || ! is_array($p['geo']) ) $p['geo'] = array();
        $p['geo'] = array_merge(array('place_id'=>'','lat'=>'','lng'=>'','status'=>'','error'=>''), $p['geo']);

        if ( ! isset($p['languages']) || ! is_array($p['languages']) ) $p['languages'] = array();

        if ( ! isset($p['status']) || $p['status'] === '' ) $p['status'] = 'pending';
        if ( ! isset($p['avatar_id']) ) $p['avatar_id'] = 0;
        if ( ! isset($p['user_id']) ) $p['user_id'] = $user_id;

        return $p;
    }

    /**
     * ✅ Controller envía profile[] crudo.
     * Este service:
     *  - normaliza
     *  - sanitiza
     *  - valida
     *  - guarda SIN borrar campos que no vinieron (PATCH)
     */
    public function save_from_form( int $user_id, array $payload ): array {

        $raw  = $this->normalize_payload( $payload );

        // ✅ Detecta qué campos vinieron en el POST (para PATCH)
        $present = $this->detect_present_fields( $raw );

        $data = $this->sanitize_payload( $raw );

        $errors   = $this->validate( $data );
        $messages = array();

        if ( ! empty($errors) ) {
            $messages[] = array('type' => 'warning', 'text' => 'Please fix the highlighted fields and try again.');
            return array(
                'saved'    => false,
                'errors'   => $errors,
                'messages' => $messages,
                'profile'  => $this->merge_for_view($user_id, $data),
            );
        }

        // ✅ PATCH: mezcla con lo que ya existe para NO borrar defaults del admin
        $current = $this->get_profile_for_view( $user_id );
        $to_save = $this->apply_patch( $current, $data, $present );

        // ✅ Guardado
        $this->repo->save_profile( $user_id, $to_save );

        $messages[] = array('type' => 'success', 'text' => 'Profile saved successfully.');

        if ( ! empty($to_save['geo']['status']) && strtolower((string)$to_save['geo']['status']) !== 'ok' ) {
            $messages[] = array('type' => 'warning', 'text' => 'Location not verified yet.');
        }

        return array(
            'saved'    => true,
            'errors'   => array(),
            'messages' => $messages,
            'profile'  => $this->get_profile_for_view( $user_id ),
        );
    }

    // =========================
    // Present fields (PATCH)
    // =========================

    /**
     * Devuelve mapa booleano indicando qué keys vinieron realmente en el POST.
     * Ej: ['first_name'=>true, 'address.city'=>true, 'geo.lat'=>false...]
     */
    private function detect_present_fields( array $raw ): array {
        $p = array();

        $top = array('first_name','last_name','email','phone','bio','radius','geo_force','languages','avatar_id','portfolio','status','address','geo');
        foreach ($top as $k) {
            if ( array_key_exists($k, $raw) ) $p[$k] = true;
        }

        if ( isset($raw['address']) && is_array($raw['address']) ) {
            foreach ( array('formatted','line1','city','state','zip') as $k ) {
                if ( array_key_exists($k, $raw['address']) ) $p["address.$k"] = true;
            }
        }

        if ( isset($raw['geo']) && is_array($raw['geo']) ) {
            foreach ( array('place_id','lat','lng','status','error') as $k ) {
                if ( array_key_exists($k, $raw['geo']) ) $p["geo.$k"] = true;
            }
        }

        return $p;
    }

    /**
     * Aplica PATCH: solo pisa lo que vino presente.
     */
    private function apply_patch( array $current, array $data, array $present ): array {

        // básicos
        foreach ( array('first_name','last_name','email','phone','bio','radius','geo_force','avatar_id','status') as $k ) {
            if ( ! empty($present[$k]) ) {
                $current[$k] = $data[$k] ?? $current[$k] ?? '';
            }
        }

        // languages (si vino)
        if ( ! empty($present['languages']) ) {
            $current['languages'] = $data['languages'] ?? array();
        }

        // address (solo subkeys presentes)
        if ( ! isset($current['address']) || ! is_array($current['address']) ) $current['address'] = array();
        $current['address'] = array_merge(
            array('formatted'=>'','line1'=>'','city'=>'','state'=>'','zip'=>''),
            $current['address']
        );

        foreach ( array('formatted','line1','city','state','zip') as $k ) {
            if ( ! empty($present["address.$k"]) ) {
                $current['address'][$k] = $data['address'][$k] ?? '';
            }
        }

        // geo (solo subkeys presentes)
        if ( ! isset($current['geo']) || ! is_array($current['geo']) ) $current['geo'] = array();
        $current['geo'] = array_merge(array('place_id'=>'','lat'=>'','lng'=>'','status'=>'','error'=>''), $current['geo']);

        foreach ( array('place_id','lat','lng','status','error') as $k ) {
            if ( ! empty($present["geo.$k"]) && array_key_exists($k, $data['geo'] ?? array()) ) {
                $current['geo'][$k] = $data['geo'][$k];
            }
        }

        // portfolio (si vino)
        if ( ! empty($present['portfolio']) ) {
            $current['portfolio'] = $data['portfolio'] ?? array();
        }

        // si geo_force=1 y no vino lat/lng => status pending (pero NO borres lat/lng existentes)
        if ( (string)($current['geo_force'] ?? '0') === '1' ) {
            $has_lat = isset($current['geo']['lat']) && $current['geo']['lat'] !== '';
            $has_lng = isset($current['geo']['lng']) && $current['geo']['lng'] !== '';
            if ( ! $has_lat || ! $has_lng ) {
                $current['geo']['status'] = 'pending';
                $current['geo']['error']  = '';
            }
        }

        return $current;
    }

    // =========================
    // Normalize / Sanitize
    // =========================

    private function normalize_payload( array $raw ): array {

        if ( empty($raw['address']) || ! is_array($raw['address']) ) {
            $raw['address'] = array();
        }

        // Compat viejo (flat -> nested)
        if ( isset($raw['address_formatted']) && ! isset($raw['address']['formatted']) ) $raw['address']['formatted'] = $raw['address_formatted'];
        if ( isset($raw['address_line1'])     && ! isset($raw['address']['line1']) )     $raw['address']['line1']     = $raw['address_line1'];
        if ( isset($raw['city'])              && ! isset($raw['address']['city']) )      $raw['address']['city']      = $raw['city'];
        if ( isset($raw['state'])             && ! isset($raw['address']['state']) )     $raw['address']['state']     = $raw['state'];
        if ( isset($raw['zip'])               && ! isset($raw['address']['zip']) )       $raw['address']['zip']       = $raw['zip'];

        if ( isset($raw['geo']) && ! is_array($raw['geo']) ) $raw['geo'] = array();

        return $raw;
    }

    private function sanitize_payload( array $raw ): array {

        $out = array();

        $out['first_name'] = isset($raw['first_name']) ? sanitize_text_field((string)$raw['first_name']) : '';
        $out['last_name']  = isset($raw['last_name'])  ? sanitize_text_field((string)$raw['last_name'])  : '';
        $out['email']      = isset($raw['email'])      ? sanitize_email((string)$raw['email']) : '';
        $out['phone']      = isset($raw['phone'])      ? sanitize_text_field((string)$raw['phone']) : '';
        $out['bio']        = isset($raw['bio'])        ? sanitize_textarea_field((string)$raw['bio']) : '';

        $out['radius'] = ( isset($raw['radius']) && $raw['radius'] !== '' )
            ? (string)(float)$raw['radius']
            : '';

        $geo_force_raw = $raw['geo_force'] ?? '0';
        $out['geo_force'] = in_array((string)$geo_force_raw, array('1','true','on','yes'), true) ? '1' : '0';

        $langs = array();
        if ( isset($raw['languages']) && is_array($raw['languages']) ) {
            foreach ( $raw['languages'] as $l ) {
                $l = sanitize_text_field((string)$l);
                if ( $l !== '' ) $langs[] = $l;
            }
        }
        $out['languages'] = array_values(array_unique($langs));

        $addr = ( isset($raw['address']) && is_array($raw['address']) ) ? $raw['address'] : array();
        $out['address'] = array(
            'formatted' => isset($addr['formatted']) ? sanitize_text_field((string)$addr['formatted']) : '',
            'line1'     => isset($addr['line1'])     ? sanitize_text_field((string)$addr['line1'])     : '',
            'city'      => isset($addr['city'])      ? sanitize_text_field((string)$addr['city'])      : '',
            'state'     => isset($addr['state'])     ? sanitize_text_field((string)$addr['state'])     : '',
            'zip'       => isset($addr['zip'])       ? sanitize_text_field((string)$addr['zip'])       : '',
        );

        $geo = ( isset($raw['geo']) && is_array($raw['geo']) ) ? $raw['geo'] : array();
        $out['geo'] = array();

        if ( array_key_exists('place_id', $geo) ) {
            $out['geo']['place_id'] = sanitize_text_field((string)$geo['place_id']);
        }
        if ( array_key_exists('lat', $geo) ) {
            $out['geo']['lat'] = ($geo['lat'] === '' || $geo['lat'] === null) ? '' : (float)$geo['lat'];
        }
        if ( array_key_exists('lng', $geo) ) {
            $out['geo']['lng'] = ($geo['lng'] === '' || $geo['lng'] === null) ? '' : (float)$geo['lng'];
        }
        if ( array_key_exists('status', $geo) ) {
            $out['geo']['status'] = sanitize_text_field((string)$geo['status']);
        }
        if ( array_key_exists('error', $geo) ) {
            $out['geo']['error'] = sanitize_text_field((string)$geo['error']);
        }

        if ( isset($raw['avatar_id']) ) $out['avatar_id'] = (int)$raw['avatar_id'];

        if ( isset($raw['portfolio']) && is_array($raw['portfolio']) ) {
            $clean = array();
            foreach ( $raw['portfolio'] as $u ) {
                $u = esc_url_raw((string)$u);
                if ( $u !== '' ) $clean[] = $u;
            }
            $out['portfolio'] = array_values($clean);
        }

        if ( array_key_exists('status', $raw) ) {
            $out['status'] = sanitize_text_field((string)$raw['status']);
        }

        return $out;
    }

    // =========================
    // Validation (minimal)
    // =========================
    private function validate( array $data ): array {

        $errors = array();

        if ( $data['first_name'] === '' ) $errors['first_name'] = 'First name is required.';
        if ( $data['last_name']  === '' ) $errors['last_name']  = 'Last name is required.';
        if ( $data['email']      === '' ) $errors['email']      = 'Email is required.';
        if ( $data['email'] !== '' && ! is_email($data['email']) ) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if ( $data['radius'] !== '' && (float)$data['radius'] <= 0 ) {
            $errors['radius'] = 'Radius must be greater than 0.';
        }

        return $errors;
    }

    private function merge_for_view( int $user_id, array $data ): array {
        $current = $this->get_profile_for_view($user_id);
        foreach ($data as $k => $v) $current[$k] = $v;
        return $current;
    }
}
