<?php
require_once('funcs.php');

// POSTデータ取得（フォーム以外から開かれた場合はフォームへ戻す）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$book_name    = trim($_POST['book_name'] ?? '');
$book_url     = trim($_POST['book_url'] ?? '');
$book_comment = trim($_POST['book_comment'] ?? '');
$book_image   = trim($_POST['book_image'] ?? ''); //表紙画像URL（任意・「本を探す」で自動設定）

// 入力チェック（書籍名・URLは必須、コメントは任意。書籍名64文字超もフォームへ戻す）
if ($book_name === '' || $book_url === '' || mb_strlen($book_name) > 64) {
    header('Location: index.php');
    exit;
}

// URLはhttp/httpsのみ許可（javascript:等を保存させない＝格納型XSS対策）
if (!preg_match('#\Ahttps?://#i', $book_url)) {
    header('Location: index.php');
    exit;
}

// 表紙URLは任意項目。https以外や長すぎるものは捨てて本文だけ登録する
if ($book_image !== '' && (!preg_match('#\Ahttps://#i', $book_image) || mb_strlen($book_image) > 500)) {
    $book_image = '';
}

// 1. DB接続（funcs.phpの共通関数。ローカル/本番はconfig.phpの有無で切替）
$pdo = db_conn();

// 2. SQL作成（バインド変数でSQLインジェクション対策）
$stmt = $pdo->prepare('INSERT INTO gs_bm_table (book_name, book_url, book_comment, image_url, created_at) VALUES (:book_name, :book_url, :book_comment, :image_url, NOW())');
$stmt->bindValue(':book_name', $book_name, PDO::PARAM_STR);
$stmt->bindValue(':book_url', $book_url, PDO::PARAM_STR);
$stmt->bindValue(':book_comment', $book_comment, PDO::PARAM_STR);
$stmt->bindValue(':image_url', $book_image, PDO::PARAM_STR);

// 3. 実行（PHP8はSQLエラー時に例外が飛ぶのでcatchする）
try {
    $status = $stmt->execute();
} catch (PDOException $e) {
    exit('ErrorMessage:' . $e->getMessage());
}

// 4. 実行後の処理（登録できたら入力画面へ戻る）
if ($status === false) {
    $error = $stmt->errorInfo();
    exit('ErrorMessage:' . print_r($error, true));
} else {
    header('Location: index.php');
    exit;
}
