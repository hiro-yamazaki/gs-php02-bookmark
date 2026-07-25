-- ============================================
-- Phase 5 移行SQL：退会を「論理削除＋期限付き物理削除」に変える
-- ============================================
-- 前提：migrate_phase4.sql まで実行済みであること。
--
-- 変更内容
--   ・gs_user_table に deleted_at を追加（NULL＝在籍中、日時入り＝退会済み）
--   ・退会の記録を匿名で残す gs_withdrawal_log を追加
--
-- 【実行前に必ずバックアップを取ること】
-- ============================================

USE gs_bookmark_db;

-- 1. 退会日時。NULLなら在籍中
--    アプリ側は「deleted_at IS NULL の人だけ」を対象に動く。
--    この条件を書き忘れた画面があると、退会した人のデータが見えてしまう。
ALTER TABLE gs_user_table
  ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER created_at,
  ADD KEY idx_deleted (deleted_at);

-- 2. 退会の記録（個人を特定できる情報は持たない）
--    「いつ退会したか」「何日使ったか」「何冊貯めていたか」だけを残す。
--    ユーザーIDも保存しない。保存すると個人と結び付いてしまうため。
--
--    ※この表は退会傾向の分析用。不要なら作らなくてよい。
CREATE TABLE IF NOT EXISTS gs_withdrawal_log (
  id INT(12) NOT NULL AUTO_INCREMENT,
  used_days INT(6) NOT NULL,      -- 登録から退会までの日数
  book_count INT(6) NOT NULL,     -- 退会時点の登録冊数
  withdrawn_at DATETIME NOT NULL, -- 退会日
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 確認
SELECT id, email, nickname, created_at, deleted_at FROM gs_user_table ORDER BY id;
