# 📚 積読ストック（PHP02〜04 課題）

G's ACADEMY DEV31 PHP02〜04（PHP & DB / CRUD / ログイン）の課題。
**本をブックマークするDB＋登録・一覧・編集・削除＋ログイン機能** です。
（PHP04課題「ブックマークアプリ その4」= ログイン機能の追加、として提出）

🌐 **公開URL**: https://hr-frameworks.sakura.ne.jp/php02_hw/

「気になった瞬間に貯めておく。積読は資産。」をコンセプトに、
課題仕様（`gs_bm_table`）をベースに、積読を見える化する機能を足しています。

---

## 🔴 再提出での修正点（フィードバック対応）

> **いただいた指摘**: 「ログインを踏まなくてもメイン画面が表示されてしまう」

初回提出版では、一覧ページ（旧 `select.php`）を**ログイン不要**にしていました。
課題文の例示（「ログインが不要なページ 例；一覧ページ」）に沿わせた作りでしたが、
**アプリの中身がログインなしで見えてしまう**状態だったため、以下のとおり修正しました。

| # | 修正内容 |
|---|---|
| 1 | **メイン画面を含む全ページをログイン必須に**。`index.php`（メイン画面）の先頭で `loginCheck()` を呼び、未ログインでURLを直接叩いても `login.php` へ戻す |
| 2 | **未ログインの入口として `welcome.php` を新設**。ログインしていない人にはサービス紹介だけを見せ、**登録データは一切表示しない** |
| 3 | 旧 `select.php` は `index.php` へ **301リダイレクト**（過去の提出先URLから来ても、ログイン画面に着地する） |

**ログイン必須ページ / 不要ページの切り分け**（課題要件「ログイン不要ページとログイン必要ページを含む」への対応）

| 区分 | ページ |
|---|---|
| 🔓 ログイン不要 | `welcome.php`（サービス紹介＝未ログインの入口） / `login.php` / `signup.php`（アカウント作成） |
| 🔒 ログイン必要 | **`index.php`（メイン画面）** / `detail.php` / `insert.php` / `update.php` / `delete.php` / `mypage.php` / `verify_email.php` |
| 👑 管理者に追加権限 | `delete.php`：一般ユーザーは**自分の本しか削除できない**が、管理者(`kanri_flg=1`)は全件削除できる |

---

## PHP04課題での実装内容（ログイン機能）

授業Day4で学んだ **セッションを使ったログイン機能** がベースです。

| # | 実装 | 内容 |
|---|---|---|
| 1 | 🔑 ログイン / ログアウト | `login.php`（フォーム）→ `login_act.php`（`gs_user_table` をメールアドレスで検索し `password_verify()` で照合）。成功時に `session_regenerate_id(true)` でセッションIDを作り替え、その値を `$_SESSION['chk_ssid']` に「鍵」として保存。`logout.php` でセッション破棄 |
| 2 | 🔒 ログインチェックの共通関数化 | `funcs.php` の `loginCheck()`。ログイン必要ページの先頭で呼ぶだけで「未ログイン or セッションID不一致なら `login.php` へ戻す」を実現。毎回 `session_regenerate_id()` でIDを更新しセッションハイジャックに備える |
| 3 | 👑 権限分岐（kanri_flg） | `delete.php` で `isAdmin()` により発行するSQLを切り替える。一般ユーザーは `WHERE id = :id AND user_id = :user_id` で**自分の本しか消せない**、管理者は `WHERE id = :id` で全件削除できる（公開された不適切な内容に対応するための権限）。画面のボタン表示だけに頼らず、**サーバー側のSQLで制限**（URL直打ち・hidden書き換えでも弾ける） |
| 4 | 🔐 パスワードのハッシュ化 | `password_hash()`（bcrypt）で保存し `password_verify()` で照合。平文パスワードはDBに持たない |

### 授業内容からの発展

課題の範囲を超えて、実サービスとして公開できる水準まで作り込んでいます。

| 機能 | 内容 |
|---|---|
| 👤 アカウント作成 | `signup.php` → `signup_act.php`。メールアドレス＋パスワードで自分のアカウントを作れる |
| ✉️ メール本人確認 | 登録すると確認コードをメール送信（`mailer.php`）。`verifyCheck()` で**確認が済むまでアプリを使わせない**。コードはハッシュ化して保存し、有効期限・試行回数・再送回数を制限 |
| 📚 ユーザーごとの本棚 | ブックマークを `user_id` で持ち主に紐付け。既定は非公開で、「マイ本棚」には自分の本だけが出る。`is_public` を立てた本だけが「みんなの本棚」（`index.php?scope=public`）で他の人にも見える |
| 🙍 マイページ | `mypage.php`：表示名の変更 / パスワード変更 / 退会 |
| 🗑 退会（論理削除＋復元） | 退会しても即消さず `deleted_at` を立てるだけ。**30日以内なら `restore.php` で復元可能**。期限後は `purge.php`（cron実行）で物理削除し、個人情報を残さない |
| 🛡 ログイン試行制限 | `gs_login_attempt` テーブルで失敗回数を記録し、一定回数で一時ロック（総当たり対策） |
| 🎫 CSRF対策 | `csrfToken()` / `csrfField()` / `csrfCheck()` で全POSTフォームを保護 |
| ⏰ セッション有効期限 | `isSessionExpired()` で放置されたセッションを自動失効 |

---

## PHP03課題での実装内容（CRUD）

| # | 機能 | 内容 |
|---|---|---|
| 1 | ✏️ 編集（UPDATE） | 一覧の「編集」→ `detail.php?id=◯`（GETでID受け渡し）→ 初期値入りフォーム → `update.php` が `UPDATE ... WHERE id = :id` で更新 |
| 2 | 🗑 削除（DELETE） | 「削除」ボタン（POST + hidden id）→ 確認ダイアログ → `delete.php` が `DELETE ... WHERE id = :id` で1件削除 |
| 3 | ⌨️ Enterで書籍検索 | 書籍名欄でEnter → 登録ではなく「本を探す」を実行（IME変換確定のEnterでは発動しない） |
| 4 | 🔍 検索候補の改善 | 候補を最大8件。関連度順で返るGoogle Booksを主役に、国立国会図書館APIを補完役に |
| 5 | 💬 コメントを任意に | 書籍名・URLのみ必須。未入力は空文字で保存 |

**授業の最重要ポイントの反映**: UPDATE / DELETE は必ず `WHERE`＋`bindValue`（idは `PARAM_INT`）、
DB書き換えはPOST、IDはhiddenで送りつつ `ctype_digit()` でサーバー側検証（hiddenは書き換えられる前提）。

---

## 機能

- 📝 本のブックマーク登録（書籍名 / URL は必須、コメントは任意）
- 📖 **本を探す**: 書籍名から候補を最大8件表示し、クリックで**Amazon商品ページのURLを自動入力**
  （Google Books API→国立国会図書館サーチAPIの2段構え・どちらもキー不要。
  取得したISBNからAmazonのURLを組み立てる。API連携は第6回授業の応用）
- 🖼 **表紙表示**: 「本を探す」で選んだ本は表紙画像も保存され、一覧がミニ本棚になる
- 🔍 **検索**: 書籍名・コメントの部分一致（授業で習った `LIKE '%〜%'` を応用）
- 📊 **集計バー**: 積読ストック数 / 今週の追加数 / 表示中件数（`COUNT` と `SUM(条件)` で集計）
- ✏️ 編集（UPDATE）/ 🗑 削除（DELETE・確認ダイアログつき）
- 🔓 1冊ごとの公開／非公開切り替え（公開した本だけが「みんなの本棚」に並ぶ）

### Amazonアソシエイト対応（準備済み）

`funcs.php` の `AMAZON_ASSOCIATE_TAG` にトラッキングID（例: `xxxx-22`）を設定すると、
「本を探す」が生成するAmazonリンクが自動でアフィリエイトURLになる。未設定の間は通常の商品リンク。

## 画面遷移

```
【未ログイン】
welcome.php（サービス紹介・唯一の入口）
   ├─ signup.php ─POST─> signup_act.php ─> verify_email.php（確認コード入力）
   └─ login.php  ─POST─> login_act.php
                             │ 認証OK
                             ▼
【ログイン後】
index.php（メイン画面：本を探す → 積む → 一覧・検索・集計 を1画面で完結）
   │  ※ select.php は index.php へ301リダイレクト（旧URL互換）
   │  ※ タブ切替：「マイ本棚」= 自分の全部 / 「みんなの本棚」= 他の人が公開した本（?scope=public）
   │
   ├─ POST ──────────────> insert.php（INSERT）→ index.php へ戻る
   │
   ├─「編集」--(GET: ?id=◯)-> detail.php（編集フォーム・初期値入り）
   │                              │ POST（idはhidden）
   │                              ▼
   │                          update.php（UPDATE ... WHERE id）→ index.php へ戻る
   │
   ├─「削除」--(POST・確認ダイアログ)-> delete.php（DELETE ... WHERE id）※管理者のみ
   │
   └─ mypage.php（表示名変更 / パスワード変更 / 退会）
                              │ 退会（論理削除）
                              ▼
                          restore.php（30日以内なら復元）… 期限後は purge.php が物理削除
```

## DB仕様

- DB名: `gs_bookmark_db`（utf8mb4_unicode_ci ※絵文字も保存可）

### `gs_bm_table`（ブックマーク）

| カラム名 | 型 | 補足 |
|---|---|---|
| id | int(12) | PRIMARY KEY / AUTO_INCREMENT |
| user_id | int(12) | 持ち主（`gs_user_table.id` への外部キー。退会時は ON DELETE CASCADE） |
| book_name | varchar(64) | 書籍名 |
| book_url | text | 書籍URL |
| book_comment | text | 書籍コメント（任意） |
| image_url | text | 表紙画像URL（任意。「本を探す」で自動設定） |
| is_public | tinyint(1) | 公開フラグ（1=公開, 0=非公開）。既定は0 |
| created_at | datetime | 登録日時（INSERT時に `NOW()` で自動設定） |

### `gs_user_table`（ユーザー）

| カラム名 | 型 | 補足 |
|---|---|---|
| id | int(12) | PRIMARY KEY / AUTO_INCREMENT |
| email | varchar(255) | ログインID兼連絡先（UNIQUE） |
| phone | varchar(20) | 電話番号（任意・NULL可） |
| nickname | varchar(32) | 画面表示名（重複可） |
| lpw | varchar(255) | パスワード。`password_hash()`（bcrypt）のハッシュ値 |
| email_verified | tinyint(1) | メール確認済みフラグ（1=確認済み） |
| kanri_flg | int(1) | 管理者フラグ（1=管理者, 0=一般） |
| created_at | datetime | 登録日時 |
| deleted_at | datetime | 退会日時（NULL=有効。値が入っていれば退会＝論理削除） |

### その他のテーブル

| テーブル | 役割 |
|---|---|
| `gs_verify_code` | メール確認コード（ハッシュ・有効期限・試行回数・再送回数） |
| `gs_login_attempt` | ログイン失敗回数（総当たり対策の一時ロック用） |
| `gs_withdrawal_log` | 退会の統計ログ（個人情報は持たず、利用日数と冊数のみ） |

> **⚠️ 動作確認用アカウントについて**
> 以前このREADMEに固定のテストアカウント（`admin` / パスワード）を記載していましたが、
> **公開リポジトリに認証情報を書くのは危険**なため削除しました。
> 管理者アカウントはサーバー上で `php create_admin.php` を実行して作成します（対話式・CLI専用）。

## ファイル構成

| ファイル | 役割 |
|---|---|
| welcome.php | **未ログインの入口**。サービス紹介のみ。登録データは一切出さない |
| index.php | **メイン画面（要ログイン）**。本を探す→積む→一覧・検索・集計を1画面で完結 |
| select.php | 旧一覧ページ。`index.php` へ301リダイレクト（旧URL互換のため残置） |
| insert.php | 登録処理。POSTチェック→バインド変数でINSERT |
| detail.php | 編集フォーム。GETでidを受け取り、対象1件をWHEREで取得して初期値表示 |
| update.php | 更新処理。`UPDATE ... WHERE id = :id`（WHERE必須！） |
| delete.php | 削除処理。`DELETE ... WHERE id = :id`（WHERE必須！）。一般ユーザーは `AND user_id = :user_id` が付き自分の本だけ、管理者は全件削除可 |
| signup.php / signup_act.php | アカウント作成フォーム / 作成処理 |
| verify_email.php / verify_act.php / resend_act.php | メール確認コードの入力 / 照合 / 再送 |
| login.php / login_act.php / logout.php | ログインフォーム / 認証処理 / ログアウト |
| mypage.php | マイページ（表示名・パスワード変更、退会） |
| profile_act.php / password_act.php / withdraw_act.php | 上記各処理 |
| restore.php / restore_act.php | 退会後30日以内のアカウント復元 |
| purge.php | 猶予期間を過ぎた退会アカウントの物理削除（**CLI専用**・cronで定期実行） |
| create_admin.php | 管理者アカウント作成（**CLI専用**・対話式） |
| search.php | 書籍検索の中継API（Google Books→NDL、ISBN→Amazon URL変換） |
| funcs.php | 共通関数（`h()` / `db_conn()` / `loginCheck()` / `adminCheck()` / `verifyCheck()` / CSRF / 認証コード など） |
| mailer.php / sms.php | メール送信 / SMS送信（SMSは将来の2段階認証用・現在は未使用） |
| config.sample.php | 本番用設定のサンプル（`config.php` にコピーして使う） |
| .htaccess | HTTPS強制 / `.sql`・`.md`・`config.php`・CLI専用スクリプトへの直接アクセス遮断 / 保護ヘッダー |
| setup.sql | **新規構築用**：全テーブルの作成 |
| migrate_phase1〜6.sql | **既存DB更新用**：稼働中のDBを作り直さずに移行する |

## 動かし方（ローカル / MAMP）

1. MAMPを起動（Apache / MySQL。MySQLポートは8889＝MAMPデフォルト）
2. phpMyAdmin で `setup.sql` を実行（初回のみ。全テーブルが作られる）
3. `php create_admin.php` で最初の管理者アカウントを作成
4. MAMP の htdocs に配置して http://localhost:8888/kadai03_bookmark/
   （または このフォルダで `php -S localhost:8000`）
5. `welcome.php` から「アカウント作成」または「ログイン」
   ※未ログインで `index.php` を直接開いても `login.php` へ戻されます

## 本番サーバーへのデプロイ（さくらサーバー等）

### A. 新規に構築する場合

1. コントロールパネルでDBを作成し、phpMyAdmin で `setup.sql` を実行
2. `config.sample.php` を `config.php` にコピーし、DB接続情報とメール送信設定を記入
   （`config.php` は .gitignore 済みなので**公開リポジトリには載らない**）
3. 一式をFTPでアップロード
4. サーバー上で `php create_admin.php` を実行して管理者を作成

### B. 稼働中のDBを更新する場合（移行）

> **⚠️ 実行前に必ず phpMyAdmin の「エクスポート」でDB全体のバックアップを取ること。**
> 途中で失敗した場合、戻せるのはバックアップだけです。

`migrate_phase1.sql` → `phase2` → … → `phase6` の**順番どおり**に実行します。

> **⚠️ `migrate_phase2.sql` の手順3を必ず手で書き換えてから進めること。**
> 既存ユーザーに仮のメールアドレス（`nickname@example.com`）を割り当てる箇所です。
> ここを仮の値のままにすると、**`migrate_phase6.sql` が `admin@example.com` を削除し、
> 外部キーの ON DELETE CASCADE によって、そのユーザーの登録済みブックマークが
> すべて巻き添えで消えます。** 実際に使えるアドレスへ書き換えてから phase3 以降へ進んでください。

### C. メール送信の設定

`config.php` の `mail` を設定しないと `driver` が `log` にフォールバックし、
確認コードが**実際には送信されません**（PHPのエラーログに出るだけ）。
本番では `'driver' => 'mail'`、`from` は**そのサーバーで送信が許可されているドメイン**にしてください。

### D. 退会データの定期削除（任意だが推奨）

```
0 4 * * * /usr/local/bin/php /home/ユーザー名/www/purge.php
```

## 工夫した点

- **SQLインジェクション対策**: prepare + bindValue（授業どおり）
- **UPDATE / DELETE は必ずWHERE**: 授業の最重要ポイント。idは `PARAM_INT` でバインドし、`ctype_digit()` で数字のみ許可
- **XSS対策**: 出力箇所はすべて `h()`（htmlspecialchars ENT_QUOTES）。書籍URLは `http/https` のみ受け付け、`javascript:` を保存させない（格納型XSS対策）
- **直接アクセス対策**: 各処理ファイルはPOST以外を弾き、必須項目の未入力はフォームへリダイレクト（クライアントの `required` に頼らない）
- **認可はサーバー側で**: ボタンを隠すだけでなく、SQLの `WHERE` に `user_id` を入れて**他人のデータは操作できない**ようにする（URL直打ち・hidden書き換えでも弾ける）
- **パスワードのハッシュ化**: `password_hash()`（bcrypt）で保存し `password_verify()` で照合
- **確認コードもハッシュ化**: DBを覗かれても他人の本人確認を突破されないようにする
- **個人情報を残さない**: 退会は論理削除→猶予期間後に物理削除（`purge.php`）
- **秘密情報をリポジトリに置かない**: DB接続情報は `config.php`（.gitignore）、管理者パスワードは `create_admin.php` で対話入力
- **PHP8対応**: PDOの例外を try-catch で捕捉し、本番では詳細を画面に出さずログへ
- **文字コード**: utf8mb4 で絵文字コメントにも対応
