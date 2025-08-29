<?php
/**
 * PDOドライバー確認スクリプト
 */

echo "<h1>PDOドライバー確認</h1>";
echo "<hr>";

// 利用可能なPDOドライバーを確認
echo "<h2>利用可能なPDOドライバー</h2>";
$drivers = PDO::getAvailableDrivers();
echo "<ul>";
foreach ($drivers as $driver) {
    echo "<li>" . htmlspecialchars($driver) . "</li>";
}
echo "</ul>";

// PostgreSQL PDOドライバーの確認
echo "<h2>PostgreSQL PDOドライバー確認</h2>";
if (in_array('pgsql', $drivers)) {
    echo "<p style='color: green;'>✓ PostgreSQL PDOドライバー（pdo_pgsql）が利用可能です</p>";
} else {
    echo "<p style='color: red;'>✗ PostgreSQL PDOドライバー（pdo_pgsql）が見つかりません</p>";
    echo "<p><strong>解決方法:</strong></p>";
    echo "<ol>";
    echo "<li>XAMPPコントロールパネルでApacheを停止</li>";
    echo "<li>C:\\xampp\\php\\php.ini ファイルを編集</li>";
    echo "<li>;extension=pdo_pgsql の行を見つけて、先頭の ; を削除</li>";
    echo "<li>ファイルを保存してApacheを再起動</li>";
    echo "</ol>";
}

// PHP拡張モジュール確認
echo "<h2>PHP拡張モジュール確認</h2>";
$extensions = get_loaded_extensions();
echo "<p>読み込まれている拡張モジュール数: " . count($extensions) . "</p>";

// PostgreSQL関連の拡張を確認
$pgsql_extensions = array_filter($extensions, function($ext) {
    return stripos($ext, 'pgsql') !== false || stripos($ext, 'postgres') !== false;
});

if (!empty($pgsql_extensions)) {
    echo "<p style='color: green;'>PostgreSQL関連拡張:</p>";
    echo "<ul>";
    foreach ($pgsql_extensions as $ext) {
        echo "<li>" . htmlspecialchars($ext) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>PostgreSQL関連の拡張が見つかりません</p>";
}

// PHP設定情報
echo "<h2>PHP設定情報</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>PHP SAPI:</strong> " . php_sapi_name() . "</p>";
echo "<p><strong>Configuration File Path:</strong> " . php_ini_loaded_file() . "</p>";

echo "<hr>";
echo "<p><a href='test_supabase_integration.php'>Supabase統合テストに戻る</a></p>";
?>