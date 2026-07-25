<?php
session_start();
require_once('funcs.php');
loginCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verify_email.php');
    exit;
}
csrfCheck();

//すでに確認済みなら何もしない
if (isEmailVerified()) {
    header('Location: index.php');
    exit;
}

//入力されたコード（全角で打たれても通るように半角へ直し、数字以外を落とす）
$code = normalizeDigits($_POST['code'] ?? '');

if ($code === '') {
    setFlash('verify_error', '確認コードを入力してください。');
    header('Location: verify_email.php');
    exit;
}

$pdo    = db_conn();
$result = checkVerifyCode($pdo, currentUserId(), $code);

if (!$result['ok']) {
    setFlash('verify_error', $result['error']);
    header('Location: verify_email.php');
    exit;
}

//確認できたのでセッションにも反映する（以降 verifyCheck() を通過できる）
$_SESSION['email_verified'] = 1;

header('Location: index.php?verified=1');
exit;
