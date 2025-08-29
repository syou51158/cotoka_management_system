<?php
require_once 'config/config.php';
require_once 'classes/SupabaseClient.php';

// Supabaseクライアントを初期化
$supabase = new SupabaseClient();

echo "=== Supabase接続テスト ===\n";

// 接続テスト
try {
    $connectionTest = $supabase->testConnection();
    echo "接続テスト結果: " . ($connectionTest ? "成功" : "失敗") . "\n\n";
} catch (Exception $e) {
    echo "接続エラー: " . $e->getMessage() . "\n\n";
}

// テーブル一覧を取得
echo "=== テーブル一覧 ===\n";
try {
    // PostgreSQLのinformation_schemaからテーブル一覧を取得
    $tables = $supabase->select('information_schema.tables', '*', [
        'table_schema' => 'eq.public',
        'table_type' => 'eq.BASE TABLE'
    ]);
    
    if ($tables && isset($tables['data'])) {
        echo "public スキーマのテーブル数: " . count($tables['data']) . "\n";
        foreach ($tables['data'] as $table) {
            echo "- " . $table['table_name'] . "\n";
        }
    } else {
        echo "テーブル情報を取得できませんでした\n";
    }
} catch (Exception $e) {
    echo "テーブル一覧取得エラー: " . $e->getMessage() . "\n";
}

echo "\n=== ユーザーテーブル確認 ===\n";
try {
    // usersテーブルの件数を確認
    $users = $supabase->select('users', 'id,email,name,created_at', [], 'id', false, 5);
    
    if ($users && isset($users['data'])) {
        echo "ユーザー数: " . count($users['data']) . "\n";
        if (count($users['data']) > 0) {
            echo "ユーザー一覧:\n";
            foreach ($users['data'] as $user) {
                echo "- ID: {$user['id']}, Email: {$user['email']}, Name: {$user['name']}, Created: {$user['created_at']}\n";
            }
        } else {
            echo "ユーザーが登録されていません\n";
        }
    } else {
        echo "ユーザーテーブルにアクセスできませんでした\n";
    }
} catch (Exception $e) {
    echo "ユーザーテーブル確認エラー: " . $e->getMessage() . "\n";
}

echo "\n=== テナントテーブル確認 ===\n";
try {
    // tenantsテーブルの件数を確認
    $tenants = $supabase->select('tenants', 'tenant_id,company_name,email,subscription_plan,subscription_status', [], 'tenant_id', false, 5);
    
    if ($tenants && isset($tenants['data'])) {
        echo "テナント数: " . count($tenants['data']) . "\n";
        if (count($tenants['data']) > 0) {
            echo "テナント一覧:\n";
            foreach ($tenants['data'] as $tenant) {
                echo "- ID: {$tenant['tenant_id']}, Company: {$tenant['company_name']}, Email: {$tenant['email']}, Plan: {$tenant['subscription_plan']}, Status: {$tenant['subscription_status']}\n";
            }
        } else {
            echo "テナントが登録されていません\n";
        }
    } else {
        echo "テナントテーブルにアクセスできませんでした\n";
    }
} catch (Exception $e) {
    echo "テナントテーブル確認エラー: " . $e->getMessage() . "\n";
}

echo "\n=== サロンテーブル確認 ===\n";
try {
    // salonsテーブルの件数を確認
    $salons = $supabase->select('salons', 'salon_id,tenant_id,name,status', [], 'salon_id', false, 5);
    
    if ($salons && isset($salons['data'])) {
        echo "サロン数: " . count($salons['data']) . "\n";
        if (count($salons['data']) > 0) {
            echo "サロン一覧:\n";
            foreach ($salons['data'] as $salon) {
                echo "- ID: {$salon['salon_id']}, Tenant ID: {$salon['tenant_id']}, Name: {$salon['name']}, Status: {$salon['status']}\n";
            }
        } else {
            echo "サロンが登録されていません\n";
        }
    } else {
        echo "サロンテーブルにアクセスできませんでした\n";
    }
} catch (Exception $e) {
    echo "サロンテーブル確認エラー: " . $e->getMessage() . "\n";
}

echo "\n=== 設定情報 ===\n";
echo "SUPABASE_URL: " . SUPABASE_URL . "\n";
echo "SUPABASE_ANON_KEY: " . substr(SUPABASE_ANON_KEY, 0, 20) . "...\n";
echo "SUPABASE_SERVICE_ROLE_KEY: " . (defined('SUPABASE_SERVICE_ROLE_KEY') ? substr(SUPABASE_SERVICE_ROLE_KEY, 0, 20) . "..." : "未設定") . "\n";
?>