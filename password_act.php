<?php
session_start();
require_once('funcs.php');
loginCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mypage.php');
    exit;
}
csrfCheck();

$currentPw = (string)($_POST['current_pw'] ?? '');
$newPw     = (string)($_POST['new_pw'] ?? '');
$newPw2    = (string)($_POST['new_pw2'] ?? '');

//1. 新しいパスワードの形式チェック
$errors = [];
if (!isValidPassword($newPw)) {
    $errors[] = '新しいパスワードは8文字以上で、英字と数字の両方を含めてください。';
}
if ($newPw !== $newPw2) {
    $errors[] = '新しいパスワード（確認）が一致しません。';
}
if ($errors) {
    setFlash('mypage_errors', $errors);
    header('Location: mypage.php');
    exit;
}

//2. 現在のパスワードを照合する
//   ログイン済みでも必ず確認する。席を外した端末を第三者に操作されたとき、
//   パスワードを勝手に変更されて乗っ取られるのを防ぐため。
$pdo  = db_conn();
$stmt = $pdo->prepare('SELECT lpw FROM gs_user_table WHERE id = :id');
$stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
$stmt->execute();
$hash = (string)$stmt->fetchColumn();

if ($hash === '' || !password_verify($currentPw, $hash)) {
    setFlash('mypage_errors', ['現在のパスワードが違います。']);
    header('Location: mypage.php');
    exit;
}

//3. 更新
$upd = $pdo->prepare('UPDATE gs_user_table SET lpw = :lpw WHERE id = :id');
$upd->bindValue(':lpw', password_hash($newPw, PASSWORD_DEFAULT), PDO::PARAM_STR);
$upd->bindValue(':id', currentUserId(), PDO::PARAM_INT);

try {
    $upd->execute();
} catch (PDOException $e) {
    error_log('password update failed: ' . $e->getMessage());
    exit('更新処理でエラーが発生しました。時間をおいてお試しください。');
}

//4. セッションIDを作り替える
//   パスワード変更は「乗っ取られたかもしれない」状況で行われることがある。
//   IDを変えることで、盗まれた古いセッションを無効にする。
session_regenerate_id(true);
$_SESSION['chk_ssid'] = session_id();

setFlash('mypage_notice', 'パスワードを変更しました。');
header('Location: mypage.php');
exit;
