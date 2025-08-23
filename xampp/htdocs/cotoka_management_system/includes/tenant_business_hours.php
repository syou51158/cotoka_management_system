<?php
// 権限チェック（スーパー管理者のみアクセス可能）
if (!isUserSuperAdmin()) {
    setFlashMessage('error', '管理者権限が必要です。');
    header('Location: dashboard.php');
    exit;
}

// データベース接続
require_once 'includes/db_connection.php';
$db = getDbConnection();

// サロン一覧を取得
$stmt = $db->prepare("SELECT s.id, s.name, t.company_name FROM salons s 
                     JOIN tenants t ON s.tenant_id = t.tenant_id 
                     ORDER BY t.company_name, s.name");
$stmt->execute();
$salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// フォーム送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_hours') {
    // CSRF対策
    if (!validateCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'セキュリティトークンが無効です。再度お試しください。');
        header('Location: tenant_management.php?tab=business_hours');
        exit;
    }

    $salon_id = intval($_POST['salon_id']);
    $is_closed = isset($_POST['is_closed']) ? $_POST['is_closed'] : [];

    try {
        $db->beginTransaction();

        // 既存の営業時間を削除
        $deleteStmt = $db->prepare("DELETE FROM salon_business_hours WHERE salon_id = ?");
        $deleteStmt->execute([$salon_id]);

        // 新しい営業時間を挿入
        $insertStmt = $db->prepare("INSERT INTO salon_business_hours 
                                  (salon_id, day_of_week, open_time, close_time, is_closed, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

        // 曜日ごとに処理
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        foreach ($days as $index => $day) {
            $dayIndex = $index;
            $isClosed = in_array($day, $is_closed) ? 1 : 0;
            $openTime = $isClosed ? '09:00:00' : $_POST['open_time'][$day];
            $closeTime = $isClosed ? '17:00:00' : $_POST['close_time'][$day];

            $insertStmt->execute([
                $salon_id,
                $dayIndex,
                $openTime,
                $closeTime,
                $isClosed,
            ]);
        }

        $db->commit();
        setFlashMessage('success', '営業時間を更新しました。');
        header('Location: tenant_management.php?tab=business_hours');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        setFlashMessage('error', '営業時間の更新に失敗しました: ' . $e->getMessage());
        header('Location: tenant_management.php?tab=business_hours');
        exit;
    }
}

// サロンが選択されている場合
$selectedSalonId = isset($_GET['salon_id']) ? intval($_GET['salon_id']) : 0;
$businessHours = [];

if ($selectedSalonId > 0) {
    // 営業時間を取得
    $hoursStmt = $db->prepare("SELECT * FROM salon_business_hours WHERE salon_id = ? ORDER BY day_of_week");
    $hoursStmt->execute([$selectedSalonId]);
    $businessHoursData = $hoursStmt->fetchAll(PDO::FETCH_ASSOC);

    // 曜日ごとにデータを整理
    $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    foreach ($days as $index => $day) {
        $found = false;
        foreach ($businessHoursData as $hour) {
            if ($hour['day_of_week'] == $index) {
                $businessHours[$day] = [
                    'open_time' => substr($hour['open_time'], 0, 5),
                    'close_time' => substr($hour['close_time'], 0, 5),
                    'is_closed' => $hour['is_closed']
                ];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            // デフォルト値を設定
            $businessHours[$day] = [
                'open_time' => '09:00',
                'close_time' => '17:00',
                'is_closed' => 0
            ];
        }
    }
}

// 曜日の日本語表記
$dayNames = [
    'mon' => '月曜日',
    'tue' => '火曜日',
    'wed' => '水曜日',
    'thu' => '木曜日',
    'fri' => '金曜日',
    'sat' => '土曜日',
    'sun' => '日曜日'
];
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">サロン営業時間設定</h4>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="mb-4">
            <input type="hidden" name="tab" value="business_hours">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label for="salon_id" class="form-label">サロンを選択</label>
                    <select class="form-select" id="salon_id" name="salon_id" required>
                        <option value="">-- サロンを選択してください --</option>
                        <?php foreach ($salons as $salon): ?>
                            <option value="<?php echo $salon['id']; ?>" <?php echo ($selectedSalonId == $salon['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($salon['company_name'] . ' - ' . $salon['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">営業時間を編集</button>
                </div>
            </div>
        </form>

        <?php if ($selectedSalonId > 0): ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_hours">
                <input type="hidden" name="salon_id" value="<?php echo $selectedSalonId; ?>">
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>曜日</th>
                                <th>営業開始時間</th>
                                <th>営業終了時間</th>
                                <th>定休日</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dayNames as $day => $dayName): ?>
                                <tr>
                                    <td><?php echo $dayName; ?></td>
                                    <td>
                                        <input type="time" class="form-control time-input" 
                                               name="open_time[<?php echo $day; ?>]" 
                                               value="<?php echo isset($businessHours[$day]) ? $businessHours[$day]['open_time'] : '09:00'; ?>"
                                               <?php echo (isset($businessHours[$day]) && $businessHours[$day]['is_closed']) ? 'disabled' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="time" class="form-control time-input" 
                                               name="close_time[<?php echo $day; ?>]" 
                                               value="<?php echo isset($businessHours[$day]) ? $businessHours[$day]['close_time'] : '17:00'; ?>"
                                               <?php echo (isset($businessHours[$day]) && $businessHours[$day]['is_closed']) ? 'disabled' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input closed-check" type="checkbox" 
                                                   id="closed_<?php echo $day; ?>" 
                                                   name="is_closed[]" 
                                                   value="<?php echo $day; ?>"
                                                   <?php echo (isset($businessHours[$day]) && $businessHours[$day]['is_closed']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="closed_<?php echo $day; ?>">
                                                定休日
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary">営業時間を更新</button>
                </div>
            </form>

            <script>
                // 定休日チェックボックスの動作
                document.addEventListener('DOMContentLoaded', function() {
                    const closedCheckboxes = document.querySelectorAll('.closed-check');
                    
                    closedCheckboxes.forEach(function(checkbox) {
                        checkbox.addEventListener('change', function() {
                            const day = this.value;
                            const timeInputs = document.querySelectorAll(`input[name^="open_time[${day}]"], input[name^="close_time[${day}]"]`);
                            
                            timeInputs.forEach(function(input) {
                                input.disabled = checkbox.checked;
                            });
                        });
                    });
                });
            </script>
        <?php else: ?>
            <div class="alert alert-info">
                サロンを選択して、営業時間を編集してください。
            </div>
        <?php endif; ?>
    </div>
</div> 