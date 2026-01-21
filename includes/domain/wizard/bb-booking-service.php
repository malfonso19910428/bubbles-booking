<?php
if ( ! defined('ABSPATH') ) exit;

class BB_Booking_Finalize_Service {

  /** @var BB_Booking_Repository */
  protected $repo;

  public function __construct( BB_Booking_Repository $repo ){
    $this->repo = $repo;
  }

  /**
   * ✅ Normaliza keys del draft real a un formato estable para validar/mapear.
   */
  protected function normalize_state( array $state ): array {

    // vehicle.vehicle_type <= vehicle.type
    if ( isset($state['vehicle']) && is_array($state['vehicle']) ) {
      if ( empty($state['vehicle']['vehicle_type']) && ! empty($state['vehicle']['type']) ) {
        $state['vehicle']['vehicle_type'] = $state['vehicle']['type'];
      }
    }

    // package.service_id <= package.id
    if ( isset($state['package']) && is_array($state['package']) ) {
      if ( empty($state['package']['service_id']) && ! empty($state['package']['id']) ) {
        $state['package']['service_id'] = $state['package']['id'];
      }
    }

    // address.address_line1 <= address.street OR address.address OR address.formatted
    if ( isset($state['address']) && is_array($state['address']) ) {
      if ( empty($state['address']['address_line1']) ) {
        if ( ! empty($state['address']['street']) ) {
          $state['address']['address_line1'] = $state['address']['street'];
        } elseif ( ! empty($state['address']['address']) ) {
          $state['address']['address_line1'] = $state['address']['address'];
        } elseif ( ! empty($state['address']['formatted']) ) {
          $state['address']['address_line1'] = $state['address']['formatted'];
        }
      }
    }

    // date.slot: si es array, lo convertimos a string "start-end"
    if ( isset($state['date']) && is_array($state['date']) ) {
      if ( ! empty($state['date']['slot']) && is_array($state['date']['slot']) ) {
        $start = $state['date']['slot']['start'] ?? '';
        $end   = $state['date']['slot']['end'] ?? '';
        if ( $start && $end ) {
          $state['date']['slot'] = $start . '-' . $end;
        } elseif ( $start ) {
          $state['date']['slot'] = $start;
        } else {
          // si no hay start/end, dejamos como string vacío para que falle validación
          $state['date']['slot'] = '';
        }
      }
    }

    // tech.tech_id (ya lo tienes)
    return $state;
  }

  public function validate_draft_state( array $state ): array {

    $state = $this->normalize_state($state);

    $missing = array();

    // vehicle
    if ( empty($state['vehicle']) || ! is_array($state['vehicle']) ) {
      $missing[] = 'vehicle';
    } else {
      foreach ( array('year','make','model','vehicle_type') as $k ) {
        if ( empty($state['vehicle'][$k]) ) $missing[] = 'vehicle.' . $k;
      }
    }

    // package/service
    if ( empty($state['package']) || ! is_array($state['package']) || empty($state['package']['service_id']) ) {
      $missing[] = 'package.service_id';
    }

    // address
    if ( empty($state['address']) || ! is_array($state['address']) ) {
      $missing[] = 'address';
    } else {
      foreach ( array('address_line1','city','state','zip') as $k ) {
        if ( empty($state['address'][$k]) ) $missing[] = 'address.' . $k;
      }
    }

    // date
    if ( empty($state['date']) || ! is_array($state['date']) ) {
      $missing[] = 'date';
    } else {
      foreach ( array('date','slot') as $k ) {
        if ( empty($state['date'][$k]) ) $missing[] = 'date.' . $k;
      }
    }

    // tech
    if ( empty($state['tech']) || ! is_array($state['tech']) || empty($state['tech']['tech_id']) ) {
      $missing[] = 'tech.tech_id';
    }

    return $missing;
  }

  public function finalize_from_draft_state( array $state ): array {

    $state = $this->normalize_state($state);

    $missing = $this->validate_draft_state($state);
    if ( ! empty($missing) ) {
      return array(
        'ok' => false,
        'message' => 'Missing required data: ' . implode(', ', $missing),
      );
    }

    $booking = $this->map_state_to_booking($state);
    $booking_id = $this->repo->insert_booking($booking);

    if ( ! $booking_id ) {
      return array('ok' => false, 'message' => 'Failed to create booking in database.');
    }

    return array(
      'ok' => true,
      'booking_id' => (int) $booking_id,
    );
  }

  protected function map_state_to_booking( array $state ): array {

    $vehicle = $state['vehicle'];
    $package = $state['package'];
    $addons  = isset($state['addons']) && is_array($state['addons']) ? $state['addons'] : array();
    $address = $state['address'];
    $date    = $state['date'];
    $tech    = $state['tech'];

    $totals = $this->compute_totals_from_state($state);

    return array(
      'status' => 'pending',

      'vehicle_year'  => sanitize_text_field($vehicle['year']),
      'vehicle_make'  => sanitize_text_field($vehicle['make']),
      'vehicle_model' => sanitize_text_field($vehicle['model']),
      'vehicle_type'  => sanitize_text_field($vehicle['vehicle_type']),

      'service_id'  => (int) $package['service_id'],
      'addons_json' => wp_json_encode($addons),

      'address_line1' => sanitize_text_field($address['address_line1']),
      'address_line2' => sanitize_text_field($address['address_line2'] ?? ($address['extra'] ?? '')),
      'city'          => sanitize_text_field($address['city']),
      'state'         => sanitize_text_field($address['state']),
      'zip'           => sanitize_text_field($address['zip']),
      'lat'           => isset($address['lat']) ? (float)$address['lat'] : 0.0,
      'lng'           => isset($address['lng']) ? (float)$address['lng'] : 0.0,

      'scheduled_date' => sanitize_text_field($date['date']),
      'scheduled_slot' => sanitize_text_field($date['slot']), // ya normalizado a string

      'tech_id' => (int) $tech['tech_id'],

      'subtotal' => $totals['subtotal'],
      'tax'      => $totals['tax'],
      'total'    => $totals['total'],

      'created_at' => current_time('mysql'),
    );
  }

  protected function compute_totals_from_state( array $state ): array {
    if ( isset($state['totals']) && is_array($state['totals']) ) {
      $sub = isset($state['totals']['subtotal']) ? (float)$state['totals']['subtotal'] : 0.0;
      $tax = isset($state['totals']['tax']) ? (float)$state['totals']['tax'] : 0.0;
      $tot = isset($state['totals']['grand_total']) ? (float)$state['totals']['grand_total'] : ($sub + $tax);
      return array('subtotal'=>round($sub,2),'tax'=>round($tax,2),'total'=>round($tot,2));
    }
    return array('subtotal'=>0.0,'tax'=>0.0,'total'=>0.0);
  }
}
