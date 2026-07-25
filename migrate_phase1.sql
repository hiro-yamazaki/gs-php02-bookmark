-- ============================================
-- Phase 1 移行SQL：既に動いているDBを作り直さずに更新する
-- ============================================
-- 対象：setup.sql の旧版（gs_bm_table に user_id / is_public が無い状態）で
--       すでにデータが入っているDB（ローカルのMAMP・さくらサーバー）。
--
-- 新規に作り直す場合はこのファイルは不要。setup.sql をそのまま実行する。
--
-- 【実行前に必ずバックアップを取ること】
--   phpMyAdmin →「エクスポート」で gs_bookmark_db を丸ごと保存してから実行する。
--   途中で失敗した場合、戻せるのはバックアップだけ。
-- ============================================

USE gs_bookmark_db;

-- 1. カラムを追加する
--    user_id は最初 DEFAULT 0 で入れる（既存行に値が無いと NOT NULL を付けられないため）
ALTER TABLE gs_bm_table
  ADD COLUMN user_id INT(12) NOT NULL DEFAULT 0 AFTER id,
  ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER image_url;

-- 2. 既存のブックマークの持ち主を決める
--    これまでは全員で1つの本棚を共有していたので、持ち主の情報が残っていない。
--    ここでは「管理者(kanri_flg=1)のうち一番若いID」の本棚として引き取る。
--    ※別の人に割り当てたい場合は、下のサブクエリを実際のIDに置き換える
--      例: UPDATE gs_bm_table SET user_id = 2 WHERE user_id = 0;
UPDATE gs_bm_table
SET user_id = (SELECT id FROM gs_user_table WHERE kanri_flg = 1 ORDER BY id LIMIT 1)
WHERE user_id = 0;

-- 3. 引き取り先が見つからなかった行が無いか確認する
--    ここで 0 件でないまま次へ進むと、手順4の外部キー作成が必ず失敗する。
SELECT COUNT(*) AS 持ち主未設定の件数 FROM gs_bm_table WHERE user_id = 0;

-- 4. DEFAULT 0 を外し、外部キー制約を付ける
--    ON DELETE CASCADE：退会したユーザーのブックマークは自動で削除される
ALTER TABLE gs_bm_table
  ALTER COLUMN user_id DROP DEFAULT;

ALTER TABLE gs_bm_table
  ADD KEY idx_user (user_id),
  ADD KEY idx_public (is_public),
  ADD CONSTRAINT fk_bm_user FOREIGN KEY (user_id) REFERENCES gs_user_table (id) ON DELETE CASCADE;

-- 5. 確認（各ユーザーが何件持っているか）
SELECT u.id, u.lid, COUNT(b.id) AS 冊数
FROM gs_user_table u
LEFT JOIN gs_bm_table b ON b.user_id = u.id
GROUP BY u.id, u.lid
ORDER BY u.id;
