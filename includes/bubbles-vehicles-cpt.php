<?php
// 1) CPT Vehicles
add_action('init', function () {
    register_post_type('bubbles_vehicle', [
        'label' => 'Vehicles',
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-car',
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
});

// 2) Guest key (cookie 1 año)
function bubbles_get_guest_key() {
    $cookie = 'bubbles_guest_key';
    if (!empty($_COOKIE[$cookie])) return sanitize_text_field($_COOKIE[$cookie]);
    $key = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('guest_', true);
    setcookie($cookie, $key, time() + 365*DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    $_COOKIE[$cookie] = $key;
    return $key;
}

// 3) Guardar/actualizar vehículo (evita duplicado exacto por propietario)
function bubbles_save_vehicle($args) {
    $year  = isset($args['year'])  ? sanitize_text_field($args['year'])  : '';
    $make  = isset($args['make'])  ? sanitize_text_field($args['make'])  : '';
    $model = isset($args['model']) ? sanitize_text_field($args['model']) : '';
    $color = isset($args['color']) ? sanitize_text_field($args['color']) : '';

    $owner_user_id = is_user_logged_in() ? get_current_user_id() : 0;
    $guest_key     = $owner_user_id ? '' : bubbles_get_guest_key();

    $meta_query = [
        'relation' => 'AND',
        ['key'=>'year','value'=>$year],
        ['key'=>'make','value'=>$make],
        ['key'=>'model','value'=>$model],
        ['key'=>'color','value'=>$color],
        $owner_user_id
            ? ['key'=>'owner_user_id','value'=>$owner_user_id]
            : ['key'=>'guest_key','value'=>$guest_key],
    ];

    $dups = get_posts([
        'post_type' => 'bubbles_vehicle',
        'post_status' => 'private',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => $meta_query,
    ]);

    if (!empty($dups)) {
        update_post_meta($dups[0], 'updated_at', time());
        return (int)$dups[0];
    }

    $title = trim("$year $make $model ($color)");
    $post_id = wp_insert_post([
        'post_type'   => 'bubbles_vehicle',
        'post_status' => 'private',
        'post_title'  => $title,
    ]);

    if (!is_wp_error($post_id) && $post_id) {
        update_post_meta($post_id, 'year', $year);
        update_post_meta($post_id, 'make', $make);
        update_post_meta($post_id, 'model', $model);
        update_post_meta($post_id, 'color', $color);
        update_post_meta($post_id, 'created_at', time());
        update_post_meta($post_id, 'updated_at', time());
        if ($owner_user_id) update_post_meta($post_id, 'owner_user_id', $owner_user_id);
        else update_post_meta($post_id, 'guest_key', $guest_key);
    }
    return (int)$post_id;
}

// 4) Listar vehículos del “dueño actual” (user o guest)
function bubbles_list_my_vehicles($limit = 20) {
    $meta_query = [
        is_user_logged_in()
            ? ['key'=>'owner_user_id','value'=>get_current_user_id()]
            : ['key'=>'guest_key','value'=>bubbles_get_guest_key()],
    ];
    return get_posts([
        'post_type' => 'bubbles_vehicle',
        'post_status' => 'private',
        'posts_per_page' => $limit,
        'meta_query' => $meta_query,
        'orderby' => 'meta_value_num',
        'meta_key' => 'updated_at',
        'order' => 'DESC',
    ]);
}

// 5) Borrar vehículo (solo si es del dueño actual)
function bubbles_delete_vehicle($post_id) {
    $post_id = (int)$post_id;
    if (!$post_id) return false;
    $owner_user_id = (int) get_post_meta($post_id, 'owner_user_id', true);
    $guest_key     = (string) get_post_meta($post_id, 'guest_key', true);

    $can = false;
    if (is_user_logged_in() && $owner_user_id === get_current_user_id()) $can = true;
    if (!$owner_user_id && $guest_key && !$can) $can = ($guest_key === bubbles_get_guest_key());

    if (!$can) return false;
    wp_delete_post($post_id, true);
    return true;
}

// 6) Al iniciar sesión: migrar vehículos del guest a la cuenta
add_action('wp_login', function($user_login, $user) {
    $uid = $user->ID;
    $guest_key = !empty($_COOKIE['bubbles_guest_key']) ? sanitize_text_field($_COOKIE['bubbles_guest_key']) : '';
    if (!$guest_key) return;

    $guest_posts = get_posts([
        'post_type' => 'bubbles_vehicle',
        'post_status' => 'private',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [['key'=>'guest_key','value'=>$guest_key]],
    ]);
    foreach ($guest_posts as $pid) {
        delete_post_meta($pid, 'guest_key');
        update_post_meta($pid, 'owner_user_id', $uid);
        update_post_meta($pid, 'updated_at', time());
    }
}, 10, 2);
// Columnas del admin para Vehicles
add_filter('manage_bubbles_vehicle_posts_columns', function($cols){
    $new = [];
    $new['cb']      = $cols['cb'];
    $new['title']   = __('Vehicle', 'bubbles');
    $new['year']    = __('Year', 'bubbles');
    $new['make']    = __('Make', 'bubbles');
    $new['model']   = __('Model', 'bubbles');
    $new['color']   = __('Color', 'bubbles');
    $new['owner']   = __('Owner / Guest', 'bubbles');
    $new['updated'] = __('Updated', 'bubbles');
    return $new;
});

add_action('manage_bubbles_vehicle_posts_custom_column', function($col, $post_id){
    if ($col==='year')    echo esc_html(get_post_meta($post_id,'year',true));
    if ($col==='make')    echo esc_html(get_post_meta($post_id,'make',true));
    if ($col==='model')   echo esc_html(get_post_meta($post_id,'model',true));
    if ($col==='color')   echo esc_html(get_post_meta($post_id,'color',true));
    if ($col==='owner') {
        $uid = (int) get_post_meta($post_id,'owner_user_id',true);
        $gk  = get_post_meta($post_id,'guest_key',true);
        if ($uid) {
            $u = get_user_by('id',$uid);
            echo $u ? esc_html($u->user_login . ' (#'.$uid.')') : '—';
        } else {
            echo $gk ? 'Guest: ' . esc_html(substr($gk,0,10)) . '…' : '—';
        }
    }
    if ($col==='updated') {
        $t = (int) get_post_meta($post_id,'updated_at',true);
        echo $t ? esc_html(date_i18n(get_option('date_format').' '.get_option('time_format'), $t)) : '—';
    }
}, 10, 2);

add_filter('manage_edit-bubbles_vehicle_sortable_columns', function($cols){
    $cols['year'] = 'year';
    $cols['updated'] = 'updated';
    return $cols;
});

// Ordenar por meta (updated/year) cuando se hace clic en la cabecera
add_action('pre_get_posts', function($q){
    if (!is_admin() || !$q->is_main_query()) return;
    if ($q->get('post_type')!=='bubbles_vehicle') return;
    $orderby = $q->get('orderby');
    if ($orderby==='updated'){
        $q->set('meta_key','updated_at');
        $q->set('orderby','meta_value_num');
    } elseif ($orderby==='year'){
        $q->set('meta_key','year');
        $q->set('orderby','meta_value');
    }
});

