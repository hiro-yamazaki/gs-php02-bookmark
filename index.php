<?php
session_start();
require_once('funcs.php');
loginCheck(); //ログインしていない人は登録フォームを見られない（ログイン必要ページ）
?>
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
            <div class="nav-actions">
                <span class="nav-user">
                    <i class="fas fa-user-circle"></i>
                    <?= h($_SESSION['lid'] ?? '') ?>さん
                    <?php if (isAdmin()): ?><span class="nav-badge">管理者</span><?php endif; ?>
                </span>
                <a href="select.php" class="nav-link">
                    <i class="fas fa-list"></i>
                    積読を見る
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
                    <!-- 選択した本の表紙URL（「本を探す」で自動設定・一覧に表紙が出る） -->
                    <input type="hidden" id="book_image" name="book_image">
                    <div id="pickedPreview" class="picked-preview" hidden>
                        <img id="pickedCover" alt="選択した本の表紙">
                        <span>この表紙も一緒に登録されます</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="book_url" class="form-label">
                        <i class="fas fa-link"></i> 書籍URL
                    </label>
                    <input type="url" id="book_url" name="book_url" class="form-input" placeholder="例：https://www.example.com/book" required>
                </div>

                <div class="form-group">
                    <label for="book_comment" class="form-label">
                        <i class="fas fa-comment"></i> 書籍コメント <span class="form-optional">任意</span>
                    </label>
                    <textarea id="book_comment" name="book_comment" class="form-textarea" placeholder="読みたい理由やメモがあれば（あとから編集でも書けます）"></textarea>
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
        const imageInput = document.getElementById('book_image');
        const pickedPreview = document.getElementById('pickedPreview');
        const pickedCover = document.getElementById('pickedCover');

        // 表紙画像が存在しない場合（読込エラー or 1x1のダミー画像）は
        // プレビューを隠し、登録もしない
        pickedCover.addEventListener('error', () => {
            pickedPreview.hidden = true;
            imageInput.value = '';
        });
        pickedCover.addEventListener('load', () => {
            if (pickedCover.naturalWidth < 2) {
                pickedPreview.hidden = true;
                imageInput.value = '';
                return;
            }
            pickedCover.style.opacity = '1';
        });

        // ---- 検索と自動入力 ----
        let urlAutoFilled = false; //URL欄を自動入力で埋めたか（手入力は上書きしない）
        let searchTimer = null;
        let lastQuery = '';

        // URLを手で書いたら、以後は自動入力で上書きしない
        urlInput.addEventListener('input', () => { urlAutoFilled = false; });

        // 書籍名の入力が止まったら自動検索 → URL欄が空なら先頭候補で自動入力
        nameInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = nameInput.value.trim();
            if (q.length < 2) return;
            searchTimer = setTimeout(() => runSearch(q, true), 900); //間隔を空けて検索APIのレート制限を避ける
        });

        // 書籍名の欄でEnter → 登録ではなく「本を探す」を実行する
        // （日本語入力の変換確定Enterでは発動しないようにisComposingを見る）
        nameInput.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || e.isComposing || e.keyCode === 229) return;
            e.preventDefault(); //フォーム送信（登録）を止める
            searchBtn.click();
        });

        searchBtn.addEventListener('click', () => {
            const q = nameInput.value.trim();
            if (!q) { nameInput.focus(); return; }
            runSearch(q, false);
        });

        // 選んだ本の表紙をプレビュー表示（読み込み完了までは透明）
        function showPreviewCover(thumbnail) {
            if (thumbnail) {
                pickedCover.style.opacity = '0';
                pickedCover.style.transition = 'opacity 0.2s ease';
                pickedCover.src = thumbnail;
                pickedPreview.hidden = false;
            } else {
                pickedCover.removeAttribute('src');
                pickedPreview.hidden = true;
            }
        }

        async function runSearch(q, auto) {
            if (auto && q === lastQuery) return; //同じ語での再検索はしない
            lastQuery = q;
            if (!auto) {
                searchBtn.disabled = true;
                searchBtn.textContent = '検索中…';
            }
            try {
                const res = await fetch('search.php?q=' + encodeURIComponent(q));
                const data = await res.json();
                if (nameInput.value.trim() !== q) return; //入力が進んでいたら古い結果は捨てる
                suggestBox.replaceChildren();
                if (!data.items.length) {
                    if (auto) { suggestBox.hidden = true; return; } //入力中は静かに閉じる
                    suggestBox.hidden = false;
                    suggestBox.textContent = '見つかりませんでした。別の書名でお試しください。';
                    return;
                }
                suggestBox.hidden = false;
                data.items.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'book-suggest-item';
                    row.tabIndex = 0;
                    if (item.thumbnail) {
                        const img = document.createElement('img');
                        img.alt = '';
                        //読み込み成功までは透明にして、壊れた画像アイコンを見せない
                        img.style.opacity = '0';
                        img.style.transition = 'opacity 0.2s ease';
                        //表紙がない本（読込エラー or 1x1のダミー画像）はサムネ非表示
                        img.onerror = () => img.remove();
                        img.onload = () => {
                            if (img.naturalWidth < 2) { img.remove(); return; }
                            img.style.opacity = '1';
                        };
                        img.src = item.thumbnail;
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
                        imageInput.value = item.thumbnail || '';
                        urlAutoFilled = true; //書籍名を変えたら追従してよい
                        lastQuery = nameInput.value.trim();
                        showPreviewCover(item.thumbnail);
                        suggestBox.hidden = true;
                        document.getElementById('book_comment').focus();
                    };
                    row.addEventListener('click', pick);
                    row.addEventListener('keydown', (e) => { if (e.key === 'Enter') pick(); });
                    suggestBox.appendChild(row);
                });
                // URL欄が空（or 前回の自動入力のまま）なら先頭候補で自動入力
                if (auto && (urlInput.value.trim() === '' || urlAutoFilled)) {
                    const first = data.items[0];
                    urlInput.value = first.url;
                    imageInput.value = first.thumbnail || '';
                    urlAutoFilled = true;
                    showPreviewCover(first.thumbnail);
                }
            } catch (err) {
                if (!auto) {
                    suggestBox.hidden = false;
                    suggestBox.textContent = '検索でエラーが発生しました。時間をおいてお試しください。';
                }
            } finally {
                if (!auto) {
                    searchBtn.disabled = false;
                    searchBtn.innerHTML = '<i class="fas fa-search"></i> 本を探す';
                }
            }
        }
    </script>
</body>

</html>
