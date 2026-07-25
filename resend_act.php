<?php
require_once('funcs.php');
appSessionStart();
loginCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verify_email.php');
    exit;
}
csrfCheck();

if (isEmailVerified()) {
    header('Location: index.php');
    exit;
}

$pdo = db_conn();

//送り先のメールアドレスを取り出す
$stmt = $pdo->prepare('SELECT email FROM gs_user_table WHERE id = :id');
$stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
$stmt->execute();
$email = (string)$stmt->fetchColumn();

if ($email === '') {
    setFlash('verify_error', 'メールアドレスが登録されていません。アカウント設定から登録してください。');
    header('Location: verify_email.php');
    exit;
}

//発行して送る（送信回数の上限判定は issueVerifyCode() の中で行う）
$result = issueVerifyCode($pdo, currentUserId(), $email);

if ($result['ok']) {
    setFlash('verify_notice', '確認コードを再送信しました。');
} else {
    setFlash('verify_error', $result['error']);
}

header('Location: verify_email.php');
exit;
