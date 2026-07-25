-- ============================================
-- Phase 3 移行SQL：電話番号のSMS認証用テーブルを追加する
-- ============================================
-- 前提：migrate_phase2.sql まで実行済みであること。
-- 新規に作り直す場合は setup.sql に同じ定義が入っているので不要。
-- ============================================

USE gs_bookmark_db;

-- 確認コードの保管テーブル
--   user_id    : 対象ユーザー（1人につき常に最新の1件だけ持つ）
--   code_hash  : 確認コードのハッシュ。コードそのものは保存しない
--                （DBを覗かれても、他人の本人確認を突破されないようにするため）
--   expires_at : 有効期限。これを過ぎたコードは使えない
--   attempts   : 入力を間違えた回数。増えすぎたら総当たりとみなして無効にする
--   send_count : 1時間あたりの送信回数。SMSは1通ごとに課金されるので上限を設ける
--   sent_at    : 直近の送信時刻
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

-- 既存ユーザーは確認済み扱いのまま残す（後から全員に再認証を求めない）
-- 改めて全員に認証させたい場合は下のコメントを外して実行する
-- UPDATE gs_user_table SET phone_verified = 0;
