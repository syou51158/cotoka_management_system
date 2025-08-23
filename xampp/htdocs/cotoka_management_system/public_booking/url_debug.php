<?php
/**
 * URL解析デバッグツール
 * 
 * 予約URLがなぜ動作しないかを詳細に診断します
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL診断ツール</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 1200px; margin: 0 auto; }
        h1, h2, h3 { color: #333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .error { color: #e53935; background: #ffebee; padding: 10px; border-radius: 5px; }
        .success { color: #2e7d32; background: #e8f5e9; padding: 10px; border-radius: 5px; }
        .warning { color: #ef6c00; background: #fff3e0; padding: 10px; border-radius: 5px; }
        .info { color: #0277bd; background: #e1f5fe; padding: 10px; border-radius: 5px; }
        .step { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>URL診断ツール</h1>
    <p>このツールは予約URLが正しく動作しない理由を診断します。</p>

    <div class="step">
        <h2>1. リクエスト情報</h2>
        <h3>現在のURL</h3>
        <div class="info">
            <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>
        </div>

        <h3>サーバー環境</h3>
        <pre><?php 
            echo "Server Name: " . $_SERVER['SERVER_NAME'] . "\n";
            echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
            echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
            echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
            echo "PHP Version: " . phpversion() . "\n";
            echo "mod_rewrite: " . (in_array('mod_rewrite', apache_get_modules()) ? "有効" : "無効") . "\n";
        ?></pre>

        <h3>GET パラメータ</h3>
        <pre><?php print_r($_GET); ?></pre>

        <h3>URLの解析</h3>
        <?php
        $parsed_url = parse_url($_SERVER['REQUEST_URI']);
        echo "<pre>";
        print_r($parsed_url);
        echo "</pre>";
        
        // パスからスラッグを抽出
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $path_parts = explode('/', trim($path, '/'));
        $potential_slug = end($path_parts);
        
        if ($potential_slug !== 'url_debug.php') {
            echo "<div class='info'>パスから抽出された可能性のあるスラッグ: " . htmlspecialchars($potential_slug) . "</div>";
        } else {
            echo "<div class='warning'>URLパスにスラッグが含まれていません。?slug=XXXの形式でアクセスしてください。</div>";
        }
        
        // GETパラメータからスラッグとソースを抽出
        $slug = isset($_GET['slug']) ? $_GET['slug'] : null;
        $source = isset($_GET['source']) ? $_GET['source'] : null;
        
        if ($slug) {
            echo "<div class='info'>GETパラメータから抽出されたスラッグ: " . htmlspecialchars($slug) . "</div>";
        }
        
        if ($source) {
            echo "<div class='info'>GETパラメータから抽出されたソース: " . htmlspecialchars($source) . "</div>";
        }
        ?>
    </div>

    <div class="step">
        <h2>2. データベース接続テスト</h2>
        <?php
        try {
            require_once '../includes/config.php';
            require_once '../includes/database.php';
            echo "<div class='success'>データベース接続成功!</div>";
            
            // サロンテーブルの構造を確認
            $query = "DESCRIBE salons";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>salonsテーブルの構造</h3>";
            echo "<table>";
            echo "<tr><th>フィールド</th><th>タイプ</th><th>NULL許可</th><th>キー</th><th>デフォルト</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
                echo "<td>" . (isset($column['Default']) ? htmlspecialchars($column['Default']) : "NULL") . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // スラッグの検証
            if ($slug || $potential_slug !== 'url_debug.php') {
                $test_slug = $slug ?: $potential_slug;
                $query = "SELECT salon_id, name, url_slug FROM salons WHERE url_slug = :slug";
                $stmt = $pdo->prepare($query);
                $stmt->bindParam(':slug', $test_slug);
                $stmt->execute();
                $salon = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($salon) {
                    echo "<div class='success'>";
                    echo "<h3>スラッグが見つかりました!</h3>";
                    echo "<p>サロンID: " . $salon['salon_id'] . "</p>";
                    echo "<p>サロン名: " . $salon['name'] . "</p>";
                    echo "<p>スラッグ: " . $salon['url_slug'] . "</p>";
                    echo "</div>";
                } else {
                    echo "<div class='error'>";
                    echo "<h3>スラッグが見つかりませんでした</h3>";
                    echo "<p>テストしたスラッグ: " . htmlspecialchars($test_slug) . "</p>";
                    echo "</div>";
                    
                    // 似たスラッグを検索
                    $query = "SELECT salon_id, name, url_slug FROM salons WHERE url_slug LIKE :pattern LIMIT 5";
                    $pattern = "%" . substr($test_slug, 0, 3) . "%";
                    $stmt = $pdo->prepare($query);
                    $stmt->bindParam(':pattern', $pattern);
                    $stmt->execute();
                    $similar_salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($similar_salons) > 0) {
                        echo "<div class='info'>";
                        echo "<h3>類似のスラッグが見つかりました</h3>";
                        echo "<ul>";
                        foreach ($similar_salons as $s) {
                            echo "<li>" . $s['name'] . " - スラッグ: <strong>" . $s['url_slug'] . "</strong> ";
                            echo "<a href='url_debug.php?slug=" . $s['url_slug'] . "'>このスラッグでテスト</a></li>";
                        }
                        echo "</ul>";
                        echo "</div>";
                    }
                }
            }
            
            // 利用可能なすべてのサロンを表示
            $query = "SELECT salon_id, name, url_slug FROM salons LIMIT 10";
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $all_salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>利用可能なサロン (最大10件)</h3>";
            echo "<table>";
            echo "<tr><th>ID</th><th>サロン名</th><th>スラッグ</th><th>テストリンク</th></tr>";
            foreach ($all_salons as $s) {
                echo "<tr>";
                echo "<td>" . $s['salon_id'] . "</td>";
                echo "<td>" . $s['name'] . "</td>";
                echo "<td>" . $s['url_slug'] . "</td>";
                echo "<td>";
                echo "<a href='url_debug.php?slug=" . $s['url_slug'] . "'>GETパラメータでテスト</a> | ";
                echo "<a href='http://localhost/cotoka_management_system/public_booking/" . $s['url_slug'] . "'>クリーンURLでテスト</a>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } catch (PDOException $e) {
            echo "<div class='error'>";
            echo "<h3>データベース接続エラー</h3>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "</div>";
        }
        ?>
    </div>

    <div class="step">
        <h2>3. .htaccessテスト</h2>
        <?php
        $htaccess_path = '.htaccess';
        if (file_exists($htaccess_path)) {
            echo "<div class='success'>.htaccessファイルが存在します</div>";
            echo "<h3>.htaccessの内容</h3>";
            echo "<pre>" . htmlspecialchars(file_get_contents($htaccess_path)) . "</pre>";
        } else {
            echo "<div class='error'>.htaccessファイルが見つかりません</div>";
        }
        ?>
    </div>

    <div class="step">
        <h2>4. テストURL</h2>
        <p>以下のURLで予約ページにアクセスできるか試してください：</p>
        
        <?php if (isset($salon) && $salon): ?>
        <div class="info">
            <h3>検出したサロン「<?php echo htmlspecialchars($salon['name']); ?>」へのアクセス方法</h3>
            <p><strong>1. index.phpを使用 (最も確実な方法):</strong><br>
            <a href="http://localhost/cotoka_management_system/public_booking/index.php?salon_id=<?php echo $salon['salon_id']; ?>&source=instagram">
                http://localhost/cotoka_management_system/public_booking/index.php?salon_id=<?php echo $salon['salon_id']; ?>&source=instagram
            </a></p>
            
            <p><strong>2. スラッグパラメータを使用:</strong><br>
            <a href="http://localhost/cotoka_management_system/public_booking/index.php?slug=<?php echo urlencode($salon['url_slug']); ?>&source=instagram">
                http://localhost/cotoka_management_system/public_booking/index.php?slug=<?php echo urlencode($salon['url_slug']); ?>&source=instagram
            </a></p>
            
            <p><strong>3. クリーンURL (.htaccessが有効な場合):</strong><br>
            <a href="http://localhost/cotoka_management_system/public_booking/<?php echo urlencode($salon['url_slug']); ?>?source=instagram">
                http://localhost/cotoka_management_system/public_booking/<?php echo urlencode($salon['url_slug']); ?>?source=instagram
            </a></p>
        </div>
        <?php endif; ?>
        
        <div class="warning">
            <h3>テスト結果のメモ</h3>
            <ul>
                <li>方法1が動作せず、方法2または3が動作する場合：index.phpのsalon_idパラメータ処理に問題がある可能性</li>
                <li>方法1は動作するが、方法2が動作しない場合：slugパラメータの処理に問題がある可能性</li>
                <li>方法1と2は動作するが、方法3が動作しない場合：.htaccessの設定に問題がある可能性</li>
                <li>どの方法も動作しない場合：パス、サーバー設定、またはデータベースに根本的な問題がある可能性</li>
            </ul>
        </div>
    </div>
    
    <div class="step">
        <h2>5. 問題解決のための提案</h2>
        <ol>
            <li><strong>予約ソース管理で生成されるURLを確認</strong>：
                <ul>
                    <li>予約ソース管理で生成されるURLの形式を確認し、必要に応じて修正してください</li>
                    <li>管理画面から生成されたURLをこのデバッグツールで検証してください</li>
                </ul>
            </li>
            <li><strong>パスの問題</strong>：
                <ul>
                    <li>URLに「cotoka_management_system」が含まれているか確認してください</li>
                    <li>サブディレクトリを使用している場合は、パスを正しく設定してください</li>
                </ul>
            </li>
            <li><strong>.htaccess設定</strong>：
                <ul>
                    <li>RewriteBaseの設定が正しいか確認してください</li>
                    <li>mod_rewriteモジュールが有効か確認してください</li>
                </ul>
            </li>
            <li><strong>日本語URLの問題</strong>：
                <ul>
                    <li>URLに日本語が含まれる場合、エンコードの問題がある可能性があります</li>
                    <li>可能であれば、一時的に英数字のみのスラッグでテストしてください</li>
                </ul>
            </li>
        </ol>
    </div>
</body>
</html> 