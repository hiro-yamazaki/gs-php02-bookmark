<?php
session_start();
require_once('funcs.php');

//ログアウト：セッションの中身を空にして破棄する
$_SESSION = [];

//セッションクッキーも無効化する（鍵をブラウザからも消す）
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

//セッションを完全に破棄
session_destroy();

//ログイン画面へ戻す
header('Location: login.php');
exit;
