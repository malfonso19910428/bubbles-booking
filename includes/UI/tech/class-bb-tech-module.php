<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BB_Tech_Module {

    /** @var BB_Tech_Shell */
    protected $shell;

    /** @var TechAvailabilityService */
    protected $availability_service;

    /** @var TechProfileService */
    protected $profile_service;

    /** @var BB_Tech_Profile_Controller */
    protected $profile_controller;

    private static bool $assets_enqueued = false;

    public function __construct() {
        $this->includes();
        $this->init_services_and_shell();

        add_shortcode( 'bb_staff_dashboard', array( $this, 'render_staff_dashboard' ) );

        add_filter(
            'ajax_query_attachments_args',
            array( $this, 'restrict_media_library_for_techs' )
        );
    }

    private function includes(): void {

        require_once BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityRepo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/availability/TechAvailabilityService.php';

        require_once BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileRepo.php';
        require_once BB_PLUGIN_DIR . 'includes/domain/tech/profile/TechProfileService.php';

        require_once BB_PLUGIN_DIR . 'includes/UI/tech/class-bb-tech-profile.php';
        require_once BB_PLUGIN_DIR . 'includes/UI/tech/class-bb-tech-shell.php';
    }

    private function init_services_and_shell(): void {

        $this->availability_service = new TechAvailabilityService(
            new TechAvailabilityRepo()
        );

        $this->profile_service = new TechProfileService(
            new TechProfileRepo()
        );

        if ( class_exists( 'BB_Tech_Profile_Controller' ) ) {
            $this->profile_controller = new BB_Tech_Profile_Controller( $this->profile_service );
            $this->profile_controller->register_hooks();
        }

        $this->shell = new BB_Tech_Shell(
            $this->availability_service,
            $this->profile_service
        );
    }

    private function ensure_assets_enqueued(): void {

        if ( self::$assets_enqueued ) return;
        self::$assets_enqueued = true;

        // ✅ IMPORTANT: media stack BEFORE your avatar JS uses wp.media
        if ( function_exists( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        // ===== CSS =====
        $css_path = BB_PLUGIN_DIR . 'assets/css/tech/tech.css';
        $ver_css  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';

        wp_enqueue_style(
            'bb-tech-dashboard',
            BB_PLUGIN_URL . 'assets/css/tech/tech.css',
            array(),
            $ver_css
        );

        // ===== Autocomplete TECH =====
        $ac_path = BB_PLUGIN_DIR . 'assets/js/tech/autocomplete-address.js';
        $ac_ver  = file_exists( $ac_path ) ? filemtime( $ac_path ) : '1.0.0';

        wp_enqueue_script(
            'bb-tech-address-autocomplete',
            BB_PLUGIN_URL . 'assets/js/tech/autocomplete-address.js',
            array( 'jquery' ),
            $ac_ver,
            true
        );

        if ( function_exists( 'bb_google_places_loader' ) ) {
            bb_google_places_loader( 'bb-tech-address-autocomplete' );
        }

        // ===== Avatar (Media modal) =====
        $avatar_path = BB_PLUGIN_DIR . 'assets/js/tech/tech-avatar.js';
        $avatar_ver  = file_exists( $avatar_path ) ? filemtime( $avatar_path ) : '1.0.0';

        wp_enqueue_script(
            'bb-tech-avatar-js',
            BB_PLUGIN_URL . 'assets/js/tech/tech-avatar.js',
            array( 'jquery' ),
            $avatar_ver,
            true
        );
    }

    /**
     * ✅ Dashboard shortcode
     * - If not logged in: show login form + redirect back here
     * - If logged in but not staff/admin: show message (+ optional apply link)
     */
    public function render_staff_dashboard( $atts = array(), $content = '' ) {

        $this->ensure_assets_enqueued();

        ob_start();

        $dashboard_url = $this->get_staff_dashboard_url();

        // ✅ Not logged in => show login form (with redirect back)
        if ( ! is_user_logged_in() ) {

            // If WP is set to block access, this still works as normal WP login.
            echo '<div class="bb-tech-card bb-tech-login">';

            echo '<h2>Staff login</h2>';
            echo '<p>Please log in to access the staff dashboard.</p>';

            // Optional: show any login error message passed by wp-login
            if ( isset($_GET['login']) && $_GET['login'] === 'failed' ) {
                echo '<div class="bb-notice bb-notice--warning">Login failed. Please try again.</div>';
            }

            wp_login_form( array(
                'echo'           => true,
                'redirect'       => $dashboard_url,
                'form_id'        => 'bb_staff_loginform',
                'label_username' => __( 'Email or Username' ),
                'label_password' => __( 'Password' ),
                'label_remember' => __( 'Remember Me' ),
                'label_log_in'   => __( 'Log In' ),
                'remember'       => true,
            ) );

            echo '<p style="margin-top:10px;">';
            echo '<a href="' . esc_url( wp_lostpassword_url( $dashboard_url ) ) . '">Forgot password?</a>';
            echo '</p>';

            // OPTIONAL: If you have an apply/register page, set it here:
            // echo '<p><a class="button" href="' . esc_url( home_url('/become-a-tech/') ) . '">Apply to be a technician</a></p>';

            echo '</div>';

            return ob_get_clean();
        }

        // ✅ Logged in => role check
        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        if ( ! array_intersect( array( 'bb_staff', 'administrator' ), $roles ) ) {

            echo '<div class="bb-tech-card bb-tech-no-access">';
            echo '<h2>No access</h2>';
            echo '<p>Your account does not have permission to view this page.</p>';

            // Optional: show who is logged in + logout link
            echo '<p style="opacity:.8;">Logged in as <strong>' . esc_html( $user->user_login ) . '</strong></p>';
            echo '<p><a href="' . esc_url( wp_logout_url( $dashboard_url ) ) . '">Log out</a></p>';

            // OPTIONAL apply link:
            // echo '<p><a class="button" href="' . esc_url( home_url('/become-a-tech/') ) . '">Apply to be a technician</a></p>';

            echo '</div>';

            return ob_get_clean();
        }

        // ✅ Authorized => render shell
        $this->shell->render( $user );

        return ob_get_clean();
    }

    /**
     * Returns the staff dashboard URL (safe redirect target)
     */
    private function get_staff_dashboard_url(): string {
        // If your staff dashboard is a WP page slug "staff-dashboard"
        if ( function_exists('get_permalink') ) {
            $page = get_page_by_path( 'staff-dashboard' );
            if ( $page ) {
                return (string) get_permalink( $page->ID );
            }
        }
        // fallback
        return home_url( '/staff-dashboard/' );
    }

    public function restrict_media_library_for_techs( $query ) {

        if ( current_user_can( 'manage_options' ) ) {
            return $query;
        }

        $user = wp_get_current_user();
        if ( $user && $user->ID && in_array( 'bb_staff', (array) $user->roles, true ) ) {
            $query['author'] = $user->ID;
        }

        return $query;
    }
}
