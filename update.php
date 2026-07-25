<?php
session_start();
require_once('funcs.php');
loginCheck(); //ログインしていない人は更新処理をさせない（ログイン必要ページ）
verifyCheck(); //電話番号の確認が済むまで使わせない

//POSTデータ取得（フォーム以外から開かれた場合は本棚へ戻す）
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrfCheck();

$id           = $_POST['id'] ?? '';
$book_name    = trim($_POST['book_name'] ?? '');
$book_url     = trim($_POST['book_url'] ?? '');
$book_comment = trim($_POST['book_comment'] ?? '');
$book_image   = trim($_POST['book_image'] ?? '');
//公開設定（チェックが無ければ非公開）
$is_public    = isset($_POST['is_public']) ? 1 : 0;

//idは数字のみ許可（hiddenはデベロッパーツールで書き換えられる前提で検証する）
if (!ctype_digit($id)) {
    header('Location: index.php');
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
//   WHERE に user_id も入れるのが要点。
//   idだけで絞ると、hiddenのidを他人のものに書き換えるだけで他人のブックマークを
//   編集できてしまう。持ち主が一致する行だけを対象にする。
$stmt = $pdo->prepare('UPDATE gs_bm_table SET book_name = :book_name, book_url = :book_url, book_comment = :book_comment, image_url = :image_url, is_public = :is_public WHERE id = :id AND user_id = :user_id');
$stmt->bindValue(':book_name', $book_name, PDO::PARAM_STR);
$stmt->bindValue(':book_url', $book_url, PDO::PARAM_STR);
$stmt->bindValue(':book_comment', $book_comment, PDO::PARAM_STR);
$stmt->bindValue(':image_url', $book_image, PDO::PARAM_STR);
$stmt->bindValue(':is_public', $is_public, PDO::PARAM_INT);
$stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
$stmt->bindValue(':user_id', currentUserId(), PDO::PARAM_INT);

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
    header('Location: index.php');
    exit;
}
