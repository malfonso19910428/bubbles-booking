<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: tech-availability.php
 *
 * Recibe:
 * - $bb_current_user          (WP_User) opcional
 * - $bb_weekly_availability   (array)   opcional
 */

// Usuario actual (técnico)
$current_user = isset( $bb_current_user ) && $bb_current_user instanceof WP_User
    ? $bb_current_user
    : wp_get_current_user();

$tech_id = $current_user->ID;

// Disponibilidad que viene desde Bubbles_Tech_Availability -> TechAvailabilityService
$weekly = isset( $bb_weekly_availability ) && is_array( $bb_weekly_availability )
    ? $bb_weekly_availability
    : array();

/**
 * Mapeo de días:
 * 1 = Monday, 2 = Tuesday, ..., 6 = Saturday, 0 = Sunday
 * (coincide con WEEKDAY() y con lo que suele usar MySQL)
 */
$days = array(
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    0 => 'Sunday',
);
?>

<section class="bb-staff-availability bb-staff-availability--frontend">
    <h2 class="bb-staff-availability__title">My availability</h2>

    <p class="bb-staff-availability__intro">
        Select the days and time ranges when you are available for jobs.
        Admin will use this information to assign bookings and build your schedule.
    </p>

    <form method="post" class="bb-staff-availability__form">

        <?php wp_nonce_field( 'bb_save_availability', 'bb_availability_nonce' ); ?>
        <input type="hidden" name="bb_availability_action" value="save" />

        <div class="bb-availability-list">
            <?php foreach ( $days as $weekday => $label ) :

                // De momento usamos un solo "slot" por día (índice 0)
                $slot = isset( $weekly[ $weekday ][0] ) ? $weekly[ $weekday ][0] : null;

                $start_value = $slot && ! empty( $slot['start_time'] )
                    ? substr( $slot['start_time'], 0, 5 ) // "HH:MM"
                    : '';

                $end_value = $slot && ! empty( $slot['end_time'] )
                    ? substr( $slot['end_time'], 0, 5 )
                    : '';

                // Consideramos "enabled" si hay algún horario cargado
                $enabled = ( $start_value !== '' && $end_value !== '' );
            ?>
                <div class="bb-availability-item">
                    <div class="bb-availability-row bb-availability-row--top">
                        <div class="bb-availability-day">
                            <?php echo esc_html( $label ); ?>
                        </div>

                        <label class="bb-availability-available">
                            <input type="checkbox"
                                   class="bb-availability-toggle"
                                   data-weekday="<?php echo esc_attr( $weekday ); ?>"
                                   <?php checked( $enabled, true ); ?> />
                            <span>Available</span>
                        </label>
                    </div>

                    <div class="bb-availability-row bb-availability-row--times">
                        <div class="bb-availability-time">
                            <label>
                                <span class="bb-availability-time__label">From</span>
                                <input type="time"
                                       name="availability[<?php echo esc_attr( $weekday ); ?>][0][start]"
                                       value="<?php echo esc_attr( $start_value ); ?>"
                                       class="bb-availability-time__input" />
                            </label>
                        </div>

                        <div class="bb-availability-time">
                            <label>
                                <span class="bb-availability-time__label">To</span>
                                <input type="time"
                                       name="availability[<?php echo esc_attr( $weekday ); ?>][0][end]"
                                       value="<?php echo esc_attr( $end_value ); ?>"
                                       class="bb-availability-time__input" />
                            </label>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bb-staff-availability__footer">
            <button type="submit" class="button button-primary">
                Save availability
            </button>
            <p class="bb-staff-availability__note">
                Your availability will be used to assign jobs and generate your schedule.
            </p>
        </div>
    </form>
</section>
