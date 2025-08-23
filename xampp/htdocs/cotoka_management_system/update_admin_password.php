<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

// スーパー管理者のパスワードを更新する
try {
    $db = new Database();
    
    // パスワードをハッシュ化
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "生成されたハッシュ: $hash <br>";
    
    // ハッシュ値をエスケープしてデータベースに保存
    $sql = "UPDATE super_admins SET password = ? WHERE username = 'superadmin'";
    $result = $db->query($sql, [$hash]);
    
    if ($result) {
        echo "パスワードが正常に更新されました。<br>";
        echo "ユーザー名: superadmin<br>";
        echo "パスワード: $password<br>";
        
        // 確認のため、更新後のレコードを取得して表示
        $sql = "SELECT * FROM super_admins WHERE username = 'superadmin'";
        $admin = $db->fetchOne($sql, []);
        
        if ($admin) {
            echo "<hr><pre>";
            print_r($admin);
            echo "</pre>";
            
            // パスワード検証のテスト
            $verifyResult = password_verify($password, $admin['password']);
            echo "<hr>パスワード検証結果: " . ($verifyResult ? "成功" : "失敗");
        }
    } else {
        echo "パスワードの更新に失敗しました。";
    }
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
}
?>
