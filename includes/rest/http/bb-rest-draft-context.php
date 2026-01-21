<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * BB_Rest_Draft_Context
 * - Resuelve token con la misma política que tu UI:
 *   cookie-first, y solo acepta token de request si no hay cookie.
 * - Devuelve el DraftStepsService listo.
 */
final class BB_Rest_Draft_Context {

  /**
   * Si no hay cookie aún, permite que REST acepte token por:
   * - JSON body: { "token": "..." }
   * - query: ?bb_token=...
   * y lo fija en cookie para el resto de requests.
   */
  public static function accept_token_if_no_cookie( WP_REST_Request $req ): void {

    if ( ! class_exists('BB_Draft_Steps_Token') ) {
      require_once BB_PLUGIN_DIR . 'includes/core/bb-draft-steps-token.php';
    }

    // si ya existe cookie, NO aceptamos otro token
    if ( isset($_COOKIE[ BB_Draft_Steps_Token::COOKIE_NAME ]) && $_COOKIE[ BB_Draft_Steps_Token::COOKIE_NAME ] !== '' ) {
      return;
    }

    $body = $req->get_json_params();
    $candidate = '';

    if ( is_array($body) && ! empty($body['token']) ) {
      $candidate = (string) $body['token'];
    }
    if ( ! $candidate ) {
      $candidate = (string) $req->get_param('bb_token');
    }

    $candidate = sanitize_text_field(trim($candidate));
    if ( $candidate === '' ) return;

    // misma validación básica que tu clase
    if ( strlen($candidate) < 16 || strlen($candidate) > 128 ) return;
    if ( ! preg_match('/^[a-zA-Z0-9\-_]+$/', $candidate) ) return;

    // fijar cookie (similar a tu set_cookie)
    if ( ! headers_sent() ) {
      $secure = is_ssl();
      setcookie(
        BB_Draft_Steps_Token::COOKIE_NAME,
        $candidate,
        time() + ( 7 * DAY_IN_SECONDS ),
        COOKIEPATH ? COOKIEPATH : '/',
        COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
        $secure,
        true
      );
    }
    $_COOKIE[ BB_Draft_Steps_Token::COOKIE_NAME ] = $candidate;
  }

  public static function token(): string {
    if ( ! class_exists('BB_Draft_Steps_Token') ) {
      require_once BB_PLUGIN_DIR . 'includes/core/bb-draft-steps-token.php';
    }
    return BB_Draft_Steps_Token::get_or_create();
  }

  public static function draft_service(): BB_Draft_Steps_Service {

    // intenta factory primero (tu forma preferida)
    $factory = BB_PLUGIN_DIR . 'includes/core/bb-draft-steps-factory.php';
    if ( file_exists($factory) ) require_once $factory;

    if ( function_exists('bb_wizard_draft_steps_service') ) {
      $svc = bb_wizard_draft_steps_service();
      if ( $svc instanceof BB_Draft_Steps_Service ) return $svc;
    }

    // fallback: instanciar directo, usando tu token resolver
    require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php';
    require_once BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-service.php';

    $repo  = new BB_Draft_Steps_Repo();
    $token = self::token();

    return new BB_Draft_Steps_Service($repo, $token, 24);
  }
}
