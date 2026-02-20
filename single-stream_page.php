<?php
global $wpdb;
$pid = get_the_ID();
$error = false;

// 1. LOGIN LOGIKA
if (isset($_POST['sm_login'])) {
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sm_users WHERE username=%s AND password=%s", $_POST['l_user'], $_POST['l_pass']));
    $allowed = get_post_meta($pid, '_sm_allowed', true) ?: [];
    
    if ($user && (empty($allowed) || in_array($user->id, $allowed))) {
        $_SESSION['sm_auth_'.$pid] = true; 
        $_SESSION['sm_uname_'.$pid] = $user->username;
        wp_redirect(get_permalink($pid)); exit;
    } else {
        $error = "Neplatné údaje nebo nemáte přístup.";
    }
}

$allowed_list = get_post_meta($pid, '_sm_allowed', true);
$is_locked = !empty($allowed_list); 
$access_granted = !$is_locked || isset($_SESSION['sm_auth_'.$pid]);

$url = get_post_meta($pid, '_sm_url', true);
if (is_ssl()) { $url = str_replace("http://", "https://", $url); }
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title><?php the_title(); ?></title>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body, html { margin: 0; padding: 0; width: 100%; height: 100%; background: #000; overflow: hidden; font-family: sans-serif; color: white; }
        .login-overlay { width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center; background: #111; }
        .login-box { background: #222; padding: 30px; border-radius: 10px; text-align: center; width: 300px; }
        .login-box input { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        .login-box button { width: 100%; padding: 10px; background: #3498db; border: none; color: white; cursor: pointer; }
        .video-js { width: 100vw; height: 100vh; }
    </style>
</head>
<body>

<?php if ($access_granted) : ?>
    <video-js id="sm_player" class="vjs-default-skin vjs-big-play-centered" controls preload="auto" autoplay muted playsinline>
        <source src="<?php echo esc_url($url); ?>" type="application/dash+xml">
    </video-js>

    <script>
        var player = videojs('sm_player', { fluid: false, fill: true });
        var sm_log_id = 0;

        function sendPuls() {
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'sm_action_puls',
                pid: '<?php echo $pid; ?>',
                log_id: sm_log_id,
                user_name: '<?php echo $_SESSION['sm_uname_'.$pid] ?? 'Host'; ?>'
            }, function(res) {
                var nid = parseInt(res.trim());
                if (nid > 0) sm_log_id = nid;
            });
        }

        setInterval(sendPuls, 1000); // KAŽDOU SEKUNDU
        sendPuls();
    </script>
<?php else : ?>
    <div class="login-overlay">
        <div class="login-box">
            <h2>🔒 Zamčený stream</h2>
            <?php if ($error) echo "<p style='color:red;'>$error</p>"; ?>
            <form method="post">
                <input type="text" name="l_user" placeholder="Jméno" required>
                <input type="password" name="l_pass" placeholder="Heslo" required>
                <button type="submit" name="sm_login">Vstoupit</button>
            </form>
        </div>
    </div>
<?php endif; ?>
</body>
</html>