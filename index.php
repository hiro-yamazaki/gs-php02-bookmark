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
                    <div class="name-search-row">
                        <input type="text" id="book_name" name="book_name" class="form-input" placeholder="例：リーダブルコード" maxlength="64" required>
                        <button type="button" id="bookSearchBtn" class="book-search-btn">
                            <i class="fas fa-search"></i> 本を探す
                        </button>
                    </div>
                    <div id="bookSuggest" class="book-suggest" hidden></div>
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

        // 書籍検索（search.php経由でGoogle Books APIを利用）
        // 候補をクリックすると書籍名とURLが自動入力される
        const searchBtn = document.getElementById('bookSearchBtn');
        const nameInput = document.getElementById('book_name');
        const urlInput = document.getElementById('book_url');
        const suggestBox = document.getElementById('bookSuggest');

        searchBtn.addEventListener('click', async () => {
            const q = nameInput.value.trim();
            if (!q) { nameInput.focus(); return; }
            searchBtn.disabled = true;
            searchBtn.textContent = '検索中…';
            try {
                const res = await fetch('search.php?q=' + encodeURIComponent(q));
                const data = await res.json();
                suggestBox.hidden = false;
                suggestBox.replaceChildren();
                if (!data.items.length) {
                    suggestBox.textContent = '見つかりませんでした。別の書名でお試しください。';
                    return;
                }
                data.items.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'book-suggest-item';
                    row.tabIndex = 0;
                    if (item.thumbnail) {
                        const img = document.createElement('img');
                        img.src = item.thumbnail;
                        img.alt = '';
                        img.onerror = () => img.remove(); //表紙画像がない本はサムネ非表示
                        row.appendChild(img);
                    }
                    const meta = document.createElement('div');
                    meta.className = 'book-suggest-meta';
                    const title = document.createElement('div');
                    title.className = 'book-suggest-title';
                    title.textContent = item.title;
                    const author = document.createElement('div');
                    author.className = 'book-suggest-author';
                    author.textContent = item.authors;
                    meta.append(title, author);
                    row.appendChild(meta);
                    const pick = () => {
                        nameInput.value = item.title.slice(0, 64);
                        urlInput.value = item.url;
                        suggestBox.hidden = true;
                        document.getElementById('book_comment').focus();
                    };
                    row.addEventListener('click', pick);
                    row.addEventListener('keydown', (e) => { if (e.key === 'Enter') pick(); });
                    suggestBox.appendChild(row);
                });
            } catch (err) {
                suggestBox.hidden = false;
                suggestBox.textContent = '検索でエラーが発生しました。時間をおいてお試しください。';
            } finally {
                searchBtn.disabled = false;
                searchBtn.innerHTML = '<i class="fas fa-search"></i> 本を探す';
            }
        });
    </script>
</body>

</html>
