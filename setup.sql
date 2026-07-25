-- ============================================
-- 積読ストック：DB作成 + テーブル作成 + サンプルデータ
-- phpMyAdminのSQLタブに貼り付けて実行してもOK
--
-- ※すでに動いているDBを更新する場合はこのファイルではなく
--   migrate_phase1.sql → migrate_phase2.sql の順に実行すること。
-- ============================================

-- 1. DB作成（好きなDB名で新規作成）
--    ※絵文字も保存できるよう utf8mb4 を使用
CREATE DATABASE IF NOT EXISTS gs_bookmark_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gs_bookmark_db;

-- ============================================
-- ユーザーテーブル（gs_bm_table から参照するので先に作る）
-- ============================================
-- 2. ユーザーテーブル作成（Table名: gs_user_table）
--   id             : ユニーク値（int / PRIMARY / AUTO_INCREMENT）
--   email          : ログインに使うメールアドレス（重複禁止）
--   phone          : 電話番号。任意項目。ハイフンなしの数字だけで保存する
--                    ※未入力はNULL。空文字にすると2人目以降がUNIQUE制約に引っかかる
--   lpw            : パスワード（password_hash()のハッシュ値を保存 / varchar 255）
--   nickname       : 画面に表示する名前（本名でなくてよい）
--   email_verified : メールアドレスの確認が済んでいるか（1=済 / 0=未）
--                    0の間は本棚を使えず、確認コードの入力画面に留まる
--   kanri_flg      : 管理者フラグ（1=管理者, 0=一般）
--   created_at     : 登録日時
CREATE TABLE IF NOT EXISTS gs_user_table (
  id INT(12) NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NULL,
  lpw VARCHAR(255) NOT NULL,
  nickname VARCHAR(32) NOT NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  kanri_flg INT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. サンプルユーザー（パスワードは password_hash() でハッシュ化済み・平文では保存しない）
--    admin@example.com / admin1234 … 管理者
--    user@example.com  / user1234  … 一般
--    ※ハッシュは bcrypt。別のパスワードにしたい場合は自分でハッシュを作り直して差し替える。
--    ※一般公開する前にこの2件は必ず削除すること。
INSERT INTO gs_user_table (email, phone, lpw, nickname, email_verified, kanri_flg, created_at) VALUES
('admin@example.com', NULL, '$2y$12$mfiv1GcAJxE0ipBKqpRFTeTW7H4lqHxJ5jBxMFlJTVOCZucJYgALW', 'admin', 1, 1, NOW()),
('user@example.com',  NULL, '$2y$12$NC27aUWoEc76WHSgnNisLuP45Uv3Y8Sg.2XQTIK05MixIhZW.Dl4a', 'user',  1, 0, NOW());

-- ============================================
-- ブックマークテーブル
-- ============================================
-- 4. テーブル作成（Table名: gs_bm_table）
--   id           : ユニーク値（int 12 / PRIMARY / AUTO_INCREMENT）
--   user_id      : 持ち主のユーザーID（gs_user_table.id を参照）
--   book_name    : 書籍名（varchar 64）
--   book_url     : 書籍URL（text）
--   book_comment : 書籍コメント（text）
--   image_url    : 表紙画像URL（text / 任意。「本を探す」で自動設定）
--   is_public    : 公開フラグ（1=他の利用者にも見せる, 0=自分だけ）※既定は非公開
--   created_at   : 登録日時（datetime）
--
-- ON DELETE CASCADE：ユーザーが退会したら、その人のブックマークも一緒に消える。
--   （持ち主のいないデータが残らないようにするため）
CREATE TABLE IF NOT EXISTS gs_bm_table (
  id INT(12) NOT NULL AUTO_INCREMENT,
  user_id INT(12) NOT NULL,
  book_name VARCHAR(64) NOT NULL,
  book_url TEXT NOT NULL,
  book_comment TEXT NOT NULL,
  image_url TEXT,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_user (user_id),
  KEY idx_public (is_public),
  CONSTRAINT fk_bm_user FOREIGN KEY (user_id) REFERENCES gs_user_table (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 電話番号のSMS認証で使う確認コードの保管テーブル
-- ============================================
--   code_hash  : 確認コードのハッシュ。コードそのものは保存しない
--   expires_at : 有効期限
--   attempts   : 入力を間違えた回数（総当たり対策）
--   send_count : 1時間あたりの送信回数（SMSは1通ごとに課金されるため上限を設ける）
CREATE TABLE IF NOT EXISTS gs_verify_code (
  user_id INT(12) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts INT(3) NOT NULL DEFAULT 0,
  send_count INT(3) NOT NULL DEFAULT 1,
  sent_at DATETIME NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_verify_user FOREIGN KEY (user_id) REFERENCES gs_user_table (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. サンプルデータ（admin=1 の本棚に3件。1件だけ公開にしてある）
INSERT INTO gs_bm_table (id, user_id, book_name, book_url, book_comment, image_url, is_public, created_at) VALUES
(NULL, 1, 'リーダブルコード', 'https://www.oreilly.co.jp/books/9784873115658/', '読みやすいコードの書き方の定番本。変数名の付け方から学び直したい。', 'https://images-na.ssl-images-amazon.com/images/P/4873115655.09.MZZZZZZZ.jpg', 1, NOW()),
(NULL, 1, '独習PHP 第4版', 'https://www.shoeisha.co.jp/book/detail/9784798168491', 'PHPの基礎固めに。授業の復習用として手元に置いておきたい一冊。', 'https://images-na.ssl-images-amazon.com/images/P/4798168491.09.MZZZZZZZ.jpg', 0, NOW()),
(NULL, 1, 'SQLアンチパターン', 'https://www.oreilly.co.jp/books/9784873115894/', 'DBを学び始めたので、やってはいけない設計を先に知っておきたい。', 'https://images-na.ssl-images-amazon.com/images/P/4873115892.09.MZZZZZZZ.jpg', 0, NOW());
