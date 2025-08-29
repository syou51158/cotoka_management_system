<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    // Supabaseデータベース接続
    $database = new Database();
    $pdo = $database->getConnection();
    
    // デモ顧客データの挿入
    $customers = [
        ['田中', '一郎', 'tanaka@example.com', '090-1234-5678', '東京都新宿区〇〇1-2-3', '1985-06-15'],
        ['佐藤', '美咲', 'sato@example.com', '090-8765-4321', '東京都渋谷区〇〇4-5-6', '1990-11-23'],
        ['鈴木', '健太', 'suzuki@example.com', '090-2468-1357', '東京都中野区〇〇7-8-9', '1978-03-08'],
        ['高橋', '由美', 'takahashi@example.com', '090-1357-2468', '東京都杉並区〇〇10-11-12', '1995-09-30'],
        ['渡辺', '大輔', 'watanabe@example.com', '090-3698-5214', '東京都世田谷区〇〇13-14-15', '1982-12-05']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO customers (salon_id, tenant_id, last_name, first_name, email, phone, address, birthday, created_at) 
                          VALUES (1, 1, ?, ?, ?, ?, ?, ?, NOW())");
    
    foreach ($customers as $customer) {
        $stmt->execute([
            $customer[0], // last_name
            $customer[1], // first_name
            $customer[2], // email
            $customer[3], // phone
            $customer[4], // address
            $customer[5]  // birthday
        ]);
    }
    
    echo "デモ顧客データを5件追加しました。<br>";
    
    // 今日の予約を2件追加
    $today = date('Y-m-d');
    $appointmentsToday = [
        [1, 1, 1, '10:00:00', '10:30:00', 'scheduled'],
        [2, 2, 2, '14:00:00', '15:00:00', 'scheduled']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO appointments (salon_id, tenant_id, customer_id, staff_id, service_id, appointment_date, start_time, end_time, status, created_at) 
                          VALUES (1, 1, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    foreach ($appointmentsToday as $appointment) {
        $stmt->execute([
            $appointment[0], // customer_id
            $appointment[1], // staff_id
            $appointment[2], // service_id
            $today,
            $appointment[3], // start_time
            $appointment[4], // end_time
            $appointment[5]  // status
        ]);
        
        // 支払いを半分の予約に追加
        if (rand(0, 1) == 1) {
            $appointmentId = $pdo->lastInsertId();
            // サービスの価格を取得
            $servicePrice = $pdo->query("SELECT price FROM services WHERE service_id = " . $appointment[2])->fetchColumn();
            
            $paymentMethod = ['cash', 'credit_card', 'debit_card', 'transfer', 'other'][rand(0, 4)];
            $pdo->exec("INSERT INTO payments (salon_id, tenant_id, appointment_id, amount, payment_method, payment_date) 
                       VALUES (1, 1, $appointmentId, $servicePrice, '$paymentMethod', NOW())");
        }
    }
    
    echo "今日の予約を2件追加しました。<br>";
    
    // 過去の予約を4件追加（完了済み）
    $pastDates = [
        date('Y-m-d', strtotime('-7 days')),
        date('Y-m-d', strtotime('-14 days')),
        date('Y-m-d', strtotime('-21 days')),
        date('Y-m-d', strtotime('-28 days'))
    ];
    
    $pastAppointments = [
        [3, 3, 3, '11:00:00', '12:30:00', 'completed'],
        [4, 2, 4, '13:00:00', '13:45:00', 'completed'],
        [5, 1, 5, '16:00:00', '16:30:00', 'completed'],
        [1, 4, 2, '15:00:00', '16:00:00', 'completed']
    ];
    
    for ($i = 0; $i < 4; $i++) {
        $stmt->execute([
            $pastAppointments[$i][0], // customer_id
            $pastAppointments[$i][1], // staff_id
            $pastAppointments[$i][2], // service_id
            $pastDates[$i],
            $pastAppointments[$i][3], // start_time
            $pastAppointments[$i][4], // end_time
            $pastAppointments[$i][5]  // status
        ]);
        
        $appointmentId = $pdo->lastInsertId();
        // サービスの価格を取得
        $servicePrice = $pdo->query("SELECT price FROM services WHERE service_id = " . $pastAppointments[$i][2])->fetchColumn();
        
        $paymentMethod = ['cash', 'credit_card', 'debit_card', 'transfer', 'other'][rand(0, 4)];
        $pdo->exec("INSERT INTO payments (salon_id, tenant_id, appointment_id, amount, payment_method, payment_date) 
                   VALUES (1, 1, $appointmentId, $servicePrice, '$paymentMethod', '" . $pastDates[$i] . "')");
    }
    
    echo "過去の予約と支払いを4件追加しました。<br>";
    
    // 将来の予約を3件追加
    $futureDates = [
        date('Y-m-d', strtotime('+3 days')),
        date('Y-m-d', strtotime('+7 days')),
        date('Y-m-d', strtotime('+14 days'))
    ];
    
    $futureAppointments = [
        [2, 1, 2, '10:00:00', '11:00:00', 'scheduled'],
        [3, 4, 5, '15:30:00', '16:00:00', 'scheduled'],
        [4, 3, 3, '13:00:00', '14:30:00', 'scheduled']
    ];
    
    for ($i = 0; $i < 3; $i++) {
        $stmt->execute([
            $futureAppointments[$i][0], // customer_id
            $futureAppointments[$i][1], // staff_id
            $futureAppointments[$i][2], // service_id
            $futureDates[$i],
            $futureAppointments[$i][3], // start_time
            $futureAppointments[$i][4], // end_time
            $futureAppointments[$i][5]  // status
        ]);
    }
    
    echo "将来の予約を3件追加しました。<br>";
    
    echo "<br>デモ顧客データのセットアップが完了しました！<br>";
    echo "<a href='../login.php'>ログインページに戻る</a>";
    
} catch (PDOException $e) {
    die("データベースエラー: " . $e->getMessage());
}