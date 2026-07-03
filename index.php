<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 ブックブックマーク - 登録</title>
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
            <h1 class="form-title">📚 本をブックマーク</h1>
            <p class="form-subtitle">気になった瞬間に貯めておく。積読は資産。</p>

            <form method="POST" action="insert.php">
                <div class="form-group">
                    <label for="book_name" class="form-label">
                        <i class="fas fa-book"></i> 書籍名
                    </label>
                    <input type="text" id="book_name" name="book_name" class="form-input" placeholder="例：リーダブルコード" maxlength="64" required>
                </div>

                <div class="form-group">
                    <label for="book_url" class="form-label">
                        <i class="fas fa-link"></i> 書籍URL
                    </label>
                    <input type="url" id="book_url" name="book_url" class="form-input" placeholder="例：https://www.example.com/book" required>
                </div>

                <div class="form-group">
                    <label for="book_comment" class="form-label">
                        <i class="fas fa-comment"></i> 書籍コメント
                    </label>
                    <textarea id="book_comment" name="book_comment" class="form-textarea" placeholder="読みたい理由やメモを書いておきましょう..." required></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-bookmark"></i>
                    ブックマークする
                </button>
            </form>
        </div>
    </main>

    <!-- ページ間ナビ（→で積読ストックへ） -->
    <a href="select.php" class="page-nav page-nav--right" aria-label="積読ストックを見る">
        <span class="page-nav-circle"><i class="fas fa-chevron-right"></i></span>
        <span class="page-nav-label">積読を見る</span>
    </a>

    <script>
        // キーボードの→でも移動できる（入力中は無効）
        document.addEventListener('keydown', (e) => {
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (e.key === 'ArrowRight') location.href = 'select.php';
        });
    </script>
</body>

</html>
