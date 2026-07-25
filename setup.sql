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
--   deleted_at     : 退会日時。NULLなら在籍中（論理削除）
--                    アプリは「deleted_at IS NULL の人だけ」を対象に動く。
--                    一定期間の経過後、purge.php が物理削除する。
CREATE TABLE IF NOT EXISTS gs_user_table (
  id INT(12) NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NULL,
  lpw VARCHAR(255) NOT NULL,
  nickname VARCHAR(32) NOT NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  kanri_flg INT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  UNIQUE KEY uq_phone (phone),
  KEY idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 退会の記録（個人を特定できる情報は持たない）
--   「いつ・何日使って・何冊貯めていたか」だけを残す。ユーザーIDも保存しない。
--   purge.php が物理削除する直前に1行書き込む。
CREATE TABLE IF NOT EXISTS gs_withdrawal_log (
  id INT(12) NOT NULL AUTO_INCREMENT,
  used_days INT(6) NOT NULL,
  book_count INT(6) NOT NULL,
  withdrawn_at DATETIME NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 最初の管理者アカウントの作り方
--
--    ここにサンプルユーザーのINSERTは書かない。
--    パスワードが公開リポジトリに載ってしまい、誰でもその鍵でログインできるため。
--    （実際に「admin/admin1234」を書いていて、公開直前まで残っていた）
--
--    代わりに、サーバー上でコマンドを1回実行して作る:
--        php create_admin.php
--
--    ローカルで試すだけの場合も同じコマンドでよい。

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

-- 5. サンプルの本は入れない
--    持ち主(user_id)が決まっていないと入れられないため。
--    アカウントを作ったあと、画面から登録してください。

-- ============================================
-- ログイン試行の記録（パスワード総当たり対策）
-- ============================================
--   attempt_key  : メールアドレスとIPアドレスを組み合わせたハッシュ値
--                  ※メールアドレスそのものは保存しない（記録から会員が分かってしまうため）
--                  ※IPも混ぜるので、他人が特定の人を狙ってロックすることはできない
--   fail_count   : 連続で失敗した回数
--   last_fail_at : 直近の失敗時刻
CREATE TABLE IF NOT EXISTS gs_login_attempt (
  attempt_key CHAR(64) NOT NULL,
  fail_count INT(4) NOT NULL DEFAULT 0,
  last_fail_at DATETIME NOT NULL,
  PRIMARY KEY (attempt_key),
  KEY idx_last_fail (last_fail_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
