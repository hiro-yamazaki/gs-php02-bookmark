<?php
// ======================================================
// SMS送信（電話番号の本人確認に使う）
//
// 送信手段を1箇所に閉じ込めてある。呼び出し側は sendSms() だけを使う。
//
// ドライバ
//   'log'    … 実際には送らず、確認コードをサーバーのエラーログに書き出す（開発用）
//   'twilio' … Twilio経由で実際にSMSを送る（本番用・有料）
//
// 本番で使うには config.php に以下を足す（config.sample.php 参照）
//   'sms' => [
//       'driver' => 'twilio',
//       'sid'    => 'ACxxxxxxxx',
//       'token'  => '（Twilioの認証トークン）',
//       'from'   => '+81xxxxxxxxxx',  // Twilioで購入した送信元番号
//   ]
// ======================================================

//SMS設定を読み込む。設定が無ければ 'log'（実際には送らない）で動く
function sms_config(){
    static $conf = null;
    if ($conf !== null) {
        return $conf;
    }
    $conf = ['driver' => 'log', 'sid' => '', 'token' => '', 'from' => ''];
    if (file_exists(__DIR__ . '/config.php')) {
        $c = require __DIR__ . '/config.php';
        if (isset($c['sms']) && is_array($c['sms'])) {
            $conf = array_merge($conf, $c['sms']);
        }
    }
    return $conf;
}

//国内の番号（09012345678）を国際表記（+819012345678）に直す
//  Twilioは国際表記でないと受け付けない
function toE164($phone){
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strncmp($digits, '0', 1) === 0) {
        return '+81' . substr($digits, 1); //先頭の0を国番号に置き換える
    }
    return '+' . $digits;
}

//SMSを送る。送れたら true、失敗したら false を返す
//  ※戻り値は必ず呼び出し側で確認すること。
//    送れていないのに「送信しました」と表示すると、利用者が延々と待つことになる。
function sendSms($phone, $body){
    $conf = sms_config();

    if ($conf['driver'] === 'twilio') {
        return sendSmsViaTwilio($conf, $phone, $body);
    }

    //開発用：実際には送らず、サーバーのログに残す。
    //MAMPなら /Applications/MAMP/logs/php_error.log に出る。
    error_log('[SMS:log driver] to=' . $phone . ' body=' . $body);
    return true;
}

//Twilio の Messages API を叩く
function sendSmsViaTwilio($conf, $phone, $body){
    if ($conf['sid'] === '' || $conf['token'] === '' || $conf['from'] === '') {
        error_log('SMS送信の設定が不足しています（sid/token/from）');
        return false;
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($conf['sid']) . '/Messages.json';
    $post = http_build_query([
        'From' => $conf['from'],
        'To'   => toE164($phone),
        'Body' => $body,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $conf['sid'] . ':' . $conf['token'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    //2xx以外は失敗。原因はログに残すが、画面には出さない（認証情報が混ざるため）
    if ($res === false || $code < 200 || $code >= 300) {
        error_log('Twilio送信失敗 http=' . $code . ' err=' . $err . ' res=' . (string)$res);
        return false;
    }
    return true;
}
