<?php
session_start();
require_once('funcs.php');
loginCheck();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mypage.php');
    exit;
}
csrfCheck();

$confirmPw = (string)($_POST['confirm_pw'] ?? '');
$agreed    = isset($_POST['agree']);

if (!$agreed) {
    setFlash('mypage_errors', ['データが削除されることへの同意にチェックを入れてください。']);
    header('Location: mypage.php');
    exit;
}

$pdo = db_conn();
$uid = currentUserId();

//1. パスワードを再確認する（取り返しがつかない操作なので必ず本人確認する）
$stmt = $pdo->prepare('SELECT lpw, kanri_flg FROM gs_user_table WHERE id = :id');
$stmt->bindValue(':id', $uid, PDO::PARAM_INT);
$stmt->execute();
$me = $stmt->fetch(PDO::FETCH_ASSOC);

if ($me === false || !password_verify($confirmPw, $me['lpw'])) {
    setFlash('mypage_errors', ['パスワードが違います。']);
    header('Location: mypage.php');
    exit;
}

//2. 管理者が全員いなくなる退会は止める
//   最後の管理者が退会すると、誰も管理操作をできない状態になって復旧できなくなる。
if ((int)$me['kanri_flg'] === 1) {
    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM gs_user_table WHERE kanri_flg = 1')->fetchColumn();
    if ($cnt <= 1) {
        setFlash('mypage_errors', ['最後の管理者アカウントは退会できません。先に別の管理者を用意してください。']);
        header('Location: mypage.php');
        exit;
    }
}

//3. アカウントを削除する
//   ブックマークは gs_bm_table の外部キーが ON DELETE CASCADE なので
//   このDELETE 1本で一緒に消える（消し忘れが起きない）。
$del = $pdo->prepare('DELETE FROM gs_user_table WHERE id = :id');
$del->bindValue(':id', $uid, PDO::PARAM_INT);

try {
    $del->execute();
} catch (PDOException $e) {
    error_log('withdraw failed: ' . $e->getMessage());
    exit('退会処理でエラーが発生しました。時間をおいてお試しください。');
}

//4. セッションを破棄してお別れ画面へ
logoutSession();
header('Location: login.php?withdrawn=1');
exit;
