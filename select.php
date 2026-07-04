<?php
require_once('funcs.php');

//1. DB接続（funcs.phpの共通関数。ローカル/本番はconfig.phpの有無で切替）
$pdo = db_conn();

//2. 検索キーワードを受け取る（未指定なら全件表示）
$q = trim($_GET['q'] ?? '');

//3. データ取得SQL作成（新しい順）
//   検索時は 書籍名 or コメント の部分一致（授業で習ったLIKE検索）
//   ※PHP8はSQLエラー時に例外が飛ぶのでcatchする
try {
  if ($q !== '') {
    $stmt = $pdo->prepare("SELECT * FROM gs_bm_table WHERE book_name LIKE :q OR book_comment LIKE :q ORDER BY id DESC");
    // % と _ はLIKEの特殊文字なのでエスケープしてからバインド
    $stmt->bindValue(':q', '%' . addcslashes($q, '\\%_') . '%', PDO::PARAM_STR);
  } else {
    $stmt = $pdo->prepare("SELECT * FROM gs_bm_table ORDER BY id DESC");
  }
  $stmt->execute();

  //4. 集計（積読の見える化）: 全体の冊数と直近7日の追加数
  $stat = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(created_at >= NOW() - INTERVAL 7 DAY), 0) AS week_cnt FROM gs_bm_table")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  exit('ErrorQuery:' . $e->getMessage());
}

//5. データ表示（h()でXSS対策してから出力）
$view = "";
$hit = 0;
while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $hit++;
  $img = trim((string)($result['image_url'] ?? ''));
  $view .= '<div class="data-item">';
  // 表紙（画像がない本は本のアイコンを表示）
  if ($img !== '' && preg_match('#\Ahttps://#i', $img)) {
    $view .= '<div class="data-cover"><img src="'.h($img).'" alt="" loading="lazy" onerror="this.closest(\'.data-cover\').classList.add(\'data-cover--empty\');this.remove()"></div>';
  } else {
    $view .= '<div class="data-cover data-cover--empty"></div>';
  }
  $view .= '<div class="data-body">';
  $view .= '<div class="data-date"><i class="fas fa-clock"></i> '.h($result['created_at']).'</div>';
  $view .= '<div class="data-name"><i class="fas fa-book"></i> '.h($result['book_name']).'</div>';
  $view .= '<div class="data-content">'.nl2br(h($result['book_comment'])).'</div>';
  $view .= '<div class="data-url"><i class="fas fa-link"></i> <a href="'.h($result['book_url']).'" target="_blank" rel="noopener noreferrer">'.h($result['book_url']).'</a></div>';
  $view .= '</div>';
  $view .= '</div>';
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 積読ストック - ブックマーク一覧</title>
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
            <a href="index.php" class="nav-link">
                <i class="fas fa-plus"></i>
                ブックマーク登録
            </a>
        </div>
    </header>

    <!-- メインコンテンツ -->
    <main class="main-container">
        <div class="content-card">
            <h1 class="page-title">📚 積読ストック</h1>
            <p class="page-subtitle">貯めた本を見える化して、次の一冊を決めよう</p>

            <!-- 集計バー -->
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
                    <span class="stat-number"><?= $hit ?></span>
                    <span class="stat-label">表示中</span>
                </div>
            </div>

            <!-- 検索フォーム（授業で習ったLIKE検索） -->
            <form method="GET" action="select.php" class="search-form">
                <input type="text" name="q" class="search-input" placeholder="書籍名・コメントで検索" value="<?= h($q) ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> 検索
                </button>
                <?php if($q !== ''): ?>
                    <a href="select.php" class="search-clear">クリア</a>
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
                        <?php else: ?>
                            <p>まだブックマークがありません</p>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #999;">
                                最初の1冊を登録してみましょう！
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

    <!-- ページ間ナビ（←でブックマーク登録へ） -->
    <a href="index.php" class="page-nav page-nav--left" aria-label="ブックマーク登録へ戻る">
        <span class="page-nav-circle"><i class="fas fa-chevron-left"></i></span>
        <span class="page-nav-label">登録へ</span>
    </a>

    <script>
        // キーボードの←でも移動できる（入力中は無効）
        document.addEventListener('keydown', (e) => {
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (e.key === 'ArrowLeft') location.href = 'index.php';
        });
    </script>
</body>

</html>
