-- ============================================
-- Phase 2 移行SQL：ログインIDをメールアドレスに切り替える
-- ============================================
-- 前提：migrate_phase1.sql を実行済みであること。
--
-- 変更内容
--   ・lid（ログインID）→ nickname（画面表示名）に用途変更
--   ・email / phone / phone_verified / created_at を追加
--   ・ログインは email + パスワードで行う
--
-- 【実行前に必ずバックアップを取ること】
-- ============================================

USE gs_bookmark_db;

-- 1. 列を追加する
--    email と phone は既存行にも値が要るので、いったんデフォルト空で足す
ALTER TABLE gs_user_table
  ADD COLUMN email VARCHAR(255) NOT NULL DEFAULT '' AFTER id,
  ADD COLUMN phone VARCHAR(20) NOT NULL DEFAULT '' AFTER email,
  ADD COLUMN phone_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER lpw,
  ADD COLUMN created_at DATETIME NULL AFTER kanri_flg;

-- 2. lid を nickname にリネーム（表示名として使い続ける）
ALTER TABLE gs_user_table
  CHANGE COLUMN lid nickname VARCHAR(32) NOT NULL;

-- 3. 既存ユーザーに仮のメールアドレスと電話番号を入れる
--    ※ここは必ず実際に使えるアドレス／番号へ手で書き換えること。
--      仮の値のままだとパスワードを忘れたときに復旧できない。
UPDATE gs_user_table SET
  email = CONCAT(nickname, '@example.com'),
  phone = CONCAT('0900000', LPAD(id, 4, '0')),
  phone_verified = 1,
  created_at = NOW()
WHERE email = '';

-- 4. 重複がないことを確認する（0件でないと手順5が失敗する）
SELECT email, COUNT(*) AS 件数 FROM gs_user_table GROUP BY email HAVING COUNT(*) > 1;
SELECT phone, COUNT(*) AS 件数 FROM gs_user_table GROUP BY phone HAVING COUNT(*) > 1;

-- 5. 制約を付ける
ALTER TABLE gs_user_table
  ALTER COLUMN email DROP DEFAULT,
  ALTER COLUMN phone DROP DEFAULT;

ALTER TABLE gs_user_table
  MODIFY COLUMN created_at DATETIME NOT NULL,
  ADD UNIQUE KEY uq_email (email),
  ADD UNIQUE KEY uq_phone (phone);

-- 6. 旧ログインIDのユニーク制約は不要になったので外す
--    （nickname は重複してよい。同じ表示名の人がいても困らない）
ALTER TABLE gs_user_table DROP INDEX uq_lid;

-- 7. 確認
SELECT id, email, phone, nickname, phone_verified, kanri_flg, created_at
FROM gs_user_table ORDER BY id;
