<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Summary_Controller {

    private function get_package_id_from_state( array $state ): int {
        if ( ! empty( $state['package'] ) && is_array( $state['package'] ) && ! empty( $state['package']['id'] ) ) {
            return (int) $state['package']['id'];
        }
        if ( ! empty( $state['bb_package'] ) ) return (int) $state['bb_package'];
        if ( ! empty( $state['vehicle_bb_package'] ) ) return (int) $state['vehicle_bb_package'];
        if ( ! empty( $state['selected_package'] ) ) return (int) $state['selected_package'];
        return 0;
    }

    private function get_addon_ids_from_state( array $state ): array {
        $ids = array();

        if ( isset( $state['addons'] ) && is_array( $state['addons'] ) ) {
            $ids = $state['addons'];
        } elseif ( isset( $state['vehicle_addons_current'] ) && is_array( $state['vehicle_addons_current'] ) ) {
            $ids = $state['vehicle_addons_current'];
        }

        $ids = array_map( 'strval', (array) $ids );
        $ids = array_values( array_unique( array_filter( $ids, function( $v ){
            $v = trim((string)$v);
            return $v !== '' && $v !== '0';
        })) );

        return array_map( 'intval', $ids );
    }

    private function get_vehicle_label( array $state ): string {
        $veh = array();

        if ( isset( $state['vehicle'] ) && is_array( $state['vehicle'] ) ) {
            $veh = $state['vehicle'];
        } elseif ( isset( $state['vehicle_form'] ) && is_array( $state['vehicle_form'] ) ) {
            $veh = $state['vehicle_form'];
        }

        if ( ! empty( $veh['label'] ) ) {
            return (string) $veh['label'];
        }

        $year  = isset( $veh['year'] )  ? trim( (string) $veh['year'] )  : '';
        $make  = isset( $veh['make'] )  ? trim( (string) $veh['make'] )  : '';
        $model = isset( $veh['model'] ) ? trim( (string) $veh['model'] ) : '';
        $color = isset( $veh['color'] ) ? trim( (string) $veh['color'] ) : '';

        $label = trim( implode( ' ', array_filter( array( $year, $make, $model ) ) ) );

        if ( $label !== '' && $color !== '' ) {
            $label .= ' (' . $color . ')';
        }

        return $label !== '' ? $label : 'Vehicle';
    }

    private function to_money( $v ): float {
        if ( is_null( $v ) ) return 0.0;
        if ( is_int( $v ) || is_float( $v ) ) return (float) $v;

        $s = trim( (string) $v );
        if ( $s === '' ) return 0.0;

        $s = str_replace( array( '$', ' ' ), '', $s );

        if ( strpos( $s, ',' ) !== false && strpos( $s, '.' ) !== false ) {
            $s = str_replace( ',', '', $s );
        } elseif ( strpos( $s, ',' ) !== false && strpos( $s, '.' ) === false ) {
            $s = str_replace( ',', '.', $s );
        }

        return is_numeric( $s ) ? (float) $s : 0.0;
    }

    private function get_address_from_state( array $state ): array {
        $addr = array();

        if ( isset( $state['address'] ) && is_array( $state['address'] ) ) {
            $addr = $state['address'];
        } elseif ( isset( $state['vehicle_address'] ) && is_array( $state['vehicle_address'] ) ) {
            $addr = $state['vehicle_address'];
        }

        return array(
            'line1' => (string) ( $addr['line1'] ?? $addr['address1'] ?? $addr['street'] ?? $addr['street1'] ?? '' ),
            'line2' => (string) ( $addr['line2'] ?? $addr['address2'] ?? $addr['street2'] ?? '' ),
            'city'  => (string) ( $addr['city']  ?? '' ),
            'state' => (string) ( $addr['state'] ?? $addr['region'] ?? '' ),
            'zip'   => (string) ( $addr['zip']   ?? $addr['postal'] ?? $addr['postal_code'] ?? '' ),
        );
    }

    // ✅ NUEVO: Customer desde state
    private function get_customer_from_state( array $state ): array {

        $c = array();

        if ( isset( $state['customer'] ) && is_array( $state['customer'] ) ) {
            $c = $state['customer'];
        } elseif ( isset( $state['confirm'] ) && is_array( $state['confirm'] ) ) {
            $c = $state['confirm'];
        }

        return array(
            'name'  => (string) ( $c['name']  ?? $c['bb_name']  ?? '' ),
            'phone' => (string) ( $c['phone'] ?? $c['bb_phone'] ?? '' ),
            'email' => (string) ( $c['email'] ?? $c['bb_email'] ?? '' ),
            'notes' => (string) ( $c['notes'] ?? $c['bb_notes'] ?? '' ),
        );
    }

    // ✅ date/slot desde state
    private function get_date_from_state( array $state ): array {

        $d = array();

        if ( isset( $state['date'] ) && is_array( $state['date'] ) ) {
            $d = $state['date'];
        } elseif ( isset( $state['vehicle_date'] ) && is_array( $state['vehicle_date'] ) ) {
            $d = $state['vehicle_date'];
        }

        $date  = (string) ( $d['date'] ?? '' );
        $time  = (string) ( $d['time'] ?? '' );
        $label = (string) ( $d['slot_label'] ?? $d['time_label'] ?? $time );

        return array(
            'date'       => $date,
            'time'       => $time,
            'slot_label' => $label,
            'slot_id'    => (string) ( $d['slot_id'] ?? '' ),
            'timezone'   => (string) ( $d['timezone'] ?? '' ),
        );
    }

    private function format_date_label( string $ymd ): string {
        $ymd = trim($ymd);
        if ( $ymd === '' ) return '';

        $ts = strtotime( $ymd . ' 00:00:00' );
        if ( ! $ts ) return $ymd;

        return function_exists('date_i18n') ? date_i18n( 'M j, Y', $ts ) : date('M j, Y', $ts);
    }

    private function resolve_service( int $service_id ): array {

        if ( $service_id <= 0 ) return array();

        $repo_file = BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-repo.php';
        $svc_file  = BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/services/bb-services-service.php';

        if ( file_exists( $repo_file ) ) require_once $repo_file;
        if ( file_exists( $svc_file ) )  require_once $svc_file;

        $fallback = array(
            'id'    => $service_id,
            'name'  => '',
            'label' => '',
            'price' => 0.0,
            'meta'  => array( 'id' => $service_id ),
        );

        if ( ! class_exists( 'BB_Services_Service' ) || ! class_exists( 'BB_Services_Repo' ) ) {
            return $fallback;
        }

        try {
            $svc = new BB_Services_Service( new BB_Services_Repo() );
        } catch ( \Throwable $e ) {
            return $fallback;
        }

        $row = null;

        foreach ( array( 'get_by_id', 'get_service', 'find_by_id', 'find', 'get' ) as $m ) {
            if ( method_exists( $svc, $m ) ) {
                try {
                    $tmp = $svc->{$m}( $service_id );
                    if ( is_array( $tmp ) ) { $row = $tmp; break; }
                } catch ( \Throwable $e ) {}
            }
        }

        if ( ! is_array( $row ) ) {
            $all = null;
            foreach ( array( 'list', 'get_all', 'all' ) as $m2 ) {
                if ( method_exists( $svc, $m2 ) ) {
                    try {
                        $tmp = $svc->{$m2}();
                        if ( is_array( $tmp ) ) { $all = $tmp; break; }
                    } catch ( \Throwable $e ) {}
                }
            }
            if ( is_array( $all ) ) {
                foreach ( $all as $r ) {
                    if ( is_array( $r ) && (int)( $r['id'] ?? 0 ) === (int)$service_id ) {
                        $row = $r;
                        break;
                    }
                }
            }
        }

        if ( ! is_array( $row ) ) return $fallback;

        $name  = (string) ( $row['name']  ?? $row['title'] ?? $row['label'] ?? '' );
        $label = (string) ( $row['label'] ?? '' );

        $raw_price =
            $row['price']
            ?? $row['base_price']
            ?? $row['cost']
            ?? $row['amount']
            ?? $row['value']
            ?? $row['rate']
            ?? ( is_array( $row['meta'] ?? null )
                ? ( $row['meta']['price'] ?? $row['meta']['base_price'] ?? $row['meta']['cost'] ?? null )
                : null
            )
            ?? 0;

        $price = $this->to_money( $raw_price );

        return array(
            'id'    => $service_id,
            'name'  => $name,
            'label' => $label,
            'price' => $price,
            'meta'  => is_array( $row['meta'] ?? null ) ? $row['meta'] : array( 'id' => $service_id ),
        );
    }

    private function resolve_addons( array $addon_ids ): array {

        if ( empty( $addon_ids ) ) return array();

        $addon_ids = array_values( array_unique( array_map( 'intval', (array) $addon_ids ) ) );

        $repo_file = BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-repo.php';
        $svc_file  = BB_PLUGIN_DIR . 'includes/domain/admin/catalog-services-addons/addons/bb-addons-service.php';

        if ( file_exists( $repo_file ) ) require_once $repo_file;
        if ( file_exists( $svc_file ) )  require_once $svc_file;

        if ( ! class_exists( 'BB_Addons_Service' ) || ! class_exists( 'BB_Addons_Repo' ) ) {
            return array();
        }

        try {
            $svc = new BB_Addons_Service( new BB_Addons_Repo() );
        } catch ( \Throwable $e ) {
            return array();
        }

        $resolved_by_id = array();

        foreach ( $addon_ids as $aid ) {

            $aid = (int) $aid;
            if ( $aid <= 0 ) continue;

            $row = null;

            foreach ( array( 'get_by_id', 'get_addon', 'find_by_id', 'find', 'get' ) as $m ) {
                if ( method_exists( $svc, $m ) ) {
                    try {
                        $tmp = $svc->{$m}( $aid );
                        if ( is_array( $tmp ) ) { $row = $tmp; break; }
                    } catch ( \Throwable $e ) {}
                }
            }

            if ( ! is_array( $row ) ) {
                $all = null;
                foreach ( array( 'list', 'get_all', 'all' ) as $m2 ) {
                    if ( method_exists( $svc, $m2 ) ) {
                        try {
                            $tmp = $svc->{$m2}();
                            if ( is_array( $tmp ) ) { $all = $tmp; break; }
                        } catch ( \Throwable $e ) {}
                    }
                }
                if ( is_array( $all ) ) {
                    foreach ( $all as $r ) {
                        if ( is_array( $r ) && (int)( $r['id'] ?? 0 ) === $aid ) {
                            $row = $r;
                            break;
                        }
                    }
                }
            }

            if ( is_array( $row ) ) {
                $raw_price =
                    $row['price']
                    ?? $row['cost']
                    ?? $row['amount']
                    ?? ( is_array($row['meta'] ?? null) ? ( $row['meta']['price'] ?? null ) : null )
                    ?? 0;

                $resolved_by_id[ $aid ] = array(
                    'id'    => $aid,
                    'name'  => (string)( $row['name'] ?? $row['title'] ?? $row['label'] ?? 'Add-on' ),
                    'price' => $this->to_money( $raw_price ),
                );
            }
        }

        return array_values( $resolved_by_id );
    }

    public function get_view_data( array $state ): array {

    $pkg_id        = $this->get_package_id_from_state( $state );
    $addon_ids     = $this->get_addon_ids_from_state( $state );
    $vehicle_label = $this->get_vehicle_label( $state );
    $address       = $this->get_address_from_state( $state );
    $date          = $this->get_date_from_state( $state );

    // ✅ Customer
    $customer      = $this->get_customer_from_state( $state );

    $service = array();
    if ( $pkg_id > 0 ) {
        $service = $this->resolve_service( $pkg_id );
    }

    $addons = $this->resolve_addons( $addon_ids );

    $has_service = ! empty( $service ) && ( (int)( $service['id'] ?? 0 ) > 0 );
    $has_addons  = ! empty( $addons );

    /**
     * ✅ IMPORTANT:
     * Summary NO recalcula el precio.
     * Usa el precio FINAL guardado en el state por el step Package:
     *   $state['package']['price']
     */
    $base = 0.0;

    if ( isset( $state['package']['price'] ) ) {
        // precio final (base + add tier) ya calculado por Pricing Engine
        $base = $this->to_money( (float) $state['package']['price'] );
    } elseif ( $has_service ) {
        // fallback (por si llegas a summary sin haber pasado por package)
        $base = $this->to_money( $service['price'] ?? 0 );
    }

    $adds = 0.0;
    foreach ( (array) $addons as $a ) {
        if ( ! is_array( $a ) ) continue;
        $adds += $this->to_money( $a['price'] ?? 0 );
    }

    $total = $base + $adds;

    $job = array(
        'subject' => array(
            'label' => (string) $vehicle_label,
        ),
        'service' => array(
            'id'    => (int) ( $service['id'] ?? 0 ),
            'name'  => (string) ( $service['name'] ?? ( $service['label'] ?? '' ) ),
            'label' => (string) ( $service['label'] ?? '' ),

            // ✅ mostrar el precio final (no el base)
            'price' => (float) $base,

            'meta'  => (array) ( $service['meta'] ?? array() ),
        ),
        'addons'  => $addons,
        'address' => $address,
        'schedule' => array(
            'date'       => (string) ( $date['date'] ?? '' ),
            'date_label' => $this->format_date_label( (string) ( $date['date'] ?? '' ) ),
            'time'       => (string) ( $date['time'] ?? '' ),
            'slot_label' => (string) ( $date['slot_label'] ?? '' ),
            'timezone'   => (string) ( $date['timezone'] ?? '' ),
        ),

        // ✅ Customer agregado para sidebar
        'customer' => array(
            'name'  => (string) ( $customer['name'] ?? '' ),
            'phone' => (string) ( $customer['phone'] ?? '' ),
            'email' => (string) ( $customer['email'] ?? '' ),
            'notes' => (string) ( $customer['notes'] ?? '' ),
        ),

        'cost'    => array(
            'total' => (float) $total,
        ),
    );

    $has_customer = (
        trim((string)($customer['name'] ?? '')) !== '' ||
        trim((string)($customer['phone'] ?? '')) !== '' ||
        trim((string)($customer['email'] ?? '')) !== ''
    );

    return array(
        'jobs' => array( $job ),
        'totals' => array(
            'grand_total' => (float) $total,
        ),
        'meta' => array(
            'has_vehicle'   => ( trim( (string) $vehicle_label ) !== '' && strtolower(trim($vehicle_label)) !== 'vehicle' ),
            'has_service'   => $has_service,
            'has_addons'    => $has_addons,
            'has_address'   => ( trim((string)($address['line1'] ?? '')) !== '' ),
            'has_date'      => ( trim((string)($date['date'] ?? '')) !== '' && trim((string)($date['time'] ?? '')) !== '' ),
            'has_customer'  => $has_customer,
            'pkg_id'        => $pkg_id,
            'addon_ids'     => $addon_ids,
        ),
    );
}

}
