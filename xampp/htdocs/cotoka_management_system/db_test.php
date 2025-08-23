<?php
// エラー表示を最大限に有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログの設定
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');

echo "<h1>データベース接続テスト</h1>";

// 設定ファイルを読み込み
require_once 'config.php';

// 設定値のデバッグ
echo '<h2>設定値検証</h2>';
echo '<p>DB_USER: ' . (defined('DB_USER') ? DB_USER : '未定義') . '</p>';
echo '<p>DB_PASS: ' . (defined('DB_PASS') ? '****' : '未定義') . '</p>';

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
    
    // usersテーブルの存在確認
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✓ usersテーブルが存在します</p>";
        
        // usersテーブルの構造確認
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
        
        // ユーザーデータの確認
        echo "<h3>登録ユーザー数</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        echo "<p>登録ユーザー数: {$count}</p>";
        
        if ($count > 0) {
            echo "<h3>ユーザー一覧（最大5件）</h3>";
            $stmt = $pdo->query("SELECT id, user_id, email, name, status FROM users LIMIT 5");
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>ユーザーID</th><th>メール</th><th>名前</th><th>ステータス</th></tr>";
            while ($row = $stmt->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color:red'>✗ usersテーブルが存在しません</p>";
        echo "<p>データベースのセットアップが必要です。setup_db.phpを実行してください。</p>";
    }
    
    // rolesテーブルの存在確認
    $stmt = $pdo->query("SHOW TABLES LIKE 'roles'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>✓ rolesテーブルが存在します</p>";
    } else {
        echo "<p style='color:red'>✗ rolesテーブルが存在しません</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ データベース接続エラー: " . $e->getMessage() . "</p>";
    echo "<h3>考えられる原因:</h3>";
    echo "<ul>";
    echo "<li>データベース '{$dbname}' が存在しない</li>";
    echo "<li>ユーザー '{$dbuser}' のアクセス権限がない</li>";
    echo "<li>パスワードが間違っている</li>";
    echo "<li>MySQLサーバーが実行されていない</li>";
    echo "</ul>";
}
?>

<p><a href="login.php">ログインページに戻る</a></p>