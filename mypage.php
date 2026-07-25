<?php
require_once('funcs.php');
appSessionStart();
loginCheck(); //ログイン必須ページ

//1. 自分のアカウント情報を取り出す
$pdo  = db_conn();
$stmt = $pdo->prepare('SELECT * FROM gs_user_table WHERE id = :id');
$stmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
try {
    $stmt->execute();
} catch (PDOException $e) {
    error_log('mypage select failed: ' . $e->getMessage());
    exit('データの取得でエラーが発生しました。');
}
$me = $stmt->fetch(PDO::FETCH_ASSOC);

//アカウントが消えている（別の端末で退会した等）場合はログアウト扱いにする
if ($me === false) {
    logoutSession();
    header('Location: login.php');
    exit;
}

//2. 退会したときに何件消えるかを数えておく（警告文に出す）
$cntStmt = $pdo->prepare('SELECT COUNT(*) FROM gs_bm_table WHERE user_id = :id');
$cntStmt->bindValue(':id', currentUserId(), PDO::PARAM_INT);
$cntStmt->execute();
$bookCount = (int)$cntStmt->fetchColumn();

//3. 直前の操作結果を受け取る
$errors = takeFlash('mypage_errors', []);
$notice = takeFlash('mypage_notice', '');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - アカウント設定</title>
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
            <a href="index.php" class="logo">
                <i class="fas fa-book-bookmark"></i>
                積読ストック
            </a>
            <div class="nav-actions">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-list"></i>
                    本棚へ戻る
                </a>
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
            <h1 class="form-title">⚙️ アカウント設定</h1>

            <?php if ($notice): ?>
                <p class="login-notice"><i class="fas fa-circle-check"></i> <?= h($notice) ?></p>
            <?php endif; ?>

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

            <!-- 現在の登録内容 -->
            <dl class="account-info">
                <dt>メールアドレス</dt>
                <dd>
                    <?= h($me['email']) ?>
                    <?php if ((int)$me['email_verified'] === 1): ?>
                        <span class="data-badge data-badge--public"><i class="fas fa-circle-check"></i> 確認済み</span>
                    <?php else: ?>
                        <span class="data-badge"><i class="fas fa-clock"></i> 未確認</span>
                    <?php endif; ?>
                </dd>
                <dt>電話番号</dt>
                <dd><?= $me['phone'] === null || $me['phone'] === '' ? '（未登録）' : h($me['phone']) ?></dd>
                <dt>表示名</dt>
                <dd><?= h($me['nickname']) ?></dd>
                <dt>登録日</dt>
                <dd><?= h($me['created_at']) ?></dd>
            </dl>

            <!-- 登録内容の変更 -->
            <section class="settings-section">
                <h2 class="settings-title"><i class="fas fa-pen"></i> 登録内容の変更</h2>
                <form method="POST" action="profile_act.php">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="email" class="form-label">メールアドレス</label>
                        <input type="email" id="email" name="email" class="form-input" maxlength="255" required
                               value="<?= h($me['email']) ?>">
                        <p class="form-help">変更すると新しいアドレスに確認コードを送ります。確認するまで本棚は使えません。</p>
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">電話番号 <span class="form-optional">任意</span></label>
                        <input type="tel" id="phone" name="phone" class="form-input" maxlength="20"
                               value="<?= h($me['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="nickname" class="form-label">表示名</label>
                        <input type="text" id="nickname" name="nickname" class="form-input" maxlength="32" required
                               value="<?= h($me['nickname']) ?>">
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-rotate"></i> 変更を保存する
                    </button>
                </form>
            </section>

            <!-- パスワード変更 -->
            <section class="settings-section">
                <h2 class="settings-title"><i class="fas fa-key"></i> パスワードの変更</h2>
                <form method="POST" action="password_act.php">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="current_pw" class="form-label">現在のパスワード</label>
                        <input type="password" id="current_pw" name="current_pw" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label for="new_pw" class="form-label">新しいパスワード</label>
                        <input type="password" id="new_pw" name="new_pw" class="form-input"
                               placeholder="8文字以上・英字と数字を含む" required>
                    </div>
                    <div class="form-group">
                        <label for="new_pw2" class="form-label">新しいパスワード（確認）</label>
                        <input type="password" id="new_pw2" name="new_pw2" class="form-input" required>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-key"></i> パスワードを変更する
                    </button>
                </form>
            </section>

            <!-- 退会 -->
            <section class="settings-section settings-section--danger">
                <h2 class="settings-title"><i class="fas fa-triangle-exclamation"></i> 退会する</h2>
                <p class="settings-warning">
                    退会すると、登録した本 <strong><?= $bookCount ?></strong> 件を含むすべてのデータが利用できなくなります。<br>
                    <strong>退会後 <?= WITHDRAW_GRACE_DAYS ?> 日以内</strong>であれば、同じメールアドレスとパスワードでログインして復元できます。<br>
                    <?= WITHDRAW_GRACE_DAYS ?> 日を過ぎるとすべて完全に削除され、<strong>元に戻すことはできません。</strong>
                </p>
                <form method="POST" action="withdraw_act.php"
                      onsubmit="return confirm('本当に退会しますか？\n<?= WITHDRAW_GRACE_DAYS ?>日以内ならログインして復元できます。')">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="confirm_pw" class="form-label">確認のためパスワードを入力してください</label>
                        <input type="password" id="confirm_pw" name="confirm_pw" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" name="agree" value="1" required>
                            <span><?= WITHDRAW_GRACE_DAYS ?>日後にデータが完全に削除されることに同意します</span>
                        </label>
                    </div>
                    <button type="submit" class="submit-btn submit-btn--danger">
                        <i class="fas fa-user-slash"></i> 退会してアカウントを削除する
                    </button>
                </form>
            </section>
        </div>
    </main>
</body>

</html>
