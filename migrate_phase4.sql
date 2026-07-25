-- ============================================
-- Phase 4 移行SQL：本人確認をSMSからメールに切り替える
-- ============================================
-- 前提：migrate_phase3.sql まで実行済みであること。
-- 新規に作り直す場合は setup.sql に反映済みなので不要。
--
-- 変更内容
--   ・phone_verified → email_verified に用途変更（確認対象が電話番号からメールへ）
--   ・電話番号を任意項目に（NULL許可）
--
-- 【実行前に必ずバックアップを取ること】
-- ============================================

USE gs_bookmark_db;

-- 1. 確認済みフラグの対象をメールアドレスに変える
ALTER TABLE gs_user_table
  CHANGE COLUMN phone_verified email_verified TINYINT(1) NOT NULL DEFAULT 0;

-- 2. 電話番号を任意項目にする（本人確認はメールで行うため必須ではなくなった）
ALTER TABLE gs_user_table
  MODIFY COLUMN phone VARCHAR(20) NULL;

-- 3. 空文字で入っている電話番号をNULLに寄せる
--    空文字のままだと、2人目以降が UNIQUE 制約に引っかかって登録できない。
--    NULL は UNIQUE 制約の対象外なので、未入力の人が何人いても問題ない。
UPDATE gs_user_table SET phone = NULL WHERE phone = '';

-- 4. 既存ユーザーの扱い
--    すでに使っているアカウントを締め出さないよう、確認済みのまま残す。
--    全員にメール確認をやり直させたい場合は、下のコメントを外して実行する。
-- UPDATE gs_user_table SET email_verified = 0;

-- 5. 確認
SELECT id, email, phone, nickname, email_verified, kanri_flg, created_at
FROM gs_user_table ORDER BY id;
