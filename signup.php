<?php
session_start();
require_once('funcs.php');
nocache();

//すでにログイン済みなら本棚へ（登録画面を見せる必要がない）
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

//前の送信でエラーになった場合、内容とエラーを引き継ぐ
//  （入力し直しにならないよう、パスワード以外は書き戻す）
$errors = takeFlash('signup_errors', []);
$old    = takeFlash('signup_old', []);
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - アカウント作成</title>
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
            <a href="login.php" class="logo">
                <i class="fas fa-book-bookmark"></i>
                積読ストック
            </a>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="main-container form-page">
        <div class="form-card">
            <h1 class="form-title">📝 アカウント作成</h1>
            <p class="form-subtitle">積んだ本を記録して、次の一冊を決めましょう。</p>

            <?php if ($errors): ?>
                <div class="login-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <ul class="error-list">
                        <?php foreach ($errors as $e): ?>
                            <li><?= h($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="signup_act.php">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope"></i> メールアドレス
                    </label>
                    <input type="email" id="email" name="email" class="form-input"
                           placeholder="例：you@example.com" maxlength="255" required
                           value="<?= h($old['email'] ?? '') ?>">
                    <p class="form-help">ログインに使います。確認コードをこのアドレスに送ります。</p>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">
                        <i class="fas fa-mobile-screen"></i> 電話番号 <span class="form-optional">任意</span>
                    </label>
                    <input type="tel" id="phone" name="phone" class="form-input"
                           placeholder="例：090-1234-5678" maxlength="20"
                           value="<?= h($old['phone'] ?? '') ?>">
                    <p class="form-help">入力しなくても登録できます。ハイフンはあってもなくても構いません。</p>
                </div>

                <div class="form-group">
                    <label for="nickname" class="form-label">
                        <i class="fas fa-user"></i> 表示名
                    </label>
                    <input type="text" id="nickname" name="nickname" class="form-input"
                           placeholder="例：ヤマザキ" maxlength="32" required
                           value="<?= h($old['nickname'] ?? '') ?>">
                    <p class="form-help">本を公開したときに他の利用者へ表示されます。本名でなくて構いません。</p>
                </div>

                <div class="form-group">
                    <label for="lpw" class="form-label">
                        <i class="fas fa-lock"></i> パスワード
                    </label>
                    <input type="password" id="lpw" name="lpw" class="form-input"
                           placeholder="8文字以上・英字と数字を含む" required>
                </div>

                <div class="form-group">
                    <label for="lpw2" class="form-label">
                        <i class="fas fa-lock"></i> パスワード（確認）
                    </label>
                    <input type="password" id="lpw2" name="lpw2" class="form-input"
                           placeholder="もう一度入力してください" required>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-user-plus"></i>
                    アカウントを作成する
                </button>
            </form>

            <p class="form-cancel">
                すでにアカウントをお持ちですか？ <a href="login.php">ログイン</a>
            </p>
        </div>
    </main>
</body>

</html>
