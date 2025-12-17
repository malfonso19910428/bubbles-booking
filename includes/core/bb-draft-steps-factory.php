<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'bb_wizard_draft_steps_service' ) ) {

function bb_wizard_draft_steps_service() {

    $token_file = BB_PLUGIN_DIR . 'includes/core/bb-draft-steps-token.php';
    $repo_file  = BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-repo.php';
    $svc_file   = BB_PLUGIN_DIR . 'includes/domain/wizard/bb-draft-steps-service.php';

    foreach ( array($token_file, $repo_file, $svc_file) as $f ) {
        if ( ! file_exists($f) ) {
            error_log('[BB] Missing file: ' . $f);
            return null;
        }
        require_once $f;
    }

    if ( ! class_exists('BB_Draft_Steps_Token') ) {
        error_log('[BB] Missing class BB_Draft_Steps_Token');
        return null;
    }
    if ( ! class_exists('BB_Draft_Steps_Repo') ) {
        error_log('[BB] Missing class BB_Draft_Steps_Repo');
        return null;
    }
    if ( ! class_exists('BB_Draft_Steps_Service') ) {
        error_log('[BB] Missing class BB_Draft_Steps_Service');
        return null;
    }

    try {
        $token = BB_Draft_Steps_Token::get_or_create();
        $repo  = new BB_Draft_Steps_Repo();
        return new BB_Draft_Steps_Service( $repo, $token, 24 ); // TTL 24h
    } catch (Throwable $e) {
        error_log('[BB] Draft service build failed: ' . $e->getMessage());
        return null;
    }
}

}

