<?php
// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログの設定
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');

echo "<h1>データベース診断ツール</h1>";

// 設定ファイルを読み込み
require_once 'config/config.php';

// PHPバージョン情報
echo "<h2>PHPバージョン情報</h2>";
echo "<p>PHP バージョン: " . phpversion() . "</p>";
echo "<p>PDO サポート: " . (extension_loaded('pdo') ? '有効' : '無効') . "</p>";
echo "<p>PDO MySQL サポート: " . (extension_loaded('pdo_mysql') ? '有効' : '無効') . "</p>";

// データベース接続情報の表示
echo "<h2>データベース設定</h2>";
echo "<ul>";
echo "<li>ホスト: " . DB_HOST . "</li>";
echo "<li>ユーザー: " . DB_USER . "</li>";
echo "<li>データベース名: " . DB_NAME . "</li>";
echo "<li>文字セット: " . DB_CHARSET . "</li>";
echo "</ul>";

// データベース接続テスト
echo "<h2>接続テスト</h2>";
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    echo "<p style='color:green'>✓ データベース接続に成功しました！</p>";
    
    // データベースの存在確認
    echo "<h3>データベース '{" . DB_NAME . "}' の確認</h3>";
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch();
    echo "<p>現在のデータベース: {$result['db_name']}</p>";
    
    // 必要なテーブルの存在確認
    $required_tables = ['users', 'roles', 'tenants', 'salons', 'remember_tokens'];
    echo "<h3>必要なテーブルの確認</h3>";
    echo "<ul>";
    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "<li style='color:green'>✓ {$table}テーブルが存在します</li>";
            
            // テーブルのレコード数を確認
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch()['count'];
            echo "<ul><li>レコード数: {$count}</li></ul>";
        } else {
            echo "<li style='color:red'>✗ {$table}テーブルが存在しません</li>";
        }
    }
    echo "</ul>";
    
    // usersテーブルの構造確認（存在する場合）
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<h3>usersテーブルの構造</h3>";
        $stmt = $pdo->query("DESCRIBE users");
        echo "<table border='1'>";
        echo "<tr><th>フィールド</th><th>タイプ</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>その他</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // rolesテーブルの構造確認（存在する場合）
    $stmt = $pdo->query("SHOW TABLES LIKE 'roles'");
    if ($stmt->rowCount() > 0) {
        echo "<h3>rolesテーブルの構造</h3>";
        $stmt = $pdo->query("DESCRIBE roles");
        echo "<table border='1'>";
        echo "<tr><th>フィールド</th><th>タイプ</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>その他</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // データベースの作成方法を表示
    echo "<h3>データベースセットアップ方法</h3>";
    echo "<p>データベースが存在しない場合は、以下の手順で作成してください：</p>";
    echo "<ol>";
    echo "<li>XAMPPのコントロールパネルからMySQLを起動</li>";
    echo "<li>phpMyAdminにアクセス（<a href='http://localhost/phpmyadmin/' target='_blank'>http://localhost/phpmyadmin/</a>）</li>";
    echo "<li>左側のメニューから「新規作成」をクリック</li>";
    echo "<li>データベース名に 'cotoka_management' を入力</li>";
    echo "<li>照合順序は 'utf8mb4_general_ci' を選択</li>";
    echo "<li>「作成」ボタンをクリック</li>";
    echo "</ol>";
    
    // セットアップスクリプトの実行方法
    echo "<h3>セットアップスクリプトの実行</h3>";
    echo "<p>データベースを作成した後、以下のリンクからセットアップスクリプトを実行してください：</p>";
    echo "<p><a href='database/setup_db.php' class='btn btn-primary'>データベースセットアップを実行</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ データベース接続エラー: " . $e->getMessage() . "</p>";
    echo "<h3>考えられる原因:</h3>";
    echo "<ul>";
    echo "<li>データベース '" . DB_NAME . "' が存在しない</li>";
    echo "<li>ユーザー '" . DB_USER . "' のアクセス権限がない</li>";
    echo "<li>パスワードが間違っている</li>";
    echo "<li>MySQLサーバーが実行されていない</li>";
    echo "</ul>";
    
    // データベースの作成方法を表示
    echo "<h3>データベースの作成方法</h3>";
    echo "<p>データベースが存在しない場合は、以下の手順で作成してください：</p>";
    echo "<ol>";
    echo "<li>XAMPPのコントロールパネルからMySQLを起動</li>";
    echo "<li>phpMyAdminにアクセス（<a href='http://localhost/phpmyadmin/' target='_blank'>http://localhost/phpmyadmin/</a>）</li>";
    echo "<li>左側のメニューから「新規作成」をクリック</li>";
    echo "<li>データベース名に 'cotoka_management' を入力</li>";
    echo "<li>照合順序は 'utf8mb4_general_ci' を選択</li>";
    echo "<li>「作成」ボタンをクリック</li>";
    echo "</ol>";
}
?>

<p><a href="login.php">ログインページに戻る</a></p>