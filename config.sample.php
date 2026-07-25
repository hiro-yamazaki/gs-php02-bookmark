<?php
// 本番サーバー用の設定サンプル
// このファイルを config.php という名前でコピーして、サーバーの値に書き換える
// ※config.php は .gitignore 済みなので公開リポジトリには載らない
return [
    // ---- データベース ----
    // さくらサーバーの例: host はコントロールパネルのDBサーバー名
    'dsn'  => 'mysql:dbname=あなたのDB名;charset=utf8mb4;host=mysqlXX.db.sakura.ne.jp',
    'user' => 'あなたのDBユーザー名',
    'pass' => 'あなたのDBパスワード',

    // ---- SMS送信（電話番号の本人確認）----
    // driver を 'log' にすると実際には送信せず、確認コードをPHPのエラーログに出す（開発用）。
    // 'twilio' にすると実際にSMSを送る（1通ごとに課金される）。
    // Twilioの管理画面で取得する値:
    //   sid   … Account SID（ACで始まる文字列）
    //   token … Auth Token
    //   from  … Twilioで購入した送信元電話番号（国際表記 +81... で書く）
    'sms' => [
        'driver' => 'log',
        'sid'    => '',
        'token'  => '',
        'from'   => '',
    ],
];
