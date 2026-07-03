# 📚 積読ストック（PHP02 課題）

G's ACADEMY DEV31 PHP02（PHP & DB）の課題。
**本をブックマークするDB＋登録ページ** です。

🌐 **公開URL**: https://hr-frameworks.sakura.ne.jp/php02_hw/

「気になった瞬間に貯めておく。積読は資産。」をコンセプトに、
課題仕様（gs_bm_table）はそのままに、積読を見える化する機能を足しています。

## 機能

- 📝 本のブックマーク登録（書籍名 / URL / コメント）
- 📚 一覧表示（新しい順）
- 🔍 **検索**: 書籍名・コメントの部分一致（授業で習った `LIKE '%〜%'` を応用）
- 📊 **集計バー**: 積読ストック数 / 今週の追加数 / 表示中件数（`COUNT` と `SUM(条件)` で集計）

## 画面遷移

```
index.php（登録フォーム）
   │ POST
   ▼
insert.php（DBへINSERT）
   │ 登録成功
   ▼
index.php へリダイレクトで戻る

select.php（一覧・検索・集計）… ヘッダーのリンクから移動
```

## DB仕様

- DB名: `gs_bookmark_db`（新規作成 / utf8mb4_unicode_ci ※絵文字も保存可）
- テーブル名: `gs_bm_table`

| カラム名 | 型 | 補足 |
|---|---|---|
| id | int(12) | PRIMARY KEY / AUTO_INCREMENT（ユニーク値） |
| book_name | varchar(64) | 書籍名 |
| book_url | text | 書籍URL |
| book_comment | text | 書籍コメント |
| created_at | datetime | 登録日時（INSERT時に `NOW()` で自動設定） |

DB・テーブルの作成は `setup.sql` を phpMyAdmin のSQLタブで実行（サンプルデータ3件つき）。

## ファイル構成

| ファイル | 役割 |
|---|---|
| index.php | 登録フォーム（書籍名 / 書籍URL / 書籍コメント） |
| insert.php | 登録処理。POSTチェック→バインド変数でINSERT→index.phpへ戻る |
| select.php | 一覧・検索（LIKE）・集計（COUNT）。h()でエスケープして出力 |
| funcs.php | 共通関数（h() / db_conn() = DB接続の一元化） |
| config.sample.php | 本番サーバー用DB設定のサンプル（config.php にコピーして使う） |
| css/style.css | 見た目（PHP02授業のスタイルをベースに配色変更） |
| setup.sql | DB / テーブル作成＋サンプルデータ |

## 動かし方（ローカル / MAMP）

1. MAMPを起動（Apache / MySQL。MySQLポートは8889）
2. phpMyAdmin で `setup.sql` を実行（初回のみ）
3. このフォルダで `php -S localhost:8000` → http://localhost:8000/index.php
   （または MAMP の htdocs に配置して http://localhost:8888/kadai03_bookmark/）

## 本番サーバーへのデプロイ（さくらサーバー等）

1. サーバーのコントロールパネルでDBを作成し、phpMyAdmin で `setup.sql` の
   `CREATE TABLE` 以降を実行（DB自体はコンパネで作るため）
2. `config.sample.php` を `config.php` にコピーして、サーバーのDB接続情報を記入
   （`config.php` は .gitignore 済みなので**公開リポジトリには載らない**）
3. フォルダ一式をFTP等でアップロード → `index.php` にアクセス

## 工夫した点

- **SQLインジェクション対策**: prepare + bindValue（授業どおり）
- **XSS対策**: 出力箇所はすべて `h()`（htmlspecialchars ENT_QUOTES）を通す。さらに書籍URLは `http/https` のみ受け付け、`javascript:` などを保存させない（格納型XSS対策）
- **直接アクセス対策**: insert.php はPOST以外・未入力・書籍名64文字超はフォームへリダイレクト（クライアントのrequired/maxlengthに頼らない）
- **PHP8対応**: PDOの例外を try-catch で捕捉（SQLエラー時に生のスタックトレースを出さない）
- **文字コード**: utf8mb4 で絵文字コメントにも対応
- 登録日時はフォームに持たせず DB側の `NOW()` で自動付与（idもAUTO_INCREMENT任せ）
- 一覧のURLは `target="_blank" rel="noopener noreferrer"`付きリンクで開ける
