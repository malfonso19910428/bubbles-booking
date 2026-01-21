<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Technician Dashboard Shell
 *
 * Variables recibidas desde BB_Tech_Shell:
 *  - $bb_current_user         (WP_User)
 *  - $bb_current_view         (string)
 *  - $bb_weekly_availability  (array)  // vista "availability"
 *
 * ✅ NUEVO:
 *  - $bb_view                 (array)  // vista "profile" (profile + ui)
 *
 * ♻️ Compat viejo (si aún existe en algún lado):
 *  - $bb_profile              (array)  // vista "profile" legacy
 *
 *  - $bb_portfolio_approved   (array)  // portfolio aprobadas
 *  - $bb_portfolio_pending    (array)  // portfolio pendientes
 */

// Usuario
$current_user = isset( $bb_current_user ) && $bb_current_user instanceof WP_User
    ? $bb_current_user
    : wp_get_current_user();

// Vista actual
$current_view = isset( $bb_current_view ) ? $bb_current_view : 'overview';

// URL base
$current_url = get_permalink();

// URLs para el menú
$overview_url     = add_query_arg( 'bb_view', 'overview',     $current_url );
$profile_url      = add_query_arg( 'bb_view', 'profile',      $current_url );
$availability_url = add_query_arg( 'bb_view', 'availability', $current_url );
$schedule_url     = add_query_arg( 'bb_view', 'schedule',     $current_url );
$jobs_url         = add_query_arg( 'bb_view', 'jobs',         $current_url );
$portfolio_url    = add_query_arg( 'bb_view', 'portfolio',    $current_url );
$logout_url       = wp_logout_url( home_url( '/' ) );

// ✅ Asegura $bb_view para la vista profile (compat con $bb_profile viejo)
if ( $current_view === 'profile' ) {
    if ( ! isset($bb_view) || ! is_array($bb_view) ) {
        $bb_view = array();
    }

    // si no vino $bb_view, pero vino $bb_profile (legacy), lo envolvemos
    if ( empty($bb_view) && isset($bb_profile) && is_array($bb_profile) ) {
        $bb_view = array(
            'profile' => $bb_profile,
            'ui'      => array(
                'errors'   => array(),
                'messages' => array(),
                'preview_text' => '-',
                'profile_saved' => false,
            ),
        );
    }

    // ultra-safe: nunca dejes que falte la estructura
    if ( ! isset($bb_view['profile']) || ! is_array($bb_view['profile']) ) $bb_view['profile'] = array();
    if ( ! isset($bb_view['ui']) || ! is_array($bb_view['ui']) ) $bb_view['ui'] = array();
}

// Mapeo vista → archivo de plantilla
$view_map = array(
    'overview'     => BB_PLUGIN_DIR . 'templates/tech/dashboard.php',
    'profile'      => BB_PLUGIN_DIR . 'templates/tech/tech-profile.php',   // ✅ usa $bb_view
    'availability' => BB_PLUGIN_DIR . 'templates/tech/availability.php',
    'schedule'     => BB_PLUGIN_DIR . 'templates/tech/schedule.php',
    'jobs'         => BB_PLUGIN_DIR . 'templates/tech/bookings.php',
    'portfolio'    => BB_PLUGIN_DIR . 'templates/tech/tech-portfolio.php',
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
                    <li class="<?php echo ( $current_view === 'portfolio' ) ? 'is-active' : ''; ?>">
                        <a href="<?php echo esc_url( $portfolio_url ); ?>">My portfolio</a>
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
                include $view_template;
            } else {
                echo '<div class="bb-tech-card">Missing view: <code>' . esc_html( $current_view ) . '</code></div>';
            }
            ?>
        </main>
    </div>
</div>
