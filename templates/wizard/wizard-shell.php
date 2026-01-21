<?php
/**
 * Template: Wizard Shell
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$view = isset( $view_for_template ) && is_array( $view_for_template ) ? $view_for_template : array();

$steps        = isset( $view['steps'] ) ? (array) $view['steps'] : array( 'vehicle','package','addons','address','date','confirm','payment' );
$current_step = isset( $view['current_step'] ) ? (string) $view['current_step'] : 'vehicle';
$step_tpl     = isset( $view['step_template'] ) ? (string) $view['step_template'] : '';
$step_data    = isset( $view['step_data'] ) ? $view['step_data'] : array();
$errors       = isset( $view['errors'] ) ? (array) $view['errors'] : array();
$buttons      = isset( $view['buttons'] ) ? (array) $view['buttons'] : array(
    'back_disabled'     => true,
    'continue_disabled' => false,
);

// state completo (Draft DB)
$state = isset( $view['state'] ) && is_array( $view['state'] ) ? $view['state'] : array();

// Debug flag
if ( ! defined( 'BB_WIZARD_DEBUG' ) ) define( 'BB_WIZARD_DEBUG', false );
$can_debug = current_user_can( 'manage_options' ) && BB_WIZARD_DEBUG;

if ( ! function_exists( 'bb_wizard_step_label' ) ) {
    function bb_wizard_step_label( $slug ) {
        switch ( $slug ) {
            case 'vehicle': return 'Vehicle';
            case 'package': return 'Package & price';
            case 'addons':  return 'Add-ons';
            case 'address': return 'Address';
            case 'date':    return 'Date & time';
            case 'confirm': return 'Confirm';
            case 'payment': return 'Payment';
            default:        return ucfirst( $slug );
        }
    }
}

// Action limpio
$form_action = remove_query_arg(
    array( 's', 'bb_vehicle_submit', 'bb_step', 'bb_nav', 'bb_continue', 'bb_back' ),
    get_permalink()
);

// Token persistente (Draft token)
$bb_token = '';
$token_file = BB_PLUGIN_DIR . 'includes/core/bb-draft-steps-token.php';
if ( file_exists( $token_file ) ) {
    require_once $token_file;
    if ( class_exists( 'BB_Draft_Steps_Token' ) ) {
        $bb_token = (string) BB_Draft_Steps_Token::get_or_create();
    }
}

// fallback: si por alguna razón no cargó el token helper, intenta desde state
if ( $bb_token === '' && ! empty( $state['bb_token'] ) ) {
    $bb_token = (string) $state['bb_token'];
}
if ( $bb_token === '' && ! empty( $state['draft_token'] ) ) {
    $bb_token = (string) $state['draft_token'];
}

// En payment NO queremos que exista Continue
$hide_continue = ( $current_step === 'payment' );

// ======================================================
// Coverage info (solo para mostrar mensaje / debug)
// El DISABLE del botón se controla en JS (Address step)
// ======================================================
$coverage = null;
if ( isset($state['address']['coverage']) && is_array($state['address']['coverage']) ) {
    $coverage = $state['address']['coverage'];
} elseif ( isset($state['coverage']) && is_array($state['coverage']) ) {
    $coverage = $state['coverage'];
}

$coverage_yes = null;
if ( is_array($coverage) ) {
    if ( array_key_exists('yes', $coverage) ) {
        $coverage_yes = (bool) $coverage['yes'];
    } elseif ( array_key_exists('ok', $coverage) ) {
        $coverage_yes = (bool) $coverage['ok'];
    }
}

// Optional message (si quieres mostrarlo; NO bloquea botón aquí)
$coverage_block_msg = '';
if ( $current_step === 'address' && $coverage_yes === false ) {
    $reason = isset($coverage['reason']) ? (string) $coverage['reason'] : 'out_of_service_area';
    if ( $reason === 'invalid_location' ) {
        $coverage_block_msg = 'Please select a valid address from the suggestions.';
    } else {
        $coverage_block_msg = 'Sorry, we don’t currently serve this area.';
    }
}
?>
<div class="bb-wizard bb-wizard-wrapper">

    <div class="bb-steps-top">
        <?php foreach ( $steps as $index => $slug ) : ?>
            <?php
            $css_step = 'bb-step-item';
            if ( $slug === $current_step ) $css_step .= ' is-active';

            $cur_idx = array_search( $current_step, $steps, true );
            if ( false !== $cur_idx ) {
                $slug_idx = array_search( $slug, $steps, true );
                if ( false !== $slug_idx && (int) $slug_idx < (int) $cur_idx ) {
                    $css_step .= ' is-done';
                }
            }
            ?>
            <div class="<?php echo esc_attr( $css_step ); ?>">
                <?php echo esc_html( ( $index + 1 ) . '. ' . bb_wizard_step_label( $slug ) ); ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="bb-layout bb-left-content-right-summary">

        <main class="bb-step-content bb-panel">

            <h2 class="bb-step-title">
                <?php
                $idx = array_search( $current_step, $steps, true );
                $num = ( false === $idx ) ? 1 : ( (int) $idx + 1 );
                echo esc_html( 'Step ' . $num . ' · ' . bb_wizard_step_label( $current_step ) );
                ?>
            </h2>

            <?php if ( $can_debug ) : ?>
                <pre style="background:#f7f7ff;border:1px solid #cfcfff;padding:10px;overflow:auto;max-height:220px;">
<?php echo esc_html( print_r( array(
    'shell_file'    => $state['_dbg_shell_file'] ?? '',
    'last_post'     => $state['_dbg_shell_before_controller'] ?? array(),
    'bb_token'      => $bb_token,
    'coverage'      => $coverage,
    'coverage_yes'  => $coverage_yes,
), true ) ); ?>
                </pre>
            <?php endif; ?>

            <form id="bb-wizard-form" method="post" action="<?php echo esc_url( $form_action ); ?>" class="bb-wizard-form">

                <input type="hidden" name="bb_step" value="<?php echo esc_attr( $current_step ); ?>" />
                <input type="hidden" name="bb_nav" value="" />

                <?php if ( $bb_token !== '' ) : ?>
                    <input type="hidden" name="bb_token" value="<?php echo esc_attr( $bb_token ); ?>" />
                    <input type="hidden" id="bb_draft_token" name="bb_draft_token" value="<?php echo esc_attr( $bb_token ); ?>" />
                <?php endif; ?>

                <?php if ( $coverage_block_msg !== '' ) : ?>
                    <div class="bb-errors" style="margin-bottom:12px;">
                        <ul><li><?php echo esc_html( $coverage_block_msg ); ?></li></ul>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $errors ) ) : ?>
                    <div class="bb-errors">
                        <ul>
                            <?php foreach ( $errors as $err ) : ?>
                                <li><?php echo esc_html( $err ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php
                $inner_tpl = BB_PLUGIN_DIR . 'templates/wizard/' . $step_tpl;

                if ( is_array( $step_data ) ) extract( $step_data, EXTR_SKIP );
                $step = $step_data;

                if ( file_exists( $inner_tpl ) ) {
                    include $inner_tpl;
                } else {
                    echo '<p class="bb-error">Missing step template: ' . esc_html( $step_tpl ) . '</p>';
                }
                ?>

                <div class="bb-wizard-buttons">

                    <?php if ( empty( $buttons['back_disabled'] ) ) : ?>
                        <button type="submit" name="bb_back" value="1"
                                class="bb-btn bb-btn-back"
                                formnovalidate
                                onclick="this.form.bb_nav.value='back';">
                            &lt;&lt; Back
                        </button>
                    <?php endif; ?>

                    <?php if ( ! $hide_continue ) : ?>
                        <?php $continue_disabled = ( ! empty($buttons['continue_disabled']) ); ?>
                        <button
                            type="submit"
                            name="bb_continue"
                            value="1"
                            class="bb-btn bb-btn-continue<?php echo $continue_disabled ? ' is-disabled' : ''; ?>"
                            onclick="if(!this.disabled){ this.form.bb_nav.value='continue'; }"
                            <?php disabled( $continue_disabled ); ?>
                        >
                            Continue &gt;&gt;
                        </button>
                    <?php endif; ?>

                </div>

            </form>
        </main>

        <aside class="bb-summary bb-panel">
            <?php
            $summary = isset( $view['summary'] ) && is_array( $view['summary'] ) ? $view['summary'] : array();

            $tpl = BB_PLUGIN_DIR . 'templates/wizard/summary-sidebar.php';
            if ( file_exists( $tpl ) ) {
                include $tpl;
            } else {
                echo '<p class="bb-error">Missing summary template.</p>';
            }
            ?>
        </aside>

    </div>
</div>
