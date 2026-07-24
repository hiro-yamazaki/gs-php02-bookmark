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

//ログインチェック（ログインが必要なページの先頭で呼ぶ）
//  未ログイン、またはブラウザとサーバーのセッションIDが一致しない場合は
//  ログイン画面へ戻す（＝ログインしていないと中身は見られない）
function loginCheck(){
    if (!isset($_SESSION['chk_ssid']) || $_SESSION['chk_ssid'] !== session_id()) {
        header('Location: login.php');
        exit;
    }
    //正しいログイン中は、毎回セッションIDを作り替えて盗用（セッションハイジャック）に備える
    session_regenerate_id(true);
    $_SESSION['chk_ssid'] = session_id();
}

//管理者チェック（loginCheck()の後に呼ぶ。kanri_flg=1以外は一覧へ戻す）
//  削除など「管理者だけに許可したい処理」の先頭で使う
function adminCheck(){
    if ((int)($_SESSION['kanri_flg'] ?? 0) !== 1) {
        header('Location: select.php');
        exit;
    }
}

//ログイン中かどうかを返す（リダイレクトはしない。画面の出し分け用）
function isLoggedIn(){
    return isset($_SESSION['chk_ssid']) && $_SESSION['chk_ssid'] === session_id();
}

//管理者としてログイン中かどうかを返す（削除ボタンの出し分け用）
function isAdmin(){
    return isLoggedIn() && (int)($_SESSION['kanri_flg'] ?? 0) === 1;
}
