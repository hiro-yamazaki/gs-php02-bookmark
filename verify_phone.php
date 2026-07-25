<?php
session_start();
require_once('funcs.php');
loginCheck(); //ログインは必要。ただし verifyCheck() は呼ばない（呼ぶとこの画面自体に来られない）

//すでに確認済みなら本棚へ
if (isPhoneVerified()) {
    header('Location: index.php');
    exit;
}

//表示用に自分の電話番号を取り出す
$pdo  = db_conn();
$stmt = $pdo->prepare('SELECT phone FROM gs_user_table WHERE id = :id');
$stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
$stmt->execute();
$phone = (string)$stmt->fetchColumn();

//番号の下4桁だけ見せる（肩越しに画面を覗かれても番号が分からないように）
$masked = $phone === '' ? '' : str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);

$error  = takeFlash('verify_error', '');
$notice = takeFlash('verify_notice', '');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - 電話番号の確認</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>

<body>
    <!-- 装飾要素 -->
    <div class="decoration"></div>
    <div class="decoration"></div>

    <!-- ヘッダー -->
    <header class="header">
        <div class="nav-container">
            <a href="#" class="logo">
                <i class="fas fa-book-bookmark"></i>
                積読ストック
            </a>
            <div class="nav-actions">
                <a href="logout.php" class="nav-link nav-link--ghost">
                    <i class="fas fa-right-from-bracket"></i>
                    ログアウト
                </a>
            </div>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="main-container form-page">
        <div class="form-card">
            <h1 class="form-title">📱 電話番号の確認</h1>
            <p class="form-subtitle">
                <?= h($masked) ?> 宛にSMSで6桁の確認コードを送りました。<br>
                コードを入力すると、ご利用を開始できます。
            </p>

            <?php if ($notice): ?>
                <p class="login-notice"><i class="fas fa-circle-check"></i> <?= h($notice) ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="login-error"><i class="fas fa-circle-exclamation"></i> <?= h($error) ?></p>
            <?php endif; ?>

            <form method="POST" action="verify_act.php">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="code" class="form-label">
                        <i class="fas fa-key"></i> 確認コード（6桁）
                    </label>
                    <!-- inputmode="numeric" でスマホのキーボードが数字になる -->
                    <input type="text" id="code" name="code" class="form-input verify-code-input"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           placeholder="123456" autocomplete="one-time-code" required autofocus>
                    <p class="form-help">コードの有効期限は10分です。</p>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-circle-check"></i>
                    確認する
                </button>
            </form>

            <!-- 再送（届かなかった場合） -->
            <form method="POST" action="resend_act.php" class="resend-form">
                <?= csrfField() ?>
                <button type="submit" class="link-btn">
                    <i class="fas fa-rotate-right"></i> コードが届かない場合は再送信する
                </button>
            </form>

            <p class="form-cancel">
                番号を間違えましたか？ <a href="mypage.php">アカウント設定</a> から変更できます。
            </p>
        </div>
    </main>
</body>

</html>
