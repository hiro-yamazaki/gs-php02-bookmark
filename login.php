<?php
session_start();
require_once('funcs.php');

//すでにログイン済みなら一覧へ（ログイン画面を二重に見せない）
if (isLoggedIn()) {
    header('Location: select.php');
    exit;
}

//直前のログインに失敗して ?err=1 で戻ってきたか
$loginError = isset($_GET['err']);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - ログイン</title>
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
            <a href="select.php" class="nav-link">
                <i class="fas fa-list"></i>
                積読を見る
            </a>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="main-container form-page">
        <div class="form-card">
            <h1 class="form-title">🔑 ログイン</h1>
            <p class="form-subtitle">登録・編集・削除にはログインが必要です。</p>

            <?php if ($loginError): ?>
                <p class="login-error"><i class="fas fa-circle-exclamation"></i> ログインIDまたはパスワードが違います。</p>
            <?php endif; ?>

            <form method="POST" action="login_act.php">
                <div class="form-group">
                    <label for="lid" class="form-label"><i class="fas fa-user"></i> ログインID</label>
                    <input type="text" id="lid" name="lid" class="form-input" placeholder="例：admin" required autofocus>
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

            <!-- 動作確認用アカウント（採点・レビュー用のメモ。実運用ではこの表示は消す） -->
            <div class="login-hint">
                <p class="login-hint-title"><i class="fas fa-circle-info"></i> 動作確認用アカウント</p>
                <ul>
                    <li>管理者：<code>admin</code> / <code>admin1234</code>（登録・編集・削除まで可）</li>
                    <li>一般：<code>user</code> / <code>user1234</code>（登録・編集は可／削除は不可）</li>
                </ul>
            </div>
        </div>
    </main>

    <!-- ページ間ナビ（→で積読ストックへ・ログイン不要で見られる） -->
    <a href="select.php" class="page-nav page-nav--right" aria-label="積読ストックを見る">
        <span class="page-nav-circle"><i class="fas fa-chevron-right"></i></span>
        <span class="page-nav-label">積読を見る</span>
    </a>
</body>

</html>
