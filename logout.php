<?php
/** e-PKKM - keluar dari aplikasi. */
require_once __DIR__ . '/inc/init.php';
mulai_sesi();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
}
session_destroy();
redirect('login.php');
