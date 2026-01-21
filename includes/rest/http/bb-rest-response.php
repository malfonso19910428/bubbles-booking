<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Rest_Response {

  public static function ok( array $data = array(), int $status = 200 ): WP_REST_Response {
    return new WP_REST_Response(array(
      'ok'   => true,
      'data' => $data,
      'meta' => BB_Rest_Request_Id::meta(),
    ), $status);
  }

  public static function error( string $code, string $message, array $fields = array(), int $status = 400 ): WP_REST_Response {
    return new WP_REST_Response(array(
      'ok' => false,
      'error' => array(
        'code'    => $code,
        'message' => $message,
        'fields'  => $fields,
      ),
      'meta' => BB_Rest_Request_Id::meta(),
    ), $status);
  }
}
