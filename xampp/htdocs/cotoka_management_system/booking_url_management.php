<?php
// 共通の設定ファイルを読み込む
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/role_permissions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

// ページタイトル
$page_title = '予約URL管理';

// データベース接続
$db = new Database();
$conn = $db->getConnection();

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 権限チェック（マネージャー権限以上が必要）
if (!userHasPermission('settings', 'edit')) {
    header('Location: dashboard.php');
    exit;
}

// サロンIDをGETパラメータから取得し、セッションを更新
if (isset($_GET['salon_id'])) {
    $salon_id = intval($_GET['salon_id']);
    
    // セッション変数を更新
    $_SESSION['current_salon_id'] = $salon_id;
    $_SESSION['salon_id'] = $salon_id;
    
    error_log("booking_url_management: サロンIDをセッションに設定: " . $salon_id);
} else {
    $salon_id = getCurrentSalonId();
}

error_log("booking_url_management: サロンID = " . $salon_id);

// フォーム送信時の処理
$success_message = '';
$error_message = '';

// 現在のサロン情報を取得
try {
    // サロンIDを明示的に指定して取得 - シンプルに修正
    $stmt = $conn->prepare("SELECT * FROM salons WHERE salon_id = ? LIMIT 1");
    $stmt->bindValue(1, $salon_id, PDO::PARAM_INT);
    $stmt->execute();
    $salon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$salon) {
        $error_message = '指定されたサロン情報が見つかりません。サロンID: ' . $salon_id;
        error_log("booking_url_management: サロン情報が見つかりません。サロンID: " . $salon_id);
    }
    else {
        error_log("booking_url_management: サロン情報取得成功 - サロンID: " . $salon['salon_id'] . ", 名前: " . $salon['name']);
        
        // サロンIDが一致しない場合は深刻なエラー
        if ($salon['salon_id'] != $salon_id) {
            $error_message = '【重大なエラー】取得したサロン情報(ID:' . $salon['salon_id'] . ')が要求されたサロン(ID:' . $salon_id . ')と一致しません。';
            error_log("booking_url_management: 重大なエラー - 取得ID不一致: " . $salon['salon_id'] . " != " . $salon_id);
            
            // 正しいサロン情報を強制的に再取得
            $stmt = $conn->prepare("SELECT * FROM salons WHERE salon_id = ? LIMIT 1");
            $stmt->bindValue(1, $salon_id, PDO::PARAM_INT);
            $stmt->execute();
            $salon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($salon) {
                error_log("booking_url_management: サロン情報を再取得しました - サロンID: " . $salon['salon_id']);
                $error_message .= ' 情報を再取得しました。';
            }
        }
    }
} catch (Exception $e) {
    $error_message = 'サロン情報の取得中にエラーが発生しました: ' . $e->getMessage();
    error_log("booking_url_management: サロン情報取得エラー: " . $e->getMessage());
}

// 予約ソース情報を確実に取得
try {
    $stmt = $conn->prepare("
        SELECT * FROM booking_sources
        WHERE salon_id = ?
        ORDER BY source_name
    ");
    $stmt->bindValue(1, $salon_id, PDO::PARAM_INT);
    $stmt->execute();
    $booking_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("booking_url_management: 予約ソース取得 - サロンID: " . $salon_id . ", 件数: " . count($booking_sources));
} catch (Exception $e) {
    $error_message = '予約ソース情報の取得中にエラーが発生しました: ' . $e->getMessage();
    error_log("booking_url_management: 予約ソース取得エラー: " . $e->getMessage());
}

// URLスラッグ更新の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_url_slug') {
    $new_url_slug = trim($_POST['url_slug']);
    $info_message = '';
    
    // URLスラッグのバリデーション
    if (empty($new_url_slug)) {
        // 空欄の場合は自動生成
        $temp_name = $salon['name'];
        
        // 基本的な変換マッピング
        $japanese_to_english = [
            'サロン' => 'salon',
            '店' => 'ten',
            'デモ' => 'demo',
            'テスト' => 'test',
            '新宿' => 'shinjuku', 
            '渋谷' => 'shibuya',
            '銀座' => 'ginza',
            '池袋' => 'ikebukuro',
            '原宿' => 'harajuku',
            '表参道' => 'omotesando',
            '東京' => 'tokyo',
            '大阪' => 'osaka',
            '京都' => 'kyoto',
            '名古屋' => 'nagoya',
            '福岡' => 'fukuoka',
            '札幌' => 'sapporo',
            '仙台' => 'sendai',
            '横浜' => 'yokohama',
            '代々木' => 'yoyogi',
            '六本木' => 'roppongi',
            '代官山' => 'daikanyama',
            '恵比寿' => 'ebisu',
            '中野' => 'nakano',
            '吉祥寺' => 'kichijoji',
            '三軒茶屋' => 'sangenjaya',
            '下北沢' => 'shimokitazawa',
        ];
        
        // 日本語単語を英語に置換する
        foreach ($japanese_to_english as $ja => $en) {
            $temp_name = str_replace($ja, $en, $temp_name);
        }
        
        // 全ての文字を小文字にして、英数字以外をハイフンに変換
        $romanized_name = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $temp_name));
        // 先頭と末尾のハイフンを削除
        $romanized_name = trim($romanized_name, '-');
        
        // 日本語文字が残っている可能性があるため、英数字のみで構成されているか確認
        if (empty($romanized_name) || $romanized_name == '-' || !preg_match('/^[a-z0-9\-]+$/', $romanized_name)) {
            // 英数字に変換できない場合は、サロンの種類と番号で汎用的なスラッグを作成
            $romanized_name = 'salon-' . $salon_id;
        }
        
        // ランダムな数字を追加
        $new_url_slug = $romanized_name . '-' . rand(100, 999);
        error_log("booking_url_management: URLスラッグを自動生成しました: " . $new_url_slug);
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $new_url_slug)) {
        // 入力されたスラッグに日本語などの無効な文字が含まれている場合は自動変換を試みる
        $temp_slug = $new_url_slug;
        
        // 基本的な変換マッピング（上記と同じ）
        $japanese_to_english = [
            'サロン' => 'salon',
            '店' => 'ten',
            'デモ' => 'demo',
            'テスト' => 'test',
            '新宿' => 'shinjuku', 
            '渋谷' => 'shibuya',
            '銀座' => 'ginza',
            '池袋' => 'ikebukuro',
            '原宿' => 'harajuku',
            '表参道' => 'omotesando',
            '東京' => 'tokyo',
            '大阪' => 'osaka',
            '京都' => 'kyoto',
            '名古屋' => 'nagoya',
            '福岡' => 'fukuoka',
            '札幌' => 'sapporo',
            '仙台' => 'sendai',
            '横浜' => 'yokohama',
            '代々木' => 'yoyogi',
            '六本木' => 'roppongi',
            '代官山' => 'daikanyama',
            '恵比寿' => 'ebisu',
            '中野' => 'nakano',
            '吉祥寺' => 'kichijoji',
            '三軒茶屋' => 'sangenjaya',
            '下北沢' => 'shimokitazawa',
        ];
        
        // 日本語単語を英語に置換する
        foreach ($japanese_to_english as $ja => $en) {
            $temp_slug = str_replace($ja, $en, $temp_slug);
        }
        
        // 全ての文字を小文字にして、英数字以外をハイフンに変換
        $converted_slug = strtolower(preg_replace('/[^a-z0-9\-]+/i', '-', $temp_slug));
        // 先頭と末尾のハイフンを削除
        $converted_slug = trim($converted_slug, '-');
        
        // 変換後も英数字のみで構成されているか確認
        if (empty($converted_slug) || $converted_slug == '-' || !preg_match('/^[a-z0-9\-]+$/', $converted_slug)) {
            // それでも変換できなければ、汎用的なスラッグを設定
            $converted_slug = 'salon-' . $salon_id . '-' . rand(100, 999);
            $info_message = '入力されたURLスラッグを変換できなかったため、汎用的なスラッグ "' . $converted_slug . '" を設定しました。';
        } else {
            $info_message = '入力されたURLスラッグに無効な文字が含まれていたため、自動的に "' . $converted_slug . '" に変換しました。';
        }
        
        $new_url_slug = $converted_slug;
        error_log("booking_url_management: URLスラッグを自動変換しました: " . $new_url_slug);
    } else {
        // 処理を続行
    }
    
    if (empty($error_message)) {
        try {
            // 既に同じスラッグが存在するか確認
            $stmt = $conn->prepare("SELECT salon_id FROM salons WHERE url_slug = ? AND salon_id != ?");
            $stmt->execute([$new_url_slug, $salon_id]);
            
            if ($stmt->rowCount() > 0) {
                // 重複したスラッグが見つかった場合の詳細なエラーメッセージ
                $duplicate_salon = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt_name = $conn->prepare("SELECT name FROM salons WHERE salon_id = ?");
                $stmt_name->execute([$duplicate_salon['salon_id']]);
                $duplicate_name = $stmt_name->fetchColumn();
                
                $error_message = 'このURLスラッグは既に「' . $duplicate_name . '」(ID: ' . $duplicate_salon['salon_id'] . ')で使用されています。別のスラッグを入力してください。';
                error_log("booking_url_management: 重複スラッグ - " . $new_url_slug . " は既にサロンID " . $duplicate_salon['salon_id'] . " で使用されています");
            } else {
                // URLスラッグを更新
                $stmt = $conn->prepare("UPDATE salons SET url_slug = ? WHERE salon_id = ?");
                $stmt->execute([$new_url_slug, $salon_id]);
                
                $success_message = 'URLスラッグを更新しました: ' . $new_url_slug;
                if (!empty($info_message)) {
                    $success_message .= '<br>' . $info_message;
                }
                error_log("booking_url_management: URLスラッグを更新しました: " . $new_url_slug . " (サロンID: " . $salon_id . ")");
                
                // サロン情報を再取得
                $stmt = $conn->prepare("SELECT * FROM salons WHERE salon_id = ?");
                $stmt->execute([$salon_id]);
                $salon = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$salon || $salon['salon_id'] != $salon_id) {
                    error_log("booking_url_management: 警告 - 更新後のサロン情報取得エラー");
                    // 再取得を試みる
                    $stmt = $conn->prepare("SELECT * FROM salons WHERE salon_id = ? LIMIT 1");
                    $stmt->execute([$salon_id]);
                    $salon = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                // 各予約ソースのトラッキングURLも更新
                $stmt = $conn->prepare("
                    SELECT source_id, source_code FROM booking_sources 
                    WHERE salon_id = ?
                ");
                $stmt->execute([$salon_id]);
                $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($sources as $source) {
                    // URLパスの修正
                    $base_path = '';
                    $protocol = 'https://';
                    
                    // ローカル環境かどうかを判定
                    if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                        $base_path = '/cotoka_management_system';
                        $protocol = 'http://';
                    }
                    
                    $tracking_url = "{$base_path}/public_booking/{$new_url_slug}?source={$source['source_code']}";
                    $update = $conn->prepare("UPDATE booking_sources SET tracking_url = ? WHERE source_id = ?");
                    $update->execute([$tracking_url, $source['source_id']]);
                }
                
                error_log("booking_url_management: 予約ソースのトラッキングURLも更新しました");
            }
        } catch (Exception $e) {
            $error_message = 'URLスラッグの更新中にエラーが発生しました: ' . $e->getMessage();
            error_log("booking_url_management: URLスラッグ更新エラー: " . $e->getMessage());
        }
    }
}

// 予約ソース追加の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_source') {
    $source_name = trim($_POST['source_name']);
    $source_code = trim($_POST['source_code']);
    
    // バリデーション
    if (empty($source_name) || empty($source_code)) {
        $error_message = '予約ソース名とソースコードを入力してください。';
    } elseif (!preg_match('/^[a-z0-9\-_]+$/', $source_code)) {
        $error_message = 'ソースコードは半角英数字、ハイフン、アンダースコアのみ使用できます。';
    } else {
        try {
            // 既に同じソースコードが存在するか確認
            $stmt = $conn->prepare("SELECT source_id FROM booking_sources WHERE salon_id = ? AND source_code = ?");
            $stmt->execute([$salon_id, $source_code]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'このソースコードは既に使用されています。別のコードを入力してください。';
            } else {
                // 新しい予約ソースを追加
                $base_path = '';
                $protocol = 'https://';
                
                // ローカル環境かどうかを判定
                if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                    $base_path = '/cotoka_management_system';
                    $protocol = 'http://';
                }
                
                $tracking_url = "{$base_path}/public_booking/index.php?salon_id={$salon_id}&source={$source_code}";
                if (!empty($salon['url_slug'])) {
                    $tracking_url = "{$base_path}/public_booking/{$salon['url_slug']}?source={$source_code}";
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO booking_sources (salon_id, source_name, source_code, tracking_url)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$salon_id, $source_name, $source_code, $tracking_url]);
                
                $success_message = '予約ソースを追加しました。';
                
                // 予約ソース情報を再取得
                $stmt = $conn->prepare("
                    SELECT * FROM booking_sources
                    WHERE salon_id = ?
                    ORDER BY source_name
                ");
                $stmt->execute([$salon_id]);
                $booking_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error_message = '予約ソースの追加中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 予約ソース削除の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_source') {
    $source_id = (int)$_POST['source_id'];
    
    try {
        // 予約ソースが存在するか確認
        $stmt = $conn->prepare("
            SELECT * FROM booking_sources 
            WHERE source_id = ? AND salon_id = ?
        ");
        $stmt->execute([$source_id, $salon_id]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$source) {
            $error_message = '指定された予約ソースが見つかりません。';
        } else {
            // デフォルトの予約ソースとして設定されているか確認
            $stmt = $conn->prepare("
                SELECT default_booking_source_id 
                FROM salons 
                WHERE salon_id = ? AND default_booking_source_id = ?
            ");
            $stmt->execute([$salon_id, $source_id]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'このソースはデフォルトに設定されているため削除できません。別のソースをデフォルトに設定してから削除してください。';
            } else {
                // 予約ソースを削除
                $stmt = $conn->prepare("DELETE FROM booking_sources WHERE source_id = ? AND salon_id = ?");
                $stmt->execute([$source_id, $salon_id]);
                
                $success_message = '予約ソースを削除しました。';
                
                // 予約ソース情報を再取得
                $stmt = $conn->prepare("
                    SELECT * FROM booking_sources
                    WHERE salon_id = ?
                    ORDER BY source_name
                ");
                $stmt->execute([$salon_id]);
                $booking_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        $error_message = '予約ソースの削除中にエラーが発生しました: ' . $e->getMessage();
    }
}

// デフォルト予約ソース設定の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_default_source') {
    $source_id = (int)$_POST['source_id'];
    
    try {
        // 予約ソースが存在するか確認
        $stmt = $conn->prepare("
            SELECT * FROM booking_sources 
            WHERE source_id = ? AND salon_id = ?
        ");
        $stmt->execute([$source_id, $salon_id]);
        
        if ($stmt->rowCount() === 0) {
            $error_message = '指定された予約ソースが見つかりません。';
        } else {
            // デフォルト予約ソースを設定
            $stmt = $conn->prepare("UPDATE salons SET default_booking_source_id = ? WHERE salon_id = ?");
            $stmt->execute([$source_id, $salon_id]);
            
            $success_message = 'デフォルト予約ソースを設定しました。';
            
            // サロン情報を再取得
            $stmt = $conn->prepare("
                SELECT s.* 
                FROM salons s
                WHERE s.salon_id = ?
            ");
            $stmt->execute([$salon_id]);
            $salon = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $error_message = 'デフォルト予約ソースの設定中にエラーが発生しました: ' . $e->getMessage();
    }
}

// 予約ソースの更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_source') {
    $source_id = (int)$_POST['source_id'];
    $source_name = trim($_POST['source_name']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // バリデーション
    if (empty($source_name)) {
        $error_message = '予約ソース名を入力してください。';
    } else {
        try {
            // 予約ソースが存在するか確認
            $stmt = $conn->prepare("
                SELECT * FROM booking_sources 
                WHERE source_id = ? AND salon_id = ?
            ");
            $stmt->execute([$source_id, $salon_id]);
            
            if ($stmt->rowCount() === 0) {
                $error_message = '指定された予約ソースが見つかりません。';
            } else {
                // 予約ソースを更新
                $stmt = $conn->prepare("
                    UPDATE booking_sources 
                    SET source_name = ?, is_active = ? 
                    WHERE source_id = ? AND salon_id = ?
                ");
                $stmt->execute([$source_name, $is_active, $source_id, $salon_id]);
                
                $success_message = '予約ソースを更新しました。';
                
                // 予約ソース情報を再取得
                $stmt = $conn->prepare("
                    SELECT * FROM booking_sources
                    WHERE salon_id = ?
                    ORDER BY source_name
                ");
                $stmt->execute([$salon_id]);
                $booking_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $error_message = '予約ソースの更新中にエラーが発生しました: ' . $e->getMessage();
        }
    }
}

// 予約数統計の取得
try {
    $stmt = $conn->prepare("
        SELECT bs.source_id, bs.source_name, bs.source_code, 
               COUNT(a.appointment_id) as total_bookings
        FROM booking_sources bs
        LEFT JOIN appointments a ON bs.source_id = a.booking_source_id
        WHERE bs.salon_id = ?
        GROUP BY bs.source_id, bs.source_name, bs.source_code
        ORDER BY total_bookings DESC
    ");
    $stmt->execute([$salon_id]);
    $booking_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message .= '予約統計の取得中にエラーが発生しました: ' . $e->getMessage();
}

// ヘッダーとナビゲーションを含める
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <!-- ClipboardJSを追加 -->
                <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>
                
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">予約URL管理 (サロンID: <?php echo $salon_id; ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <!-- サロン切り替えフォーム -->
                    <div class="mb-4">
                        <form method="get" class="form-inline">
                            <div class="form-group mr-2">
                                <label for="salon_id" class="mr-2">サロン選択:</label>
                                <select name="salon_id" id="salon_id" class="form-control">
                                    <?php 
                                    // サロン一覧を取得
                                    $stmt = $conn->prepare("SELECT salon_id, name FROM salons ORDER BY name");
                                    $stmt->execute();
                                    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($salons as $s) {
                                        $selected = $s['salon_id'] == $salon_id ? 'selected' : '';
                                        echo "<option value=\"{$s['salon_id']}\" {$selected}>{$s['name']} (ID: {$s['salon_id']})</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">サロン切替</button>
                            
                            <!-- デバッグ情報 -->
                            <div class="small text-muted ml-3">
                                セッションサロンID: <?php echo getCurrentSalonId(); ?><br>
                                現在表示中のサロンID: <?php echo $salon_id; ?><br>
                                表示中サロン名: <?php echo htmlspecialchars($salon['name'] ?? 'Unknown'); ?><br>
                                URL: <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>
                            </div>
                        </form>
                    </div>
                    
                    <!-- タブナビゲーション - 完全に書き直し -->
                    <ul class="nav nav-tabs mb-3" id="nav-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="nav-url-tab" data-bs-toggle="tab" data-bs-target="#nav-url" type="button" role="tab" aria-controls="nav-url" aria-selected="true">URLスラッグ設定</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="nav-sources-tab" data-bs-toggle="tab" data-bs-target="#nav-sources" type="button" role="tab" aria-controls="nav-sources" aria-selected="false">予約ソース管理</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="nav-stats-tab" data-bs-toggle="tab" data-bs-target="#nav-stats" type="button" role="tab" aria-controls="nav-stats" aria-selected="false">予約統計</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="nav-tabContent">
                        <!-- URLスラッグ設定タブ -->
                        <div class="tab-pane fade show active" id="nav-url" role="tabpanel" aria-labelledby="nav-url-tab">
                            <div class="p-3">
                                <h4>サロンURLスラッグ設定</h4>
                                <p>サロンの予約ページのURLに使用するスラッグを設定します。<br>
                                スラッグは半角英数字とハイフン(-)のみ使用できます。</p>
                                
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <?php
                                        // 表示直前に最終確認 - 正しいサロン情報を確実に取得する
                                        try {
                                            $stmt = $conn->prepare("SELECT * FROM salons WHERE salon_id = ? LIMIT 1");
                                            $stmt->execute([$salon_id]);
                                            $salon = $stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if (!$salon) {
                                                error_log("booking_url_management: 最終確認 - サロンID {$salon_id} の情報が取得できませんでした");
                                            } else {
                                                error_log("booking_url_management: 最終確認 - サロンID {$salon_id} の情報を取得: 名前={$salon['name']}");
                                            }
                                        } catch (Exception $e) {
                                            error_log("booking_url_management: 最終確認 - サロン情報取得エラー: " . $e->getMessage());
                                        }
                                        ?>
                                        <h5>現在のサロン情報</h5>
                                        <p><strong>サロンID:</strong> <?php echo $salon_id; ?> (表示中のサロンID)</p>
                                        <p><strong>サロン名:</strong> <?php echo htmlspecialchars($salon['name'] ?? '取得できませんでした'); ?></p>
                                        <p><strong>取得されたデータのサロンID:</strong> <?php echo $salon['salon_id'] ?? '不明'; ?></p>
                                        <p><strong>現在のURLスラッグ:</strong> <?php echo htmlspecialchars($salon['url_slug'] ?? '未設定'); ?></p>
                                        
                                        <?php if (!$salon || $salon['salon_id'] != $salon_id): ?>
                                        <div class="alert alert-danger">
                                            <strong>エラー:</strong> 表示されているサロン情報が要求したサロンIDと一致しません。
                                            管理者に連絡してください。
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($salon['url_slug'])): ?>
                                        <div class="form-group">
                                            <label>予約ページURL:</label>
                                            <div class="input-group">
                                                <?php
                                                $base_path = '';
                                                $protocol = 'https://';
                                                
                                                // ローカル環境かどうかを判定
                                                if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                                                    $base_path = '/cotoka_management_system';
                                                    $protocol = 'http://';
                                                }
                                                
                                                $booking_url = "{$protocol}{$_SERVER['HTTP_HOST']}{$base_path}/public_booking/{$salon['url_slug']}";
                                                ?>
                                                <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($booking_url); ?>">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary copy-url-btn" type="button" data-clipboard-text="<?php echo htmlspecialchars($booking_url); ?>">
                                                        <i class="fas fa-copy"></i> コピー
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <form method="post" class="mt-4">
                                            <input type="hidden" name="action" value="update_url_slug">
                                            <div class="form-group">
                                                <label for="url_slug">新しいURLスラッグ:</label>
                                                <?php
                                                // URLスラッグの例を生成
                                                $temp_name = $salon['name'];
                                                
                                                // 基本的な変換マッピング
                                                $japanese_to_english = [
                                                    'サロン' => 'salon',
                                                    '店' => 'ten',
                                                    'デモ' => 'demo',
                                                    'テスト' => 'test',
                                                    '新宿' => 'shinjuku', 
                                                    '渋谷' => 'shibuya',
                                                    '銀座' => 'ginza',
                                                    '池袋' => 'ikebukuro',
                                                    '原宿' => 'harajuku',
                                                    '表参道' => 'omotesando',
                                                    '東京' => 'tokyo',
                                                    '大阪' => 'osaka',
                                                    '京都' => 'kyoto',
                                                    '名古屋' => 'nagoya',
                                                    '福岡' => 'fukuoka',
                                                    '札幌' => 'sapporo',
                                                    '仙台' => 'sendai',
                                                    '横浜' => 'yokohama',
                                                ];
                                                
                                                // 日本語単語を英語に置換する
                                                foreach ($japanese_to_english as $ja => $en) {
                                                    $temp_name = str_replace($ja, $en, $temp_name);
                                                }
                                                
                                                $romanized_example = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $temp_name));
                                                $romanized_example = trim($romanized_example, '-');
                                                
                                                // 変換後も日本語が残っている可能性があるため確認
                                                if (empty($romanized_example) || $romanized_example == '-' || !preg_match('/^[a-z0-9\-]+$/', $romanized_example)) {
                                                    $romanized_example = 'salon-' . $salon_id;
                                                }
                                                
                                                $example_slug = $romanized_example . '-' . rand(100, 999);
                                                ?>
                                                <input type="text" class="form-control" id="url_slug" name="url_slug" value="<?php echo htmlspecialchars($salon['url_slug'] ?? ''); ?>" placeholder="例: <?php echo $example_slug; ?>" pattern="[a-z0-9\-]+" required>
                                                <small class="form-text text-muted">半角英小文字、数字、ハイフン(-)のみ使用できます。空欄の場合はサロン名をもとに自動生成されます。<br>※日本語文字は使えません。英語またはローマ字を使用してください。<br>※日本語を入力した場合は自動的に変換または汎用的なスラッグが生成されます。</small>
                                            </div>
                                            <button type="submit" class="btn btn-primary">URLスラッグを更新</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 予約ソース管理タブ -->
                        <div class="tab-pane fade" id="nav-sources" role="tabpanel" aria-labelledby="nav-sources-tab">
                            <div class="p-3">
                                <h4>予約ソース管理</h4>
                                <p>予約の流入元を管理します。SNS、広告、紹介など、集客源ごとに予約URLを発行できます。</p>
                                
                                <?php if (empty($salon['url_slug'])): ?>
                                <div class="alert alert-warning">
                                    <strong><i class="fas fa-exclamation-triangle"></i> 注意:</strong> URLスラッグが設定されていません。
                                    <p>有効な予約URLを生成するには、先に「URLスラッグ設定」タブでURLスラッグを設定してください。</p>
                                    <button class="btn btn-primary btn-sm mt-2" id="go-to-url-tab">URLスラッグ設定に移動</button>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (empty($booking_sources)): ?>
                                <div class="alert alert-info">予約ソースがまだ登録されていません。</div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ソース名</th>
                                                <th>ソースコード</th>
                                                <th>予約URL</th>
                                                <th>状態</th>
                                                <th>デフォルト</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($booking_sources as $source): ?>
                                            <tr>
                                                <td>
                                                    <form method="post" class="source-update-form">
                                                        <input type="hidden" name="action" value="update_source">
                                                        <input type="hidden" name="source_id" value="<?php echo $source['source_id']; ?>">
                                                        <input type="text" class="form-control" name="source_name" value="<?php echo htmlspecialchars($source['source_name']); ?>" required>
                                                </td>
                                                <td><?php echo htmlspecialchars($source['source_code']); ?></td>
                                                <td>
                                                    <div class="input-group">
                                                        <?php
                                                        $base_path = '';
                                                        $protocol = 'https://';
                                                        
                                                        // ローカル環境かどうかを判定
                                                        if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                                                            $base_path = '/cotoka_management_system';
                                                            $protocol = 'http://';
                                                        }
                                                        
                                                        if (!empty($salon['url_slug'])) {
                                                            $source_url = "{$protocol}{$_SERVER['HTTP_HOST']}{$base_path}/public_booking/{$salon['url_slug']}?source={$source['source_code']}";
                                                        } else {
                                                            $source_url = "URLスラッグが設定されていません。先にURLスラッグを設定してください。";
                                                        }
                                                        ?>
                                                        <input type="text" class="form-control form-control-sm" readonly value="<?php echo htmlspecialchars($source_url); ?>"
                                                            <?php if (empty($salon['url_slug'])): ?>style="color: #d9534f; background-color: #ffe6e6;"<?php endif; ?>>
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary btn-sm copy-url-btn" type="button" data-clipboard-text="<?php echo htmlspecialchars($source_url); ?>"
                                                                <?php if (empty($salon['url_slug'])): ?>disabled<?php endif; ?>>
                                                                <i class="fas fa-copy"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="is_active_<?php echo $source['source_id']; ?>" name="is_active" <?php echo $source['is_active'] ? 'checked' : ''; ?>>
                                                        <label class="custom-control-label" for="is_active_<?php echo $source['source_id']; ?>"><?php echo $source['is_active'] ? '有効' : '無効'; ?></label>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($salon['default_booking_source_id'] == $source['source_id']): ?>
                                                    <span class="badge badge-success"><i class="fas fa-check"></i> デフォルト</span>
                                                    <?php else: ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="set_default_source">
                                                        <input type="hidden" name="source_id" value="<?php echo $source['source_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">デフォルトに設定</button>
                                                    </form>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="submit" class="btn btn-sm btn-success source-update-btn">
                                                        <i class="fas fa-save"></i> 更新
                                                    </button>
                                                    </form>
                                                    
                                                    <?php if ($salon['default_booking_source_id'] != $source['source_id']): ?>
                                                    <form method="post" class="d-inline source-delete-form">
                                                        <input type="hidden" name="action" value="delete_source">
                                                        <input type="hidden" name="source_id" value="<?php echo $source['source_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger source-delete-btn">
                                                            <i class="fas fa-trash"></i> 削除
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                                
                                <hr class="mt-4">
                                
                                <h5>新しい予約ソースを追加</h5>
                                <form method="post">
                                    <input type="hidden" name="action" value="add_source">
                                    <div class="form-row">
                                        <div class="col-md-4 mb-3">
                                            <label for="source_name">ソース名:</label>
                                            <input type="text" class="form-control" id="source_name" name="source_name" required>
                                            <small class="form-text text-muted">例: Instagram, LINE, チラシなど</small>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="source_code">ソースコード:</label>
                                            <input type="text" class="form-control" id="source_code" name="source_code" pattern="[a-z0-9\-_]+" required>
                                            <small class="form-text text-muted">例: instagram, line, flyer など (半角英小文字、数字、ハイフン、アンダースコアのみ)</small>
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary">予約ソースを追加</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- 予約統計タブ -->
                        <div class="tab-pane fade" id="nav-stats" role="tabpanel" aria-labelledby="nav-stats-tab">
                            <div class="p-3">
                                <h4>予約ソース統計</h4>
                                <p>各予約ソースからの予約状況を確認できます。</p>
                                
                                <!-- デバッグ情報 -->
                                <?php if (isset($_GET['debug'])): ?>
                                <div class="alert alert-info">
                                    <p><strong>デバッグ情報:</strong></p>
                                    <p>サロンID: <?php echo $salon_id; ?></p>
                                    <p>統計件数: <?php echo count($booking_stats); ?></p>
                                    <pre><?php print_r(array_map(function($stat) { 
                                        return ['source' => $stat['source_name'], 'bookings' => $stat['total_bookings']]; 
                                    }, $booking_stats)); ?></pre>
                                    <p>統計データ取得SQL:</p>
                                    <pre>
SELECT bs.source_id, bs.source_name, bs.source_code, 
    COUNT(a.appointment_id) as total_bookings
FROM booking_sources bs
LEFT JOIN appointments a ON bs.source_id = a.booking_source_id
WHERE bs.salon_id = <?php echo $salon_id; ?>
GROUP BY bs.source_id, bs.source_name, bs.source_code
ORDER BY total_bookings DESC
                                    </pre>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>予約ソース</th>
                                                        <th>ソースコード</th>
                                                        <th>予約数</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($booking_stats)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center">予約データがありません</td>
                                                    </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($booking_stats as $stat): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($stat['source_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($stat['source_code']); ?></td>
                                                            <td><?php echo number_format($stat['total_bookings']); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // URLスラッグ設定タブに移動するボタンのクリックイベント
    $('#go-to-url-tab').on('click', function() {
        $('#nav-url-tab').tab('show');
    });
    
    // タブ切り替えの処理
    $('.nav-link').on('click', function() {
        $('.nav-link').removeClass('active');
        $('.tab-pane').removeClass('show active');
        
        $(this).addClass('active');
        $($(this).data('bs-target')).addClass('show active');
    });
    
    // Bootstrap 5のタブ機能を初期化
    var triggerTabList = [].slice.call(document.querySelectorAll('#nav-tab button'));
    triggerTabList.forEach(function(triggerEl) {
        triggerEl.addEventListener('click', function(event) {
            event.preventDefault();
            var tabTrigger = new bootstrap.Tab(triggerEl);
            tabTrigger.show();
        });
    });
    
    // クリップボードコピー機能の初期化
    try {
        var clipboard = new ClipboardJS('.copy-url-btn');
        
        clipboard.on('success', function(e) {
            // コピー成功時の処理
            var $button = $(e.trigger);
            var originalHtml = $button.html();
            
            $button.html('<i class="fas fa-check"></i> コピーしました');
            
            setTimeout(function() {
                $button.html(originalHtml);
            }, 2000);
            
            e.clearSelection();
        });
        
        clipboard.on('error', function(e) {
            // コピー失敗時の処理
            var $button = $(e.trigger);
            var originalHtml = $button.html();
            
            $button.html('<i class="fas fa-times"></i> エラー');
            console.error('コピーに失敗しました:', e);
            
            setTimeout(function() {
                $button.html(originalHtml);
            }, 2000);
        });
    } catch (error) {
        console.error('ClipboardJSの初期化に失敗しました:', error);
    }
    
    // フォーム送信時の確認
    $('.source-delete-form').on('submit', function(e) {
        if (!confirm('この予約ソースを削除してもよろしいですか？')) {
            e.preventDefault();
            return false;
        }
        return true;
    });
});
</script>

<style>
/* タブ関連の全スタイルを完全に書き直し */
.nav-tabs {
    border-bottom: 1px solid #dee2e6;
}

.nav-tabs .nav-item {
    margin-bottom: -1px;
}

.nav-tabs .nav-link {
    cursor: pointer !important;
    user-select: none !important;
    -webkit-user-select: none !important;
    -moz-user-select: none !important;
    -ms-user-select: none !important;
    border: 1px solid transparent;
    border-top-left-radius: 0.25rem;
    border-top-right-radius: 0.25rem;
    color: #495057;
    padding: 0.5rem 1rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
}

.nav-tabs .nav-link:hover, 
.nav-tabs .nav-link:focus {
    border-color: #e9ecef #e9ecef #dee2e6;
    background-color: rgba(78, 115, 223, 0.05);
    color: #495057;
}

.nav-tabs .nav-link.active {
    color: #4e73df;
    font-weight: bold;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
    border-bottom: 2px solid #4e73df;
}

/* タブコンテンツのスタイル */
.tab-content > .tab-pane {
    display: none;
}

.tab-content > .active {
    display: block;
}

/* コピーボタンのホバー効果改善 */
.copy-url-btn:hover {
    cursor: pointer;
    background-color: #e2e6ea;
}

/* モバイルでのタブ表示を改善 */
@media (max-width: 576px) {
    .nav-tabs {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        -ms-overflow-style: -ms-autohiding-scrollbar;
    }
    
    .nav-tabs .nav-item {
        flex: 0 0 auto;
    }
}
</style>

<?php
// フッターを含める
include 'includes/footer.php';
?>
