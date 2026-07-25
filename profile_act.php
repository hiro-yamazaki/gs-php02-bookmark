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
if (!isValidPhone($phone)) {
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

//変更前の電話番号を見て、番号が変わったら確認済み状態をリセットする
//  （別の番号に付け替えて「確認済み」を引き継がせないため）
$cur = $pdo->prepare('SELECT phone FROM gs_user_table WHERE id = :id');
$cur->bindValue(':id', currentUserId(), PDO::PARAM_INT);
$cur->execute();
$currentPhone = (string)$cur->fetchColumn();
$phoneChanged = ($currentPhone !== $phone);

$stmt = $pdo->prepare('UPDATE gs_user_table SET email = :email, phone = :phone, nickname = :nickname'
    . ($phoneChanged ? ', phone_verified = 0' : '')
    . ' WHERE id = :id');
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
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

//番号を変えたらセッション側も未確認に戻す。
//  これを忘れると、DBは未確認なのに画面だけ使えてしまう。
if ($phoneChanged) {
    $_SESSION['phone_verified'] = 0;
    //新しい番号宛に確認コードを送り、そのまま確認画面へ進んでもらう
    issueVerifyCode($pdo, currentUserId(), $phone);
    setFlash('verify_notice', '新しい電話番号に確認コードを送信しました。');
    header('Location: verify_phone.php');
    exit;
}

setFlash('mypage_notice', '登録内容を変更しました。');
header('Location: mypage.php');
exit;
