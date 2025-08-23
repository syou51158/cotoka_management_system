<?php
// 現在のページを取得して、アクティブなステップを決定
if (!isset($active_step)) {
    $current_page = basename($_SERVER['PHP_SELF']);
    
    switch ($current_page) {
        case 'index.php':
            $active_step = 'home';
            break;
        case 'select_service.php':
            $active_step = 'service';
            break;
        case 'select_datetime.php':
            $active_step = 'datetime';
            break;
        case 'input_info.php':
            $active_step = 'info';
            break;
        case 'confirm.php':
            $active_step = 'confirm';
            break;
        case 'complete.php':
            $active_step = 'complete';
            break;
        default:
            $active_step = '';
            break;
    }
}

// ステップの定義
$steps = [
    'home' => [
        'name' => 'ホーム',
        'icon' => 'fas fa-home'
    ],
    'service' => [
        'name' => 'メニュー選択',
        'icon' => 'fas fa-list'
    ],
    'datetime' => [
        'name' => '日時選択',
        'icon' => 'far fa-calendar-alt'
    ],
    'info' => [
        'name' => '情報入力',
        'icon' => 'fas fa-user'
    ],
    'confirm' => [
        'name' => '予約確認',
        'icon' => 'fas fa-check'
    ],
    'complete' => [
        'name' => '予約完了',
        'icon' => 'fas fa-check-circle'
    ]
];
?>

<ul class="booking-steps">
    <?php foreach ($steps as $step_id => $step): ?>
        <li class="<?php echo ($active_step === $step_id) ? 'active' : ''; ?>">
            <i class="<?php echo $step['icon']; ?>"></i> <?php echo $step['name']; ?>
        </li>
    <?php endforeach; ?>
</ul> 