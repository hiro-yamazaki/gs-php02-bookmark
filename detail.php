<?php
require_once('funcs.php');

//一覧から ?id=◯ で編集対象を受け取る（数字以外・未指定は一覧へ戻す）
$id = $_GET['id'] ?? '';
if (!ctype_digit($id)) {
    header('Location: select.php');
    exit;
}

//1. DB接続（funcs.phpの共通関数）
$pdo = db_conn();

//2. SQL作成（WHEREで編集対象の1件だけ取得。バインド変数でSQLインジェクション対策）
$stmt = $pdo->prepare('SELECT * FROM gs_bm_table WHERE id = :id');
$stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);

//3. 実行（PHP8はSQLエラー時に例外が飛ぶのでcatchする）
try {
    $stmt->execute();
} catch (PDOException $e) {
    exit('ErrorQuery:' . $e->getMessage());
}

//4. 実行後の処理（対象データがなければ一覧へ戻す）
$result = $stmt->fetch(PDO::FETCH_ASSOC);
if ($result === false) {
    header('Location: select.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📚 ブックブックマーク - 編集</title>
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
            <h1 class="form-title">✏️ ブックマークを編集</h1>
            <p class="form-subtitle">内容を書き換えて「更新する」を押してください。</p>

            <!-- 更新対象のidはhiddenで送る（画面には出さない） -->
            <form method="POST" action="update.php">
                <input type="hidden" name="id" value="<?= (int)$result['id'] ?>">
                <!-- 表紙画像URLは編集画面では変更しないので、既存の値をそのまま持ち回す -->
                <input type="hidden" name="book_image" value="<?= h($result['image_url'] ?? '') ?>">

                <div class="form-group">
                    <label for="book_name" class="form-label">
                        <i class="fas fa-book"></i> 書籍名
                    </label>
                    <input type="text" id="book_name" name="book_name" class="form-input" maxlength="64" required value="<?= h($result['book_name']) ?>">
                </div>

                <div class="form-group">
                    <label for="book_url" class="form-label">
                        <i class="fas fa-link"></i> 書籍URL
                    </label>
                    <input type="url" id="book_url" name="book_url" class="form-input" required value="<?= h($result['book_url']) ?>">
                </div>

                <div class="form-group">
                    <label for="book_comment" class="form-label">
                        <i class="fas fa-comment"></i> 書籍コメント <span class="form-optional">任意</span>
                    </label>
                    <!-- textareaはvalue属性が使えないので、タグの間に初期値を書く -->
                    <textarea id="book_comment" name="book_comment" class="form-textarea"><?= h($result['book_comment']) ?></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-rotate"></i>
                    更新する
                </button>
            </form>

            <p class="form-cancel">
                <a href="select.php"><i class="fas fa-arrow-left"></i> 変更せずに一覧へ戻る</a>
            </p>
        </div>
    </main>
</body>

</html>
