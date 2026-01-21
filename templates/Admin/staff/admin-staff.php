<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template: Admin Staff
 * Expected:
 * - array $bb_view
 *   - users: rows
 *   - flash: ['msg'=>...]
 */

$bb_view = isset($bb_view) && is_array($bb_view) ? $bb_view : array();

$users = isset($bb_view['users']) && is_array($bb_view['users']) ? $bb_view['users'] : array();
$flash = isset($bb_view['flash']) && is_array($bb_view['flash']) ? $bb_view['flash'] : array();

$msg = (string)($flash['msg'] ?? '');

function bb_staff_msg_text( string $msg ): string {
    switch ( $msg ) {
        case 'approved':     return 'Technician approved.';
        case 'rejected':     return 'Technician rejected.';
        case 'invalid_tech': return 'Invalid technician.';
        case 'not_staff':    return 'User is not staff.';
        default:             return '';
    }
}

function bb_staff_badge( string $status ): string {
    $status = $status ?: 'pending';
    $map = array(
        'approved' => array('Approved', '#46b450'),
        'pending'  => array('Pending',  '#ffb900'),
        'rejected' => array('Rejected', '#dc3232'),
    );
    $d = $map[$status] ?? array( ucfirst($status), '#777' );
    $label = $d[0];
    $color = $d[1];

    return '<span style="display:inline-block;padding:3px 8px;border-radius:12px;background:' . esc_attr($color) . ';color:#fff;font-size:12px;line-height:1;">' . esc_html($label) . '</span>';
}

function bb_staff_ok_badge( bool $ok, string $ok_text = 'OK', string $bad_text = 'Missing' ): string {
    $color = $ok ? '#46b450' : '#dc3232';
    $label = $ok ? $ok_text : $bad_text;
    return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;background:' . esc_attr($color) . ';color:#fff;font-size:12px;line-height:1;">' . esc_html($label) . '</span>';
}

?>
<div class="wrap">
  <h1>Staff</h1>

  <?php if ( $msg !== '' ) : ?>
    <?php $t = bb_staff_msg_text($msg); ?>
    <?php if ( $t !== '' ) : ?>
      <div class="notice notice-success is-dismissible"><p><?php echo esc_html($t); ?></p></div>
    <?php endif; ?>
  <?php endif; ?>

  <p>
    Approve or reject technicians.
    Admin approves based on <strong>photo</strong>, <strong>bio</strong>, <strong>location</strong>, <strong>email</strong> and <strong>phone</strong>.
    Radius is informational (not approved).
  </p>

  <style>
    .bb-staff-nowrap { white-space: nowrap; }
    .bb-staff-ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bb-staff-small { font-size: 12px; opacity: .85; }
    .bb-staff-actions form { display:inline-block; margin-right:8px; vertical-align: top; }
    .bb-staff-actions input[type="text"] { width: 160px; }
    .bb-staff-cell-pad > div { margin-bottom: 4px; }
  </style>

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width:60px;">ID</th>
        <th style="width:70px;">Photo</th>
        <th style="width:260px;">Name</th>
        <th style="width:230px;">Contact</th>
        <th style="width:110px;">Languages</th>
        <th style="width:120px;">Status</th>
        <th style="width:90px;">Radius</th>
        <th style="width:330px;">Location</th>
        <th style="width:280px;">Bio</th>
        <th style="width:320px;">Actions</th>
      </tr>
    </thead>
    <tbody>

    <?php if ( empty($users) ) : ?>
      <tr><td colspan="10">No staff users found (role: bb_staff).</td></tr>
    <?php else : ?>
      <?php foreach ( $users as $r ) :
        $tech_id = (int)($r['id'] ?? 0);
        $status  = (string)($r['status'] ?? 'pending');

        $radius  = (string)($r['radius'] ?? '');
        $address = (string)($r['address'] ?? '');

        $lat = (string)($r['geo_lat'] ?? '');
        $lng = (string)($r['geo_lng'] ?? '');

        $has_geo     = ! empty($r['has_geo']);
        $has_photo   = ! empty($r['has_photo']);
        $has_bio     = ! empty($r['has_bio']);
        $has_address = ! empty($r['has_address']);

        $phone = (string)($r['phone'] ?? '');
        $email = (string)($r['email'] ?? '');

        $has_email = ($email !== '');
        $has_phone = ($phone !== '');

        $langs = isset($r['languages']) && is_array($r['languages']) ? $r['languages'] : array();
        $langs_txt = ! empty($langs) ? implode(', ', array_map('strtoupper', $langs)) : '—';

        $bio = (string)($r['bio'] ?? '');
        $bio_short = $bio !== '' ? wp_trim_words( wp_strip_all_tags($bio), 16, '…' ) : '—';

        $avatar_id = (int)($r['avatar_id'] ?? 0);

        $geo_txt = $has_geo ? ($lat . ', ' . $lng) : '—';
        $name = (string)($r['name'] ?? '');

        // ✅ Ready / Not ready (tooltip explains missing)
        $missing = array();
        if ( ! $has_photo )   $missing[] = 'Photo';
        if ( ! $has_bio )     $missing[] = 'Bio';
        if ( ! $has_address ) $missing[] = 'Address';
        if ( ! $has_geo )     $missing[] = 'Geo';
        if ( ! $has_email )   $missing[] = 'Email';
        if ( ! $has_phone )   $missing[] = 'Phone';

        $is_ready = empty( $missing );
        $missing_text = $is_ready
          ? 'All required info provided'
          : ('Missing: ' . implode(', ', $missing));
      ?>
        <tr>
          <td class="bb-staff-nowrap"><?php echo esc_html((string)$tech_id); ?></td>

          <td class="bb-staff-nowrap">
            <?php
              if ( $avatar_id > 0 ) {
                  echo wp_get_attachment_image( $avatar_id, array(48,48), false, array('style'=>'border-radius:50%;') );
              } else {
                  echo get_avatar( $tech_id, 48, '', '', array('style'=>'border-radius:50%;') );
              }
            ?>
            <div style="margin-top:6px;">
              <?php echo bb_staff_ok_badge( $has_photo, 'Photo', 'No photo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
          </td>

          <td>
            <div class="bb-staff-ellipsis" style="max-width:250px;">
              <strong><?php echo esc_html($name !== '' ? $name : ('User #' . $tech_id)); ?></strong>
            </div>
            <div class="bb-staff-small bb-staff-ellipsis" style="max-width:250px;">
              <?php echo esc_html($email !== '' ? $email : '—'); ?>
            </div>
          </td>

          <td class="bb-staff-nowrap bb-staff-cell-pad">
            <div><strong>Email:</strong> <?php echo esc_html($email !== '' ? $email : '—'); ?></div>
            <div><strong>Phone:</strong> <?php echo esc_html($phone !== '' ? $phone : '—'); ?></div>
            <div class="bb-staff-small">
              <?php echo bb_staff_ok_badge( $has_email, 'Email', 'No email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              <?php echo ' '; ?>
              <?php echo bb_staff_ok_badge( $has_phone, 'Phone', 'No phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
          </td>

          <td class="bb-staff-nowrap"><?php echo esc_html($langs_txt); ?></td>

          <td class="bb-staff-nowrap">
            <?php echo bb_staff_badge($status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </td>

          <td class="bb-staff-nowrap"><?php echo esc_html($radius !== '' ? $radius . ' mi' : '—'); ?></td>

          <td>
            <div style="margin-bottom:6px;">
              <?php echo bb_staff_ok_badge( $has_address, 'Address', 'No address' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              <?php echo ' '; ?>
              <?php echo bb_staff_ok_badge( $has_geo, 'Geo', 'No geo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>

            <div class="bb-staff-small">
              <strong>Address:</strong>
              <span><?php echo esc_html($address !== '' ? $address : '—'); ?></span>
            </div>

            <div class="bb-staff-small bb-staff-nowrap">
              <strong>Geo:</strong> <?php echo esc_html($geo_txt); ?>
            </div>
          </td>

          <td>
            <?php echo bb_staff_ok_badge( $has_bio, 'Bio', 'No bio' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="bb-staff-small" style="margin-top:6px;">
              <?php echo esc_html($bio_short); ?>
            </div>
          </td>

          <td class="bb-staff-actions">
            <!-- ✅ Ready / Not ready message with tooltip -->
            <div
              style="margin-bottom:6px;font-weight:600;color:<?php echo $is_ready ? '#46b450' : '#dc3232'; ?>;"
              title="<?php echo esc_attr($missing_text); ?>"
            >
              <?php echo $is_ready ? 'Ready for approval' : 'Not ready for approval'; ?>
            </div>

            <form method="post">
              <?php wp_nonce_field('bb_staff_action', 'bb_staff_nonce'); ?>
              <input type="hidden" name="tech_id" value="<?php echo esc_attr((string)$tech_id); ?>">
              <input type="hidden" name="bb_staff_action" value="approve">
              <button class="button button-primary" type="submit" <?php disabled($status === 'approved'); ?>>
                Approve
              </button>
            </form>

            <form method="post">
              <?php wp_nonce_field('bb_staff_action', 'bb_staff_nonce'); ?>
              <input type="hidden" name="tech_id" value="<?php echo esc_attr((string)$tech_id); ?>">
              <input type="hidden" name="bb_staff_action" value="reject">
              <input type="text" name="reject_reason" placeholder="Reason (optional)">
              <button class="button" type="submit" <?php disabled($status === 'rejected'); ?>>
                Reject
              </button>
            </form>

            <!-- ✅ Checklist + tooltip explaining missing -->
            <div
              class="bb-staff-small"
              style="margin-top:8px;"
              title="<?php echo esc_attr($missing_text); ?>"
            >
              Checklist:
              Photo <?php echo $has_photo ? '✅' : '❌'; ?> |
              Bio <?php echo $has_bio ? '✅' : '❌'; ?> |
              Location <?php echo ($has_address && $has_geo) ? '✅' : '❌'; ?> |
              Email <?php echo $has_email ? '✅' : '❌'; ?> |
              Phone <?php echo $has_phone ? '✅' : '❌'; ?>
            </div>
          </td>

        </tr>
      <?php endforeach; ?>
    <?php endif; ?>

    </tbody>
  </table>
</div>
