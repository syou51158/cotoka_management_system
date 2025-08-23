<?php
// 定数チェック用スクリプト

// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>システム定数チェック</h1>";

// 設定ファイルを読み込み
require_once 'config/config.php';

// 定数の確認
echo "<h2>基本設定</h2>";
echo "<ul>";
echo "<li>DB_HOST: " . DB_HOST . "</li>";
echo "<li>DB_USER: " . DB_USER . "</li>";
echo "<li>DB_NAME: " . DB_NAME . "</li>";
echo "<li>DB_CHARSET: " . DB_CHARSET . "</li>";
echo "</ul>";

// 定義されているべき定数のチェック
echo "<h2>必須定数チェック</h2>";

$required_constants = [
    'SITE_NAME',
    'DATE_FORMAT',
    'TIME_FORMAT',
    'HASH_COST'
];

echo "<ul>";
foreach ($required_constants as $constant) {
    if (defined($constant)) {
        echo "<li style='color:green'>✓ {$constant} は定義されています: " . constant($constant) . "</li>";
    } else {
        echo "<li style='color:red'>✗ {$constant} は定義されていません</li>";
    }
}
echo "</ul>";

// 設定ファイルに追加すべき定数の提案
echo "<h2>修正方法</h2>";
echo "<p>config.phpに以下の定数を追加してください：</p>";
echo "<pre>";
echo "// サイト名設定\n";
echo "define('SITE_NAME', 'Cotoka Management System');\n\n";

echo "// 日付と時間のフォーマット設定\n";
echo "define('DATE_FORMAT', 'Y年m月d日');\n";
echo "define('TIME_FORMAT', 'H:i');\n\n";

echo "// パスワードハッシュコスト\n";
echo "define('HASH_COST', 12);\n";
echo "</pre>";
?>