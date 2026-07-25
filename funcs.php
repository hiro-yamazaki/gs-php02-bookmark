<?php
//共通に使う関数を記述

//Amazonアソシエイトのトラッキングタグ（例: 'xxxx-22'）
//アソシエイト審査に承認されたらここに設定する。
//設定すると「本を探す」で生成されるAmazonリンクが自動でアフィリエイトURLになる。
const AMAZON_ASSOCIATE_TAG = '';

//XSS対応（ echoする場所で使用！それ以外はNG ）
function h($str){
    return htmlspecialchars($str, ENT_QUOTES,'UTF-8');
}

//DB接続（接続情報をここ1箇所に集約）
//本番サーバー（さくら等）では config.php（git管理外）の接続情報を使う
//ローカルのMac（開発機）では config.php があっても常にMAMPへ接続する
function db_conn(){
    $is_local = (PHP_OS_FAMILY === 'Darwin'); //Mac＝ローカル開発機
    if (!$is_local && file_exists(__DIR__ . '/config.php')) {
        //本番用（さくらサーバー等）
        $c = require __DIR__ . '/config.php';
    } else {
        //ローカル用（MAMP） Password:MAMP='root',XAMPP=''
        //※MAMPデフォルトポート（MySQL=8889）。MAMP側を標準ポート設定にした場合は3306に変更
        $c = [
            'dsn'  => 'mysql:dbname=gs_bookmark_db;charset=utf8mb4;host=127.0.0.1;port=8889',
            'user' => 'root',
            'pass' => 'root',
        ];
    }
    try {
        //接続できない時に長時間待たないよう5秒でタイムアウト
        return new PDO($c['dsn'], $c['user'], $c['pass'], [PDO::ATTR_TIMEOUT => 5]);
    } catch (PDOException $e) {
        exit('DBConnectError:' . $e->getMessage());
    }
}

// ======================================================
// ログイン関連（課題4で追加）
// セッションIDを「鍵」として使い、ログイン状態を管理する。
// ※これらを呼ぶページは、先頭で session_start() を実行しておくこと
// ======================================================

//ログインの有効期限（最終操作からの秒数）。これを過ぎたら自動ログアウトする
const LOGIN_TIMEOUT = 1800; //30分

//ログイン中ページをブラウザ／プロキシにキャッシュさせない
//  ログアウト後に「戻る」ボタンで中身が再表示されるのを防ぐ。
//  ※PHPの既定（session.cache_limiter=nocache）でも同等のヘッダーは出るが、
//    サーバーのphp.ini設定に左右されないようここで明示する。
function nocache(){
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

//有効期限切れかどうか（最終操作からLOGIN_TIMEOUT秒経過したか）
//  last_activityが無い場合も「切れている」扱いにする（安全側に倒す）
function isSessionExpired(){
    return !isset($_SESSION['last_activity'])
        || (time() - $_SESSION['last_activity']) > LOGIN_TIMEOUT;
}

//セッションを完全に破棄する（ログアウトと有効期限切れの共通処理）
function logoutSession(){
    $_SESSION = [];
    //セッションクッキーも無効化する（鍵をブラウザからも消す）
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

//ログインチェック（ログインが必要なページの先頭で呼ぶ）
//  未ログイン、またはブラウザとサーバーのセッションIDが一致しない場合は
//  ログイン画面へ戻す（＝ログインしていないと中身は見られない）
function loginCheck(){
    //① 未ログイン（シークレットウィンドウ・URL直打ちを含む）→ ログイン画面へ戻す
    if (!isset($_SESSION['chk_ssid']) || $_SESSION['chk_ssid'] !== session_id()) {
        header('Location: login.php');
        exit; //exitを忘れると以降のHTMLが出力され、中身が見えてしまう
    }
    //② 一定時間操作がなければ自動ログアウト（セッションの有効期限切れ）
    if (isSessionExpired()) {
        logoutSession();
        header('Location: login.php?timeout=1');
        exit;
    }
    //③ 操作があったので有効期限を延長する
    $_SESSION['last_activity'] = time();

    //④ ログイン中のページはキャッシュさせない
    nocache();
    //※セッションIDの再生成はログイン成功時（login_act.php）だけで行う。
    //  ここで毎回作り替えると、複数タブや戻るボタン操作で古いIDが無効化され、
    //  意図しないログアウトが起きるため。
}

//管理者チェック（loginCheck()の後に呼ぶ。kanri_flg=1以外は一覧へ戻す）
//  ブックマークの削除はSQL側で「自分の行 or 管理者」を判定するようになったので現在は未使用。
//  Phase 3 のユーザー管理画面（管理者専用ページ）で使う。
function adminCheck(){
    if ((int)($_SESSION['kanri_flg'] ?? 0) !== 1) {
        header('Location: select.php');
        exit;
    }
}

//ログイン中かどうかを返す（リダイレクトはしない。画面の出し分け用）
//  有効期限が切れていればログイン中とみなさない
function isLoggedIn(){
    return isset($_SESSION['chk_ssid'])
        && $_SESSION['chk_ssid'] === session_id()
        && !isSessionExpired();
}

//管理者としてログイン中かどうかを返す（削除ボタンの出し分け用）
function isAdmin(){
    return isLoggedIn() && (int)($_SESSION['kanri_flg'] ?? 0) === 1;
}

//ログイン中のユーザーID（gs_user_table.id）を返す
//  ブックマークの持ち主を判定する基準になる。0が返るのは未ログイン時のみ。
function currentUserId(){
    return (int)($_SESSION['user_id'] ?? 0);
}
