<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // サロンIDとテナントID（既存のものを使う）
    $salon_id = 1;
    $tenant_id = 1;
    
    // トランザクション開始
    $conn->beginTransaction();
    
    // 1. ジョジョバオイルフットケア
    $stmt = $conn->prepare('INSERT INTO services (salon_id, tenant_id, name, description, duration, price, status) VALUES 
        (:salon_id, :tenant_id, :name, :description, :duration, :price, :status)');
    
    // 30分コース
    $name = 'ジョジョバオイルフットケア 30分';
    $description = '高品質なジョジョバオイルを使用した海外ゲスト向けの贅沢なフットケア（30分コース）';
    $duration = 30;
    $price = 5800;
    $status = 'active';
    
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':tenant_id', $tenant_id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':duration', $duration);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    
    // 60分コース
    $name = 'ジョジョバオイルフットケア 60分';
    $description = '高品質なジョジョバオイルを使用した海外ゲスト向けの贅沢なフットケア（60分コース）';
    $duration = 60;
    $price = 9800;
    $stmt->execute();
    
    // 90分コース
    $name = 'ジョジョバオイルフットケア 90分';
    $description = '高品質なジョジョバオイルを使用した海外ゲスト向けの贅沢なフットケア（90分コース）';
    $duration = 90;
    $price = 13800;
    $stmt->execute();
    
    // 120分コース
    $name = 'ジョジョバオイルフットケア 120分';
    $description = '高品質なジョジョバオイルを使用した海外ゲスト向けの贅沢なフットケア（120分コース）';
    $duration = 120;
    $price = 17800;
    $stmt->execute();
    
    // 2. ジョジョバオイルリンパデトックス
    $name = 'ジョジョバオイルリンパデトックス 30分';
    $description = '高品質なジョジョバオイルを使用したリンパデトックス（30分コース）';
    $duration = 30;
    $price = 5800;
    $stmt->execute();
    
    $name = 'ジョジョバオイルリンパデトックス 60分';
    $description = '高品質なジョジョバオイルを使用したリンパデトックス（60分コース）';
    $duration = 60;
    $price = 9800;
    $stmt->execute();
    
    $name = 'ジョジョバオイルリンパデトックス 90分';
    $description = '高品質なジョジョバオイルを使用したリンパデトックス（90分コース）';
    $duration = 90;
    $price = 13800;
    $stmt->execute();
    
    $name = 'ジョジョバオイルリンパデトックス 120分';
    $description = '高品質なジョジョバオイルを使用したリンパデトックス（120分コース）';
    $duration = 120;
    $price = 17800;
    $stmt->execute();
    
    // 3. コンボメニュー
    $name = 'フットケア＆リンパデトックスコンボ 60分';
    $description = 'フットケア(30分)とリンパデトックス(30分)のお得なセットメニュー';
    $duration = 60;
    $price = 11500;
    $stmt->execute();
    
    $name = 'フットケア＆リンパデトックスコンボ 90分';
    $description = 'フットケア(30分)とリンパデトックス(60分)のお得なセットメニュー';
    $duration = 90;
    $price = 14800;
    $stmt->execute();
    
    // 4. フェイシャルトリートメント
    $name = 'フェイシャルトリートメント 15分';
    $description = '顔全体のリラクゼーションケア（15分コース）';
    $duration = 15;
    $price = 2500;
    $stmt->execute();
    
    $name = 'フェイシャルトリートメント 30分';
    $description = '顔全体のリラクゼーションケア（30分コース）';
    $duration = 30;
    $price = 4500;
    $stmt->execute();
    
    // 5. ヘッド&ショルダートリートメント
    $name = 'ヘッド&ショルダートリートメント 15分';
    $description = '頭部と肩のリラクゼーションケア（15分コース）';
    $duration = 15;
    $price = 1500;
    $stmt->execute();
    
    $name = 'ヘッド&ショルダートリートメント 30分';
    $description = '頭部と肩のリラクゼーションケア（30分コース）';
    $duration = 30;
    $price = 3000;
    $stmt->execute();
    
    // 6. 中国式フットケア
    $name = '中国式フットケア 30分';
    $description = '伝統的な中国式の足裏ケア（30分コース）';
    $duration = 30;
    $price = 5800;
    $stmt->execute();
    
    $name = '中国式フットケア 50分';
    $description = '伝統的な中国式の足裏ケア（50分コース）';
    $duration = 50;
    $price = 6800;
    $stmt->execute();
    
    // 7. スッキリコース
    $name = 'スッキリコース';
    $description = 'ボディケア20分＋リンパオイル30分＋中国式足つぼ15分の総合ケア';
    $duration = 65;
    $price = 8000;
    $stmt->execute();
    
    // 8. リンパオイルトリートメント
    $name = 'リンパオイルトリートメント 上半身 30分';
    $description = 'リンパの流れを促進する上半身専用オイルトリートメント';
    $duration = 30;
    $price = 4800;
    $stmt->execute();
    
    $name = 'リンパオイルトリートメント 下半身 30分';
    $description = 'リンパの流れを促進する下半身専用オイルトリートメント';
    $duration = 30;
    $price = 4800;
    $stmt->execute();
    
    $name = 'リンパオイルトリートメント 70分';
    $description = 'リンパの流れを促進する全身オイルトリートメント（70分コース）';
    $duration = 70;
    $price = 7800;
    $stmt->execute();
    
    $name = 'リンパオイルトリートメント 100分';
    $description = 'リンパの流れを促進する全身オイルトリートメント（100分コース）';
    $duration = 100;
    $price = 10800;
    $stmt->execute();
    
    $name = 'リンパオイルトリートメント 120分';
    $description = 'リンパの流れを促進する全身オイルトリートメント（120分コース）';
    $duration = 120;
    $price = 13800;
    $stmt->execute();
    
    // 9. ホホバオイルトリートメント
    $name = 'ホホバオイルトリートメント 30分';
    $description = '上質なホホバオイルを使用した全身トリートメント（30分コース）';
    $duration = 30;
    $price = 5800;
    $stmt->execute();
    
    $name = 'ホホバオイルトリートメント 60分';
    $description = '上質なホホバオイルを使用した全身トリートメント（60分コース）';
    $duration = 60;
    $price = 9800;
    $stmt->execute();
    
    $name = 'ホホバオイルトリートメント 90分';
    $description = '上質なホホバオイルを使用した全身トリートメント（90分コース）';
    $duration = 90;
    $price = 13800;
    $stmt->execute();
    
    $name = 'ホホバオイルトリートメント 120分';
    $description = '上質なホホバオイルを使用した全身トリートメント（120分コース）';
    $duration = 120;
    $price = 17800;
    $stmt->execute();
    
    $name = 'ホホバオイルトリートメント 延長30分';
    $description = 'ホホバオイルトリートメントの延長オプション（30分）';
    $duration = 30;
    $price = 5000;
    $stmt->execute();
    
    // 10. 中国式刮痧（カッサ）
    $name = '中国式刮痧（カッサ） 30分';
    $description = '伝統的な中国の刮痧（かっさ）技術を用いたトリートメント（30分コース）';
    $duration = 30;
    $price = 3800;
    $stmt->execute();
    
    $name = '中国式刮痧（カッサ） 50分';
    $description = '伝統的な中国の刮痧（かっさ）技術を用いたトリートメント（50分コース）';
    $duration = 50;
    $price = 5800;
    $stmt->execute();
    
    // 11. プレミアムローズヒップオイルコース
    $name = 'プレミアムローズヒップオイルコース 60分';
    $description = 'アンチエイジング効果のあるローズヒップオイルとホホバオイルを使用した贅沢なトリートメント（60分）';
    $duration = 60;
    $price = 18500;
    $stmt->execute();
    
    $name = 'プレミアムローズヒップオイルコース 90分';
    $description = 'アンチエイジング効果のあるローズヒップオイルとホホバオイルを使用した贅沢なトリートメント（90分）';
    $duration = 90;
    $price = 25800;
    $stmt->execute();
    
    // 12. トレーナー特別施術メニュー
    $name = 'カッサ＆オイルリンパデトックス 100分';
    $description = 'トレーナーによる特別施術。カッサ技術と特殊なオイルを用いたリンパデトックスで末梢循環促進・デトックス効果・深部筋肉のコリ緩和などの効果（100分）';
    $duration = 100;
    $price = 25800;
    $stmt->execute();

    // コミット
    $conn->commit();
    echo '<div style="background-color: #d4edda; color: #155724; padding: 15px; margin: 20px; border-radius: 5px; border: 1px solid #c3e6cb;">';
    echo '<h2>成功！</h2>';
    echo '<p>すべてのサービスメニューがデータベースに追加されました。</p>';
    echo '<p><a href="services.php" style="color: #155724; text-decoration: underline;">サービス管理画面に戻る</a></p>';
    echo '</div>';
    
} catch (PDOException $e) {
    // エラー時はロールバック
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px; border: 1px solid #f5c6cb;">';
    echo '<h2>エラー</h2>';
    echo '<p>サービスの追加中にエラーが発生しました：' . $e->getMessage() . '</p>';
    echo '<p><a href="services.php" style="color: #721c24; text-decoration: underline;">サービス管理画面に戻る</a></p>';
    echo '</div>';
}
?> 