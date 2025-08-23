<?php
// 完全なデバッグ情報表示用ファイル
header('Content-Type: text/html; charset=utf-8');

echo "<h1>詳細デバッグ情報</h1>";

echo "<h2>サーバー情報</h2>";
echo "<pre>";
foreach ($_SERVER as $key => $value) {
    echo htmlspecialchars($key) . " => " . htmlspecialchars(print_r($value, true)) . "<br>";
}
echo "</pre>";

echo "<h2>GET パラメータ</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>POST パラメータ</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>SESSION 変数</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>PHP情報</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "mod_rewrite: " . (in_array('mod_rewrite', apache_get_modules()) ? '有効' : '無効') . "<br>";

echo "<h2>APIテスト</h2>";
echo "<p>以下のリンクをクリックして、さまざまなパラメータでAPIをテストできます</p>";
echo "<ul>";
echo "<li><a href='index.php?salon_id=1'>salon_id=1</a></li>";
echo "<li><a href='index.php?salon_id=2'>salon_id=2</a></li>";
echo "<li><a href='index.php?salon_id=1&source=instagram'>salon_id=1&source=instagram</a></li>";
echo "</ul>";

echo "<h2>ファイルパスチェック</h2>";
$requiredFiles = [
    '../config/config.php',
    '../classes/Database.php',
    '../includes/functions.php'
];

foreach ($requiredFiles as $file) {
    echo $file . " exists: " . (file_exists($file) ? "YES" : "NO") . "<br>";
}

// ファイルの詳細情報
echo "<h2>index.php ファイル情報</h2>";
$indexFile = 'index.php';
if (file_exists($indexFile)) {
    echo "File size: " . filesize($indexFile) . " bytes<br>";
    echo "Last modified: " . date("Y-m-d H:i:s", filemtime($indexFile)) . "<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($indexFile)), -4) . "<br>";
} else {
    echo "index.php ファイルが存在しません";
}

?> 