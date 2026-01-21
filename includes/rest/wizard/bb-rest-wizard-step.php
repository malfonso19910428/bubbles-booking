<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Rest_Wizard_Step {

  private static array $allowed_steps = array('vehicle','package','addons','address','date','confirm','payment');

  public static function register(): void {
    register_rest_route('bb/v1', '/wizard/step', array(
      'methods'  => WP_REST_Server::CREATABLE,
      'callback' => array(__CLASS__, 'handle'),
      'permission_callback' => '__return_true',
    ));
  }

  public static function handle( WP_REST_Request $req ) {

    BB_Rest_Draft_Context::accept_token_if_no_cookie($req);
    $svc = BB_Rest_Draft_Context::draft_service();

    $body = $req->get_json_params();
    if ( ! is_array($body) ) $body = array();

    // contrato estable: current_step + state_patch (PLANO)
    $step  = sanitize_key( (string)($body['current_step'] ?? '') );
    $patch = $body['state_patch'] ?? null;

    $fields = array();
    if ( $step === '' ) $fields['current_step'] = 'Required';
    if ( ! is_array($patch) ) $fields['state_patch'] = 'Must be an object';

    if ( $fields ) {
      return BB_Rest_Response::error(BB_Rest_Errors::VALIDATION, 'Invalid payload.', $fields, 400);
    }

    if ( ! in_array($step, self::$allowed_steps, true) ) {
      return BB_Rest_Response::error(BB_Rest_Errors::VALIDATION, 'Invalid step.', array('current_step' => 'Not allowed'), 400);
    }

    // asegura draft (por si step llega antes que session)
    if ( ! $svc->ensure_created() ) {
      return BB_Rest_Response::error(BB_Rest_Errors::SERVER, 'Unable to ensure draft.', array(), 500);
    }

    // tu lógica real: state PLANO + merge + save
    $svc->set_current_step($step);
    $svc->merge_state($patch);

    if ( ! $svc->save('draft', true) ) {
      return BB_Rest_Response::error(BB_Rest_Errors::SERVER, 'Unable to save draft.', array(), 500);
    }

    return BB_Rest_Response::ok(array(
      'token'        => $svc->get_token(),
      'current_step' => $svc->get_current_step(),
      'state'        => $svc->get_state(),
    ));
  }
}
