<?php
// 共通の設定ファイルを読み込む
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/role_permissions.php';
require_once 'classes/Database.php';
require_once 'classes/User.php';

// ページタイトル
$page_title = '店舗管理';

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

// 現在のサロンID
$salon_id = getCurrentSalonId();

// フォーム送信時の処理
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	$action = $_POST['action'];
	
	// 営業時間の更新（Supabase RPC）
	if ($action === 'update_business_hours') {
		try {
			$hoursPayload = [];
			for ($i = 0; $i < 7; $i++) {
				$is_closed = isset($_POST['is_closed'][$i]) ? true : false;
				$open_time = !$is_closed ? ($_POST['open_time'][$i] ?? null) : null;
				$close_time = !$is_closed ? ($_POST['close_time'][$i] ?? null) : null;
				$hoursPayload[] = [
					'day_of_week' => $i,
					'open_time' => $open_time,
					'close_time' => $close_time,
					'is_closed' => $is_closed
				];
			}
			$res = supabaseRpcCall('salon_business_hours_set', [
				'p_salon_id' => (int)$salon_id,
				'p_hours' => $hoursPayload
			]);
			if ($res['success']) {
				$success_message = '営業時間を更新しました。';
			} else {
				$error_message = 'エラーが発生しました: ' . ($res['message'] ?? '');
			}
		} catch (Exception $e) {
			$error_message = 'エラーが発生しました: ' . $e->getMessage();
		}
	}
	
	// 予約時間単位の更新（Supabase RPC）
	if ($action === 'update_time_settings') {
		$time_interval = isset($_POST['time_interval']) ? (int)$_POST['time_interval'] : 30;
		$business_hours_start = isset($_POST['business_hours_start']) ? $_POST['business_hours_start'] : '09:00';
		$business_hours_end = isset($_POST['business_hours_end']) ? $_POST['business_hours_end'] : '18:00';
		
		// バリデーション
		$valid_time_intervals = [5, 10, 15, 30, 60];
		
		if (!in_array($time_interval, $valid_time_intervals)) {
			$error_message = '無効な時間間隔です';
		} else {
			try {
				$res = supabaseRpcCall('salon_time_settings_set', [
					'p_salon_id' => (int)$salon_id,
					'p_time_interval' => $time_interval,
					'p_business_hours_start' => $business_hours_start,
					'p_business_hours_end' => $business_hours_end
				]);
				if ($res['success']) {
					$success_message = '予約時間設定を更新しました。';
				} else {
					$error_message = 'エラーが発生しました: ' . ($res['message'] ?? '');
				}
			} catch (Exception $e) {
				$error_message = 'エラーが発生しました: ' . $e->getMessage();
			}
		}
	}
}

// 現在の営業時間を取得（Supabase RPC）
$business_hours = [];
$resHours = supabaseRpcCall('salon_business_hours_get', ['p_salon_id' => (int)$salon_id]);
$hours_data = [];
if ($resHours['success']) {
	$hours_data = is_array($resHours['data']) ? $resHours['data'] : [];
}

// 曜日ごとのデータを整理
foreach ($hours_data as $hour) {
	$business_hours[$hour['day_of_week']] = [
		'open_time' => $hour['open_time'],
		'close_time' => $hour['close_time'],
		'is_closed' => ($hour['is_closed'] ? 1 : 0)
	];
}

// 未設定の曜日にデフォルト値を設定
for ($i = 0; $i < 7; $i++) {
	if (!isset($business_hours[$i])) {
		$business_hours[$i] = [
			'open_time' => '09:00',
			'close_time' => '18:00',
			'is_closed' => 0
		];
	}
}

// 予約時間設定を取得（Supabase RPC）
$time_settings = [
	'time_interval' => 30,
	'business_hours_start' => '09:00',
	'business_hours_end' => '18:00'
];
$resTS = supabaseRpcCall('salon_time_settings_get', ['p_salon_id' => (int)$salon_id]);
if ($resTS['success']) {
	$rows = is_array($resTS['data']) ? $resTS['data'] : [];
	if (!empty($rows)) {
		$time_settings['time_interval'] = (int)($rows[0]['time_interval'] ?? 30);
		$time_settings['business_hours_start'] = $rows[0]['business_hours_start'] ?? '09:00';
		$time_settings['business_hours_end'] = $rows[0]['business_hours_end'] ?? '18:00';
	}
}

// 曜日の名前
$days_of_week = ['日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日'];

// ヘッダーを読み込む
include 'includes/header.php';
?>

<div class="container-fluid p-4">
	<div class="row">
		<div class="col-12">
			<h1 class="mb-4"><i class="fas fa-store"></i> 店舗管理</h1>
			
			<?php if ($success_message): ?>
			<div class="alert alert-success alert-dismissible fade show" role="alert">
				<?php echo htmlspecialchars($success_message); ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
			</div>
			<?php endif; ?>
			
			<?php if ($error_message): ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<?php echo htmlspecialchars($error_message); ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
			</div>
			<?php endif; ?>
			
			<div class="row">
				<!-- 営業時間設定 -->
				<div class="col-lg-6 mb-4">
					<div class="card shadow-sm">
						<div class="card-header bg-primary text-white">
							<h5 class="mb-0"><i class="far fa-clock"></i> 営業時間設定</h5>
						</div>
						<div class="card-body">
							<form method="post" action="">
								<input type="hidden" name="action" value="update_business_hours">
								
								<?php foreach ($days_of_week as $day_index => $day_name): ?>
								<div class="row mb-3 align-items-center">
									<div class="col-md-3">
										<div class="form-check">
											<input class="form-check-input day-closed-checkbox" 
												type="checkbox" 
												name="is_closed[<?php echo $day_index; ?>]" 
												id="is_closed_<?php echo $day_index; ?>" 
												<?php echo $business_hours[$day_index]['is_closed'] ? 'checked' : ''; ?>>
											<label class="form-check-label" for="is_closed_<?php echo $day_index; ?>">
												<?php echo htmlspecialchars($day_name); ?>（休業）
											</label>
										</div>
									</div>
									<div class="col-md-9 time-fields" id="time_fields_<?php echo $day_index; ?>">
										<div class="row">
											<div class="col-5">
												<input type="time" 
													class="form-control" 
													name="open_time[<?php echo $day_index; ?>]" 
													value="<?php echo htmlspecialchars($business_hours[$day_index]['open_time'] ?? '09:00'); ?>"
													<?php echo $business_hours[$day_index]['is_closed'] ? 'disabled' : ''; ?>>
											</div>
											<div class="col-2 text-center">
												<span>〜</span>
											</div>
											<div class="col-5">
												<input type="time" 
													class="form-control" 
													name="close_time[<?php echo $day_index; ?>]" 
													value="<?php echo htmlspecialchars($business_hours[$day_index]['close_time'] ?? '18:00'); ?>"
													<?php echo $business_hours[$day_index]['is_closed'] ? 'disabled' : ''; ?>>
											</div>
										</div>
									</div>
								</div>
								<?php endforeach; ?>
								
								<div class="text-end mt-3">
									<button type="submit" class="btn btn-primary">営業時間を更新</button>
								</div>
							</form>
						</div>
					</div>
				</div>
				
				<!-- 予約時間設定 -->
				<div class="col-lg-6 mb-4">
					<div class="card shadow-sm">
						<div class="card-header bg-primary text-white">
							<h5 class="mb-0"><i class="fas fa-sliders-h"></i> 予約時間設定</h5>
						</div>
						<div class="card-body">
							<form method="post" action="">
								<input type="hidden" name="action" value="update_time_settings">
								
								<div class="mb-3">
									<label for="time_interval" class="form-label">予約時間単位</label>
									<select id="time_interval" name="time_interval" class="form-select">
										<option value="5" <?php echo $time_settings['time_interval'] == 5 ? 'selected' : ''; ?>>5分</option>
										<option value="10" <?php echo $time_settings['time_interval'] == 10 ? 'selected' : ''; ?>>10分</option>
										<option value="15" <?php echo $time_settings['time_interval'] == 15 ? 'selected' : ''; ?>>15分</option>
										<option value="30" <?php echo $time_settings['time_interval'] == 30 ? 'selected' : ''; ?>>30分</option>
										<option value="60" <?php echo $time_settings['time_interval'] == 60 ? 'selected' : ''; ?>>60分（1時間）</option>
									</select>
									<div class="form-text">予約カレンダーに表示される時間間隔を設定します。</div>
								</div>
								
								<div class="mb-3">
									<label for="business_hours_start" class="form-label">営業開始時間</label>
									<input type="time" class="form-control" id="business_hours_start" name="business_hours_start" 
									       value="<?php echo htmlspecialchars($time_settings['business_hours_start']); ?>">
								</div>
								
								<div class="mb-3">
									<label for="business_hours_end" class="form-label">営業終了時間</label>
									<input type="time" class="form-control" id="business_hours_end" name="business_hours_end" 
									       value="<?php echo htmlspecialchars($time_settings['business_hours_end']); ?>">
								</div>
								
								<div class="text-end mt-3">
									<button type="submit" class="btn btn-primary">予約時間設定を更新</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// 休業設定のチェックボックスを処理
	document.querySelectorAll('.day-closed-checkbox').forEach(function(checkbox) {
		checkbox.addEventListener('change', function() {
			const dayIndex = this.id.split('_')[2];
			const timeFields = document.querySelectorAll(`#time_fields_${dayIndex} input`);
			
			timeFields.forEach(function(field) {
				field.disabled = checkbox.checked;
			});
		});
	});
});
</script>

<?php include 'includes/footer.php'; ?> 