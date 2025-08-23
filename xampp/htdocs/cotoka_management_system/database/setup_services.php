<?php
require_once __DIR__ . '/../config/config.php';

try {
    // データベース接続
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // デモサービスデータの挿入
    $services = [
        ['カット', '基本的なヘアカットサービスです', 4000, 30],
        ['カラー', 'ヘアカラーリングサービスです', 8000, 60],
        ['パーマ', 'パーマネントウェーブをかけるサービスです', 12000, 90],
        ['トリートメント', '髪の毛の保湿・補修トリートメントです', 6000, 45],
        ['ヘッドスパ', '頭皮マッサージとスカルプケアです', 5000, 30]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO services (salon_id, tenant_id, name, description, price, duration, created_at) 
                          VALUES (1, 1, ?, ?, ?, ?, NOW())");
    
    foreach ($services as $service) {
        $stmt->execute([
            $service[0], // name
            $service[1], // description
            $service[2], // price
            $service[3]  // duration
        ]);
    }
    
    echo "デモサービスデータを5件追加しました。<br>";
    
    // デモスタッフデータの挿入
    $staff = [
        ['山田', '太郎', 'yamada@example.com', '090-1234-5678', 'スタイリスト', '2010-04-01'],
        ['佐々木', '美紀', 'sasaki@example.com', '090-8765-4321', 'シニアスタイリスト', '2008-06-15'],
        ['中村', '健太', 'nakamura@example.com', '090-2468-1357', 'カラーリスト', '2015-09-10'],
        ['木村', '優子', 'kimura@example.com', '090-1357-2468', 'アシスタント', '2020-03-20']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO staff (salon_id, tenant_id, first_name, last_name, email, phone, position, hire_date, created_at) 
                          VALUES (1, 1, ?, ?, ?, ?, ?, ?, NOW())");
    
    foreach ($staff as $person) {
        $stmt->execute([
            $person[1], // first_name
            $person[0], // last_name
            $person[2], // email
            $person[3], // phone
            $person[4], // position
            $person[5]  // hire_date
        ]);
    }
    
    echo "デモスタッフデータを4件追加しました。<br>";
    
    // スタッフとサービスの関連付け（誰がどのサービスを提供できるか）
    $staffServices = [
        [1, 1], // 山田：カット
        [1, 4], // 山田：トリートメント
        [2, 1], // 佐々木：カット
        [2, 2], // 佐々木：カラー
        [2, 3], // 佐々木：パーマ
        [3, 2], // 中村：カラー
        [3, 4], // 中村：トリートメント
        [4, 4], // 木村：トリートメント
        [4, 5]  // 木村：ヘッドスパ
    ];
    
    $stmt = $pdo->prepare("INSERT INTO staff_services (salon_id, tenant_id, staff_id, service_id) 
                          VALUES (1, 1, ?, ?)");
    
    foreach ($staffServices as $relation) {
        $stmt->execute([
            $relation[0], // staff_id
            $relation[1]  // service_id
        ]);
    }
    
    echo "スタッフとサービスの関連データを9件追加しました。<br>";
    
    // スタッフの稼働時間設定
    $workDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $workHours = [
        [1, '09:00:00', '18:00:00'],
        [2, '10:00:00', '19:00:00'],
        [3, '11:00:00', '20:00:00'],
        [4, '09:00:00', '17:00:00']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO staff_availability (salon_id, tenant_id, staff_id, day_of_week, start_time, end_time) 
                          VALUES (1, 1, ?, ?, ?, ?)");
    
    foreach ($workHours as $hours) {
        foreach ($workDays as $day) {
            // スタッフID 1（山田）は土曜日休み
            if ($hours[0] == 1 && $day == 'Saturday') continue;
            
            // スタッフID 3（中村）は月曜日休み
            if ($hours[0] == 3 && $day == 'Monday') continue;
            
            $stmt->execute([
                $hours[0], // staff_id
                $day,      // day_of_week
                $hours[1], // start_time
                $hours[2]  // end_time
            ]);
        }
    }
    
    echo "スタッフの稼働時間データを追加しました。<br>";
    
    echo "<br>サービスとスタッフのセットアップが完了しました！<br>";
    echo "<a href='../login.php'>ログインページに戻る</a>";
    
} catch (PDOException $e) {
    die("データベースエラー: " . $e->getMessage());
} 