<?php
require_once 'config.php';

// Supabase接続設定
$supabase_url = 'https://fzfnjtuuxmxpngzwaueo.supabase.co';
$supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ6Zm5qdHV1eG14cG5nendhdWVvIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczNTU0NzI5NCwiZXhwIjoyMDUxMTIzMjk0fQ.example'; // サービスロールキーが必要

// テナントデータ
$tenant_data = [
    'company_name' => 'Trend Company株式会社',
    'owner_name' => '代表者',
    'email' => 'info@cotoka.jp',
    'phone' => '03-1234-5678',
    'address' => '東京都渋谷区',
    'subscription_plan' => 'premium',
    'subscription_status' => 'active',
    'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
    'subscription_ends_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
    'max_salons' => 10,
    'max_users' => 50,
    'max_storage_mb' => 10240,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

// サロンデータ
$salon_data = [
    'salon_name' => '古都華 Cotoka Relax & Beauty SPA',
    'slug' => 'cotoka-relax-beauty-spa',
    'address' => '京都府京都市中京区',
    'phone_number' => '075-123-4567',
    'email' => 'salon@cotoka.jp',
    'description' => '京都の美しい古都で心と体を癒すリラクゼーション&ビューティーサロン',
    'is_active' => true,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

// スタッフデータ
$staff_data = [
    ['name' => 'チョウ', 'email' => 'chou@cotoka.jp', 'phone' => '090-1111-1111', 'specialties' => 'カット,カラー'],
    ['name' => 'みどり', 'email' => 'midori@cotoka.jp', 'phone' => '090-2222-2222', 'specialties' => 'パーマ,トリートメント'],
    ['name' => 'ねね', 'email' => 'nene@cotoka.jp', 'phone' => '090-3333-3333', 'specialties' => 'ネイル,まつげエクステ'],
    ['name' => 'はる', 'email' => 'haru@cotoka.jp', 'phone' => '090-4444-4444', 'specialties' => 'フェイシャル,マッサージ'],
    ['name' => 'はな', 'email' => 'hana@cotoka.jp', 'phone' => '090-5555-5555', 'specialties' => 'ヘッドスパ,アロマ'],
    ['name' => 'みー', 'email' => 'mii@cotoka.jp', 'phone' => '090-6666-6666', 'specialties' => 'ブライダル,着付け']
];

// ユーザーデータ
$user_data = [
    'user_id' => 'admin_cotoka',
    'email' => 'info@cotoka.jp',
    'password' => password_hash('syou108810', PASSWORD_DEFAULT),
    'name' => '管理者',
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

function callSupabaseAPI($url, $data, $method = 'POST') {
    global $supabase_url, $supabase_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $supabase_url . '/rest/v1/' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $supabase_key,
        'apikey: ' . $supabase_key,
        'Prefer: return=representation'
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => json_decode($response, true), 'http_code' => $http_code];
}

try {
    echo "Supabaseにデータを登録中...\n\n";
    
    // 1. テナント登録
    echo "1. テナント登録中...\n";
    $result = callSupabaseAPI('cotoka.tenants', $tenant_data);
    if ($result['http_code'] === 201) {
        $tenant_id = $result['response'][0]['tenant_id'];
        echo "テナント登録成功: ID = {$tenant_id}\n\n";
        
        // 2. サロン登録
        echo "2. サロン登録中...\n";
        $salon_data['tenant_id'] = $tenant_id;
        $result = callSupabaseAPI('cotoka.salons', $salon_data);
        if ($result['http_code'] === 201) {
            $salon_id = $result['response'][0]['salon_id'];
            echo "サロン登録成功: ID = {$salon_id}\n\n";
            
            // 3. ユーザー登録
            echo "3. ユーザー登録中...\n";
            $user_data['tenant_id'] = $tenant_id;
            $result = callSupabaseAPI('cotoka.users', $user_data);
            if ($result['http_code'] === 201) {
                $user_id = $result['response'][0]['id'];
                echo "ユーザー登録成功: ID = {$user_id}\n\n";
                
                // 4. スタッフ登録
                echo "4. スタッフ登録中...\n";
                foreach ($staff_data as $index => $staff) {
                    $staff['tenant_id'] = $tenant_id;
                    $staff['salon_id'] = $salon_id;
                    $staff['user_id'] = $user_id; // 仮の関連付け
                    $staff['status'] = 'active';
                    $staff['created_at'] = date('Y-m-d H:i:s');
                    $staff['updated_at'] = date('Y-m-d H:i:s');
                    
                    $result = callSupabaseAPI('cotoka.staff', $staff);
                    if ($result['http_code'] === 201) {
                        echo "スタッフ登録成功: {$staff['name']}\n";
                    } else {
                        echo "スタッフ登録失敗: {$staff['name']} - " . json_encode($result['response']) . "\n";
                    }
                }
                
                echo "\n全データの登録が完了しました！\n";
                echo "ログイン情報:\n";
                echo "メールアドレス: info@cotoka.jp\n";
                echo "パスワード: syou108810\n";
                
            } else {
                echo "ユーザー登録失敗: " . json_encode($result['response']) . "\n";
            }
        } else {
            echo "サロン登録失敗: " . json_encode($result['response']) . "\n";
        }
    } else {
        echo "テナント登録失敗: " . json_encode($result['response']) . "\n";
    }
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
}
?>