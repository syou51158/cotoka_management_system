<?php
// 必要なファイルを読み込み
require_once 'config/config.php';
require_once 'classes/Database.php';
require_once 'includes/functions.php';

// タイムゾーンを明示的に設定
date_default_timezone_set('Asia/Tokyo');

// ログインしていない場合はログインページにリダイレクト
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// データベース接続
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $error_message = "データベース接続エラー：" . $e->getMessage();
    echo '<div class="alert alert-danger m-3">' . $error_message . '</div>';
    exit;
}

// 現在のサロンIDを取得
$salon_id = isset($_SESSION['salon_id']) ? $_SESSION['salon_id'] : 1;

// 現在のテナントIDを取得
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : 1;

// スタッフIDの検証
if (!isset($_GET['staff_id']) || !filter_var($_GET['staff_id'], FILTER_VALIDATE_INT)) {
    header('Location: staff_management.php');
    exit;
}

$staff_id = $_GET['staff_id'];

// スタッフ情報の取得
$staff_info = [];
try {
    $stmt = $conn->prepare("
        SELECT first_name, last_name 
        FROM staff 
        WHERE staff_id = :staff_id AND salon_id = :salon_id
    ");
    $stmt->bindParam(':staff_id', $staff_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $staff_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff_info) {
        // 指定されたスタッフが存在しない場合はリダイレクト
        header('Location: staff_management.php');
        exit;
    }
} catch (PDOException $e) {
    $error_message = "スタッフ情報取得エラー：" . $e->getMessage();
}

// 曜日の日本語名配列
$days_of_week_jp = ['日', '月', '火', '水', '木', '金', '土'];

// シフトパターン追加処理
$pattern_add_success = false;
$pattern_add_error = '';

if (isset($_POST['add_shift_pattern'])) {
    $day_of_week = filter_var($_POST['day_of_week'], FILTER_VALIDATE_INT);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    
    // 基本的なバリデーション
    if ($day_of_week === false || $day_of_week < 0 || $day_of_week > 6) {
        $pattern_add_error = '無効な曜日が指定されました。';
    } elseif (empty($start_time) || empty($end_time)) {
        $pattern_add_error = '開始時間と終了時間は必須です。';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $pattern_add_error = '終了時間は開始時間より後である必要があります。';
    } else {
        try {
            // 既存のパターンを確認
            $check_stmt = $conn->prepare("
                SELECT pattern_id 
                FROM staff_shift_patterns 
                WHERE staff_id = :staff_id AND salon_id = :salon_id AND day_of_week = :day_of_week
            ");
            $check_stmt->bindParam(':staff_id', $staff_id);
            $check_stmt->bindParam(':salon_id', $salon_id);
            $check_stmt->bindParam(':day_of_week', $day_of_week);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // 既存のパターンの pattern_id を取得
                $pattern_id = $check_stmt->fetchColumn();
                
                // 既存のパターンを更新
                $stmt = $conn->prepare("
                    UPDATE staff_shift_patterns SET
                        start_time = :start_time,
                        end_time = :end_time,
                        updated_at = NOW()
                    WHERE pattern_id = :pattern_id
                ");
                $stmt->bindParam(':pattern_id', $pattern_id);
            } else {
                // 新しいパターンを追加
                $stmt = $conn->prepare("
                    INSERT INTO staff_shift_patterns (
                        staff_id,
                        salon_id,
                        tenant_id,
                        day_of_week,
                        start_time,
                        end_time
                    ) VALUES (
                        :staff_id,
                        :salon_id,
                        :tenant_id,
                        :day_of_week,
                        :start_time,
                        :end_time
                    )
                ");
                $stmt->bindParam(':tenant_id', $tenant_id);
                $stmt->bindParam(':day_of_week', $day_of_week);
            }
            
            $stmt->bindParam(':staff_id', $staff_id);
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            
            if ($stmt->execute()) {
                $pattern_add_success = true;
            } else {
                $pattern_add_error = 'シフトパターンの保存に失敗しました。';
            }
        } catch (PDOException $e) {
            $pattern_add_error = 'データベースエラー: ' . $e->getMessage();
        }
    }
}

// シフトパターン削除処理
$pattern_delete_success = false;
$pattern_delete_error = '';

if (isset($_POST['delete_shift_pattern'])) {
    $pattern_id = filter_var($_POST['pattern_id'], FILTER_VALIDATE_INT);
    
    if (!$pattern_id) {
        $pattern_delete_error = '無効なパターンIDです。';
    } else {
        try {
            $stmt = $conn->prepare("
                DELETE FROM staff_shift_patterns 
                WHERE pattern_id = :pattern_id AND staff_id = :staff_id AND salon_id = :salon_id
            ");
            $stmt->bindParam(':pattern_id', $pattern_id);
            $stmt->bindParam(':staff_id', $staff_id);
            $stmt->bindParam(':salon_id', $salon_id);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $pattern_delete_success = true;
            } else {
                $pattern_delete_error = 'シフトパターンの削除に失敗しました。';
            }
        } catch (PDOException $e) {
            $pattern_delete_error = 'データベースエラー: ' . $e->getMessage();
        }
    }
}

// 個別シフト追加処理
$shift_add_success = false;
$shift_add_error = '';

if (isset($_POST['add_shift'])) {
    $shift_date = trim($_POST['shift_date']);
    $start_time = trim($_POST['shift_start_time']);
    $end_time = trim($_POST['shift_end_time']);
    
    // 基本的なバリデーション
    if (empty($shift_date)) {
        $shift_add_error = '日付は必須です。';
    } elseif (empty($start_time) || empty($end_time)) {
        $shift_add_error = '開始時間と終了時間は必須です。';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $shift_add_error = '終了時間は開始時間より後である必要があります。';
    } else {
        try {
            // 同じ日に既存のシフトがあるか確認
            $check_stmt = $conn->prepare("
                SELECT shift_id 
                FROM staff_shifts 
                WHERE staff_id = :staff_id AND salon_id = :salon_id AND shift_date = :shift_date
            ");
            $check_stmt->bindParam(':staff_id', $staff_id);
            $check_stmt->bindParam(':salon_id', $salon_id);
            $check_stmt->bindParam(':shift_date', $shift_date);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // 既存のシフトを更新
                $stmt = $conn->prepare("
                    UPDATE staff_shifts SET
                        start_time = :start_time,
                        end_time = :end_time,
                        status = 'active',
                        updated_at = NOW()
                    WHERE 
                        staff_id = :staff_id AND 
                        salon_id = :salon_id AND 
                        shift_date = :shift_date
                ");
            } else {
                // 新しいシフトを追加
                $stmt = $conn->prepare("
                    INSERT INTO staff_shifts (
                        staff_id,
                        salon_id,
                        tenant_id,
                        shift_date,
                        start_time,
                        end_time,
                        status
                    ) VALUES (
                        :staff_id,
                        :salon_id,
                        :tenant_id,
                        :shift_date,
                        :start_time,
                        :end_time,
                        'active'
                    )
                ");
                $stmt->bindParam(':tenant_id', $tenant_id);
            }
            
            $stmt->bindParam(':staff_id', $staff_id);
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':shift_date', $shift_date);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            
            if ($stmt->execute()) {
                $shift_add_success = true;
            } else {
                $shift_add_error = 'シフトの保存に失敗しました。';
            }
        } catch (PDOException $e) {
            $shift_add_error = 'データベースエラー: ' . $e->getMessage();
        }
    }
}

// 個別シフト削除処理
$shift_delete_success = false;
$shift_delete_error = '';

if (isset($_POST['delete_shift'])) {
    $shift_id = filter_var($_POST['shift_id'], FILTER_VALIDATE_INT);
    
    if (!$shift_id) {
        $shift_delete_error = '無効なシフトIDです。';
    } else {
        try {
            $stmt = $conn->prepare("
                DELETE FROM staff_shifts 
                WHERE shift_id = :shift_id AND staff_id = :staff_id AND salon_id = :salon_id
            ");
            $stmt->bindParam(':shift_id', $shift_id);
            $stmt->bindParam(':staff_id', $staff_id);
            $stmt->bindParam(':salon_id', $salon_id);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $shift_delete_success = true;
            } else {
                $shift_delete_error = 'シフトの削除に失敗しました。';
            }
        } catch (PDOException $e) {
            $shift_delete_error = 'データベースエラー: ' . $e->getMessage();
        }
    }
}

// 個別シフト編集処理
$shift_edit_success = false;
$shift_edit_error = '';

if (isset($_POST['shift_id']) && isset($_POST['shift_start_time']) && isset($_POST['shift_end_time']) && isset($_POST['shift_date'])) {
    $shift_id = filter_var($_POST['shift_id'], FILTER_VALIDATE_INT);
    $start_time = trim($_POST['shift_start_time']);
    $end_time = trim($_POST['shift_end_time']);
    $shift_date = trim($_POST['shift_date']);
    
    // 基本的なバリデーション
    if (!$shift_id) {
        $shift_edit_error = '無効なシフトIDです。';
    } elseif (empty($start_time) || empty($end_time)) {
        $shift_edit_error = '開始時間と終了時間は必須です。';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $shift_edit_error = '終了時間は開始時間より後である必要があります。';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE staff_shifts SET
                    start_time = :start_time,
                    end_time = :end_time,
                    updated_at = NOW()
                WHERE 
                    shift_id = :shift_id AND 
                    staff_id = :staff_id AND 
                    salon_id = :salon_id
            ");
            
            $stmt->bindParam(':shift_id', $shift_id);
            $stmt->bindParam(':staff_id', $staff_id);
            $stmt->bindParam(':salon_id', $salon_id);
            $stmt->bindParam(':start_time', $start_time);
            $stmt->bindParam(':end_time', $end_time);
            
            if ($stmt->execute() && $stmt->rowCount() > 0) {
                $shift_edit_success = true;
            } else {
                $shift_edit_error = 'シフトの更新に失敗しました。';
            }
        } catch (PDOException $e) {
            $shift_edit_error = 'データベースエラー: ' . $e->getMessage();
        }
    }
}

// シフトパターンの取得
$shift_patterns = [];
try {
    $stmt = $conn->prepare("
        SELECT * FROM staff_shift_patterns 
        WHERE staff_id = :staff_id AND salon_id = :salon_id 
        ORDER BY day_of_week
    ");
    $stmt->bindParam(':staff_id', $staff_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->execute();
    $shift_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "シフトパターン取得エラー：" . $e->getMessage();
}

// 今月と来月の個別シフトを取得
$today = date('Y-m-d');
$month_start = date('Y-m-01');
$next_month_end = date('Y-m-t', strtotime('+1 month'));

$shifts = [];
try {
    $stmt = $conn->prepare("
        SELECT * FROM staff_shifts 
        WHERE staff_id = :staff_id AND salon_id = :salon_id AND shift_date BETWEEN :start_date AND :end_date
        ORDER BY shift_date
    ");
    $stmt->bindParam(':staff_id', $staff_id);
    $stmt->bindParam(':salon_id', $salon_id);
    $stmt->bindParam(':start_date', $month_start);
    $stmt->bindParam(':end_date', $next_month_end);
    $stmt->execute();
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "シフト取得エラー：" . $e->getMessage();
}

// ページタイトルとCSS
$page_title = "スタッフシフト管理";
$page_css = <<<EOT
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.css">
<style>
    .fc-event {
        cursor: pointer;
    }
    .pattern-card {
        margin-bottom: 15px;
    }
    .pattern-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .time-display {
        font-size: 1.2rem;
        font-weight: bold;
    }
</style>
EOT;
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Cotoka管理システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <?php echo $page_css; ?>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }
        .container-fluid {
            padding-top: 20px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- メインコンテンツエリア -->
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <?php echo htmlspecialchars($staff_info['last_name'] . ' ' . $staff_info['first_name']); ?> さんのシフト管理
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="staff_management.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> スタッフ一覧に戻る
                    </a>
                </div>
            </div>

            <!-- 成功/エラーメッセージ表示 -->
            <?php if ($pattern_add_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    シフトパターンが正常に保存されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($pattern_delete_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    シフトパターンが正常に削除されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($shift_add_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    シフトが正常に保存されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($shift_delete_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    シフトが正常に削除されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($pattern_add_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    シフトパターンの保存に失敗しました: <?php echo htmlspecialchars($pattern_add_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($pattern_delete_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    シフトパターンの削除に失敗しました: <?php echo htmlspecialchars($pattern_delete_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($shift_add_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    シフトの保存に失敗しました: <?php echo htmlspecialchars($shift_add_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($shift_delete_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    シフトの削除に失敗しました: <?php echo htmlspecialchars($shift_delete_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($shift_edit_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    シフトが正常に更新されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($shift_edit_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    シフトの更新に失敗しました: <?php echo htmlspecialchars($shift_edit_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- シフトパターン設定 -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">定期シフトパターン</h5>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPatternModal">
                                <i class="fas fa-plus"></i> 追加
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($shift_patterns)): ?>
                                <p class="text-muted">定期シフトが設定されていません。</p>
                            <?php else: ?>
                                <?php foreach ($shift_patterns as $pattern): ?>
                                    <div class="card pattern-card">
                                        <div class="card-body">
                                            <div class="pattern-header">
                                                <h5 class="card-title mb-0"><?php echo $days_of_week_jp[$pattern['day_of_week']]; ?>曜日</h5>
                                                <form action="staff_shifts.php?staff_id=<?php echo $staff_id; ?>" method="post" class="d-inline">
                                                    <input type="hidden" name="pattern_id" value="<?php echo $pattern['pattern_id']; ?>">
                                                    <button type="submit" name="delete_shift_pattern" class="btn btn-sm btn-danger" onclick="return confirm('このシフトパターンを削除してもよろしいですか？');">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <p class="card-text time-display mt-2">
                                                <?php 
                                                echo date('H:i', strtotime($pattern['start_time'])); 
                                                echo ' 〜 '; 
                                                echo date('H:i', strtotime($pattern['end_time'])); 
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 個別シフト追加 -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">個別シフト追加</h5>
                        </div>
                        <div class="card-body">
                            <form action="staff_shifts.php?staff_id=<?php echo $staff_id; ?>" method="post">
                                <div class="mb-3">
                                    <label for="shift_date" class="form-label">日付</label>
                                    <input type="date" class="form-control date-picker" id="shift_date" name="shift_date" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="shift_start_time" class="form-label">開始時間</label>
                                        <input type="time" class="form-control time-picker" id="shift_start_time" name="shift_start_time" required value="09:00">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="shift_end_time" class="form-label">終了時間</label>
                                        <input type="time" class="form-control time-picker" id="shift_end_time" name="shift_end_time" required value="18:00">
                                    </div>
                                </div>
                                <button type="submit" name="add_shift" class="btn btn-primary w-100">シフトを追加</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- シフト一括生成ボタン -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">シフト一括生成</h5>
                        </div>
                        <div class="card-body">
                            <form action="generate_shifts.php" method="post">
                                <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
                                <div class="mb-3">
                                    <label for="generate_start_date" class="form-label">開始日</label>
                                    <input type="date" class="form-control date-picker" id="generate_start_date" name="start_date" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="generate_end_date" class="form-label">終了日</label>
                                    <input type="date" class="form-control date-picker" id="generate_end_date" name="end_date" required value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
                                </div>
                                <button type="button" id="generate_shifts_btn" class="btn btn-success w-100">パターンからシフトを生成</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- カレンダー表示 -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">シフトカレンダー</h5>
                        </div>
                        <div class="card-body">
                            <div id="staff-shifts-calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- シフトパターン追加モーダル -->
<div class="modal fade" id="addPatternModal" tabindex="-1" aria-labelledby="addPatternModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPatternModalLabel">シフトパターン追加</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <form action="staff_shifts.php?staff_id=<?php echo $staff_id; ?>" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="day_of_week" class="form-label">曜日</label>
                        <select class="form-select" id="day_of_week" name="day_of_week" required>
                            <?php for ($i = 0; $i < 7; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $days_of_week_jp[$i]; ?>曜日</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_time" class="form-label">開始時間</label>
                            <input type="time" class="form-control time-picker" id="start_time" name="start_time" required value="09:00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_time" class="form-label">終了時間</label>
                            <input type="time" class="form-control time-picker" id="end_time" name="end_time" required value="18:00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="add_shift_pattern" class="btn btn-primary">追加</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- シフト詳細・編集モーダル -->
<div class="modal fade" id="shiftDetailModal" tabindex="-1" aria-labelledby="shiftDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shiftDetailModalLabel">シフト詳細</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <p id="shift-detail-date" class="mb-4 fw-bold"></p>
                
                <form id="edit-shift-form" action="staff_shifts.php?staff_id=<?php echo $staff_id; ?>" method="post">
                    <input type="hidden" id="edit_shift_id" name="shift_id">
                    <input type="hidden" id="edit_shift_date" name="shift_date">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_shift_start_time" class="form-label">開始時間</label>
                            <input type="time" class="form-control time-picker" id="edit_shift_start_time" name="shift_start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_shift_end_time" class="form-label">終了時間</label>
                            <input type="time" class="form-control time-picker" id="edit_shift_end_time" name="shift_end_time" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger me-auto" id="open-delete-modal-btn">削除</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                <button type="button" class="btn btn-primary" id="save-shift-btn">保存</button>
            </div>
        </div>
    </div>
</div>

<!-- シフト削除確認モーダル -->
<div class="modal fade" id="deleteShiftModal" tabindex="-1" aria-labelledby="deleteShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteShiftModalLabel">シフト削除確認</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <p id="delete-shift-confirm-text">このシフトを削除してもよろしいですか？</p>
                <p class="text-danger">この操作は取り消せません。</p>
            </div>
            <div class="modal-footer">
                <form action="staff_shifts.php?staff_id=<?php echo $staff_id; ?>" method="post">
                    <input type="hidden" id="delete_shift_id" name="shift_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" name="delete_shift" class="btn btn-danger">削除</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.2/locales-all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 日付ピッカーの初期化
    flatpickr(".date-picker", {
        locale: "ja",
        dateFormat: "Y-m-d",
        allowInput: true
    });
    
    // 時間ピッカーの初期化
    flatpickr(".time-picker", {
        locale: "ja",
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 15
    });
    
    // カレンダーの初期化
    const calendarEl = document.getElementById('staff-shifts-calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'ja',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek'
        },
        businessHours: {
            daysOfWeek: [0, 1, 2, 3, 4, 5, 6], // 0:日曜日から6:土曜日
            startTime: '09:00',
            endTime: '21:00',
        },
        events: [
            <?php foreach ($shifts as $shift): ?>
            {
                id: '<?php echo $shift['shift_id']; ?>',
                title: '勤務可能: <?php echo date('H:i', strtotime($shift['start_time'])) . '-' . date('H:i', strtotime($shift['end_time'])); ?>',
                start: '<?php echo $shift['shift_date'] . 'T' . $shift['start_time']; ?>',
                end: '<?php echo $shift['shift_date'] . 'T' . $shift['end_time']; ?>',
                backgroundColor: '#28a745',
                borderColor: '#28a745',
                textColor: '#fff',
                extendedProps: {
                    shiftId: '<?php echo $shift['shift_id']; ?>',
                    shiftDate: '<?php echo $shift['shift_date']; ?>',
                    startTime: '<?php echo $shift['start_time']; ?>',
                    endTime: '<?php echo $shift['end_time']; ?>'
                }
            },
            <?php endforeach; ?>
        ],
        eventClick: function(info) {
            // シフト詳細モーダルを表示
            const shiftId = info.event.extendedProps.shiftId;
            const shiftDate = info.event.extendedProps.shiftDate;
            const startTime = info.event.extendedProps.startTime;
            const endTime = info.event.extendedProps.endTime;
            
            const formattedDate = new Date(shiftDate).toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' });
            
            // 詳細モーダルにデータをセット
            document.getElementById('shift-detail-date').textContent = `${formattedDate}のシフト`;
            document.getElementById('edit_shift_id').value = shiftId;
            document.getElementById('edit_shift_date').value = shiftDate;
            document.getElementById('edit_shift_start_time').value = startTime;
            document.getElementById('edit_shift_end_time').value = endTime;
            
            // 削除モーダル用のデータも設定
            document.getElementById('delete_shift_id').value = shiftId;
            document.getElementById('delete-shift-confirm-text').textContent = `${formattedDate}のシフトを削除してもよろしいですか？`;
            
            // シフト詳細モーダルを表示
            const shiftDetailModal = new bootstrap.Modal(document.getElementById('shiftDetailModal'));
            shiftDetailModal.show();
        }
    });
    
    calendar.render();
    
    // シフト一括生成ボタンの処理
    document.getElementById('generate_shifts_btn').addEventListener('click', function() {
        if (confirm('選択した期間のシフトをパターンから一括生成しますか？\n既存のシフトがある日は上書きされます。')) {
            const staffId = <?php echo $staff_id; ?>;
            const startDate = document.getElementById('generate_start_date').value;
            const endDate = document.getElementById('generate_end_date').value;
            
            // Ajax リクエスト
            fetch('generate_shifts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `staff_id=${staffId}&start_date=${startDate}&end_date=${endDate}`
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTPエラー: ${response.status}. レスポンス: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('シフトが正常に生成されました。');
                    // ページをリロード
                    window.location.reload();
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error詳細:', error);
                alert(`シフト生成中にエラーが発生しました。詳細はコンソールを確認してください。\nエラー: ${error.message}`);
            });
        }
    });
    
    // 削除モーダルを開くボタンのイベントリスナー
    document.getElementById('open-delete-modal-btn').addEventListener('click', function() {
        // シフト詳細モーダルを閉じる
        bootstrap.Modal.getInstance(document.getElementById('shiftDetailModal')).hide();
        
        // 削除確認モーダルを表示
        const deleteShiftModal = new bootstrap.Modal(document.getElementById('deleteShiftModal'));
        deleteShiftModal.show();
    });
    
    // シフト保存ボタンのイベントリスナー
    document.getElementById('save-shift-btn').addEventListener('click', function() {
        const shiftId = document.getElementById('edit_shift_id').value;
        const shiftDate = document.getElementById('edit_shift_date').value;
        const startTime = document.getElementById('edit_shift_start_time').value;
        const endTime = document.getElementById('edit_shift_end_time').value;
        
        // 基本的なバリデーション
        if (!startTime || !endTime) {
            alert('開始時間と終了時間は必須です。');
            return;
        }
        
        if (startTime >= endTime) {
            alert('終了時間は開始時間より後である必要があります。');
            return;
        }
        
        // フォームをサブミット
        document.getElementById('edit-shift-form').submit();
    });
});
</script>

</body>
</html> 