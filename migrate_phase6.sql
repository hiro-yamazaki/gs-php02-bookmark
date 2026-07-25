-- ============================================
-- Phase 6 移行SQL：ログイン試行回数の記録テーブルを追加する
-- ============================================
-- 前提：migrate_phase5.sql まで実行済みであること。
-- 新規に作り直す場合は setup.sql に反映済みなので不要。
-- ============================================

USE gs_bookmark_db;

-- パスワード総当たり対策の記録
--   attempt_key  : メールアドレスとIPアドレスを組み合わせたハッシュ値
--                  ※メールアドレスそのものは保存しない（記録から会員が分かってしまうため）
--                  ※IPも混ぜるので、他人が特定の人を狙ってロックすることはできない
CREATE TABLE IF NOT EXISTS gs_login_attempt (
  attempt_key CHAR(64) NOT NULL,
  fail_count INT(4) NOT NULL DEFAULT 0,
  last_fail_at DATETIME NOT NULL,
  PRIMARY KEY (attempt_key),
  KEY idx_last_fail (last_fail_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 【重要】公開前に必ず実行すること
-- ============================================
-- 過去のsetup.sqlが作ったサンプルアカウントを削除する。
-- パスワードが admin1234 / user1234 で固定されており、
-- リポジトリを見た人なら誰でもログインできてしまう。
--
-- 管理者アカウントは、削除後に次のコマンドで作り直す:
--     php create_admin.php
--
-- ※先に管理者を作ってから消したい場合は、順番を入れ替えても構わない。
--   ただし「最後の管理者は退会できない」制約はアプリ側の退会機能にのみ効くので、
--   このDELETEでは止まらない点に注意。
DELETE FROM gs_user_table WHERE email IN ('admin@example.com', 'user@example.com');

-- 確認（サンプルアカウントが残っていないこと）
SELECT id, email, nickname, kanri_flg, deleted_at FROM gs_user_table ORDER BY id;
