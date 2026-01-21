<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Rest_Wizard_State {

  public static function register(): void {
    register_rest_route('bb/v1', '/wizard/state', array(
      'methods'  => WP_REST_Server::READABLE,
      'callback' => array(__CLASS__, 'handle'),
      'permission_callback' => '__return_true',
    ));
  }

  public static function handle( WP_REST_Request $req ) {

    BB_Rest_Draft_Context::accept_token_if_no_cookie($req);
    $svc = BB_Rest_Draft_Context::draft_service();

    // aquí NO forzamos ensure_created (opcional).
    // Si quieres que siempre exista, llama $svc->ensure_created();

    return BB_Rest_Response::ok(array(
      'token'        => $svc->get_token(),
      'current_step' => $svc->get_current_step(),
      'state'        => $svc->get_state(),
    ));
  }
}
