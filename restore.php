<?php
// 退会したアカウントで正しいパスワードを入力した人に、復元するかどうかを尋ねる画面。
// ここではまだログインさせない（復元に同意して初めてログイン状態にする）。
require_once('funcs.php');
appSessionStart();
nocache();

//login_act.php を通っていない人は来られない
if (empty($_SESSION['restore_user_id'])) {
    header('Location: login.php');
    exit;
}

$deadline = (int)($_SESSION['restore_deadline'] ?? 0);
$error    = takeFlash('restore_error', '');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - アカウントの復元</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css?v=20260728" rel="stylesheet">
</head>

<body>
    <!-- 装飾要素 -->
    <div class="decoration"></div>
    <div class="decoration"></div>

    <!-- ヘッダー -->
    <header class="header">
        <div class="nav-container">
            <a href="welcome.php" class="logo">
                <i class="fas fa-book-bookmark"></i>
                積読ストック
            </a>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="main-container form-page">
        <div class="form-card">
            <h1 class="form-title">🔄 アカウントの復元</h1>
            <p class="form-subtitle">
                このアカウントは退会済みです。<br>
                <strong><?= h(date('Y年n月j日', $deadline)) ?></strong> までは、登録した本を残したまま元に戻せます。
            </p>

            <?php if ($error): ?>
                <p class="login-error"><i class="fas fa-circle-exclamation"></i> <?= h($error) ?></p>
            <?php endif; ?>

            <p class="settings-warning">
                この日を過ぎると、アカウントと登録した本はすべて削除され、
                <strong>元に戻すことはできません。</strong>
            </p>

            <form method="POST" action="restore_act.php">
                <?= csrfField() ?>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-rotate-left"></i>
                    アカウントを復元して利用を再開する
                </button>
            </form>

            <p class="form-cancel">
                <a href="logout.php">復元せずに戻る</a>
            </p>
        </div>
    </main>
</body>

</html>
