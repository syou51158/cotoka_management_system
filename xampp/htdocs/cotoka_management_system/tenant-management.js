/**
 * tenant_management.js
 * テナント管理ページの操作を制御するJavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // 営業時間設定フォーム
    const businessHoursForm = document.getElementById('businessHoursForm');
    if (businessHoursForm) {
        setupBusinessHoursForm();
    }
    
    // フラッシュメッセージの自動非表示
    const alertMessages = document.querySelectorAll('.alert:not(.alert-dismissible)');
    if (alertMessages.length > 0) {
        setTimeout(() => {
            alertMessages.forEach(alert => {
                alert.classList.add('fade');
                setTimeout(() => {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    }
});

/**
 * 営業時間設定フォームのセットアップ
 */
function setupBusinessHoursForm() {
    // 曜日ごとのチェックボックスイベント
    const dayStatusCheckboxes = document.querySelectorAll('.day-status');
    
    dayStatusCheckboxes.forEach(checkbox => {
        // 初期化時のイベント
        updateTimeInputs(checkbox);
        
        // チェックボックス変更時のイベント
        checkbox.addEventListener('change', function() {
            updateTimeInputs(this);
            
            // ステータステキストの更新
            const statusText = this.closest('td').querySelector('.status-text');
            if (statusText) {
                statusText.textContent = this.checked ? '営業' : '休業';
            }
        });
    });
    
    // フォーム送信前の検証
    const form = document.getElementById('businessHoursForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // 営業日には時間が入力されているか確認
            let hasError = false;
            
            dayStatusCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const dayKey = checkbox.id.replace('_is_open', '');
                    const openTime = document.getElementById(`${dayKey}_open_time`).value;
                    const closeTime = document.getElementById(`${dayKey}_close_time`).value;
                    
                    if (!openTime || !closeTime) {
                        hasError = true;
                        e.preventDefault();
                        showError('営業日には開店時間と閉店時間を入力してください。');
                    }
                    
                    // 開店時間が閉店時間より後になっていないか
                    if (openTime && closeTime && openTime >= closeTime) {
                        hasError = true;
                        e.preventDefault();
                        showError('開店時間は閉店時間より前に設定してください。');
                    }
                }
            });
            
            if (!hasError) {
                // 送信ボタンを無効化して二重送信防止
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
                }
            }
        });
    }
}

/**
 * 時間入力フィールドの状態を更新
 * @param {HTMLElement} checkbox - 対象の曜日のチェックボックス
 */
function updateTimeInputs(checkbox) {
    const dayKey = checkbox.id.replace('_is_open', '');
    const openTimeInput = document.getElementById(`${dayKey}_open_time`);
    const closeTimeInput = document.getElementById(`${dayKey}_close_time`);
    
    if (openTimeInput && closeTimeInput) {
        const isDisabled = !checkbox.checked;
        openTimeInput.disabled = isDisabled;
        closeTimeInput.disabled = isDisabled;
        
        // 視覚的なフィードバック
        const row = checkbox.closest('tr');
        if (row) {
            if (isDisabled) {
                row.classList.add('text-muted');
            } else {
                row.classList.remove('text-muted');
            }
        }
    }
}

/**
 * エラーメッセージを表示
 * @param {string} message - エラーメッセージ
 */
function showError(message) {
    // 既存のエラーアラートを確認
    let alertDiv = document.querySelector('.alert-danger');
    
    if (!alertDiv) {
        // エラーアラートがなければ作成
        alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger mt-3';
        alertDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        // フォームの前に挿入
        const form = document.getElementById('businessHoursForm');
        if (form) {
            form.parentNode.insertBefore(alertDiv, form);
        }
    } else {
        // 既存のアラートに追加
        const paragraph = document.createElement('p');
        paragraph.className = 'mb-0';
        paragraph.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        alertDiv.appendChild(paragraph);
    }
    
    // 表示位置までスクロール
    alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
