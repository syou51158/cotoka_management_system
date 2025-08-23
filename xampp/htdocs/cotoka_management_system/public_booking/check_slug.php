<?php
/**
 * サロンスラッグチェックツール
 * 
 * 【本番環境に関する注意】
 * このファイルは開発・デバッグ用です。本番環境では以下の対応を行ってください：
 * 1. 本番環境ではアクセス制限するか削除してください（セキュリティリスク）
 * 2. または、管理者のみアクセス可能なように認証を追加してください
 * 3. 残す場合は、データベース構造や接続情報が漏洩しないよう注意してください
 * 
 * 【使用方法】
 * - パラメータなし: デフォルトのスラッグをチェック
 * - ?slug=XXX: 指定したスラッグをチェック
 */

// 本番環境では以下の行のコメントを解除して一般アクセスを禁止することを推奨
// if (!isset($_SERVER['SERVER_ADDR']) || $_SERVER['REMOTE_ADDR'] !== $_SERVER['SERVER_ADDR']) {
//     header('HTTP/1.0 403 Forbidden');
//     echo '403 Forbidden';
//     exit;
// }

header('Content-Type: text/html; charset=utf-8');

echo "<h1>サロンスラッグチェック</h1>";
echo "<p style='color:red;'>【注意】本番環境ではこのファイルを削除するか、アクセス制限を設けてください。</p>";

// スラッグを取得（デフォルト値を設定）
$slug_to_check = isset($_GET['slug']) ? $_GET['slug'] : 'demo-salon-shinjuku-ten-123';
echo "<p>チェック対象スラッグ: <strong>" . htmlspecialchars($slug_to_check) . "</strong></p>";

try {
    require_once '../includes/config.php';
    require_once '../includes/database.php';

    // スラッグが存在するか確認（url_slugに修正）
    $query = "SELECT salon_id, name, url_slug FROM salons WHERE url_slug = :slug";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':slug', $slug_to_check);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($salon) {
        echo "<div style='color: green; padding: 10px; background: #e8f5e9; border: 1px solid #c8e6c9;'>";
        echo "<h2>スラッグが見つかりました ✓</h2>";
        echo "<p>サロンID: " . $salon['salon_id'] . "</p>";
        echo "<p>サロン名: " . $salon['name'] . "</p>";
        echo "<p>スラッグ: " . $salon['url_slug'] . "</p>";
        
        // 正しいURLの例を表示
        echo "<h3>正しいURL:</h3>";
        // ローカル環境のURL
        echo "<p><strong>ローカル環境:</strong><br>";
        echo "<a href='http://localhost/cotoka_management_system/public_booking/index.php?slug=" . $salon['url_slug'] . "&source=instagram'>従来の形式でアクセス</a><br>";
        echo "<a href='http://localhost/cotoka_management_system/public_booking/" . $salon['url_slug'] . "?source=instagram'>.htaccessを有効にした場合のURL形式</a></p>";
        
        // 本番環境のURL（例）
        echo "<p><strong>本番環境（例）:</strong><br>";
        echo "<a href='https://example.com/public_booking/index.php?slug=" . $salon['url_slug'] . "&source=instagram'>従来の形式でアクセス</a><br>";
        echo "<a href='https://example.com/public_booking/" . $salon['url_slug'] . "?source=instagram'>.htaccessを有効にした場合のURL形式</a></p>";
        echo "<p>※本番環境では、上記URLの「example.com」部分を実際のドメインに置き換えてください。</p>";
        echo "</div>";
    } else {
        echo "<div style='color: red; padding: 10px; background: #ffebee; border: 1px solid #ffcdd2;'>";
        echo "<h2>スラッグが見つかりませんでした ✗</h2>";
        echo "<p>データベース内に「" . htmlspecialchars($slug_to_check) . "」というスラッグを持つサロンは存在しません。</p>";
        
        // 実際に存在するスラッグを表示
        $query = "SELECT salon_id, name, url_slug FROM salons LIMIT 10";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($salons) > 0) {
            echo "<h3>利用可能なスラッグ:</h3>";
            echo "<ul>";
            foreach ($salons as $s) {
                echo "<li>" . $s['name'] . " - スラッグ: <strong>" . $s['url_slug'] . "</strong> ";
                echo "<a href='check_slug.php?slug=" . $s['url_slug'] . "'>このスラッグをチェック</a></li>";
            }
            echo "</ul>";
        }
        echo "</div>";
    }

    // URLスラッグとsalon.slugの列の比較
    echo "<h2>URLスラッグとデータベースの列名確認</h2>";
    echo "<p>データベースのテーブル構造を確認し、実際の列名とURLパラメータが一致しているか確認します</p>";
    
    $query = "DESCRIBE salons";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>フィールド</th><th>タイプ</th><th>NULL許可</th><th>キー</th><th>デフォルト</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        foreach ($col as $key => $value) {
            echo "<td>" . ($value === NULL ? 'NULL' : htmlspecialchars($value)) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; background: #ffebee; border: 1px solid #ffcdd2;'>";
    echo "<h2>データベースエラー</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// 他のソースからの簡易アクセステスト
echo "<h2>その他のテスト</h2>";
echo "<ul>";
echo "<li><a href='index.php?salon_id=1&source=instagram'>サロンID=1でテスト (Instagram)</a></li>";
echo "<li><a href='index.php?salon_id=1&source=google'>サロンID=1でテスト (Google)</a></li>";
echo "</ul>";

// 本番環境メモ
echo "<div style='margin-top: 30px; padding: 15px; background: #fff3e0; border: 1px solid #ffe0b2;'>";
echo "<h2>本番環境デプロイメモ</h2>";
echo "<ol>";
echo "<li>このファイル（check_slug.php）は本番環境では削除するか、認証機能を追加してください</li>";
echo "<li>debug.phpやtest.phpなどのデバッグファイルも同様に削除または制限してください</li>";
echo "<li>.htaccessファイルの設定を本番環境に合わせて調整してください：";
echo "<ul>";
echo "<li>RewriteBaseの設定</li>";
echo "<li>HTTPSリダイレクトの有効化</li>";
echo "<li>セキュリティヘッダーの設定</li>";
echo "</ul></li>";
echo "<li>Japanese characters in URLsに関する問題に注意：</li>";
echo "<ul>";
echo "<li>サーバー設定によってはURLエンコーディングの問題が発生する可能性があります</li>";
echo "<li>日本語を含むスラッグを使用する場合は、十分にテストしてください</li>";
echo "<li>可能であれば、ローマ字や英数字のスラッグの使用を推奨します</li>";
echo "</ul>";
echo "</ol>";
echo "</div>";
?> 