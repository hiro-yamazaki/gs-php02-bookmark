<?php
require_once('funcs.php');
appSessionStart();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: restore.php');
    exit;
}
csrfCheck();

//login_act.php でパスワード照合を通った人だけがここに来られる
$uid = (int)($_SESSION['restore_user_id'] ?? 0);
if ($uid === 0) {
    header('Location: login.php');
    exit;
}

$pdo = db_conn();

//1. 対象を取り直す（画面を開いたまま猶予期間が切れている可能性があるため）
$stmt = $pdo->prepare('SELECT * FROM gs_user_table WHERE id = :id');
$stmt->bindValue(':id', $uid, PDO::PARAM_INT);
$stmt->execute();
$me = $stmt->fetch(PDO::FETCH_ASSOC);

if ($me === false || $me['deleted_at'] === null) {
    //すでに物理削除された、または別経路で復元済み
    unset($_SESSION['restore_user_id'], $_SESSION['restore_deadline']);
    header('Location: login.php');
    exit;
}

if (time() > strtotime($me['deleted_at']) + WITHDRAW_GRACE_DAYS * 86400) {
    unset($_SESSION['restore_user_id'], $_SESSION['restore_deadline']);
    setFlash('restore_error', '復元できる期間を過ぎています。');
    header('Location: login.php?err=1');
    exit;
}

//2. 復元する
$upd = $pdo->prepare('UPDATE gs_user_table SET deleted_at = NULL WHERE id = :id');
$upd->bindValue(':id', $uid, PDO::PARAM_INT);

try {
    $upd->execute();
} catch (PDOException $e) {
    error_log('restore failed: ' . $e->getMessage());
    exit('復元処理でエラーが発生しました。時間をおいてお試しください。');
}

//3. そのままログイン状態にする
unset($_SESSION['restore_user_id'], $_SESSION['restore_deadline']);
session_regenerate_id(true);
$_SESSION['chk_ssid']       = session_id();
$_SESSION['user_id']        = (int)$me['id'];
$_SESSION['nickname']       = $me['nickname'];
$_SESSION['kanri_flg']      = (int)$me['kanri_flg'];
$_SESSION['email_verified'] = (int)$me['email_verified'];
$_SESSION['last_activity']  = time();

if (!isEmailVerified()) {
    header('Location: verify_email.php');
    exit;
}
header('Location: index.php?restored=1');
exit;
