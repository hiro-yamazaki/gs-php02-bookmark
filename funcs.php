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
        header('Location: index.php');
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

//電話番号の確認が済んでいるか（セッションに持っている値で判定する）
function isPhoneVerified(){
    return (int)($_SESSION['phone_verified'] ?? 0) === 1;
}

//電話番号の確認が済んでいないと使えないページの先頭で呼ぶ
//  loginCheck() の後に置くこと。
//  ※確認コードの入力画面（verify_phone.php）自体では呼ばないこと。呼ぶと無限に往復する。
function verifyCheck(){
    if (!isPhoneVerified()) {
        header('Location: verify_phone.php');
        exit;
    }
}

// ======================================================
// 電話番号のSMS認証（確認コードの発行と照合）
// ======================================================

const VERIFY_CODE_TTL      = 600; //確認コードの有効期間（秒）＝10分
const VERIFY_MAX_ATTEMPTS  = 5;   //1つのコードに対する入力ミスの上限
const VERIFY_MAX_SENDS     = 5;   //1時間あたりの送信回数の上限（SMSは1通ごとに課金される）

//6桁の確認コードを作る
//  rand()ではなく random_int() を使う。予測されると本人確認の意味がなくなるため。
function makeVerifyCode(){
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

//確認コードを発行してSMSで送る
//  戻り値: ['ok' => bool, 'error' => string]
function issueVerifyCode(PDO $pdo, $userId, $phone){
    //1. 直近1時間の送信回数を数える（送りすぎを止める）
    $stmt = $pdo->prepare('SELECT send_count, sent_at FROM gs_verify_code WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $sendCount = 1;
    if ($row) {
        //1時間以内の記録なら回数を積み増す。1時間を過ぎていれば数え直す。
        $withinHour = (strtotime($row['sent_at']) > time() - 3600);
        if ($withinHour) {
            if ((int)$row['send_count'] >= VERIFY_MAX_SENDS) {
                return ['ok' => false, 'error' => '送信回数の上限に達しました。1時間ほど時間をおいてからお試しください。'];
            }
            $sendCount = (int)$row['send_count'] + 1;
        }
    }

    //2. コードを作り、ハッシュにして保存する（コードそのものは残さない）
    $code = makeVerifyCode();
    $save = $pdo->prepare(
        'INSERT INTO gs_verify_code (user_id, code_hash, expires_at, attempts, send_count, sent_at)
         VALUES (:id, :hash, :expires, 0, :cnt, NOW())
         ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at),
                                 attempts = 0, send_count = VALUES(send_count), sent_at = NOW()'
    );
    $save->bindValue(':id', $userId, PDO::PARAM_INT);
    $save->bindValue(':hash', password_hash($code, PASSWORD_DEFAULT), PDO::PARAM_STR);
    $save->bindValue(':expires', date('Y-m-d H:i:s', time() + VERIFY_CODE_TTL), PDO::PARAM_STR);
    $save->bindValue(':cnt', $sendCount, PDO::PARAM_INT);
    $save->execute();

    //3. 送信する。送れなかった場合は正直にそう返す
    require_once __DIR__ . '/sms.php';
    $body = '【積読ストック】確認コード: ' . $code . "\n10分以内に入力してください。";
    if (!sendSms($phone, $body)) {
        return ['ok' => false, 'error' => 'SMSの送信に失敗しました。番号をご確認のうえ、もう一度お試しください。'];
    }
    return ['ok' => true, 'error' => ''];
}

//入力された確認コードを照合する
//  戻り値: ['ok' => bool, 'error' => string]
function checkVerifyCode(PDO $pdo, $userId, $input){
    $stmt = $pdo->prepare('SELECT code_hash, expires_at, attempts FROM gs_verify_code WHERE user_id = :id');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['ok' => false, 'error' => '確認コードが発行されていません。再送信してください。'];
    }
    if (strtotime($row['expires_at']) < time()) {
        return ['ok' => false, 'error' => '確認コードの有効期限が切れています。再送信してください。'];
    }
    if ((int)$row['attempts'] >= VERIFY_MAX_ATTEMPTS) {
        return ['ok' => false, 'error' => '入力できる回数を超えました。再送信して新しいコードを取得してください。'];
    }

    if (!password_verify($input, $row['code_hash'])) {
        //間違えた回数を増やす（総当たりで6桁を当てられないようにする）
        $miss = $pdo->prepare('UPDATE gs_verify_code SET attempts = attempts + 1 WHERE user_id = :id');
        $miss->bindValue(':id', $userId, PDO::PARAM_INT);
        $miss->execute();
        $left = VERIFY_MAX_ATTEMPTS - ((int)$row['attempts'] + 1);
        return ['ok' => false, 'error' => '確認コードが違います。（あと' . max(0, $left) . '回入力できます）'];
    }

    //4. 一致した。確認済みにして、使い終わったコードは消す
    $done = $pdo->prepare('UPDATE gs_user_table SET phone_verified = 1 WHERE id = :id');
    $done->bindValue(':id', $userId, PDO::PARAM_INT);
    $done->execute();

    $clean = $pdo->prepare('DELETE FROM gs_verify_code WHERE user_id = :id');
    $clean->bindValue(':id', $userId, PDO::PARAM_INT);
    $clean->execute();

    return ['ok' => true, 'error' => ''];
}

// ======================================================
// CSRF対策（Phase 2で追加）
// 「本人が意図してこのフォームから送った」ことを確認する仕組み。
// これが無いと、別サイトに置かれた <form action="退会URL"> を踏ませるだけで
// ログイン中の利用者のアカウントを消せてしまう。
// ======================================================

//このセッション用のトークンを返す（無ければ作る）
function csrfToken(){
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

//フォームに埋め込む hidden タグを出力する
function csrfField(){
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

//POSTを受け取る側で呼ぶ。合わなければその場で処理を止める
//  hash_equals は「1文字ずつ比較して途中で抜けない」比較関数（タイミング攻撃対策）
function csrfCheck(){
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(400);
        exit('不正なリクエストです。前の画面に戻ってやり直してください。');
    }
}

// ======================================================
// 入力チェック（アカウント登録・変更で共通に使う）
// ======================================================

//メールアドレスとして妥当か
function isValidEmail($email){
    return $email !== ''
        && mb_strlen($email) <= 255
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

//電話番号をハイフン・空白抜きの数字だけに整える
//  「090-1234-5678」「090 1234 5678」→「09012345678」
//  全角数字も半角に直してから処理する
function normalizePhone($phone){
    $phone = mb_convert_kana($phone, 'n');       //全角数字→半角
    return preg_replace('/[^0-9]/', '', $phone); //数字以外を除去
}

//日本の携帯・固定電話として妥当か（0で始まる10〜11桁）
function isValidPhone($phone){
    return preg_match('/\A0\d{9,10}\z/', $phone) === 1;
}

//パスワードとして妥当か
//  短すぎるものを弾く。8文字以上、英字と数字の両方を含むこと。
function isValidPassword($pw){
    return mb_strlen($pw) >= 8
        && mb_strlen($pw) <= 100
        && preg_match('/[a-zA-Z]/', $pw) === 1
        && preg_match('/[0-9]/', $pw) === 1;
}

//入力エラーを次の画面まで持ち回すための入れ物
//  リダイレクト先で takeFlash() すると1回だけ取り出せて消える
function setFlash($key, $value){
    $_SESSION['flash'][$key] = $value;
}

function takeFlash($key, $default = null){
    if (!isset($_SESSION['flash'][$key])) {
        return $default;
    }
    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $value;
}
