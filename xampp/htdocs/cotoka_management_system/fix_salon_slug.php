<?php
// URLスラッグ問題診断・修正スクリプト

// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// ログイン状態でなくても実行可能
$is_logged_in = isset($_SESSION['user_id']);

// ヘッダー
echo "<!DOCTYPE html>
<html lang='ja'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>サロンURLスラッグ修正ツール</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container my-5'>
        <h1>サロンURLスラッグ診断・修正ツール</h1>";

// データベース診断
echo "<h2 class='mt-4'>1. サロンテーブル診断</h2>";
try {
    $stmt = $conn->query("SELECT salon_id, name, url_slug, default_booking_source_id FROM salons ORDER BY salon_id");
    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='table-responsive'><table class='table table-bordered'>
        <thead>
            <tr>
                <th>サロンID</th>
                <th>サロン名</th>
                <th>URLスラッグ</th>
                <th>デフォルトソースID</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach ($salons as $salon) {
        echo "<tr>
            <td>{$salon['salon_id']}</td>
            <td>{$salon['name']}</td>
            <td>{$salon['url_slug']}</td>
            <td>{$salon['default_booking_source_id']}</td>
        </tr>";
    }
    
    echo "</tbody></table></div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>サロンテーブル診断エラー: " . $e->getMessage() . "</div>";
}

// 予約ソーステーブル診断
echo "<h2 class='mt-4'>2. 予約ソーステーブル診断</h2>";
try {
    $stmt = $conn->query("SELECT source_id, salon_id, source_name, source_code, tracking_url, is_active FROM booking_sources ORDER BY salon_id, source_id");
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='table-responsive'><table class='table table-bordered'>
        <thead>
            <tr>
                <th>ソースID</th>
                <th>サロンID</th>
                <th>ソース名</th>
                <th>ソースコード</th>
                <th>トラッキングURL</th>
                <th>状態</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach ($sources as $source) {
        echo "<tr>
            <td>{$source['source_id']}</td>
            <td>{$source['salon_id']}</td>
            <td>{$source['source_name']}</td>
            <td>{$source['source_code']}</td>
            <td>{$source['tracking_url']}</td>
            <td>" . ($source['is_active'] ? '有効' : '無効') . "</td>
        </tr>";
    }
    
    echo "</tbody></table></div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>予約ソーステーブル診断エラー: " . $e->getMessage() . "</div>";
}

// セッション情報
echo "<h2 class='mt-4'>3. セッション情報</h2>";
echo "<pre>";
echo "SESSION変数: \n";
print_r($_SESSION);
echo "\n";
echo "getCurrentSalonId(): " . getCurrentSalonId() . "\n";
echo "</pre>";

// 修正フォーム
echo "<h2 class='mt-4'>4. URLスラッグ修正</h2>";

// フォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_session') {
        // セッション変数を更新
        $new_salon_id = intval($_POST['salon_id']);
        $_SESSION['current_salon_id'] = $new_salon_id;
        $_SESSION['salon_id'] = $new_salon_id;
        echo "<div class='alert alert-success'>セッションのサロンIDを {$new_salon_id} に更新しました。</div>";
    } elseif ($_POST['action'] === 'update_slugs') {
        // すべてのサロンのURLスラッグを更新
        try {
            $conn->beginTransaction();
            
            // サロン一覧を取得
            $stmt = $conn->query("SELECT salon_id, name FROM salons");
            $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($salons as $salon) {
                // サロン名からスラッグを生成
                $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $salon['name'])) . '-' . rand(100, 999);
                
                // スラッグを更新
                $update = $conn->prepare("UPDATE salons SET url_slug = ? WHERE salon_id = ?");
                $update->execute([$slug, $salon['salon_id']]);
                
                echo "<div>サロンID {$salon['salon_id']} ({$salon['name']}) のスラッグを <strong>{$slug}</strong> に更新しました。</div>";
            }
            
            $conn->commit();
            echo "<div class='alert alert-success mt-3'>すべてのサロンのURLスラッグを更新しました。</div>";
        } catch (Exception $e) {
            $conn->rollBack();
            echo "<div class='alert alert-danger'>URLスラッグ更新エラー: " . $e->getMessage() . "</div>";
        }
    } elseif ($_POST['action'] === 'fix_tracking_urls') {
        // トラッキングURLの修正
        try {
            $conn->beginTransaction();
            
            // サロン一覧を取得
            $stmt = $conn->query("SELECT salon_id, url_slug FROM salons");
            $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($salons as $salon) {
                // サロンの予約ソースを取得
                $sources = $conn->prepare("SELECT source_id, source_code FROM booking_sources WHERE salon_id = ?");
                $sources->execute([$salon['salon_id']]);
                
                while ($source = $sources->fetch(PDO::FETCH_ASSOC)) {
                    // 新しいトラッキングURLを生成
                    $tracking_url = "/public_booking/{$salon['url_slug']}?source={$source['source_code']}";
                    
                    // トラッキングURLを更新
                    $update = $conn->prepare("UPDATE booking_sources SET tracking_url = ? WHERE source_id = ?");
                    $update->execute([$tracking_url, $source['source_id']]);
                    
                    echo "<div>ソースID {$source['source_id']} のトラッキングURLを <strong>{$tracking_url}</strong> に更新しました。</div>";
                }
            }
            
            $conn->commit();
            echo "<div class='alert alert-success mt-3'>すべてのトラッキングURLを更新しました。</div>";
        } catch (Exception $e) {
            $conn->rollBack();
            echo "<div class='alert alert-danger'>トラッキングURL更新エラー: " . $e->getMessage() . "</div>";
        }
    }
}

// フォーム表示
echo "<div class='row'>";
// セッション変更フォーム
echo "<div class='col-md-4'>
    <div class='card'>
        <div class='card-header'>セッションのサロンID変更</div>
        <div class='card-body'>
            <form method='post'>
                <input type='hidden' name='action' value='update_session'>
                <div class='mb-3'>
                    <label for='salon_id' class='form-label'>サロンID:</label>
                    <select name='salon_id' id='salon_id' class='form-select'>";
                    
                    // サロン一覧を取得
                    $stmt = $conn->query("SELECT salon_id, name FROM salons ORDER BY name");
                    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($salons as $salon) {
                        $selected = $salon['salon_id'] == getCurrentSalonId() ? 'selected' : '';
                        echo "<option value='{$salon['salon_id']}' {$selected}>{$salon['name']} (ID: {$salon['salon_id']})</option>";
                    }
                    
echo "          </select>
                </div>
                <button type='submit' class='btn btn-primary'>セッション更新</button>
            </form>
        </div>
    </div>
</div>";

// URLスラッグ更新フォーム
echo "<div class='col-md-4'>
    <div class='card'>
        <div class='card-header'>すべてのURLスラッグを再生成</div>
        <div class='card-body'>
            <p>すべてのサロンのURLスラッグを再生成します。</p>
            <form method='post'>
                <input type='hidden' name='action' value='update_slugs'>
                <button type='submit' class='btn btn-warning'>URLスラッグ再生成</button>
            </form>
        </div>
    </div>
</div>";

// トラッキングURL修正フォーム
echo "<div class='col-md-4'>
    <div class='card'>
        <div class='card-header'>トラッキングURLの修正</div>
        <div class='card-body'>
            <p>URLスラッグに基づいてトラッキングURLを更新します。</p>
            <form method='post'>
                <input type='hidden' name='action' value='fix_tracking_urls'>
                <button type='submit' class='btn btn-info'>トラッキングURL修正</button>
            </form>
        </div>
    </div>
</div>";

echo "</div>"; // .row の終了

// 戻るリンク
echo "<div class='mt-4'>
    <a href='booking_url_management.php' class='btn btn-secondary'>予約URL管理に戻る</a>
</div>";

// フッター
echo "</div>
</body>
</html>";

