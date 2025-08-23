<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'classes/Database.php';
require_once 'classes/Tenant.php';

// ヘッダー
echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システムテスト</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; }
        .test-section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        h2 { color: #0066cc; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">';

// データベース接続
try {
    $db = new Database();
    $tenantObj = new Tenant();
    
    echo '<div class="alert alert-success">データベース接続成功</div>';
} catch (Exception $e) {
    echo '<div class="alert alert-danger">データベース接続エラー: ' . $e->getMessage() . '</div>';
    echo '</div></body></html>';
    exit;
}

// セッション情報
echo '<div class="test-section">
    <h2>1. セッション情報</h2>
    <pre>' . print_r($_SESSION, true) . '</pre>
    
    <p>ログイン状態: ' . (isLoggedIn() ? '<span class="success">ログイン中</span>' : '<span class="error">未ログイン</span>') . '</p>
    <p>スーパー管理者: ' . (isSuperAdmin() ? '<span class="success">はい</span>' : '<span class="error">いいえ</span>') . '</p>
    <p>スーパー管理者としてログイン: ' . (isLoggedInAsSuperAdmin() ? '<span class="success">はい</span>' : '<span class="error">いいえ</span>') . '</p>
    
    <a href="super-admin-login.php" class="btn btn-primary">スーパー管理者ログインページへ</a>
    <a href="super_admin_test.php" class="btn btn-info">スーパー管理者テストへ</a>
</div>';

// テナント情報
echo '<div class="test-section">
    <h2>2. テナント情報</h2>';

$tenants = $tenantObj->getAll();
if (count($tenants) > 0) {
    echo '<p>テナント総数: <span class="success">' . count($tenants) . '</span></p>';
    echo '<table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>会社名</th>
                <th>オーナー</th>
                <th>プラン</th>
                <th>ステータス</th>
                <th>作成日</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($tenants as $tenant) {
        echo '<tr>
            <td>' . $tenant['tenant_id'] . '</td>
            <td>' . sanitize($tenant['company_name']) . '</td>
            <td>' . sanitize($tenant['owner_name']) . '</td>
            <td>' . sanitize($tenant['subscription_plan']) . '</td>
            <td>' . sanitize($tenant['subscription_status']) . '</td>
            <td>' . date('Y/m/d H:i', strtotime($tenant['created_at'])) . '</td>
        </tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo '<p class="error">テナントデータが見つかりません。</p>';
}

echo '<a href="tenant-management.php" class="btn btn-primary">テナント管理ページへ</a>
    <a href="business_hours_test.php" class="btn btn-info">営業時間テストへ</a>
</div>';

// テナント設定
echo '<div class="test-section">
    <h2>3. テナント設定</h2>';

$settings = $db->fetchAll("SELECT * FROM tenant_settings", []);

if (count($settings) > 0) {
    echo '<p>テナント設定総数: <span class="success">' . count($settings) . '</span></p>';
    echo '<table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>テナントID</th>
                <th>設定キー</th>
                <th>作成日</th>
                <th>更新日</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($settings as $setting) {
        echo '<tr>
            <td>' . $setting['setting_id'] . '</td>
            <td>' . $setting['tenant_id'] . '</td>
            <td>' . sanitize($setting['setting_key']) . '</td>
            <td>' . date('Y/m/d H:i', strtotime($setting['created_at'])) . '</td>
            <td>' . date('Y/m/d H:i', strtotime($setting['updated_at'])) . '</td>
        </tr>';
    }
    
    echo '</tbody></table>';
    
    // ビジネス時間の詳細表示
    $businessHoursSettings = $db->fetchAll("SELECT * FROM tenant_settings WHERE setting_key = 'business_hours'", []);
    
    if (count($businessHoursSettings) > 0) {
        echo '<h3>ビジネス時間設定詳細</h3>';
        
        foreach ($businessHoursSettings as $setting) {
            $tenantInfo = $tenantObj->getById($setting['tenant_id']);
            echo '<h4>' . sanitize($tenantInfo['company_name']) . ' (ID: ' . $setting['tenant_id'] . ')</h4>';
            
            $businessHours = json_decode($setting['setting_value'], true);
            
            if ($businessHours) {
                // 曜日の日本語名
                $weekdayNames = [
                    'monday' => '月曜日',
                    'tuesday' => '火曜日',
                    'wednesday' => '水曜日',
                    'thursday' => '木曜日',
                    'friday' => '金曜日',
                    'saturday' => '土曜日',
                    'sunday' => '日曜日'
                ];
                
                echo '<table class="table table-sm">
                    <thead>
                        <tr>
                            <th>曜日</th>
                            <th>営業状態</th>
                            <th>開始時間</th>
                            <th>終了時間</th>
                        </tr>
                    </thead>
                    <tbody>';
                
                foreach ($businessHours as $day => $hours) {
                    echo '<tr>
                        <td>' . ($weekdayNames[$day] ?? $day) . '</td>
                        <td>' . ($hours['is_open'] ? '<span class="badge bg-success">営業</span>' : '<span class="badge bg-danger">休業</span>') . '</td>
                        <td>' . ($hours['open_time'] ?? '-') . '</td>
                        <td>' . ($hours['close_time'] ?? '-') . '</td>
                    </tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<p class="error">JSONデータの解析に失敗しました。</p>';
            }
        }
    }
} else {
    echo '<p class="error">テナント設定が見つかりません。</p>';
}

echo '<a href="tenant_detail.php?id=1" class="btn btn-primary">テナント詳細へ</a>
</div>';

// API機能
echo '<div class="test-section">
    <h2>4. API機能テスト</h2>
    <div class="mb-3">
        <h3>ビジネス時間API</h3>
        <p>テナントID 1のビジネス時間を取得:</p>
        <div id="api-test-result">結果を取得中...</div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            fetch("api/get_tenant_business_hours.php?tenant_id=1")
                .then(response => response.json())
                .then(data => {
                    const resultDiv = document.getElementById("api-test-result");
                    resultDiv.innerHTML = "<pre>" + JSON.stringify(data, null, 2) + "</pre>";
                    
                    if (data.success) {
                        resultDiv.classList.add("alert", "alert-success");
                    } else {
                        resultDiv.classList.add("alert", "alert-danger");
                    }
                })
                .catch(error => {
                    document.getElementById("api-test-result").innerHTML = 
                        "<div class=\"alert alert-danger\">エラー: " + error.message + "</div>";
                });
        });
    </script>
</div>';

// リンク集
echo '<div class="test-section">
    <h2>5. 主要リンク</h2>
    <div class="list-group">
        <a href="super-admin-login.php" class="list-group-item list-group-item-action">スーパー管理者ログイン</a>
        <a href="tenant-management.php" class="list-group-item list-group-item-action">テナント管理</a>
        <a href="super_admin_test.php" class="list-group-item list-group-item-action">スーパー管理者テスト</a>
        <a href="business_hours_test.php" class="list-group-item list-group-item-action">営業時間テスト</a>
        <a href="tenant_detail.php?id=1" class="list-group-item list-group-item-action">テナント詳細（ID: 1）</a>
        <a href="api/get_tenant_business_hours.php?tenant_id=1" class="list-group-item list-group-item-action" target="_blank">営業時間API（ID: 1）</a>
        <a href="dashboard.php" class="list-group-item list-group-item-action">ダッシュボード</a>
        <a href="logout.php" class="list-group-item list-group-item-action">ログアウト</a>
    </div>
</div>';

// フッター
echo '</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
?>
