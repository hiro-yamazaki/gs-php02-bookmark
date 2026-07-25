<?php
session_start();
require_once('funcs.php');
loginCheck(); //ログインしていない人は削除できない（ログイン必要ページ）
//削除できるのは「自分のブックマーク」だけ。ただし管理者(kanri_flg=1)は全件削除できる。
//（公開された不適切な内容に対応するための権限。判定は下のSQLで行う）

//POSTデータ取得（削除ボタン以外から開かれた場合は一覧へ戻す）
//※DBの中身を書き換える処理なのでGETではなくPOSTで受ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: select.php');
    exit;
}

//idは数字のみ許可（hiddenはデベロッパーツールで書き換えられる前提で検証する）
$id = $_POST['id'] ?? '';
if (!ctype_digit($id)) {
    header('Location: select.php');
    exit;
}

//1. DB接続（funcs.phpの共通関数）
$pdo = db_conn();

//2. SQL作成（DELETEは必ずWHEREとセット！ WHEREを忘れると全データが消える大事故中の大事故になる）
//   一般ユーザーは user_id も条件に入れて、自分の行しか消せないようにする。
if (isAdmin()) {
    $stmt = $pdo->prepare('DELETE FROM gs_bm_table WHERE id = :id');
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
} else {
    $stmt = $pdo->prepare('DELETE FROM gs_bm_table WHERE id = :id AND user_id = :user_id');
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', currentUserId(), PDO::PARAM_INT);
}

//3. 実行（PHP8はSQLエラー時に例外が飛ぶのでcatchする）
try {
    $status = $stmt->execute();
} catch (PDOException $e) {
    exit('ErrorMessage:' . $e->getMessage());
}

//4. 実行後の処理（削除できたら一覧へ戻る）
if ($status === false) {
    $error = $stmt->errorInfo();
    exit('ErrorMessage:' . print_r($error, true));
} else {
    header('Location: select.php');
    exit;
}
