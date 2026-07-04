<?php
// 書籍検索API（フロントとの中継役）
// 書誌データは ①国立国会図書館サーチAPI → ②Google Books API の順で取得
// （どちらもAPIキー不要。①が混雑時は②へ自動フォールバック）
// URLは ISBN から組み立てた Amazon商品ページ を優先して返す。
// Amazonアソシエイト承認後は funcs.php の AMAZON_ASSOCIATE_TAG を設定するだけで
// 生成されるURLがアフィリエイトリンクになる。
require_once('funcs.php');

header('Content-Type: application/json; charset=utf-8');

// ISBN-13(978〜) を ISBN-10 に変換（Amazonの書籍ASIN=ISBN-10）
function isbn13_to_10(string $isbn13): ?string {
    if (!preg_match('/^978(\d{9})\d$/', $isbn13, $m)) return null; //979〜は変換不可
    $body = $m[1];
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (10 - $i) * (int)$body[$i];
    }
    $check = (11 - $sum % 11) % 11;
    return $body . ($check === 10 ? 'X' : (string)$check);
}

// ISBN-10 を ISBN-13(978〜) に変換（表紙画像URL用）
function isbn10_to_13(string $isbn10): ?string {
    if (!preg_match('/^(\d{9})[\dX]$/i', $isbn10, $m)) return null;
    $body = '978' . $m[1];
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$body[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    return $body . ((10 - $sum % 10) % 10);
}

// ISBN-10からAmazonの書影（表紙画像）URLを組み立てる
// 表紙が存在しない本は1x1の透明画像が返る（フロント側でnaturalWidth判定して除外）
function amazon_img(string $isbn10): string {
    return 'https://images-na.ssl-images-amazon.com/images/P/' . $isbn10 . '.09.MZZZZZZZ.jpg';
}

// ISBNからAmazonのURLを組み立てる（タグ設定時はアフィリエイトURLになる）
function amazon_url(string $isbn10, string $isbn13, string $fallback): string {
    if ($isbn10 !== '') {
        $url = 'https://www.amazon.co.jp/dp/' . $isbn10;
        return AMAZON_ASSOCIATE_TAG !== '' ? $url . '?tag=' . rawurlencode(AMAZON_ASSOCIATE_TAG) : $url;
    }
    if ($isbn13 !== '') {
        $url = 'https://www.amazon.co.jp/s?k=' . rawurlencode($isbn13);
        return AMAZON_ASSOCIATE_TAG !== '' ? $url . '&tag=' . rawurlencode(AMAZON_ASSOCIATE_TAG) : $url;
    }
    return $fallback;
}

function http_get(string $url): ?string {
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res === false ? null : $res;
}

// ①国立国会図書館サーチAPI（日本の書籍に強い・ISBN付き）
function search_ndl(string $q): array {
    $api = 'https://ndlsearch.ndl.go.jp/api/opensearch?' . http_build_query(['title' => $q, 'cnt' => 10]);
    // NDLの同時アクセス制限(429)は短時間で解けるため、1秒待って1回だけ再試行する
    $xml = null;
    for ($try = 0; $try < 2; $try++) {
        $res = http_get($api);
        if ($res !== null) {
            $parsed = @simplexml_load_string($res);
            if ($parsed && $parsed->getName() !== 'error' && isset($parsed->channel->item)) {
                $xml = $parsed;
                break;
            }
        }
        if ($try === 0) sleep(1);
    }
    if (!$xml) return [];

    $items = [];
    $seen = [];
    foreach ($xml->channel->item as $item) {
        $title = trim((string)$item->title);
        if ($title === '' || isset($seen[$title])) continue; //同名の版違いはまとめる

        // dc:identifier(xsi:type=dcndl:ISBN) からISBNを取得
        $isbn10 = '';
        $isbn13 = '';
        $dc = $item->children('http://purl.org/dc/elements/1.1/');
        foreach ($dc->identifier as $ident) {
            $attrs = $ident->attributes('http://www.w3.org/2001/XMLSchema-instance');
            if (strpos((string)($attrs['type'] ?? ''), 'ISBN') === false) continue;
            $isbn = str_replace('-', '', (string)$ident);
            if (strlen($isbn) === 10) $isbn10 = $isbn;
            if (strlen($isbn) === 13) $isbn13 = $isbn;
        }
        // ISBNのないもの（論文・記事等）は除外して「本」だけにする
        if ($isbn10 === '' && $isbn13 === '') continue;
        if ($isbn10 === '') $isbn10 = isbn13_to_10($isbn13) ?? '';
        if ($isbn13 === '') $isbn13 = isbn10_to_13($isbn10) ?? '';

        $url = amazon_url($isbn10, $isbn13, (string)$item->link);
        if ($url === '' || !preg_match('#\Ahttps?://#i', $url)) continue;

        $seen[$title] = true;
        $items[] = [
            'title'     => $title,
            'authors'   => trim((string)$item->author),
            'thumbnail' => $isbn10 !== '' ? amazon_img($isbn10) : '', //NDLの書影は直リンク不可(403)のためAmazon書影を使う
            'url'       => $url,
        ];
    }
    return $items;
}

// ②Google Books API（フォールバック）
function search_google(string $q): array {
    $api = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q'          => 'intitle:' . $q,
        'maxResults' => 5,
        'printType'  => 'books',
        'country'    => 'JP',
    ]);
    $res = http_get($api);
    if ($res === null) return [];
    $json = json_decode($res, true);

    $items = [];
    foreach (($json['items'] ?? []) as $it) {
        $v = $it['volumeInfo'] ?? [];
        $isbn10 = '';
        $isbn13 = '';
        foreach (($v['industryIdentifiers'] ?? []) as $id) {
            if (($id['type'] ?? '') === 'ISBN_10') $isbn10 = $id['identifier'];
            if (($id['type'] ?? '') === 'ISBN_13') $isbn13 = $id['identifier'];
        }
        if ($isbn10 === '' && $isbn13 !== '') $isbn10 = isbn13_to_10($isbn13) ?? '';

        $url = amazon_url($isbn10, $isbn13, $v['canonicalVolumeLink'] ?? ($v['infoLink'] ?? ''));
        if ($url === '' || !preg_match('#\Ahttps?://#i', $url)) continue;

        $thumb = $v['imageLinks']['smallThumbnail'] ?? ($v['imageLinks']['thumbnail'] ?? '');
        $items[] = [
            'title'     => (string)($v['title'] ?? ''),
            'authors'   => implode(', ', $v['authors'] ?? []),
            'thumbnail' => $isbn10 !== '' ? amazon_img($isbn10) : str_replace('http://', 'https://', $thumb),
            'url'       => $url,
        ];
    }
    return $items;
}

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) > 100) {
    echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$items = search_ndl($q);
if (count($items) === 0) {
    $items = search_google($q);
}

// 入力語をタイトルに含む候補を上位へ（NDLの曖昧マッチ対策）
usort($items, function ($a, $b) use ($q) {
    return (int)(mb_strpos($b['title'], $q) !== false) - (int)(mb_strpos($a['title'], $q) !== false);
});

echo json_encode(['items' => array_slice($items, 0, 5)], JSON_UNESCAPED_UNICODE);
