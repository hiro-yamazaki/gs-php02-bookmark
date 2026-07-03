<?php
//共通に使う関数を記述

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
