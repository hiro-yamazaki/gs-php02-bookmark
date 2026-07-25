<?php
session_start(); // ← ログイン処理なので忘れずに最初に呼ぶ
require_once('funcs.php');

//フォーム以外（GET直アクセス等）から開かれた場合はログイン画面へ戻す
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

csrfCheck();

//POST値を受け取る
$email = trim($_POST['email'] ?? '');
$lpw   = (string)($_POST['lpw'] ?? '');

//1. DB接続（funcs.phpの共通関数）
$pdo = db_conn();

//2. SQL作成
//   パスワードはハッシュ化して保存しているのでWHEREでは直接比較できない。
//   まずメールアドレスでユーザーを1件検索し、パスワードは後で password_verify で照合する。
$stmt = $pdo->prepare('SELECT * FROM gs_user_table WHERE email = :email');
$stmt->bindValue(':email', $email, PDO::PARAM_STR);

//3. 実行（PHP8はSQLエラー時に例外が飛ぶのでcatchする）
try {
    $stmt->execute();
} catch (PDOException $e) {
    exit('ErrorQuery:' . $e->getMessage());
}

//4. 実行後の処理（該当ユーザー1件を取得）
$val = $stmt->fetch(PDO::FETCH_ASSOC);

//5. ユーザーが存在し、かつ入力パスワードがハッシュと一致したらログイン成功
if ($val && password_verify($lpw, $val['lpw'])) {
    //セッションIDを作り替え、その値を「鍵」としてサーバー側に保存する
    //（同じIDがレスポンスでブラウザにも渡り、サーバーとブラウザで共有される）
    session_regenerate_id(true);
    $_SESSION['chk_ssid']  = session_id();
    //権限分岐に使うため、管理者フラグと表示名もセッションに持たせる
    $_SESSION['kanri_flg'] = (int)$val['kanri_flg'];
    $_SESSION['nickname']  = $val['nickname'];
    //ブックマークの持ち主判定に使うユーザーID（gs_user_table.id）
    $_SESSION['user_id']   = (int)$val['id'];
    //電話番号の確認が済んでいるか（0なら本棚を使わせず確認画面へ送る）
    $_SESSION['email_verified'] = (int)$val['email_verified'];
    //有効期限の起点。以降は loginCheck() が操作のたびに更新する
    $_SESSION['last_activity'] = time();

    //未確認のまま放置されたアカウントは、ログインのたびに確認画面へ戻す
    if (!isEmailVerified()) {
        header('Location: verify_email.php');
        exit;
    }
    header('Location: index.php');
    exit;
} else {
    //ログイン失敗 → ログイン画面へ
    //  「メールアドレスが存在しない」と「パスワードが違う」を区別せず同じ扱いにする。
    //  分けて返すと、外部から会員の有無を調べられてしまうため。
    header('Location: login.php?err=1');
    exit;
}
