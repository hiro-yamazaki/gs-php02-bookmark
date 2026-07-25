<?php
// ======================================================
// メール送信（アカウントの本人確認に使う）
//
// 送信手段を1箇所に閉じ込めてある。呼び出し側は sendMail() だけを使う。
//
// ドライバ
//   'log'  … 実際には送らず、本文をサーバーのエラーログに書き出す（開発用）
//   'mail' … PHPの mb_send_mail() で送る（さくら等のレンタルサーバー向け）
//
// 本番で使うには config.php に以下を足す（config.sample.php 参照）
//   'mail' => [
//       'driver' => 'mail',
//       'from'   => 'noreply@あなたのドメイン',
//       'name'   => '積読ストック',
//   ]
//
// ※fromは「そのサーバーで送信が許可されているドメイン」にすること。
//   無関係なドメイン（gmail.com等）を差出人にすると、迷惑メール判定でほぼ届かない。
// ======================================================

function mail_config(){
    static $conf = null;
    if ($conf !== null) {
        return $conf;
    }
    $conf = ['driver' => 'log', 'from' => '', 'name' => '積読ストック'];
    if (file_exists(__DIR__ . '/config.php')) {
        $c = require __DIR__ . '/config.php';
        if (isset($c['mail']) && is_array($c['mail'])) {
            $conf = array_merge($conf, $c['mail']);
        }
    }
    return $conf;
}

//メールを送る。送れたら true、失敗したら false を返す
//  ※戻り値は必ず呼び出し側で確認すること。
//    送れていないのに「送信しました」と表示すると、利用者が延々と待つことになる。
function sendMail($to, $subject, $body){
    $conf = mail_config();

    if ($conf['driver'] !== 'mail') {
        //開発用：実際には送らず、サーバーのログに残す。
        //MAMPなら /Applications/MAMP/logs/php_error.log に出る。
        error_log("[MAIL:log driver] to={$to}\nsubject={$subject}\n{$body}");
        return true;
    }

    if ($conf['from'] === '') {
        error_log('メール送信の設定が不足しています（from）');
        return false;
    }

    //日本語の件名・本文が文字化けしないよう UTF-8 を明示する
    mb_language('uni');
    mb_internal_encoding('UTF-8');

    //差出人名に含まれる改行を落とす（ヘッダーインジェクション対策）
    $name    = str_replace(["\r", "\n"], '', $conf['name']);
    $from    = str_replace(["\r", "\n"], '', $conf['from']);
    $headers = 'From: ' . mb_encode_mimeheader($name) . ' <' . $from . '>';

    if (!mb_send_mail($to, $subject, $body, $headers)) {
        error_log('メール送信に失敗しました to=' . $to);
        return false;
    }
    return true;
}
