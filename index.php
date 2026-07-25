<?php
require_once('funcs.php');
appSessionStart();
loginCheck(); //ログインしていない人には見せない（ログイン必要ページ）
verifyCheck(); //電話番号の確認が済むまで使わせない

//このページ1枚で「本を探す → ストックする → 積んだ本を見る」まで完結させる。
//（以前は登録＝index.php／一覧＝select.php と分かれていた）

$admin = isAdmin();
$uid   = currentUserId();

//1. DB接続（funcs.phpの共通関数。ローカル/本番はconfig.phpの有無で切替）
$pdo = db_conn();

//2. 検索キーワードと表示範囲を受け取る
$q = trim($_GET['q'] ?? '');
//   mine   = 自分の本棚（既定）
//   public = 他の利用者が公開している本棚
//   ※想定外の値が来たら mine に倒す（勝手に他人のデータを出さない）
$scope = ($_GET['scope'] ?? 'mine') === 'public' ? 'public' : 'mine';

//アカウント作成直後・電話番号の確認直後だけメッセージを出す
$welcome  = isset($_GET['welcome']);
$verified = isset($_GET['verified']);
$restored = isset($_GET['restored']);

//3. データ取得SQL作成（新しい順）
//   検索時は 書籍名 or コメント の部分一致（授業で習ったLIKE検索）
//   ※PHP8はSQLエラー時に例外が飛ぶのでcatchする
try {
  //表示範囲の条件をここで決める。
  //  自分の本棚 : 公開/非公開にかかわらず自分の行すべて
  //  みんなの本棚: is_public=1 かつ 自分以外の行（自分の分は「マイ本棚」で見られるため）
  if ($scope === 'public') {
    $scopeSql = 'b.is_public = 1 AND b.user_id <> :uid';
  } else {
    $scopeSql = 'b.user_id = :uid';
  }
  //持ち主の表示名を出したいので gs_user_table と結合する。
  //  結合条件に deleted_at IS NULL を入れるのが要点。
  //  これが無いと、退会した人が公開していた本が「みんなの本棚」に残り続ける。
  $sql = "SELECT b.*, u.nickname AS owner_name FROM gs_bm_table b JOIN gs_user_table u ON u.id = b.user_id AND u.deleted_at IS NULL WHERE {$scopeSql}";
  if ($q !== '') {
    $sql .= " AND (b.book_name LIKE :q OR b.book_comment LIKE :q)";
  }
  $sql .= " ORDER BY b.id DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
  if ($q !== '') {
    // % と _ はLIKEの特殊文字なのでエスケープしてからバインド
    $stmt->bindValue(':q', '%' . addcslashes($q, '\\%_') . '%', PDO::PARAM_STR);
  }
  $stmt->execute();

  //4. 集計（積読の見える化）: 自分の冊数・直近7日の追加数・公開中の冊数
  $statStmt = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(created_at >= NOW() - INTERVAL 7 DAY), 0) AS week_cnt, COALESCE(SUM(is_public = 1), 0) AS public_cnt FROM gs_bm_table WHERE user_id = :uid");
  $statStmt->bindValue(':uid', $uid, PDO::PARAM_INT);
  $statStmt->execute();
  $stat = $statStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  error_log('index select failed: ' . $e->getMessage());
  exit('データの取得でエラーが発生しました。時間をおいてお試しください。');
}

//5. データ表示（h()でXSS対策してから出力）
$view = "";
$hit = 0;
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $hit++;
  $img = trim((string)($result['image_url'] ?? ''));
  $isMine   = ((int)$result['user_id'] === $uid);
  $isPublic = ((int)$result['is_public'] === 1);
  $view .= '<div class="data-item">';
  // 表紙（画像がない本は本のアイコンを表示）
  if ($img !== '' && preg_match('#\Ahttps://#i', $img)) {
    $view .= '<div class="data-cover"><img src="'.h($img).'" alt="" loading="lazy" onerror="coverFail(this)" onload="if(this.naturalWidth<2){coverFail(this)}else{this.classList.add(\'loaded\')}"></div>';
  } else {
    $view .= '<div class="data-cover data-cover--empty"></div>';
  }
  $view .= '<div class="data-body">';
  $view .= '<div class="data-date"><i class="fas fa-clock"></i> '.h($result['created_at']);
  // 自分の本には公開状態を、他人の本には持ち主を表示する
  if ($isMine) {
    $view .= $isPublic
      ? ' <span class="data-badge data-badge--public"><i class="fas fa-earth-asia"></i> 公開中</span>'
      : ' <span class="data-badge"><i class="fas fa-lock"></i> 非公開</span>';
  } else {
    $view .= ' <span class="data-badge"><i class="fas fa-user"></i> '.h($result['owner_name']).'さん</span>';
  }
  $view .= '</div>';
  $view .= '<div class="data-name"><i class="fas fa-book"></i> '.h($result['book_name']).'</div>';
  // コメントは任意項目なので、空のときは行ごと出さない
  if (trim((string)$result['book_comment']) !== '') {
    $view .= '<div class="data-content">'.nl2br(h($result['book_comment'])).'</div>';
  }
  $view .= '<div class="data-url"><i class="fas fa-link"></i> <a href="'.h($result['book_url']).'" target="_blank" rel="noopener noreferrer">'.h($result['book_url']).'</a></div>';
  // 編集・削除ボタン
  //   ・編集：自分のブックマークだけ（他人の公開分は閲覧のみ）
  //   ・削除：自分のブックマーク、または管理者(kanri_flg=1)。誤操作防止に確認ダイアログを挟む
  if ($isMine || $admin) {
    $view .= '<div class="data-actions">';
    if ($isMine) {
      $view .= '<a href="detail.php?id='.(int)$result['id'].'" class="edit-btn"><i class="fas fa-pen"></i> 編集</a>';
    }
    // 書名はjson_encodeでJS文字列化してからh()する（'や"を含む書名でもJSが壊れない）
    $confirm = h(json_encode('「' . $result['book_name'] . '」を削除しますか？', JSON_UNESCAPED_UNICODE));
    $view .= '<form method="POST" action="delete.php" class="delete-form" onsubmit="return confirm('.$confirm.')">';
    $view .= csrfField();
    $view .= '<input type="hidden" name="id" value="'.(int)$result['id'].'">';
    $view .= '<button type="submit" class="delete-btn"><i class="fas fa-trash"></i> 削除</button>';
    $view .= '</form>';
    $view .= '</div>';
  }
  $view .= '</div>';
  $view .= '</div>';
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <script>
        // 表紙が読み込めない・実体がない(1x1画像)場合は📚プレースホルダーに切り替える
        function coverFail(img) {
            const box = img.parentNode;
            box.className = 'data-cover data-cover--empty';
            img.remove();
        }
    </script>
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
                <span class="nav-user">
                    <i class="fas fa-user-circle"></i>
                    <?= h($_SESSION['nickname'] ?? '') ?>さん
                    <?php if ($admin): ?><span class="nav-badge">管理者</span><?php endif; ?>
                </span>
                <a href="mypage.php" class="nav-link">
                    <i class="fas fa-gear"></i>
                    アカウント設定
                </a>
                <a href="logout.php" class="nav-link nav-link--ghost">
                    <i class="fas fa-right-from-bracket"></i>
                    ログアウト
                </a>
            </div>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="main-container">
        <?php if ($restored): ?>
            <p class="login-notice"><i class="fas fa-circle-check"></i> アカウントを復元しました。登録していた本もそのまま残っています。</p>
        <?php elseif ($verified): ?>
            <p class="login-notice"><i class="fas fa-circle-check"></i> 電話番号を確認しました。さっそく1冊目を積んでみましょう。</p>
        <?php elseif ($welcome): ?>
            <p class="login-notice"><i class="fas fa-circle-check"></i> アカウントを作成しました。さっそく1冊目を積んでみましょう。</p>
        <?php endif; ?>

        <!-- ① 探して積む（登録フォーム） -->
        <div class="content-card">
            <h1 class="page-title">📚 積読ストック</h1>
            <p class="page-subtitle">気になった瞬間に貯めておく。積読は資産。</p>

            <form method="POST" action="insert.php" class="stock-form">
                <?= csrfField() ?>

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

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_public" value="1">
                        <span><i class="fas fa-earth-asia"></i> この本を他の利用者にも公開する</span>
                    </label>
                    <p class="form-help">既定は非公開です。チェックを入れると「みんなの本棚」に並びます（あとから変更できます）。</p>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-bookmark"></i>
                    ストックする
                </button>
            </form>
        </div>

        <!-- ② 積んだ本を見る（一覧） -->
        <div class="content-card">
            <!-- 集計バー（数字はすべて自分の本棚のもの） -->
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-number"><?= (int)$stat['total'] ?></span>
                    <span class="stat-label">積読ストック</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= (int)$stat['week_cnt'] ?></span>
                    <span class="stat-label">今週の追加</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= (int)$stat['public_cnt'] ?></span>
                    <span class="stat-label">公開中</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $hit ?></span>
                    <span class="stat-label">表示中</span>
                </div>
            </div>

            <!-- 表示範囲の切り替え（自分の本棚／他の利用者の公開分） -->
            <div class="scope-tabs">
                <a href="index.php" class="scope-tab<?= $scope === 'mine' ? ' is-active' : '' ?>">
                    <i class="fas fa-book-bookmark"></i> マイ本棚
                </a>
                <a href="index.php?scope=public" class="scope-tab<?= $scope === 'public' ? ' is-active' : '' ?>">
                    <i class="fas fa-earth-asia"></i> みんなの本棚
                </a>
            </div>

            <!-- 検索フォーム（授業で習ったLIKE検索） -->
            <form method="GET" action="index.php" class="search-form">
                <!-- 検索しても表示範囲が「マイ本棚／みんなの本棚」から切り替わらないよう引き継ぐ -->
                <input type="hidden" name="scope" value="<?= h($scope) ?>">
                <input type="text" name="q" class="search-input" placeholder="書籍名・コメントで検索" value="<?= h($q) ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> 検索
                </button>
                <?php if($q !== ''): ?>
                    <a href="index.php?scope=<?= h($scope) ?>" class="search-clear">クリア</a>
                <?php endif; ?>
            </form>

            <div class="data-container">
                <?php if(empty($view)): ?>
                    <!-- もし $view データがない場合の表示 -->
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <?php if($q !== ''): ?>
                            <p>「<?= h($q) ?>」に一致するブックマークはありません</p>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #999;">
                                キーワードを変えて検索してみてください
                            </p>
                        <?php elseif($scope === 'public'): ?>
                            <p>公開されているブックマークはまだありません</p>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #999;">
                                自分の本を公開すると、ここに並びます
                            </p>
                        <?php else: ?>
                            <p>まだブックマークがありません</p>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #999;">
                                上のフォームから最初の1冊を積んでみましょう！
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- もし $view データが存在する場合 -->
                    <?= $view ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
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
                // セッション切れ（401）なら、その場でログイン画面へ戻す
                if (res.status === 401) {
                    location.href = 'login.php?timeout=1';
                    return;
                }
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
