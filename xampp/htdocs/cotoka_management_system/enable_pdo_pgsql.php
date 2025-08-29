<?php
/**
 * PostgreSQL PDO Driver Enabler
 * XAMPPでpdo_pgsqlドライバーを有効化するためのスクリプト
 */

echo "<h1>PostgreSQL PDO Driver Enabler</h1>";
echo "<hr>";

// PHP設定ファイルの場所を確認
$php_ini_path = php_ini_loaded_file();
echo "<h2>1. PHP設定ファイルの確認</h2>";
echo "<p><strong>php.ini パス:</strong> " . ($php_ini_path ?: '見つかりません') . "</p>";

// 現在のPDOドライバーを確認
echo "<h2>2. 現在のPDOドライバー</h2>";
$pdo_drivers = PDO::getAvailableDrivers();
echo "<p><strong>利用可能なドライバー:</strong> " . implode(', ', $pdo_drivers) . "</p>";

if (in_array('pgsql', $pdo_drivers)) {
    echo "<p style='color: green;'>✓ pdo_pgsql ドライバーは既に有効です</p>";
} else {
    echo "<p style='color: red;'>✗ pdo_pgsql ドライバーが見つかりません</p>";
}

// 拡張モジュールの確認
echo "<h2>3. 拡張モジュールの確認</h2>";
$loaded_extensions = get_loaded_extensions();
echo "<p><strong>読み込み済み拡張数:</strong> " . count($loaded_extensions) . "</p>";

$pdo_extensions = array_filter($loaded_extensions, function($ext) {
    return strpos(strtolower($ext), 'pdo') !== false;
});

echo "<p><strong>PDO関連拡張:</strong></p>";
echo "<ul>";
foreach ($pdo_extensions as $ext) {
    echo "<li>" . htmlspecialchars($ext) . "</li>";
}
echo "</ul>";

// PostgreSQL関連の確認
echo "<h2>4. PostgreSQL関連の確認</h2>";
if (extension_loaded('pgsql')) {
    echo "<p style='color: green;'>✓ PostgreSQL拡張が読み込まれています</p>";
} else {
    echo "<p style='color: orange;'>⚠ PostgreSQL拡張が見つかりません</p>";
}

if (extension_loaded('pdo_pgsql')) {
    echo "<p style='color: green;'>✓ PDO PostgreSQL拡張が読み込まれています</p>";
} else {
    echo "<p style='color: red;'>✗ PDO PostgreSQL拡張が見つかりません</p>";
}

// 解決策の提示
echo "<h2>5. 解決策</h2>";
if (!in_array('pgsql', $pdo_drivers)) {
    echo "<div style='background-color: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
    echo "<h3>PostgreSQL PDOドライバーを有効化する方法:</h3>";
    echo "<ol>";
    echo "<li>XAMPPコントロールパネルでApacheを停止</li>";
    echo "<li>以下のファイルを編集: <code>" . ($php_ini_path ?: 'C:\\xampp\\php\\php.ini') . "</code></li>";
    echo "<li>以下の行を見つけて、先頭の ; を削除:<br>";
    echo "<code>;extension=pdo_pgsql</code> → <code>extension=pdo_pgsql</code></li>";
    echo "<li>ファイルを保存</li>";
    echo "<li>XAMPPコントロールパネルでApacheを再起動</li>";
    echo "<li>このページを再読み込みして確認</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>✓ PostgreSQL PDOドライバーは正常に動作しています</h3>";
    echo "<p>Database.phpクラスを使用してSupabaseに接続できます。</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='test_supabase_integration.php'>Supabase統合テスト</a> | <a href='dashboard.php'>ダッシュボードに戻る</a></p>";
?>