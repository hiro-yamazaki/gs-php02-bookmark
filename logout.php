<?php
session_start();
require_once('funcs.php');

//ログアウト：セッションの中身を空にし、クッキーも消して完全に破棄する
//（有効期限切れのときと同じ処理なので funcs.php に共通化してある）
logoutSession();

//ログイン画面へ戻す
header('Location: login.php?logout=1');
exit;
