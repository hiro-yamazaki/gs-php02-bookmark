<?php
session_start();
require_once('funcs.php');
loginCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verify_phone.php');
    exit;
}
csrfCheck();

if (isPhoneVerified()) {
    header('Location: index.php');
    exit;
}

$pdo = db_conn();

//送り先の番号を取り出す
$stmt = $pdo->prepare('SELECT phone FROM gs_user_table WHERE id = :id');
$stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
$stmt->execute();
$phone = (string)$stmt->fetchColumn();

if ($phone === '') {
    setFlash('verify_error', '電話番号が登録されていません。アカウント設定から登録してください。');
    header('Location: verify_phone.php');
    exit;
}

//発行して送る（送信回数の上限判定は issueVerifyCode() の中で行う）
$result = issueVerifyCode($pdo, currentUserId(), $phone);

if ($result['ok']) {
    setFlash('verify_notice', '確認コードを再送信しました。');
} else {
    setFlash('verify_error', $result['error']);
}

header('Location: verify_phone.php');
exit;
