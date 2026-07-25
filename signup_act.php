<?php
session_start();
require_once('funcs.php');

//フォーム以外（GET直アクセス等）から開かれた場合は登録画面へ戻す
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.php');
    exit;
}
csrfCheck();

//1. POST値を受け取る
$email    = trim($_POST['email'] ?? '');
$phoneRaw = trim($_POST['phone'] ?? '');
$phone    = normalizePhone($phoneRaw);      //ハイフンを外して数字だけにする
$nickname = trim($_POST['nickname'] ?? '');
$lpw      = (string)($_POST['lpw'] ?? '');
$lpw2     = (string)($_POST['lpw2'] ?? '');

//2. 入力チェック（エラーは全部集めてから返す。1つずつ直させない）
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
if (!isValidPassword($lpw)) {
    $errors[] = 'パスワードは8文字以上で、英字と数字の両方を含めてください。';
}
if ($lpw !== $lpw2) {
    $errors[] = 'パスワード（確認）が一致しません。';
}

//エラーがあれば、入力内容を持ち回して登録画面へ戻す
//  ※パスワードは書き戻さない（画面やセッションに残さない）
if ($errors) {
    setFlash('signup_errors', $errors);
    setFlash('signup_old', ['email' => $email, 'phone' => $phoneRaw, 'nickname' => $nickname]);
    header('Location: signup.php');
    exit;
}

//3. DB接続
$pdo = db_conn();

//4. 登録
//   メール・電話の重複はDBのUNIQUE制約で弾く。
//   「先にSELECTで確認してからINSERT」は、ほぼ同時に2人が登録すると
//   すり抜けることがあるため、制約違反を捕まえる形にしている。
$stmt = $pdo->prepare('INSERT INTO gs_user_table (email, phone, lpw, nickname, phone_verified, kanri_flg, created_at) VALUES (:email, :phone, :lpw, :nickname, 0, 0, NOW())');
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
//パスワードは必ずハッシュ化して保存する（平文では絶対に持たない）
$stmt->bindValue(':lpw', password_hash($lpw, PASSWORD_DEFAULT), PDO::PARAM_STR);
$stmt->bindValue(':nickname', $nickname, PDO::PARAM_STR);

try {
    $stmt->execute();
} catch (PDOException $e) {
    //23000 = 一意制約違反（すでに使われているメールアドレス／電話番号）
    if ($e->getCode() === '23000') {
        //どちらが重複したかは伝えない。
        //「このメールアドレスは登録済み」と返すと、外部から会員の有無を調べられてしまうため。
        setFlash('signup_errors', ['このメールアドレスまたは電話番号は登録できません。別の内容をお試しください。']);
        setFlash('signup_old', ['email' => $email, 'phone' => $phoneRaw, 'nickname' => $nickname]);
        header('Location: signup.php');
        exit;
    }
    //それ以外のDBエラーは詳細を画面に出さない（サーバー内部の情報が漏れるため）
    error_log('signup insert failed: ' . $e->getMessage());
    exit('登録処理でエラーが発生しました。時間をおいてお試しください。');
}

//5. そのままログイン状態にする（登録直後にまたログインさせない）
session_regenerate_id(true);
$_SESSION['chk_ssid']      = session_id();
$_SESSION['user_id']       = (int)$pdo->lastInsertId();
$_SESSION['nickname']      = $nickname;
$_SESSION['kanri_flg']     = 0;
$_SESSION['last_activity'] = time();

header('Location: index.php?welcome=1');
exit;
