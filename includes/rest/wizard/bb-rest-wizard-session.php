<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Rest_Wizard_Session {

  public static function register(): void {
    register_rest_route('bb/v1', '/wizard/session', array(
      'methods'  => WP_REST_Server::CREATABLE,
      'callback' => array(__CLASS__, 'handle'),
      'permission_callback' => '__return_true',
    ));
  }

  public static function handle( WP_REST_Request $req ) {

    // si no hay cookie, permitir token entrante
    BB_Rest_Draft_Context::accept_token_if_no_cookie($req);

    $svc = BB_Rest_Draft_Context::draft_service();

    // Pro: asegura que exista la fila del draft
    $ok = $svc->ensure_created();
    if ( ! $ok ) {
      return BB_Rest_Response::error(BB_Rest_Errors::SERVER, 'Unable to create draft.', array(), 500);
    }

    return BB_Rest_Response::ok(array(
      'token'        => $svc->get_token(),
      'current_step' => $svc->get_current_step(),
    ), 201);
  }
}
