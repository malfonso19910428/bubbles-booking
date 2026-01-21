<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Rest_Request_Id {
  private static $id;

  public static function id(): string {
    if ( ! self::$id ) self::$id = wp_generate_uuid4();
    return self::$id;
  }

  public static function meta(): array {
    return array('request_id' => self::id());
  }
}
