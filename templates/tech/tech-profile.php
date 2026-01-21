<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Tech Profile Template (DEVICE UPLOAD VERSION) - FIXED
 * - Avatar: <input type="file"> (solo dispositivo) + preview
 * - Address: el input blanco SIEMPRE muestra la dirección completa guardada (formatted)
 * - Hidden fields: formatted/line1/city/state/zip + place_id/lat/lng (estos son los que se guardan)
 */

$p  = ( isset($bb_view['profile']) && is_array($bb_view['profile']) ) ? $bb_view['profile'] : array();
$ui = ( isset($bb_view['ui']) && is_array($bb_view['ui']) ) ? $bb_view['ui'] : array();

$errors   = ( isset($ui['errors']) && is_array($ui['errors']) ) ? $ui['errors'] : array();
$messages = ( isset($ui['messages']) && is_array($ui['messages']) ) ? $ui['messages'] : array();

$address   = ( isset($p['address']) && is_array($p['address']) ) ? $p['address'] : array();
$geo       = ( isset($p['geo']) && is_array($p['geo']) ) ? $p['geo'] : array();
$languages = ( isset($p['languages']) && is_array($p['languages']) ) ? $p['languages'] : array();

$err = function(string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};
$has_err = function(string $key) use ($err): bool {
    return $err($key) !== '';
};
$invalid_class = function(string $key) use ($has_err): string {
    return $has_err($key) ? 'bb-input--invalid' : '';
};

$avatar_id = (int)($p['avatar_id'] ?? 0);
$user_id   = (int)($p['user_id'] ?? 0);
$status    = (string)($p['status'] ?? 'pending');

// ✅ Address full display (prefer formatted; si no, line1)
$address_formatted = (string)($address['formatted'] ?? '');
$address_line1     = (string)($address['line1'] ?? '');
$address_input_val = $address_formatted !== '' ? $address_formatted : $address_line1;
?>

<div class="bb-tech-card bb-tech-profile">

  <?php if ( ! empty($messages) ) : ?>
    <?php foreach ( $messages as $m ) :
      $type = isset($m['type']) ? (string)$m['type'] : 'info';
      $text = isset($m['text']) ? (string)$m['text'] : '';
      if ( $text === '' ) continue;
    ?>
      <div class="bb-notice bb-notice--<?php echo esc_attr($type); ?>">
        <?php echo esc_html($text); ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="bb-tech-profile-status bb-tech-profile-status--<?php echo esc_attr($status); ?>">
    <strong>Profile status:</strong>
    <?php echo esc_html( ucfirst($status) ); ?>
  </div>

  <h2>My profile</h2>

  <form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('bb_save_profile', 'bb_profile_nonce'); ?>
    <input type="hidden" name="bb_profile_action" value="save">

    <h3>Profile photo</h3>

    <div class="bb-tech-profile-avatar">
      <div id="bb_profile_avatar_preview">
        <?php
        echo $avatar_id
          ? wp_get_attachment_image($avatar_id, 'thumbnail', false, array('class' => 'bb-profile-avatar'))
          : get_avatar($user_id, 96, '', '', array('class' => 'bb-profile-avatar'));
        ?>
      </div>

      <p style="margin-top:10px;">
        <label for="bb_profile_avatar">Upload new profile photo</label><br>
        <input type="file" name="bb_profile_avatar" id="bb_profile_avatar" accept="image/*">
      </p>

      <?php if ( ! empty($errors['avatar']) ) : ?>
        <div class="bb-field-error"><?php echo esc_html((string)$errors['avatar']); ?></div>
      <?php endif; ?>
    </div>

    <h3>Personal info</h3>

    <div class="bb-field-row">
      <div class="bb-field">
        <label>First name</label>
        <input
          type="text"
          name="profile[first_name]"
          value="<?php echo esc_attr((string)($p['first_name'] ?? '')); ?>"
          class="<?php echo esc_attr( $invalid_class('first_name') ); ?>"
        >
        <?php if ( $has_err('first_name') ) : ?>
          <div class="bb-field-error"><?php echo esc_html($err('first_name')); ?></div>
        <?php endif; ?>
      </div>

      <div class="bb-field">
        <label>Last name</label>
        <input
          type="text"
          name="profile[last_name]"
          value="<?php echo esc_attr((string)($p['last_name'] ?? '')); ?>"
          class="<?php echo esc_attr( $invalid_class('last_name') ); ?>"
        >
        <?php if ( $has_err('last_name') ) : ?>
          <div class="bb-field-error"><?php echo esc_html($err('last_name')); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="bb-field-row">
      <div class="bb-field">
        <label>Email</label>
        <input
          type="email"
          name="profile[email]"
          value="<?php echo esc_attr((string)($p['email'] ?? '')); ?>"
          class="<?php echo esc_attr( $invalid_class('email') ); ?>"
        >
        <?php if ( $has_err('email') ) : ?>
          <div class="bb-field-error"><?php echo esc_html($err('email')); ?></div>
        <?php endif; ?>
      </div>

      <div class="bb-field">
        <label>Phone number</label>
        <input
          type="text"
          name="profile[phone]"
          value="<?php echo esc_attr((string)($p['phone'] ?? '')); ?>"
          class="<?php echo esc_attr( $invalid_class('phone') ); ?>"
        >
        <?php if ( $has_err('phone') ) : ?>
          <div class="bb-field-error"><?php echo esc_html($err('phone')); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="bb-field">
      <label>Short bio</label>
      <textarea name="profile[bio]" rows="4"><?php echo esc_textarea((string)($p['bio'] ?? '')); ?></textarea>
    </div>

    <h3>Service area</h3>

    <div class="bb-field-row">
      <div class="bb-field">
        <label>Radius (miles)</label>
        <input
          type="number"
          step="5"
          min="5"
          name="profile[radius]"
          value="<?php echo esc_attr((string)($p['radius'] ?? '')); ?>"
          class="<?php echo esc_attr( $invalid_class('radius') ); ?>"
        >
        <?php if ( $has_err('radius') ) : ?>
          <div class="bb-field-error"><?php echo esc_html($err('radius')); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <h3>Address</h3>

    <div class="bb-field">
      <label>Street address</label>

      <!-- ✅ UI input: SOLO para mostrar/escribir, NO se guarda directo -->
      <input
        type="text"
        id="bb_tech_address_autocomplete"
        value="<?php echo esc_attr($address_input_val); ?>"
        placeholder="Start typing your address..."
        autocomplete="off"
        class="<?php echo esc_attr( $invalid_class('address') ); ?>"
        style="width:100%;max-width:520px;"
      >

      <?php if ( $has_err('address') ) : ?>
        <div class="bb-field-error"><?php echo esc_html($err('address')); ?></div>
      <?php endif; ?>

      <small style="opacity:.75;">Start typing and select your address from the suggestions.</small>
    </div>

    <!-- ✅ REAL (lo que se guarda) -->
    <input
      type="hidden"
      id="bb_tech_formatted"
      name="profile[address][formatted]"
      value="<?php echo esc_attr((string)($address['formatted'] ?? '')); ?>"
    >

    <input
      type="hidden"
      id="bb_tech_line1"
      name="profile[address][line1]"
      value="<?php echo esc_attr((string)($address['line1'] ?? '')); ?>"
    >

    <input type="hidden" id="bb_tech_city"  name="profile[address][city]"  value="<?php echo esc_attr((string)($address['city'] ?? '')); ?>">
    <input type="hidden" id="bb_tech_state" name="profile[address][state]" value="<?php echo esc_attr((string)($address['state'] ?? '')); ?>">
    <input type="hidden" id="bb_tech_zip"   name="profile[address][zip]"   value="<?php echo esc_attr((string)($address['zip'] ?? '')); ?>">

    <input type="hidden" id="bb_tech_place_id" name="profile[geo][place_id]" value="<?php echo esc_attr((string)($geo['place_id'] ?? '')); ?>">
    <input type="hidden" id="bb_tech_lat"      name="profile[geo][lat]"      value="<?php echo esc_attr((string)($geo['lat'] ?? '')); ?>">
    <input type="hidden" id="bb_tech_lng"      name="profile[geo][lng]"      value="<?php echo esc_attr((string)($geo['lng'] ?? '')); ?>">

    <h3>Languages</h3>
    <div class="bb-field bb-field--inline-checkboxes">
      <?php foreach ( array('en'=>'English','es'=>'Spanish','pt'=>'Portuguese') as $code => $label ) : ?>
        <label>
          <input
            type="checkbox"
            name="profile[languages][]"
            value="<?php echo esc_attr($code); ?>"
            <?php checked( in_array($code, $languages, true ) ); ?>
          >
          <?php echo esc_html($label); ?>
        </label>
      <?php endforeach; ?>
    </div>

    <p class="bb-tech-profile-actions">
      <button type="submit" class="button button-primary">Save profile</button>
    </p>

  </form>

</div>
