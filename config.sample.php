<?php
// 本番サーバー用のDB接続設定サンプル
// このファイルを config.php という名前でコピーして、サーバーの値に書き換える
// ※config.php は .gitignore 済みなので公開リポジトリには載らない
return [
    // さくらサーバーの例: host はコントロールパネルのDBサーバー名
    'dsn'  => 'mysql:dbname=あなたのDB名;charset=utf8mb4;host=mysqlXX.db.sakura.ne.jp',
    'user' => 'あなたのDBユーザー名',
    'pass' => 'あなたのDBパスワード',
];
