-- ============================================
-- 本番サーバー用（さくら等）: テーブル作成＋サンプルデータ
-- ※DB自体はサーバーのコントロールパネルで作成するため
--   CREATE DATABASE は含まない。
--   phpMyAdminで対象DBを選択してから実行する。
-- ============================================

CREATE TABLE IF NOT EXISTS gs_bm_table (
  id INT(12) NOT NULL AUTO_INCREMENT,
  book_name VARCHAR(64) NOT NULL,
  book_url TEXT NOT NULL,
  book_comment TEXT NOT NULL,
  image_url TEXT,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- （既存テーブルに表紙カラムを後から足す場合はこちら）
-- ALTER TABLE gs_bm_table ADD COLUMN image_url TEXT AFTER book_comment;

INSERT INTO gs_bm_table (id, book_name, book_url, book_comment, image_url, created_at) VALUES
(NULL, 'リーダブルコード', 'https://www.oreilly.co.jp/books/9784873115658/', '読みやすいコードの書き方の定番本。変数名の付け方から学び直したい。', 'https://images-na.ssl-images-amazon.com/images/P/4873115655.09.MZZZZZZZ.jpg', NOW()),
(NULL, '独習PHP 第4版', 'https://www.shoeisha.co.jp/book/detail/9784798168491', 'PHPの基礎固めに。授業の復習用として手元に置いておきたい一冊。', 'https://images-na.ssl-images-amazon.com/images/P/4798168491.09.MZZZZZZZ.jpg', NOW()),
(NULL, 'SQLアンチパターン', 'https://www.oreilly.co.jp/books/9784873115894/', 'DBを学び始めたので、やってはいけない設計を先に知っておきたい。', 'https://images-na.ssl-images-amazon.com/images/P/4873115892.09.MZZZZZZZ.jpg', NOW());

-- ============================================
-- 課題4：ログイン用ユーザーテーブル（本番サーバー用）
-- ============================================
CREATE TABLE IF NOT EXISTS gs_user_table (
  id INT(12) NOT NULL AUTO_INCREMENT,
  lid VARCHAR(32) NOT NULL,
  lpw VARCHAR(255) NOT NULL,
  kanri_flg INT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lid (lid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- サンプルユーザー（password_hash()でハッシュ化済み・平文では保存しない）
--   admin / admin1234  … 管理者（登録・編集・削除まで可能）
--   user  / user1234   … 一般（登録・編集のみ／削除は不可）
INSERT INTO gs_user_table (lid, lpw, kanri_flg) VALUES
('admin', '$2y$12$mfiv1GcAJxE0ipBKqpRFTeTW7H4lqHxJ5jBxMFlJTVOCZucJYgALW', 1),
('user',  '$2y$12$NC27aUWoEc76WHSgnNisLuP45Uv3Y8Sg.2XQTIK05MixIhZW.Dl4a', 0);
