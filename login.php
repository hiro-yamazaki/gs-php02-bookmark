<?php
require_once('funcs.php');
appSessionStart();

//すでにログイン済みなら本棚へ（ログイン画面を二重に見せない）
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

//ログイン画面はキャッシュさせない
nocache();

//どういう理由でこの画面に来たかを判定してメッセージを出し分ける
$loginError = isset($_GET['err']);       //メールアドレス/パスワードが違う
$timedOut   = isset($_GET['timeout']);   //一定時間操作がなく自動ログアウトされた
$loggedOut  = isset($_GET['logout']);    //自分でログアウトした
$withdrawn  = isset($_GET['withdrawn']); //退会が完了した
//失敗が続いてロック中（あと何分で解除されるか）
$lockedMin  = isset($_GET['locked']) ? max(1, (int)$_GET['locked']) : 0;
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - ログイン</title>
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
            <h1 class="form-title">🔑 ログイン</h1>
            <p class="form-subtitle">ご利用にはログインが必要です。</p>

            <?php if ($lockedMin): ?>
                <p class="login-error">
                    <i class="fas fa-lock"></i>
                    ログインに続けて失敗したため、一時的に受け付けを停止しています。
                    約<?= $lockedMin ?>分後にもう一度お試しください。
                </p>
            <?php elseif ($loginError): ?>
                <p class="login-error"><i class="fas fa-circle-exclamation"></i> メールアドレスまたはパスワードが違います。</p>
            <?php elseif ($timedOut): ?>
                <p class="login-error"><i class="fas fa-clock"></i> 一定時間操作がなかったため、自動的にログアウトしました。もう一度ログインしてください。</p>
            <?php elseif ($loggedOut): ?>
                <p class="login-notice"><i class="fas fa-circle-check"></i> ログアウトしました。</p>
            <?php elseif ($withdrawn): ?>
                <p class="login-notice">
                    <i class="fas fa-circle-check"></i>
                    退会が完了しました。ご利用ありがとうございました。<br>
                    <?= WITHDRAW_GRACE_DAYS ?>日以内であれば、同じメールアドレスとパスワードでログインすると復元できます。
                </p>
            <?php endif; ?>

            <form method="POST" action="login_act.php">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email" class="form-label"><i class="fas fa-envelope"></i> メールアドレス</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="例：you@example.com" required autofocus>
                </div>

                <div class="form-group">
                    <label for="lpw" class="form-label"><i class="fas fa-lock"></i> パスワード</label>
                    <input type="password" id="lpw" name="lpw" class="form-input" placeholder="パスワード" required>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-right-to-bracket"></i>
                    ログイン
                </button>
            </form>

            <p class="form-cancel">
                アカウントをお持ちでない方は <a href="signup.php">アカウント作成</a>
            </p>
        </div>
    </main>

    <!-- ログインしないと一覧は見られないので、ここにはページ間ナビを置かない -->
</body>

</html>
