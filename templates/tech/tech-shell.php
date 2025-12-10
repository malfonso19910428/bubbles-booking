<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Technician Dashboard Shell
 *
 * Variables recibidas desde BB_Tech_Shell:
 *  - $bb_current_user         (WP_User)
 *  - $bb_current_view         (string)
 *  - $bb_weekly_availability  (array)  // para la vista "availability"
 *  - $bb_profile              (array)  // para la vista "profile"
 */

// Usuario
$current_user = isset( $bb_current_user ) && $bb_current_user instanceof WP_User
    ? $bb_current_user
    : wp_get_current_user();

// Vista actual (overview, profile, availability, schedule, jobs)
$current_view = isset( $bb_current_view ) ? $bb_current_view : 'overview';

// URL base de la página donde está el shortcode
$current_url = get_permalink();

// URLs para el menú
$overview_url     = add_query_arg( 'bb_view', 'overview',     $current_url );
$profile_url      = add_query_arg( 'bb_view', 'profile',      $current_url );
$availability_url = add_query_arg( 'bb_view', 'availability', $current_url );
$schedule_url     = add_query_arg( 'bb_view', 'schedule',     $current_url );
$jobs_url         = add_query_arg( 'bb_view', 'jobs',         $current_url );
$logout_url       = wp_logout_url( home_url( '/' ) );

// Mapeo vista → archivo de plantilla
$view_map = array(
    'overview'     => BB_PLUGIN_DIR . 'templates/tech/dashboard.php',     // si la tienes
    'profile'      => BB_PLUGIN_DIR . 'templates/tech/tech-profile.php',  // <- nombre actual
    'availability' => BB_PLUGIN_DIR . 'templates/tech/availability.php',
    'schedule'     => BB_PLUGIN_DIR . 'templates/tech/schedule.php',      // opcional
    'jobs'         => BB_PLUGIN_DIR . 'templates/tech/bookings.php',      // opcional
);

$view_template = isset( $view_map[ $current_view ] ) ? $view_map[ $current_view ] : '';
?>

<div class="bb-tech-shell-wrapper">
    <div class="bb-tech-header">
        <h2>Technician Dashboard</h2>
        <p>Hello, <?php echo esc_html( $current_user->display_name ); ?>.</p>
    </div>

    <div class="bb-tech-layout">
        <!-- Sidebar -->
        <aside class="bb-tech-sidebar">
            <nav class="bb-tech-menu">
                <ul>
                    <li class="<?php echo ( $current_view === 'overview' ) ? 'is-active' : ''; ?>">
                        <a href="<?php echo esc_url( $overview_url ); ?>">Overview</a>
                    </li>
                    <li class="<?php echo ( $current_view === 'profile' ) ? 'is-active' : ''; ?>">
                        <a href="<?php echo esc_url( $profile_url ); ?>">My profile</a>
                    </li>
                    <li class="<?php echo ( $current_view === 'availability' ) ? 'is-active' : ''; ?>">
                        <a href="<?php echo esc_url( $availability_url ); ?>">My availability</a>
                    </li>
                    <li class="<?php echo ( $current_view === 'schedule' ) ? 'is-active' : ''; ?>">
                        <a href="<?php echo esc_url( $schedule_url ); ?>">My schedule</a>
                    </li>
                    <li class="<?php echo ( $current_view === 'jobs' ) ? 'is-active' : ''; ?>">
                        <a href="<?php echo esc_url( $jobs_url ); ?>">My jobs</a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( $logout_url ); ?>">Log out</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Contenido principal -->
        <main class="bb-tech-content">
            <?php
            if ( $view_template && file_exists( $view_template ) ) {
                // Las vistas (tech-profile.php, availability.php, etc.)
                // reciben $bb_current_user, $bb_weekly_availability, $bb_profile desde la clase BB_Tech_Shell.
                include $view_template;
            } else {
                echo '<div class="bb-tech-card">Missing view: <code>' . esc_html( $current_view ) . '</code></div>';
            }
            ?>
        </main>
    </div>
</div>
