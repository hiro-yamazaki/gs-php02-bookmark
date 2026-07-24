<?php
session_start();
require_once('funcs.php');
loginCheck(); //ログインしていない人は削除できない（ログイン必要ページ）
adminCheck(); //さらに削除は管理者(kanri_flg=1)だけに許可する（権限分岐）

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
$stmt = $pdo->prepare('DELETE FROM gs_bm_table WHERE id = :id');
$stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);

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
