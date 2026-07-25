<?php
session_start();
require_once('funcs.php');
loginCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mypage.php');
    exit;
}
csrfCheck();

$email    = trim($_POST['email'] ?? '');
$phoneRaw = trim($_POST['phone'] ?? '');
$phone    = normalizePhone($phoneRaw);
$nickname = trim($_POST['nickname'] ?? '');

//入力チェック
$errors = [];
if (!isValidEmail($email)) {
    $errors[] = 'メールアドレスの形式が正しくありません。';
}
//電話番号は任意項目（入力があったときだけ形式を確認する）
if ($phone !== '' && !isValidPhone($phone)) {
    $errors[] = '電話番号は0から始まる10〜11桁で入力してください。';
}
if ($nickname === '' || mb_strlen($nickname) > 32) {
    $errors[] = '表示名は1〜32文字で入力してください。';
}
if ($errors) {
    setFlash('mypage_errors', $errors);
    header('Location: mypage.php');
    exit;
}

$pdo = db_conn();

//変更前のメールアドレスを見て、変わったら確認済み状態をリセットする
//  これを外すと、他人のアドレスに書き換えても確認済みのまま使えてしまう。
//  「本当にそのアドレスを受け取れる人か」を毎回確かめ直す必要がある。
$cur = $pdo->prepare('SELECT email FROM gs_user_table WHERE id = :id');
$cur->bindValue(':id', currentUserId(), PDO::PARAM_INT);
$cur->execute();
$currentEmail = (string)$cur->fetchColumn();
$emailChanged = ($currentEmail !== $email);

$stmt = $pdo->prepare('UPDATE gs_user_table SET email = :email, phone = :phone, nickname = :nickname'
    . ($emailChanged ? ', email_verified = 0' : '')
    . ' WHERE id = :id');
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->bindValue(':phone', $phone === '' ? null : $phone, $phone === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmt->bindValue(':nickname', $nickname, PDO::PARAM_STR);
$stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);

try {
    $stmt->execute();
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        setFlash('mypage_errors', ['このメールアドレスまたは電話番号は使用できません。']);
        header('Location: mypage.php');
        exit;
    }
    error_log('profile update failed: ' . $e->getMessage());
    exit('更新処理でエラーが発生しました。時間をおいてお試しください。');
}

//画面表示に使っている表示名はセッションにも持っているので更新する
$_SESSION['nickname'] = $nickname;

//アドレスを変えたらセッション側も未確認に戻す。
//  これを忘れると、DBは未確認なのに画面だけ使えてしまう。
if ($emailChanged) {
    $_SESSION['email_verified'] = 0;
    //新しいアドレス宛に確認コードを送り、そのまま確認画面へ進んでもらう
    issueVerifyCode($pdo, currentUserId(), $email);
    setFlash('verify_notice', '新しいメールアドレスに確認コードを送信しました。');
    header('Location: verify_email.php');
    exit;
}

setFlash('mypage_notice', '登録内容を変更しました。');
header('Location: mypage.php');
exit;
