<?php
if ( ! defined('ABSPATH') ) exit;

final class BB_Rest_Bootstrap {

  public static function init(): void {
    add_action('rest_api_init', array(__CLASS__, 'register_routes'));
  }

  public static function register_routes(): void {

    // HTTP helpers (response envelope + request id + errors + draft context)
    require_once BB_PLUGIN_DIR . 'includes/rest/http/bb-rest-request-id.php';
    require_once BB_PLUGIN_DIR . 'includes/rest/http/bb-rest-errors.php';
    require_once BB_PLUGIN_DIR . 'includes/rest/http/bb-rest-response.php';
    require_once BB_PLUGIN_DIR . 'includes/rest/http/bb-rest-draft-context.php';

    // Wizard endpoints
    require_once BB_PLUGIN_DIR . 'includes/rest/wizard/bb-rest-wizard-session.php';
    require_once BB_PLUGIN_DIR . 'includes/rest/wizard/bb-rest-wizard-state.php';
    require_once BB_PLUGIN_DIR . 'includes/rest/wizard/bb-rest-wizard-step.php';

    // Register endpoints
    BB_Rest_Wizard_Session::register();
    BB_Rest_Wizard_State::register();
    BB_Rest_Wizard_Step::register();
  }
}
