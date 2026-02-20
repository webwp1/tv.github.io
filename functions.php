<?php
// 1. ZÁKLADNÍ NASTAVENÍ ŠABLONY
function streamline_setup() {
    add_theme_support('title-tag');
}
add_action('after_setup_theme', 'streamline_setup');

// 2. REGISTRACE TYPU POSTU (URL bude /tv/moje-stranka)
add_action('init', function() {
    if (!session_id() && !is_admin()) { session_start(); }
    register_post_type('tv', [
        'labels' => ['name' => 'Streamy', 'singular_name' => 'Stream', 'menu_name' => 'Streamy'],
        'public' => true, 
        'show_ui' => true, 
        'supports' => ['title', 'thumbnail'],
        'menu_icon' => 'dashicons-video-alt3',
        'rewrite' => ['slug' => 'tv', 'with_front' => false],
        'show_in_rest' => true
    ]);
});

// Propojení se šablonou single-stream_page.php
add_filter('template_include', function($template) {
    if (is_singular('tv')) {
        $new_template = locate_template(array('single-stream_page.php'));
        if (!empty($new_template)) return $new_template;
    }
    return $template;
});

// 3. DATABÁZE (Spustí se jednou při aktivaci/vstupu do adminu)
add_action('admin_init', function() {
    global $wpdb;
    $t_logs = $wpdb->prefix . "sm_logs";
    $t_users = $wpdb->prefix . "sm_users";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta("CREATE TABLE $t_logs (id bigint(20) NOT NULL AUTO_INCREMENT, stream_name varchar(255), user_name varchar(100), ip_address varchar(50), time_start datetime, duration varchar(20), last_puls datetime, PRIMARY KEY (id)) {$wpdb->get_charset_collate()};");
    dbDelta("CREATE TABLE $t_users (id bigint(20) NOT NULL AUTO_INCREMENT, username varchar(100), password varchar(100), PRIMARY KEY (id)) {$wpdb->get_charset_collate()};");
});

// 4. ADMIN MENU (Uživatelé a Historie)
add_action('admin_menu', function() {
    add_submenu_page('edit.php?post_type=tv', 'Uživatelé', 'Uživatelé', 'manage_options', 'sm_users_manage', 'sm_users_render');
    add_submenu_page('edit.php?post_type=tv', 'Historie', 'Historie', 'manage_options', 'sm_history_show', 'sm_history_render');
});

function sm_users_render() {
    global $wpdb; $t = "{$wpdb->prefix}sm_users";
    if(isset($_POST['add'])) $wpdb->insert($t, ['username'=>$_POST['u'], 'password'=>$_POST['p']]);
    if(isset($_GET['del'])) $wpdb->delete($t, ['id'=>intval($_GET['del'])]);
    $users = $wpdb->get_results("SELECT * FROM $t");
    echo '<div class="wrap"><h1>Správa uživatelů</h1><form method="post"><input type="text" name="u" placeholder="Jméno"> <input type="text" name="p" placeholder="Heslo"> <input type="submit" name="add" value="Přidat" class="button button-primary"></form><br><table class="widefat striped"><thead><tr><th>Jméno</th><th>Heslo</th><th>Akce</th></tr></thead><tbody>';
    foreach($users as $u) echo "<tr><td><b>".esc_html($u->username)."</b></td><td>".esc_html($u->password)."</td><td><a href='?post_type=tv&page=sm_users_manage&del={$u->id}' class='button'>Smazat</a></td></tr>";
    echo '</tbody></table></div>';
}

function sm_history_render() {
    global $wpdb; $t = "{$wpdb->prefix}sm_logs";
    if(isset($_POST['clear_all'])) { $wpdb->query("TRUNCATE TABLE $t"); }
    if(isset($_GET['del_log'])) { $wpdb->delete($t, ['id'=>intval($_GET['del_log'])]); }
    $logs = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC LIMIT 200");
    echo '<div class="wrap"><h1>Historie sledování</h1><form method="post"><input type="submit" name="clear_all" value="Smazat vše" class="button button-link-delete"></form><table class="widefat striped"><thead><tr><th>Start</th><th>Divák</th><th>IP</th><th>Stream</th><th>Doba</th></tr></thead><tbody>';
    foreach($logs as $l) echo "<tr><td>{$l->time_start}</td><td>".esc_html($l->user_name)."</td><td>{$l->ip_address}</td><td>".esc_html($l->stream_name)."</td><td><b style='color:#27ae60;'>{$l->duration}</b></td></tr>";
    echo '</tbody></table></div>';
}

// 5. NASTAVENÍ (URL, ZÁMEK, MONITORING)
add_action('add_meta_boxes', function() {
    add_meta_box('sm_cfg', '⚙️ Nastavení zdroje', function($post) {
        $url = get_post_meta($post->ID, '_sm_url', true);
        echo 'URL Streamu:<br><input type="text" name="sm_url" value="'.esc_attr($url).'" style="width:100%;">';
    }, 'tv', 'normal', 'high');

    add_meta_box('sm_lock', '🔒 Povolené přístupy', function($post) {
        global $wpdb;
        $allowed = get_post_meta($post->ID, '_sm_allowed', true) ?: [];
        $users = $wpdb->get_results("SELECT id, username FROM {$wpdb->prefix}sm_users ORDER BY username ASC");
        echo '<div style="max-height:150px; overflow-y:auto; background:#f9f9f9; padding:10px; border:1px solid #ddd;">';
        if ($users) {
            foreach ($users as $u) {
                $checked = in_array($u->id, $allowed) ? 'checked' : '';
                echo '<label style="display:block;"><input type="checkbox" name="sm_allowed_users[]" value="'.$u->id.'" '.$checked.'> '.esc_html($u->username).'</label>';
            }
        } else { echo 'Žádní uživatelé.'; }
        echo '</div>';
    }, 'tv', 'side', 'default');

    add_meta_box('sm_live', '🔴 ŽIVÝ MONITORING', function($post) {
        echo '<div id="sm-live-container">Načítám...</div><script>function updateLive(){ jQuery.post(ajaxurl,{action:"sm_get_live_data",pid:'.$post->ID.'},function(res){jQuery("#sm-live-container").html(res);}); } setInterval(updateLive,1000); updateLive();</script>';
    }, 'tv', 'normal', 'high');
});

add_action('save_post', function($id) { 
    if (isset($_POST['sm_url'])) update_post_meta($id, '_sm_url', $_POST['sm_url']); 
    if (isset($_POST['sm_allowed_users'])) {
        update_post_meta($id, '_sm_allowed', $_POST['sm_allowed_users']);
    } else {
        if (isset($_POST['post_type']) && 'tv' == $_POST['post_type']) { update_post_meta($id, '_sm_allowed', []); }
    }
});

// 6. AJAX LOGIKA (PŘESNĚ 1 SEKUNDA)
add_action('wp_ajax_sm_action_puls', 'sm_handle_puls');
add_action('wp_ajax_nopriv_sm_action_puls', 'sm_handle_puls');
function sm_handle_puls() {
    global $wpdb; $t = $wpdb->prefix . "sm_logs";
    $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;
    $pid = intval($_POST['pid']);
    $user = sanitize_text_field($_POST['user_name']);
    $now = current_time('mysql');

    if (ob_get_length()) ob_clean();

    $log = $wpdb->get_row($wpdb->prepare("SELECT id, time_start FROM $t WHERE id=%d", $log_id));

    if (!$log) {
        $wpdb->insert($t, ['stream_name'=>get_the_title($pid), 'user_name'=>$user, 'ip_address'=>$_SERVER['REMOTE_ADDR'], 'time_start'=>$now, 'last_puls'=>$now, 'duration'=>'00:00:01']);
        echo intval($wpdb->insert_id);
    } else {
        $diff = time() - strtotime($log->time_start);
        $duration = gmdate("H:i:s", max(1, $diff));
        $wpdb->update($t, ['last_puls'=>$now, 'duration'=>$duration], ['id'=>$log_id]);
        echo intval($log_id);
    }
    wp_die();
}

add_action('wp_ajax_sm_get_live_data', function() {
    global $wpdb; 
    $pid = intval($_POST['pid']);
    $limit = date('Y-m-d H:i:s', strtotime('-10 seconds')); 
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_logs WHERE stream_name = %s AND last_puls > %s", get_the_title($pid), $limit));
    echo '<table class="widefat striped"><thead><tr><th>Uživatel</th><th>IP</th><th>Doba</th></tr></thead><tbody>';
    if ($results) { foreach ($results as $r) echo "<tr><td><b>".esc_html($r->user_name)."</b></td><td>{$r->ip_address}</td><td><b style='color:#27ae60;'>{$r->duration}</b></td></tr>"; }
    else { echo '<tr><td colspan="3">Nikdo online.</td></tr>'; }
    echo '</tbody></table>'; wp_die();
});