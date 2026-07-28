<?php
// 未ログインの人が最初に見る画面（トップ／ランディング）。
// ここから「はじめる（新規登録）」と「ログイン」に分岐する。
require_once('funcs.php');
appSessionStart();
nocache();

//ログイン済みならこの画面は出さず、そのまま本棚へ
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - 積んだ本を、資産に</title>
    <meta name="description" content="気になった本をその場でストック。積読を見える化して、次の一冊を決められるサービスです。">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css?v=20260728" rel="stylesheet">
</head>

<body>
    <!-- 装飾要素 -->
    <div class="decoration"></div>
    <div class="decoration"></div>

    <main class="main-container hero-page">
        <div class="hero">
            <div class="hero-logo">
                <i class="fas fa-book-bookmark"></i>
            </div>
            <h1 class="hero-title">積読ストック</h1>
            <p class="hero-lead">
                気になった本を、その場で積む。<br>
                貯まった積読を見える化して、次の一冊を決められます。
            </p>

            <div class="hero-actions">
                <a href="signup.php" class="hero-btn hero-btn--primary">
                    <i class="fas fa-user-plus"></i>
                    はじめる（無料登録）
                </a>
                <a href="login.php" class="hero-btn hero-btn--ghost">
                    <i class="fas fa-right-to-bracket"></i>
                    ログイン
                </a>
            </div>

            <!-- 何ができるサービスなのかを3点だけ示す -->
            <ul class="hero-features">
                <li>
                    <i class="fas fa-magnifying-glass"></i>
                    <strong>本を探して積む</strong>
                    書名を入れるだけで候補が出ます。表紙とURLは自動で入ります。
                </li>
                <li>
                    <i class="fas fa-chart-simple"></i>
                    <strong>積読を見える化</strong>
                    冊数と今週の追加が一目で分かります。
                </li>
                <li>
                    <i class="fas fa-lock"></i>
                    <strong>基本は自分だけ</strong>
                    登録した本は既定で非公開。公開したい本だけ選べます。
                </li>
            </ul>
        </div>
    </main>
</body>

</html>
