<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shell/layout del dashboard técnico
 */
class BB_Tech_Shell {

    /** @var TechAvailabilityService */
    private $availability_service;

    /** @var TechProfileService */
    private $profile_service;

    public function __construct( $availability_service, $profile_service ) {
        $this->availability_service = $availability_service;
        $this->profile_service      = $profile_service;
    }

    public function render( WP_User $user ): void {

        $bb_current_user = $user;

        $bb_current_view = isset( $_GET['bb_view'] )
            ? sanitize_key( wp_unslash( $_GET['bb_view'] ) )
            : 'overview';

        $bb_weekly_availability = array();
        $bb_view                = array();

        if ( $bb_current_view === 'availability' ) {
            $bb_weekly_availability = $this->availability_service->week_for_user(
                (int) $bb_current_user->ID
            );
        }

        if ( $bb_current_view === 'profile' ) {

            $profile = method_exists( $this->profile_service, 'get_profile_for_view' )
                ? $this->profile_service->get_profile_for_view( (int) $bb_current_user->ID )
                : $this->profile_service->get_profile( (int) $bb_current_user->ID );

            $bb_view = array(
                'profile' => $profile,
                'ui'      => array(
                    'messages'     => array(),
                    'errors'       => array(),
                    'preview_text' => '-',
                ),
            );
        }

        $template = BB_PLUGIN_DIR . 'templates/tech/tech-shell.php';

        if ( file_exists( $template ) ) {
            include $template;
            return;
        }

        echo '<p>Tech shell template not found: <code>' . esc_html( $template ) . '</code></p>';
    }
}
