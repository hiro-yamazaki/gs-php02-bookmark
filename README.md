# 📚 積読ストック（PHP02-03 課題）

G's ACADEMY DEV31 PHP02-03（PHP & DB / CRUD）の課題。
**本をブックマークするDB＋登録・一覧・編集・削除ページ** です。
（PHP03課題「ブックマークアプリ その2」= 前回課題への更新・削除機能の追加、として提出）

🌐 **公開URL**: https://hr-frameworks.sakura.ne.jp/php02_hw/

「気になった瞬間に貯めておく。積読は資産。」をコンセプトに、
課題仕様（gs_bm_table）はそのままに、積読を見える化する機能を足しています。

## 機能

- 📝 本のブックマーク登録（書籍名 / URL は必須、コメントは任意）
- 📖 **本を探す**: 書籍名から候補を最大8件表示し、クリックで**Amazon商品ページのURLを自動入力**
  （Google Books API→国立国会図書館サーチAPIの2段構え・どちらもキー不要。
  取得したISBNからAmazonのURLを組み立てる。API連携は第6回授業の応用）
- 🖼 **表紙表示**: 「本を探す」で選んだ本は表紙画像も保存され、一覧がミニ本棚になる
  （表紙は国立国会図書館の書影API。画像がない本は📚プレースホルダー）
- 📚 一覧表示（新しい順）
- 🔍 **検索**: 書籍名・コメントの部分一致（授業で習った `LIKE '%〜%'` を応用）
- 📊 **集計バー**: 積読ストック数 / 今週の追加数 / 表示中件数（`COUNT` と `SUM(条件)` で集計）
- ✏️ **編集（UPDATE）**: 一覧の「編集」から編集画面へ。登録内容を初期値表示して書き換え・更新
- 🗑 **削除（DELETE）**: 一覧の「削除」ボタンで1件削除。誤操作防止の**確認ダイアログ**つき

### Amazonアソシエイト対応（準備済み）

`funcs.php` の `AMAZON_ASSOCIATE_TAG` にアソシエイトのトラッキングID（例: `xxxx-22`）を
設定すると、「本を探す」が生成するAmazonリンクが自動でアフィリエイトURLになる。
未設定の間は通常の商品リンク。

## 画面遷移

```
index.php（登録フォーム）
   │ POST
   ▼
insert.php（DBへINSERT）→ index.php へ戻る

select.php（一覧・検索・集計）… ヘッダーのリンクから移動
   │
   ├─ 「編集」リンク --(GET: ?id=◯)--> detail.php（編集フォーム・初期値入り）
   │                                      │ POST（idはhidden）
   │                                      ▼
   │                                   update.php（UPDATE ... WHERE id）→ select.php へ戻る
   │
   └─ 「削除」ボタン --(POST: idはhidden・確認ダイアログ)--> delete.php（DELETE ... WHERE id）→ select.php へ戻る
```

## DB仕様

- DB名: `gs_bookmark_db`（新規作成 / utf8mb4_unicode_ci ※絵文字も保存可）
- テーブル名: `gs_bm_table`

| カラム名 | 型 | 補足 |
|---|---|---|
| id | int(12) | PRIMARY KEY / AUTO_INCREMENT（ユニーク値） |
| book_name | varchar(64) | 書籍名 |
| book_url | text | 書籍URL |
| book_comment | text | 書籍コメント（任意。未入力は空文字で保存＝スキーマ変更なしで対応） |
| image_url | text | 表紙画像URL（任意。「本を探す」で自動設定） |
| created_at | datetime | 登録日時（INSERT時に `NOW()` で自動設定） |

DB・テーブルの作成は `setup.sql` を phpMyAdmin のSQLタブで実行（サンプルデータ3件つき）。

## ファイル構成

| ファイル | 役割 |
|---|---|
| index.php | 登録フォーム（書籍名 / 書籍URL / 書籍コメント） |
| insert.php | 登録処理。POSTチェック→バインド変数でINSERT→index.phpへ戻る |
| select.php | 一覧・検索（LIKE）・集計（COUNT）。h()でエスケープして出力。編集リンク・削除ボタンつき |
| detail.php | 編集フォーム。GETでidを受け取り、対象1件をWHEREで取得して初期値表示 |
| update.php | 更新処理。入力チェック→ `UPDATE ... WHERE id = :id`（WHERE必須！）→一覧へ戻る |
| delete.php | 削除処理。POSTでidを受け取り `DELETE ... WHERE id = :id`（WHERE必須！）→一覧へ戻る |
| search.php | 書籍検索の中継API（NDL→Google Books、ISBN→Amazon URL変換） |
| funcs.php | 共通関数（h() / db_conn() = DB接続の一元化 / アソシエイトタグ設定） |
| config.sample.php | 本番サーバー用DB設定のサンプル（config.php にコピーして使う） |
| css/style.css | 見た目（PHP02授業のスタイルをベースに配色変更） |
| setup.sql | DB / テーブル作成＋サンプルデータ |

## 動かし方（ローカル / MAMP）

1. MAMPを起動（Apache / MySQL。MySQLポートは8889＝MAMPデフォルト。`funcs.php` のローカル接続設定と合わせる）
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
- **UPDATE / DELETE は必ずWHERE**: 授業の最重要ポイント。idは `PARAM_INT` でバインドし、`ctype_digit()` で数字のみ許可（hiddenはデベロッパーツールで書き換えられる前提でサーバー側検証）
- **削除の確認ダイアログ**: `onsubmit="return confirm(...)"` で誤操作防止。書名は `json_encode` でJS文字列化してから `h()`（`'` や `"` を含む書名でも壊れない）
- **XSS対策**: 出力箇所はすべて `h()`（htmlspecialchars ENT_QUOTES）を通す。さらに書籍URLは `http/https` のみ受け付け、`javascript:` などを保存させない（格納型XSS対策）
- **直接アクセス対策**: insert.php / update.php / delete.php はPOST以外を弾き、必須項目（書籍名・URL）の未入力・書籍名64文字超はフォームへリダイレクト（クライアントのrequired/maxlengthに頼らない）
- **PHP8対応**: PDOの例外を try-catch で捕捉（SQLエラー時に生のスタックトレースを出さない）
- **文字コード**: utf8mb4 で絵文字コメントにも対応
- 登録日時はフォームに持たせず DB側の `NOW()` で自動付与（idもAUTO_INCREMENT任せ）
- 一覧のURLは `target="_blank" rel="noopener noreferrer"`付きリンクで開ける
