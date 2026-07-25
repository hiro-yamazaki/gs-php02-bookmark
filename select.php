<?php
session_start();
require_once('funcs.php');
loginCheck(); //一覧もログインしていない人には見せない（ログイン必要ページ）

$admin   = isAdmin();
$uid     = currentUserId();
//「登録へ」の遷移先（ここまで来ている＝ログイン済みなので登録フォームへ）
$addHref = 'index.php';

//1. DB接続（funcs.phpの共通関数。ローカル/本番はconfig.phpの有無で切替）
$pdo = db_conn();

//2. 検索キーワードと表示範囲を受け取る
$q = trim($_GET['q'] ?? '');
//   mine   = 自分の本棚（既定）
//   public = 他の利用者が公開している本棚
//   ※想定外の値が来たら mine に倒す（勝手に他人のデータを出さない）
$scope = ($_GET['scope'] ?? 'mine') === 'public' ? 'public' : 'mine';

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
  //持ち主の名前を出したいので gs_user_table と結合する
  $sql = "SELECT b.*, u.lid AS owner_lid FROM gs_bm_table b JOIN gs_user_table u ON u.id = b.user_id WHERE {$scopeSql}";
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
    $view .= '<div class="data-cover"><img src="'.h($img).'" alt="" loading="lazy" onerror="coverFail(this)" onload="if(this.naturalWidth<2){coverFail(this)}else{this.classList.add(\'loaded\')}"></div>';
  } else {
    $view .= '<div class="data-cover data-cover--empty"></div>';
  }
  $isMine   = ((int)$result['user_id'] === $uid);
  $isPublic = ((int)$result['is_public'] === 1);
  $view .= '<div class="data-body">';
  $view .= '<div class="data-date"><i class="fas fa-clock"></i> '.h($result['created_at']);
  // 自分の本には公開状態を、他人の本には持ち主を表示する
  if ($isMine) {
    $view .= $isPublic
      ? ' <span class="data-badge data-badge--public"><i class="fas fa-earth-asia"></i> 公開中</span>'
      : ' <span class="data-badge"><i class="fas fa-lock"></i> 非公開</span>';
  } else {
    $view .= ' <span class="data-badge"><i class="fas fa-user"></i> '.h($result['owner_lid']).'さん</span>';
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
    if ($isMine || $admin) {
      // 書名はjson_encodeでJS文字列化してからh()する（'や"を含む書名でもJSが壊れない）
      $confirm = h(json_encode('「' . $result['book_name'] . '」を削除しますか？', JSON_UNESCAPED_UNICODE));
      $view .= '<form method="POST" action="delete.php" class="delete-form" onsubmit="return confirm('.$confirm.')">';
      $view .= '<input type="hidden" name="id" value="'.(int)$result['id'].'">';
      $view .= '<button type="submit" class="delete-btn"><i class="fas fa-trash"></i> 削除</button>';
      $view .= '</form>';
    }
    $view .= '</div>';
  }
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
            <a href="#" class="logo">
                <i class="fas fa-book-bookmark"></i>
                積読ストック
            </a>
            <div class="nav-actions">
                <span class="nav-user">
                    <i class="fas fa-user-circle"></i>
                    <?= h($_SESSION['lid'] ?? '') ?>さん
                    <?php if ($admin): ?><span class="nav-badge">管理者</span><?php endif; ?>
                </span>
                <a href="index.php" class="nav-link">
                    <i class="fas fa-plus"></i>
                    ブックマーク登録
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
        <div class="content-card">
            <h1 class="page-title">📚 積読ストック</h1>
            <p class="page-subtitle">貯めた本を見える化して、次の一冊を決めよう</p>

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
                <a href="select.php" class="scope-tab<?= $scope === 'mine' ? ' is-active' : '' ?>">
                    <i class="fas fa-book-bookmark"></i> マイ本棚
                </a>
                <a href="select.php?scope=public" class="scope-tab<?= $scope === 'public' ? ' is-active' : '' ?>">
                    <i class="fas fa-earth-asia"></i> みんなの本棚
                </a>
            </div>

            <!-- 検索フォーム（授業で習ったLIKE検索） -->
            <form method="GET" action="select.php" class="search-form">
                <!-- 検索しても表示範囲が「マイ本棚／みんなの本棚」から切り替わらないよう引き継ぐ -->
                <input type="hidden" name="scope" value="<?= h($scope) ?>">
                <input type="text" name="q" class="search-input" placeholder="書籍名・コメントで検索" value="<?= h($q) ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> 検索
                </button>
                <?php if($q !== ''): ?>
                    <a href="select.php?scope=<?= h($scope) ?>" class="search-clear">クリア</a>
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
    <a href="<?= $addHref ?>" class="page-nav page-nav--left" aria-label="ブックマーク登録へ戻る">
        <span class="page-nav-circle"><i class="fas fa-chevron-left"></i></span>
        <span class="page-nav-label">登録へ</span>
    </a>

    <script>
        // キーボードの←でも移動できる（入力中は無効）
        document.addEventListener('keydown', (e) => {
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
            if (e.key === 'ArrowLeft') location.href = '<?= $addHref ?>';
        });
    </script>
</body>

</html>
