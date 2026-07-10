<?php
require_once('funcs.php');

//POSTデータ取得（フォーム以外から開かれた場合は一覧へ戻す）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: select.php');
    exit;
}

$id           = $_POST['id'] ?? '';
$book_name    = trim($_POST['book_name'] ?? '');
$book_url     = trim($_POST['book_url'] ?? '');
$book_comment = trim($_POST['book_comment'] ?? '');
$book_image   = trim($_POST['book_image'] ?? '');

//idは数字のみ許可（hiddenはデベロッパーツールで書き換えられる前提で検証する）
if (!ctype_digit($id)) {
    header('Location: select.php');
    exit;
}

//入力チェック（insert.phpと同じ基準。書籍名・URLは必須、コメントは任意）
if ($book_name === '' || $book_url === '' || mb_strlen($book_name) > 64) {
    header('Location: detail.php?id=' . (int)$id);
    exit;
}

//URLはhttp/httpsのみ許可（javascript:等を保存させない＝格納型XSS対策）
if (!preg_match('#\Ahttps?://#i', $book_url)) {
    header('Location: detail.php?id=' . (int)$id);
    exit;
}

//表紙URLは任意項目。https以外や長すぎるものは空にして本文だけ更新する
if ($book_image !== '' && (!preg_match('#\Ahttps://#i', $book_image) || mb_strlen($book_image) > 500)) {
    $book_image = '';
}

//1. DB接続（funcs.phpの共通関数）
$pdo = db_conn();

//2. SQL作成（UPDATEは必ずWHEREとセット！ WHEREを忘れると全レコードが書き換わる大事故になる）
$stmt = $pdo->prepare('UPDATE gs_bm_table SET book_name = :book_name, book_url = :book_url, book_comment = :book_comment, image_url = :image_url WHERE id = :id');
$stmt->bindValue(':book_name', $book_name, PDO::PARAM_STR);
$stmt->bindValue(':book_url', $book_url, PDO::PARAM_STR);
$stmt->bindValue(':book_comment', $book_comment, PDO::PARAM_STR);
$stmt->bindValue(':image_url', $book_image, PDO::PARAM_STR);
$stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);

//3. 実行（PHP8はSQLエラー時に例外が飛ぶのでcatchする）
try {
    $status = $stmt->execute();
} catch (PDOException $e) {
    exit('ErrorMessage:' . $e->getMessage());
}

//4. 実行後の処理（更新できたら一覧へ戻る）
if ($status === false) {
    $error = $stmt->errorInfo();
    exit('ErrorMessage:' . print_r($error, true));
} else {
    header('Location: select.php');
    exit;
}
