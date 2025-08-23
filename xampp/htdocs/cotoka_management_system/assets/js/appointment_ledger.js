/**
 * 予約台帳ページ用JavaScript
 * COTOKA Management System
 */

// APIのベースパス定義
const apiBasePath = 'api';

// グローバル変数
let currentAppointmentId = null;
let isMobile = window.innerWidth <= 768;

/**
 * ユーティリティ関数
 */

// ローディングオーバーレイの表示
function showLoading() {
  if ($('#loading-overlay').length === 0) {
    $('body').append(`
      <div id="loading-overlay">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">読み込み中...</span>
        </div>
      </div>
    `);
  }
  $('#loading-overlay').show();
}

// ローディングオーバーレイの非表示
function hideLoading() {
  $('#loading-overlay').hide();
}

// システムメッセージの表示
function displaySystemMessage(message, type = 'info') {
  // メッセージ表示用の要素を作成または取得
  let $messageContainer = $('#system-message-container');
  if ($messageContainer.length === 0) {
    $messageContainer = $('<div id="system-message-container"></div>');
    $('body').append($messageContainer);
  }
  
  // アラートのスタイルを設定
  let alertClass = 'alert-info';
  if (type === 'success') alertClass = 'alert-success';
  if (type === 'error' || type === 'danger') alertClass = 'alert-danger';
  if (type === 'warning') alertClass = 'alert-warning';
  
  // メッセージ要素を作成
  const $alert = $(`
    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
      ${message}
      <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>
  `);
  
  // メッセージを表示
  $messageContainer.append($alert);
  
  // 5秒後に自動的に消える
  setTimeout(function() {
    $alert.alert('close');
  }, 5000);
}

// 互換性のため、showMessageも定義
function showMessage(message, type = 'info') {
  displaySystemMessage(message, type);
}

// CSRFトークンを取得する関数
function getCsrfToken() {
  return $('#csrf_token').val();
}

// 開始時間と所要時間から終了時間を計算する関数
function calculateEndTime(startTime, durationMinutes = 30) {
  const [hours, minutes] = startTime.split(':').map(Number);
  const startMinutes = hours * 60 + minutes;
  const endMinutes = startMinutes + durationMinutes;
  
  const endHours = Math.floor(endMinutes / 60);
  const endMins = endMinutes % 60;
  
  return `${String(endHours).padStart(2, '0')}:${String(endMins).padStart(2, '0')}`;
}

// 日付をフォーマットする関数
function formatDateForDisplay(dateStr) {
  if (!dateStr) return '';
  
  const date = new Date(dateStr);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date.getDay()];
  
  return `${year}年${month}月${day}日(${dayOfWeek})`;
}

// 初期化時のデバイス判定
$(window).on('resize', function() {
  isMobile = window.innerWidth <= 768;
});

/**
 * 予約操作関連の関数
 */

// 予約時間を更新する関数
function updateAppointmentTime(appointmentId, appointmentDate, startTime, duration, staffId) {
  showLoading();
  
  // 終了時間を計算
  const endTime = calculateEndTime(startTime, duration);
  
  const formData = new FormData();
  formData.append('appointment_id', appointmentId);
  formData.append('staff_id', staffId);
  formData.append('appointment_date', appointmentDate);
  formData.append('start_time', startTime);
  formData.append('end_time', endTime);
  formData.append('csrf_token', getCsrfToken());
  
  $.ajax({
    url: 'api/appointments/update_time.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(response) {
      hideLoading();
      
      if (response.success) {
        displaySystemMessage('予約時間を更新しました。', 'success');
        // 予約表を更新（部分的に更新するか、全体を再読み込み）
        loadAppointmentsForSelectedDate();
      } else {
        displaySystemMessage('エラー：' + response.message, 'error');
        console.error('予約時間更新エラー:', response);
        // 失敗した場合、元の位置に戻す
        loadAppointmentsForSelectedDate();
      }
    },
    error: function(xhr, status, error) {
      hideLoading();
      displaySystemMessage('サーバーエラーが発生しました。', 'error');
      console.error('サーバーエラー:', error, xhr.responseText);
      // 失敗した場合、元の位置に戻す
      loadAppointmentsForSelectedDate();
    }
  });
}

// 予約ステータスの更新
function updateAppointmentStatus(appointmentId, status) {
  if (!appointmentId) {
    console.error('予約IDが指定されていません');
    return;
  }
  
  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    displaySystemMessage('セキュリティエラー: CSRFトークンがありません', 'error');
    return;
  }
  
  showLoading();
  
  $.ajax({
    url: './api/appointments/update_status.php',
    type: 'POST',
    data: {
      appointment_id: appointmentId,
      status: status,
      csrf_token: csrfToken
    },
    dataType: 'json',
    success: function(response) {
      hideLoading();
      
      if (response.success) {
        displaySystemMessage(response.message || 'ステータスが更新されました', 'success');
        
        // ステータスに応じたクラスを更新
        const $item = $(`[data-appointment-id="${appointmentId}"]`);
        $item.removeClass('confirmed cancelled no-show');
        
        if (status === 'confirmed') {
          $item.addClass('confirmed');
        } else if (status === 'cancelled') {
          $item.addClass('cancelled');
        } else if (status === 'no-show') {
          $item.addClass('no-show');
        }
        
        // 詳細モーダルを閉じる
        $('#appointmentDetailsModal').modal('hide');
        
        // 更新されたアイテムをハイライト
        $item.addClass('appointment-pulse');
        setTimeout(() => $item.removeClass('appointment-pulse'), 1000);
      },
      error: function(xhr, status, error) {
        hideLoading();
        console.error('API通信エラー:', status, error);
        displaySystemMessage('サーバーとの通信に失敗しました', 'error');
      }
    });
}

// 予約を確定済みにする
function confirmAppointment(appointmentId) {
  updateAppointmentStatus(appointmentId, 'confirmed');
}

// 予約をキャンセルにする
function cancelAppointment(appointmentId) {
  updateAppointmentStatus(appointmentId, 'cancelled');
}

// 予約をノーショーにする
function markNoShow(appointmentId) {
  updateAppointmentStatus(appointmentId, 'no-show');
}

/**
 * 予約削除関連の関数
 */

// 削除確認ダイアログを表示
function showDeleteConfirmDialog(appointmentId, isTask) {
  const title = isTask ? '業務削除の確認' : '予約削除の確認';
  const message = isTask 
    ? 'この業務を削除してもよろしいですか？この操作は元に戻せません。'
    : 'この予約を削除してもよろしいですか？この操作は元に戻せません。';
  const confirmBtnText = isTask ? '業務を削除する' : '予約を削除する';
  
  // モーダルの内容を設定
  $('#deleteConfirmModalLabel').text(title);
  $('#deleteConfirmModal .modal-body').html(`<p>${message}</p>`);
  $('.confirm-delete-btn')
    .text(confirmBtnText)
    .data('id', appointmentId)
    .data('is-task', isTask);
  
  // モーダルを表示
  $('#deleteConfirmModal').modal('show');
}

// 予約を削除する
function deleteAppointment(appointmentId, isTask) {
  if (!appointmentId) {
    displaySystemMessage('IDが指定されていません', 'error');
    return;
  }
  
  const csrfToken = getCsrfToken();
  if (!csrfToken) {
    displaySystemMessage('セキュリティエラー: CSRFトークンがありません', 'error');
    return;
  }
  
  showLoading();
  
  const apiEndpoint = isTask 
    ? './ajax/task_handler.php' 
    : './api/appointments/delete.php';
  
  const requestData = isTask 
    ? {
        action: 'delete',
        task_id: appointmentId,
        csrf_token: csrfToken
      }
    : {
        appointment_id: appointmentId,
        csrf_token: csrfToken
      };
  
  $.ajax({
    url: apiEndpoint,
    type: 'POST',
    data: requestData,
    dataType: 'json',
    success: function(response) {
      hideLoading();
      
      if (response.success) {
        const itemType = isTask ? '業務' : '予約';
        displaySystemMessage(`${itemType}が正常に削除されました`, 'success');
        
        // 予約アイテムをフェードアウトして削除
        const $item = $(`[data-appointment-id="${appointmentId}"]`);
        $item.fadeOut(300, function() {
          $(this).remove();
        });
        
        // 詳細モーダルを閉じる
        $('.modal').modal('hide');
      } else {
        displaySystemMessage(response.message || `${isTask ? '業務' : '予約'}の削除に失敗しました`, 'error');
      }
    },
    error: function(xhr, status, error) {
      hideLoading();
      console.error('API通信エラー:', status, error);
      displaySystemMessage('サーバーとの通信に失敗しました', 'error');
    }
  });
}

/**
 * 予約詳細関連の関数
 */

// 予約詳細を表示する
function showAppointmentDetails(appointmentId) {
  if (!appointmentId) {
    displaySystemMessage('予約IDが指定されていません', 'error');
    return;
  }
  
  showLoading();
  currentAppointmentId = appointmentId;
  
  $.ajax({
    url: './api/appointments/get_details.php',
    type: 'GET',
    data: { appointment_id: appointmentId },
    dataType: 'json',
    success: function(response) {
      hideLoading();
      
      if (response.success && response.appointment) {
        const appointment = response.appointment;
        
        // モーダルタイトルを設定
        $('#appointmentDetailsModalLabel').text('予約詳細');
        
        // 予約詳細を表示
        let detailsHtml = `
          <div class="appointment-details-container">
            <div class="detail-row">
              <span class="detail-label">顧客名:</span>
              <span class="detail-value">${appointment.customer_last_name || ''} ${appointment.customer_first_name || ''}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">予約日:</span>
              <span class="detail-value">${formatDateForDisplay(appointment.appointment_date)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">時間:</span>
              <span class="detail-value">${appointment.start_time} ～ ${appointment.end_time}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">サービス:</span>
              <span class="detail-value">${appointment.service_name || '未設定'}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">担当:</span>
              <span class="detail-value">${appointment.staff_last_name || ''} ${appointment.staff_first_name || ''}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">ステータス:</span>
              <span class="detail-value">${getStatusName(appointment.status || 'pending')}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">メモ:</span>
              <span class="detail-value">${appointment.notes || ''}</span>
            </div>
          </div>
        `;
        
        // フッターボタンを設定
        let footerHtml = `
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">閉じる</button>
          <button type="button" class="btn btn-warning edit-appointment-btn" data-appointment-id="${appointmentId}">
            <i class="fas fa-edit"></i> 編集
          </button>
        `;
        
        // ステータスに応じたボタン表示
        if (appointment.status !== 'confirmed') {
          footerHtml += `
            <button type="button" class="btn btn-success confirm-appointment-btn" data-appointment-id="${appointmentId}">
              <i class="fas fa-check"></i> 確定
            </button>
          `;
        }
        
        if (appointment.status !== 'cancelled') {
          footerHtml += `
            <button type="button" class="btn btn-danger cancel-appointment-btn" data-appointment-id="${appointmentId}">
              <i class="fas fa-ban"></i> キャンセル
            </button>
          `;
        }
        
        if (appointment.status !== 'no-show') {
          footerHtml += `
            <button type="button" class="btn btn-secondary no-show-appointment-btn" data-appointment-id="${appointmentId}">
              <i class="fas fa-user-slash"></i> ノーショー
            </button>
          `;
        }
        
        footerHtml += `
          <button type="button" class="btn btn-outline-danger delete-appointment-btn" data-appointment-id="${appointmentId}">
            <i class="fas fa-trash-alt"></i> 削除
          </button>
        `;
        
        // モーダル内容を設定
        $('#appointmentDetailsModal .modal-body').html(detailsHtml);
        $('#appointmentDetailsModal .modal-footer').html(footerHtml);
        
        // モーダルを表示
        $('#appointmentDetailsModal').modal('show');
        
        // ボタンのイベントハンドラを設定
        setupAppointmentActionButtons();
      } else {
        displaySystemMessage(response.message || '予約詳細の取得に失敗しました', 'error');
      }
    },
    error: function(xhr, status, error) {
      hideLoading();
      console.error('API通信エラー:', status, error);
      displaySystemMessage('サーバーとの通信に失敗しました', 'error');
    }
  });
}

// タスク詳細を表示する
function showTaskDetails(taskId) {
  if (!taskId) {
    displaySystemMessage('業務IDが指定されていません', 'error');
    return;
  }
  
  showLoading();
  currentAppointmentId = taskId;
  
  $.ajax({
    url: './ajax/task_handler.php',
    type: 'GET',
    data: { 
      action: 'get_details',
      task_id: taskId 
    },
    dataType: 'json',
    success: function(response) {
      hideLoading();
      
      if (response.success && response.task) {
        const task = response.task;
        
        // モーダルタイトルを設定
        $('#appointmentDetailsModalLabel').text('業務詳細');
        
        // 業務詳細を表示
        let detailsHtml = `
          <div class="appointment-details-container">
            <div class="detail-row">
              <span class="detail-label">業務内容:</span>
              <span class="detail-value">${task.task_description || '未設定'}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">日付:</span>
              <span class="detail-value">${formatDateForDisplay(task.task_date)}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">時間:</span>
              <span class="detail-value">${task.start_time} ～ ${task.end_time}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">担当:</span>
              <span class="detail-value">${task.staff_last_name || ''} ${task.staff_first_name || ''}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">メモ:</span>
              <span class="detail-value">${task.notes || ''}</span>
            </div>
          </div>
        `;
        
        // フッターボタンを設定
        let footerHtml = `
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">閉じる</button>
          <button type="button" class="btn btn-warning edit-task-btn" data-task-id="${taskId}">
            <i class="fas fa-edit"></i> 編集
          </button>
          <button type="button" class="btn btn-outline-danger delete-appointment-btn" data-appointment-id="${taskId}" data-is-task="1">
            <i class="fas fa-trash-alt"></i> 削除
          </button>
        `;
        
        // モーダル内容を設定
        $('#appointmentDetailsModal .modal-body').html(detailsHtml);
        $('#appointmentDetailsModal .modal-footer').html(footerHtml);
        
        // モーダルを表示
        $('#appointmentDetailsModal').modal('show');
        
        // ボタンのイベントハンドラを設定
        setupAppointmentActionButtons();
      } else {
        displaySystemMessage(response.message || '業務詳細の取得に失敗しました', 'error');
      }
    },
    error: function(xhr, status, error) {
      hideLoading();
      console.error('API通信エラー:', status, error);
      displaySystemMessage('サーバーとの通信に失敗しました', 'error');
    }
  });
}

// ステータス名を取得する
function getStatusName(status) {
  const statusMap = {
    'pending': '未確定',
    'confirmed': '確定済み',
    'cancelled': 'キャンセル',
    'no-show': 'ノーショー'
  };
  
  return statusMap[status] || status;
}

/**
 * ドラッグ＆ドロップ機能
 */

// ドラッグアンドドロップの処理を初期化
function initializeAppointmentDraggable() {
    console.log('ドラッグアンドドロップを初期化します');
    
    // すでに設定されている場合は解除
    $(".appointment-item").draggable('destroy').removeClass('ui-draggable ui-draggable-handle');
    
    $(".appointment-item").draggable({
        containment: "#timetable",
        grid: [0, 10], // Y方向に10px単位でスナップ
        snap: true,
        snapTolerance: 15,
        cursor: "move",
        revert: "invalid",
        helper: "original",
        start: function(event, ui) {
            $(this).addClass('dragging');
            console.log('ドラッグ開始:', $(this).data('appointment-id'));
        },
        stop: function(event, ui) {
            $(this).removeClass('dragging');
            console.log('ドラッグ終了:', $(this).data('appointment-id'));
        }
    });
    
    // スタッフコラムにドロップ可能にする
    $(".staff-column").droppable({
        accept: ".appointment-item",
        hoverClass: "staff-column-hover",
        drop: function(event, ui) {
            handleAppointmentDrop(event, ui);
        }
    });
    
    console.log('ドラッグアンドドロップの初期化が完了しました');
}

// 予約アイテムのクリックイベントをセットアップ
function setupAppointmentItemClickEvents() {
  // イベントが重複登録されないように一度解除
  $('.appointment-item').off('click.appointment');
  
  // クリックイベントを設定
  $('.appointment-item').on('click.appointment', function(e) {
    // クリックイベントの処理
    const appointmentId = $(this).data('appointment-id');
    const isTask = $(this).hasClass('task');
    
    // ハイライト表示
    $('.appointment-item').removeClass('appointment-active');
    $(this).addClass('appointment-active');
    
    // 予約種別に応じた処理分岐
    if (isTask) {
      showTaskDetails(appointmentId);
    } else {
      showAppointmentDetails(appointmentId);
    }
    
    e.stopPropagation();
  });
}

/**
 * 予約台帳の初期化処理とイベントハンドラ
 */

// 予約アクションボタン（確認・キャンセルなど）の設定
function setupAppointmentActionButtons() {
  // 予約確認ボタン
  $('.confirm-appointment-btn').off('click').on('click', function(e) {
    e.preventDefault();
    const appointmentId = $(this).data('appointment-id');
    
    if (confirm('この予約を確定済みにしますか？')) {
      confirmAppointment(appointmentId);
    }
  });
  
  // 予約キャンセルボタン
  $('.cancel-appointment-btn').off('click').on('click', function(e) {
    e.preventDefault();
    const appointmentId = $(this).data('appointment-id');
    
    if (confirm('この予約をキャンセルしますか？')) {
      cancelAppointment(appointmentId);
    }
  });
  
  // ノーショーボタン
  $('.no-show-appointment-btn').off('click').on('click', function(e) {
    e.preventDefault();
    const appointmentId = $(this).data('appointment-id');
    
    if (confirm('この予約をノーショーとしてマークしますか？')) {
      markNoShow(appointmentId);
    }
  });
  
  // 予約削除ボタン
  $('.delete-appointment-btn').off('click').on('click', function(e) {
    e.preventDefault();
    const appointmentId = $(this).data('appointment-id');
    const isTask = $(this).data('is-task') === 1 || $(this).hasClass('task-delete-btn');
    
    showDeleteConfirmDialog(appointmentId, isTask);
  });
  
  // 予約編集ボタン
  $('.edit-appointment-btn, .edit-task-btn').off('click').on('click', function(e) {
    e.preventDefault();
    const appointmentId = $(this).data('appointment-id') || $(this).data('task-id');
    const isTask = $(this).hasClass('edit-task-btn');
    
    // モーダルを閉じる
    $('#appointmentDetailsModal').modal('hide');
    
    // 編集モーダルを開く
    openEditModal(appointmentId, isTask);
  });
}

// 編集モーダルを開く
function openEditModal(appointmentId, isTask) {
  showLoading();
  
  // APIエンドポイントを設定
  const apiEndpoint = isTask 
    ? './ajax/task_handler.php' 
    : './api/appointments/get_details.php';
  
  // リクエストデータを設定
  const requestData = isTask 
    ? { action: 'get_details', task_id: appointmentId } 
    : { appointment_id: appointmentId };
  
  // 詳細データを取得
  $.ajax({
    url: apiEndpoint,
    type: 'GET',
    data: requestData,
    dataType: 'json',
    success: function(response) {
      hideLoading();
      
      if (response.success) {
        const data = isTask ? response.task : response.appointment;
        
        // フォームの初期値を設定
        $('#appointment_action').val('edit');
        $('#appointment_id').val(appointmentId);
        $('#appointment_type').val(isTask ? 'task' : 'customer');
        
        if (isTask) {
          // 業務編集の場合
          $('.modal-title').text('業務編集');
          $('#staff_id').val(data.staff_id);
          $('#start_time').val(data.start_time);
          $('#end_time').val(data.end_time);
          $('#task_description').val(data.task_description);
          $('#notes').val(data.notes);
          
          $('#customer_section').hide();
          $('#task_section').show();
        } else {
          // 予約編集の場合
          $('.modal-title').text('予約編集');
          $('#staff_id').val(data.staff_id);
          $('#start_time').val(data.start_time);
          $('#end_time').val(data.end_time);
          $('#notes').val(data.notes);
          
          // 顧客とサービスのリストを読み込み
          loadCustomers(data.customer_id);
          loadServices(data.service_id);
          
          $('#customer_section').show();
          $('#task_section').hide();
        }
        
        // モーダルを表示
        $('#addAppointmentModal').modal('show');
      } else {
        displaySystemMessage(response.message || 'データの取得に失敗しました', 'error');
      }
    },
    error: function(xhr, status, error) {
      hideLoading();
      console.error('API通信エラー:', status, error);
      displaySystemMessage('サーバーとの通信に失敗しました', 'error');
    }
  });
}

// 顧客一覧を読み込む関数
function loadCustomers(selectedCustomerId) {
  $('#customer_id').html('<option value="">選択してください</option>');
  
  $.ajax({
    url: './api/get_customers.php',
    type: 'GET',
    dataType: 'json',
    success: function(response) {
      if (response.success && response.customers && response.customers.length) {
        // 顧客をソート（姓名順）
        response.customers.sort(function(a, b) {
          const nameA = (a.last_name + a.first_name).toLowerCase();
          const nameB = (b.last_name + b.first_name).toLowerCase();
          
          if (nameA < nameB) return -1;
          if (nameA > nameB) return 1;
          return 0;
        });
        
        // 顧客をoption要素として追加
        response.customers.forEach(function(customer) {
          const selected = (selectedCustomerId && selectedCustomerId == customer.customer_id) ? 'selected' : '';
          const phone = customer.phone ? ` (${customer.phone})` : '';
          
          $('#customer_id').append(`<option value="${customer.customer_id}" ${selected}>${customer.last_name} ${customer.first_name}${phone}</option>`);
        });
        
        // 選択されている顧客がある場合、それを選択状態にする
        if (selectedCustomerId) {
          $('#customer_id').val(selectedCustomerId);
        }
      }
    },
    error: function(xhr, status, error) {
      console.error('顧客一覧取得エラー:', status, error);
      displaySystemMessage('顧客データ取得エラーが発生しました', 'error');
    }
  });
}

// サービス一覧を読み込む関数
function loadServices(selectedServiceId) {
  $('#service_id').html('<option value="">選択してください</option>');
  
  $.ajax({
    url: './api/get_services.php',
    type: 'GET',
    dataType: 'json',
    success: function(response) {
      if (response.success && response.services && response.services.length) {
        // カテゴリーごとにグループ化
        const servicesByCategory = {};
        
        response.services.forEach(function(service) {
          const categoryId = service.category_id || '0';
          const categoryName = service.category_name || 'その他';
          
          if (!servicesByCategory[categoryId]) {
            servicesByCategory[categoryId] = {
              name: categoryName,
              services: []
            };
          }
          
          servicesByCategory[categoryId].services.push(service);
        });
        
        // カテゴリーごとにoptgroupを作成
        for (const categoryId in servicesByCategory) {
          const category = servicesByCategory[categoryId];
          const $optgroup = $(`<optgroup label="${category.name}"></optgroup>`);
          
          // カテゴリー内のサービスをoption要素として追加
          category.services.forEach(function(service) {
            const selected = (selectedServiceId && selectedServiceId == service.service_id) ? 'selected' : '';
            const price = service.price ? ` (${service.price}円)` : '';
            const duration = service.duration ? ` - ${service.duration}分` : '';
            
            $optgroup.append(`<option value="${service.service_id}" ${selected} data-duration="${service.duration || 30}">${service.name}${price}${duration}</option>`);
          });
          
          $('#service_id').append($optgroup);
        }
        
        // 選択されているサービスがある場合、それを選択状態にする
        if (selectedServiceId) {
          $('#service_id').val(selectedServiceId);
        }
      }
    },
    error: function(xhr, status, error) {
      console.error('サービス一覧取得エラー:', status, error);
      displaySystemMessage('サービスデータ取得エラーが発生しました', 'error');
    }
  });
}

// 現在時刻インジケーターの設定
function setupCurrentTimeIndicator() {
  // 初回表示
  updateCurrentTimeIndicator();
  
  // 1分ごとに更新
  setInterval(updateCurrentTimeIndicator, 60000);
}

// 現在時刻インジケーターの更新
function updateCurrentTimeIndicator() {
  const now = new Date();
  const selectedDate = $('#date-selector').val();
  const today = new Date().toISOString().split('T')[0]; // 今日の日付（YYYY-MM-DD形式）
  
  // 選択された日付が今日かどうかチェック
  if (selectedDate !== today) {
    $('#current-time-indicator, #current-time-label').hide();
    return;
  }
  
  const currentHour = now.getHours();
  const currentMinute = now.getMinutes();
  const currentTimeStr = `${currentHour.toString().padStart(2, '0')}:${currentMinute.toString().padStart(2, '0')}`;
  
  // 営業時間内かチェック
  const businessStartHour = 9; // 営業開始時間（例: 9時）
  const businessEndHour = 21;  // 営業終了時間（例: 21時）
  
  if (currentHour < businessStartHour || currentHour >= businessEndHour) {
    $('#current-time-indicator, #current-time-label').hide();
    return;
  }
  
  // 営業時間内の場合は表示する
  const $timetable = $('#timetable');
  const timetableOffset = $timetable.offset();
  const timetableHeight = $timetable.height();
  const totalMinutes = (businessEndHour - businessStartHour) * 60;
  
  // 現在時刻の位置を計算
  const minutesSinceOpen = (currentHour - businessStartHour) * 60 + currentMinute;
  const position = (minutesSinceOpen / totalMinutes) * timetableHeight + timetableOffset.top;
  
  // インジケーターを表示
  $('#current-time-indicator').css({
    'top': position,
    'width': $timetable.width(),
    'left': timetableOffset.left,
    'display': 'block'
  });
  
  // 時間ラベルを表示
  $('#current-time-label').text(currentTimeStr).css({
    'top': position - 8,
    'left': timetableOffset.left - 45,
    'display': 'block'
  });
  
  console.log('現在時間インジケーターを更新しました:', currentTimeStr, '位置:', position);
}

// 表示モード切替関数
function updateDisplayMode() {
  const $viewModeToggle = $('#viewModeToggle');
  const isCompactMode = $('body').hasClass('compact-view');
  
  if (isCompactMode) {
    // 標準表示モードに切り替え
    $('body').removeClass('compact-view').addClass('full-view');
    $('.view-mode-badge').text('標準表示');
    $viewModeToggle.attr('title', 'コンパクト表示に切り替え');
    
    localStorage.setItem('appointmentLedgerViewMode', 'full');
  } else {
    // コンパクト表示モードに切り替え
    $('body').removeClass('full-view').addClass('compact-view');
    $('.view-mode-badge').text('コンパクト表示');
    $viewModeToggle.attr('title', '標準表示に切り替え');
    
    localStorage.setItem('appointmentLedgerViewMode', 'compact');
  }
}

// ドロップ時の処理
function handleAppointmentDrop(event, ui) {
    // ドロップ先の情報を取得
    const $dropTarget = $(event.target);
    const staffId = $dropTarget.data('staff-id');
    const selectedDate = $('#selected-date').val();
    
    // ドラッグされた予約アイテムの情報を取得
    const $appointmentItem = ui.draggable;
    const appointmentId = $appointmentItem.data('appointment-id');
    
    console.log('ドロップイベント:', {
        'appointmentId': appointmentId,
        'staffId': staffId,
        'selectedDate': selectedDate,
        'dropTarget': $dropTarget
    });
    
    // 必要な情報が揃っているか確認
    if (!appointmentId || !staffId || !selectedDate) {
        showSystemMessage('予約の移動に必要な情報が不足しています', 'danger');
        return;
    }
    
    // ドロップした位置から時間を計算
    const cellHeight = 30; // 15分セルの高さ（px）
    const offsetY = ui.position.top;
    const cellY = Math.floor(offsetY / cellHeight);
    
    // ドロップ先のTD要素の時間を取得
    const hour = $dropTarget.data('hour');
    const minute = cellY * 15;
    
    // 新しい予約時間を設定
    const appointmentDuration = Math.ceil(($appointmentItem.height() / cellHeight) * 15);
    const formattedDate = selectedDate;
    const formattedStartTime = hour.toString().padStart(2, '0') + ':' + minute.toString().padStart(2, '0');
    
    console.log('新しい予約時間:', {
        'date': formattedDate,
        'startTime': formattedStartTime,
        'duration': appointmentDuration
    });
    
    // 予約時間を更新
    updateAppointmentTime(appointmentId, formattedDate, formattedStartTime, appointmentDuration, staffId);
}

// ページが完全に読み込まれた後にこのスクリプトを実行
$(document).ready(function() {
    console.log('ページが完全に読み込まれました - 初期化処理を開始');
    
    // 現在時間インジケーターの初期化
    updateCurrentTimeIndicator();
    setInterval(updateCurrentTimeIndicator, 60000);
    
    // 予約アイテムのドラッグアンドドロップを初期化
    setTimeout(function() {
        console.log('ドラッグアンドドロップを初期化します');
        initializeAppointmentDraggable();
        
        // 予約アイテムのクリックイベントを設定
        $('.appointment-item').off('click').on('click', function(e) {
            e.stopPropagation();
            const appointmentId = $(this).data('appointment-id');
            const isTask = $(this).hasClass('task-item');
            
            console.log('予約アイテムがクリックされました:', appointmentId);
            
            if (isTask) {
                showTaskDetails(appointmentId);
            } else {
                showAppointmentDetails(appointmentId);
            }
        });
    }, 1000);
    
    // モーダル表示時にアクションボタンを初期化
    $('#appointmentDetailsModal').on('shown.bs.modal', function() {
        setupAppointmentActionButtons();
    });
    
    console.log('初期化処理が完了しました');
}); 