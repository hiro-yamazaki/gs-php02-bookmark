<?php
// 一覧は index.php に統合した（「本を探す → ストックする → 積んだ本を見る」を1画面で完結させるため）。
// 以前のURL（ブックマーク・検索エンジン・過去の課題提出先）から来た人のために、
// 検索条件を保ったまま index.php へ転送する。
session_start();
require_once('funcs.php');

$params = [];
if (isset($_GET['scope']) && $_GET['scope'] === 'public') {
    $params['scope'] = 'public';
}
if (isset($_GET['q']) && trim($_GET['q']) !== '') {
    $params['q'] = trim($_GET['q']);
}

$query = $params ? '?' . http_build_query($params) : '';
header('Location: index.php' . $query, true, 301);
exit;
