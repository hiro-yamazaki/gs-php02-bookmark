<?php
// ======================================================
// 最初の管理者アカウントを作る（コマンドラインから1回だけ実行する）
//
//   php create_admin.php
//
// パスワードをSQLファイルやソースに書かずに済ませるための道具。
// リポジトリに固定のパスワードを書くと、それを見た誰でもログインできてしまう。
//
// ※ブラウザからは実行できないようにしてある。
//   Web経由で叩けたら、誰でも自分用の管理者アカウントを作れてしまう。
// ======================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/funcs.php';

//入力を受け取る小道具
function ask($label){
    echo $label;
    return trim((string)fgets(STDIN));
}

//パスワードは画面に表示しない（肩越しに見られないように）
function askSecret($label){
    echo $label;
    if (function_exists('shell_exec') && stripos(PHP_OS_FAMILY, 'Windows') === false) {
        shell_exec('stty -echo');
        $value = trim((string)fgets(STDIN));
        shell_exec('stty echo');
        echo "\n";
        return $value;
    }
    return trim((string)fgets(STDIN));
}

echo "=== 管理者アカウントの作成 ===\n";

$email = ask('メールアドレス: ');
if (!isValidEmail($email)) {
    exit("メールアドレスの形式が正しくありません。\n");
}

$nickname = ask('表示名: ');
if ($nickname === '' || mb_strlen($nickname) > 32) {
    exit("表示名は1〜32文字で入力してください。\n");
}

$pw  = askSecret('パスワード（8文字以上・英数字を含む）: ');
$pw2 = askSecret('パスワード（確認）: ');

if (!isValidPassword($pw)) {
    exit("パスワードは8文字以上で、英字と数字の両方を含めてください。\n");
}
if ($pw !== $pw2) {
    exit("パスワードが一致しません。\n");
}

$pdo = db_conn();

//管理者は自分でメール確認する手間を省き、最初から確認済みにする
$stmt = $pdo->prepare(
    'INSERT INTO gs_user_table (email, phone, lpw, nickname, email_verified, kanri_flg, created_at)
     VALUES (:email, NULL, :lpw, :nickname, 1, 1, NOW())'
);
$stmt->bindValue(':email', $email, PDO::PARAM_STR);
$stmt->bindValue(':lpw', password_hash($pw, PASSWORD_DEFAULT), PDO::PARAM_STR);
$stmt->bindValue(':nickname', $nickname, PDO::PARAM_STR);

try {
    $stmt->execute();
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        exit("そのメールアドレスは既に登録されています。\n");
    }
    exit('作成に失敗しました: ' . $e->getMessage() . "\n");
}

echo "管理者アカウントを作成しました（id=" . $pdo->lastInsertId() . "）\n";
echo "ログイン画面からこのメールアドレスでログインできます。\n";
