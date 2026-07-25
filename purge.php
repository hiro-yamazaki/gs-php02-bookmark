<?php
// ======================================================
// 猶予期間を過ぎた退会アカウントを物理削除する
//
// 論理削除しただけでは個人情報は残り続ける。
// 「目的を終えた個人情報は消す」を実際に実行するのがこのスクリプト。
// 誰も動かさなければ永久に残るので、必ず定期実行を設定すること。
//
// 実行方法
//   コマンドライン:  php purge.php
//   さくらのcron例（毎日4時）:
//     0 4 * * * /usr/local/bin/php /home/ユーザー名/www/purge.php
//
// ※ブラウザからは実行できないようにしてある（誰でも叩けると困るため）。
// ======================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/funcs.php';

$pdo = db_conn();

//1. 猶予期間を過ぎた退会アカウントを集める
$limit = date('Y-m-d H:i:s', time() - WITHDRAW_GRACE_DAYS * 86400);
$stmt  = $pdo->prepare('SELECT id, created_at, deleted_at FROM gs_user_table WHERE deleted_at IS NOT NULL AND deleted_at < :limit');
$stmt->bindValue(':limit', $limit, PDO::PARAM_STR);
$stmt->execute();
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$targets) {
    echo "削除対象はありません（基準日時: {$limit}）\n";
    exit(0);
}

$done = 0;
foreach ($targets as $t) {
    $uid = (int)$t['id'];

    //2. 消す前に、個人を特定できない形の記録だけ残す
    //   ユーザーIDもメールアドレスも保存しない。「何日使って何冊貯めたか」だけ。
    //   これがあると「登録直後に辞める人が多い」等の傾向が分かる。
    $cnt = $pdo->prepare('SELECT COUNT(*) FROM gs_bm_table WHERE user_id = :id');
    $cnt->bindValue(':id', $uid, PDO::PARAM_INT);
    $cnt->execute();
    $bookCount = (int)$cnt->fetchColumn();

    $usedDays = (int)floor((strtotime($t['deleted_at']) - strtotime($t['created_at'])) / 86400);

    $log = $pdo->prepare('INSERT INTO gs_withdrawal_log (used_days, book_count, withdrawn_at) VALUES (:d, :c, :w)');
    $log->bindValue(':d', max(0, $usedDays), PDO::PARAM_INT);
    $log->bindValue(':c', $bookCount, PDO::PARAM_INT);
    $log->bindValue(':w', $t['deleted_at'], PDO::PARAM_STR);
    $log->execute();

    //3. 物理削除
    //   gs_bm_table と gs_verify_code は外部キーが ON DELETE CASCADE なので
    //   このDELETE 1本で一緒に消える。メールアドレスもここで解放され、再登録できるようになる。
    $del = $pdo->prepare('DELETE FROM gs_user_table WHERE id = :id AND deleted_at IS NOT NULL');
    $del->bindValue(':id', $uid, PDO::PARAM_INT);
    $del->execute();

    $done += $del->rowCount();
}

echo "{$done}件のアカウントを削除しました（基準日時: {$limit}）\n";
