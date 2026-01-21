<?php
if ( ! defined('ABSPATH') ) exit;

class BB_Booking_Repository {

  protected $table;

  public function __construct(){
    global $wpdb;
    $this->table = $wpdb->prefix . 'bb_bookings';
  }

  /**
   * Create bookings table (wp_bb_bookings) if it doesn't exist.
   * Call this from your installer on plugin activation.
   */
  public static function create_table(): void {
    global $wpdb;

    $table   = $wpdb->prefix . 'bb_bookings';
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

      status VARCHAR(40) NOT NULL DEFAULT 'pending',

      vehicle_year VARCHAR(10) NOT NULL DEFAULT '',
      vehicle_make VARCHAR(80) NOT NULL DEFAULT '',
      vehicle_model VARCHAR(80) NOT NULL DEFAULT '',
      vehicle_type VARCHAR(40) NOT NULL DEFAULT '',

      service_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      addons_json LONGTEXT NULL,

      address_line1 VARCHAR(255) NOT NULL DEFAULT '',
      address_line2 VARCHAR(255) NOT NULL DEFAULT '',
      city VARCHAR(120) NOT NULL DEFAULT '',
      state VARCHAR(40) NOT NULL DEFAULT '',
      zip VARCHAR(20) NOT NULL DEFAULT '',
      lat DECIMAL(10,7) NULL,
      lng DECIMAL(10,7) NULL,

      scheduled_date DATE NULL,
      scheduled_slot VARCHAR(40) NOT NULL DEFAULT '',

      tech_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,

      subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      tax DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      total DECIMAL(10,2) NOT NULL DEFAULT 0.00,

      created_at DATETIME NOT NULL,

      PRIMARY KEY  (id),
      KEY service_id (service_id),
      KEY tech_id (tech_id),
      KEY scheduled_date (scheduled_date),
      KEY status (status)
    ) $charset;";

    dbDelta($sql);
  }

  public function insert_booking( array $b ){
    global $wpdb;

    $data = array(
      'status' => $b['status'],

      'vehicle_year'  => $b['vehicle_year'],
      'vehicle_make'  => $b['vehicle_make'],
      'vehicle_model' => $b['vehicle_model'],
      'vehicle_type'  => $b['vehicle_type'],

      'service_id'  => (int) $b['service_id'],
      'addons_json' => $b['addons_json'],

      'address_line1' => $b['address_line1'],
      'address_line2' => $b['address_line2'],
      'city'          => $b['city'],
      'state'         => $b['state'],
      'zip'           => $b['zip'],
      'lat'           => $b['lat'],
      'lng'           => $b['lng'],

      'scheduled_date' => $b['scheduled_date'],
      'scheduled_slot' => $b['scheduled_slot'],

      'tech_id' => $b['tech_id'],

      'subtotal' => $b['subtotal'],
      'tax'      => $b['tax'],
      'total'    => $b['total'],

      'created_at' => $b['created_at'],
    );

    $formats = array(
      '%s',
      '%s','%s','%s','%s',
      '%d','%s',
      '%s','%s','%s','%s','%s','%f','%f',
      '%s','%s',
      '%d',
      '%f','%f','%f',
      '%s',
    );

    $ok = $wpdb->insert( $this->table, $data, $formats );
    if ( ! $ok ) return 0;

    return (int) $wpdb->insert_id;
  }
}
