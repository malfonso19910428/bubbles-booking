<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vista: My profile
 *
 * Variables esperadas:
 *  - $bb_current_user (WP_User)
 *  - $bb_profile      (array)
 */

$current_user = isset( $bb_current_user ) && $bb_current_user instanceof WP_User
    ? $bb_current_user
    : wp_get_current_user();

$profile = isset( $bb_profile ) && is_array( $bb_profile )
    ? $bb_profile
    : array();

// Valores desde nuestro perfil o, si están vacíos, desde WP_User
$first_name = ! empty( $profile['first_name'] )
    ? $profile['first_name']
    : get_user_meta( $current_user->ID, 'first_name', true );

$last_name = ! empty( $profile['last_name'] )
    ? $profile['last_name']
    : get_user_meta( $current_user->ID, 'last_name', true );

$email = ! empty( $profile['email'] )
    ? $profile['email']
    : $current_user->user_email;

$phone = isset( $profile['phone'] ) ? $profile['phone'] : '';

$bio    = isset( $profile['bio'] )    ? $profile['bio']    : '';
$radius = isset( $profile['radius'] ) ? $profile['radius'] : '';

$address = isset( $profile['address'] ) && is_array( $profile['address'] )
    ? $profile['address']
    : array();

$addr_line1 = isset( $address['line1'] ) ? $address['line1'] : '';
$addr_city  = isset( $address['city'] )  ? $address['city']  : '';
$addr_state = isset( $address['state'] ) ? $address['state'] : '';
$addr_zip   = isset( $address['zip'] )   ? $address['zip']   : '';

$languages = isset( $profile['languages'] ) && is_array( $profile['languages'] )
    ? $profile['languages']
    : array();

$avatar_id = isset( $profile['avatar_id'] ) ? (int) $profile['avatar_id'] : 0;

// Perfil incompleto si faltan campos críticos
$is_profile_incomplete = ( $first_name === '' || $last_name === '' || $email === '' );

// Status del perfil
$profile_status = isset( $profile['status'] ) && $profile['status']
    ? $profile['status']
    : 'pending';

// Mensajes
$profile_saved = isset( $_GET['profile_saved'] ) && $_GET['profile_saved'] == '1';
?>

<div class="bb-tech-card bb-tech-profile">
    <?php if ( $profile_saved ) : ?>
        <div class="bb-notice bb-notice--success">
            Profile saved successfully.
            <?php if ( $profile_status === 'pending' ) : ?>
                <br><small>Your profile is pending admin review.</small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ( $is_profile_incomplete ) : ?>
        <div class="bb-notice bb-notice--warning">
            Your profile is incomplete. Please fill in the required fields so customers can trust your information.
        </div>
    <?php endif; ?>

    <div class="bb-tech-profile-status bb-tech-profile-status--<?php echo esc_attr( $profile_status ); ?>">
        <strong>Profile status:</strong>
        <?php echo esc_html( ucfirst( $profile_status ) ); ?>
        <?php if ( $profile_status === 'pending' ) : ?>
            <br><small>Your profile is under review and may not be visible to customers yet.</small>
        <?php endif; ?>
    </div>

    <h2>My profile</h2>
    <p>Update your personal information, contact details, and service area.</p>

    <!-- 👇 IMPORTANTE: enctype para subir archivo -->
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'bb_save_profile', 'bb_profile_nonce' ); ?>
        <input type="hidden" name="bb_profile_action" value="save">

        <!-- Profile photo -->
        <h3>Profile photo</h3>

        <div class="bb-tech-profile-avatar">
            <div id="bb_profile_avatar_preview">
                <?php
                if ( $avatar_id ) {
                    echo wp_get_attachment_image( $avatar_id, 'thumbnail', false, array(
                        'class' => 'bb-profile-avatar',
                    ) );
                } else {
                    echo get_avatar( $current_user->ID, 96, '', '', array(
                        'class' => 'bb-profile-avatar',
                    ) );
                }
                ?>
            </div>
            <p>
                <label for="bb_profile_avatar">Upload new profile photo</label><br>
                <input type="file"
                       name="bb_profile_avatar"
                       id="bb_profile_avatar"
                       accept="image/*">
            </p>
        </div>

        <!-- Personal info -->
        <h3>Personal info</h3>

        <div class="bb-field-row">
            <div class="bb-field">
                <label>First name</label>
                <input type="text" name="profile[first_name]" value="<?php echo esc_attr( $first_name ); ?>">
            </div>
            <div class="bb-field">
                <label>Last name</label>
                <input type="text" name="profile[last_name]" value="<?php echo esc_attr( $last_name ); ?>">
            </div>
        </div>

        <div class="bb-field-row">
            <div class="bb-field">
                <label>Email</label>
                <input type="email" name="profile[email]" value="<?php echo esc_attr( $email ); ?>">
            </div>
            <div class="bb-field">
                <label>Phone number</label>
                <input type="text" name="profile[phone]" value="<?php echo esc_attr( $phone ); ?>" placeholder="(555) 123-4567">
            </div>
        </div>

        <div class="bb-field">
            <label>Short bio</label>
            <textarea name="profile[bio]" rows="4"><?php echo esc_textarea( $bio ); ?></textarea>
        </div>

        <!-- Service area -->
        <h3>Service area</h3>

        <div class="bb-field-row">
            <div class="bb-field">
                <label>Radius (miles)</label>
                <input type="number" step="5" min="5" name="profile[radius]" value="<?php echo esc_attr( $radius ); ?>">
            </div>
        </div>

        <div class="bb-field">
            <label>Street address</label>
            <input type="text" name="profile[address_line1]" value="<?php echo esc_attr( $addr_line1 ); ?>">
        </div>

        <div class="bb-field-row">
            <div class="bb-field">
                <label>City</label>
                <input type="text" name="profile[city]" value="<?php echo esc_attr( $addr_city ); ?>">
            </div>
            <div class="bb-field">
                <label>State</label>
                <input type="text" name="profile[state]" value="<?php echo esc_attr( $addr_state ); ?>">
            </div>
            <div class="bb-field">
                <label>ZIP code</label>
                <input type="text" name="profile[zip]" value="<?php echo esc_attr( $addr_zip ); ?>">
            </div>
        </div>

        <!-- Languages -->
        <h3>Languages</h3>
        <?php
        $lang_options = array(
            'en' => 'English',
            'es' => 'Spanish',
            'pt' => 'Portuguese',
        );
        ?>
        <div class="bb-field bb-field--inline-checkboxes">
            <?php foreach ( $lang_options as $code => $label ) : ?>
                <label>
                    <input type="checkbox" name="profile[languages][]" value="<?php echo esc_attr( $code ); ?>"
                        <?php checked( in_array( $code, $languages, true ) ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <p class="bb-tech-profile-actions">
            <button type="submit" class="button button-primary">Save profile</button>
        </p>
    </form>
</div>
