// JavaScript初期化の見直し

// ドラッグ＆ドロップ診断スクリプト
document.addEventListener('DOMContentLoaded', function() {
    console.log('診断ツール: DOM読み込み完了');
    setTimeout(diagnoseAppointments, 1000);
});

// 予約機能の診断
function diagnoseAppointments() {
    console.log('=== 予約ドラッグ＆ドロップ診断開始 ===');
    
    // 要素の存在確認
    const appointmentItems = document.querySelectorAll('.appointment-item');
    console.log(`予約アイテム数: ${appointmentItems.length}`);
    
    const timeSlots = document.querySelectorAll('.time-slot');
    console.log(`タイムスロット数: ${timeSlots.length}`);
    
    const emptySlots = document.querySelectorAll('.empty-slot');
    console.log(`空きスロット数: ${emptySlots.length}`);
    
    // 各予約アイテムを確認
    if (appointmentItems.length > 0) {
        appointmentItems.forEach((item, index) => {
            const id = item.dataset.id;
            const isDraggable = item.getAttribute('draggable') === 'true';
            console.log(`予約[${index}]: ID=${id}, draggable=${isDraggable}`);
            
            // ドラッグイベントを追加（既存のものを上書き）
            item.setAttribute('draggable', 'true');
            
            item.addEventListener('dragstart', function(e) {
                console.log(`診断: ドラッグ開始 ID=${id}`);
                e.dataTransfer.setData('text/plain', id);
                this.style.opacity = '0.4';
            });
            
            item.addEventListener('dragend', function(e) {
                console.log(`診断: ドラッグ終了 ID=${id}`);
                this.style.opacity = '1';
            });
        });
    }
    
    // 各タイムスロットを確認
    if (timeSlots.length > 0) {
        timeSlots.forEach((slot, index) => {
            const date = slot.dataset.date;
            const time = slot.dataset.time;
            const isEmpty = slot.classList.contains('empty-slot');
            console.log(`スロット[${index}]: 日付=${date}, 時間=${time}, 空=${isEmpty}`);
            
            // ドロップイベントを追加（既存のものを上書き）
            slot.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.backgroundColor = '#e0f7fa';
            });
            
            slot.addEventListener('dragleave', function(e) {
                this.style.backgroundColor = '';
            });
            
            slot.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.backgroundColor = '';
                
                const appointmentId = e.dataTransfer.getData('text/plain');
                console.log(`診断: ドロップ 予約ID=${appointmentId}, 日付=${date}, 時間=${time}`);
                
                if (appointmentId) {
                    if (confirm(`診断: 予約ID=${appointmentId}を${date} ${time}に移動しますか？`)) {
                        updateAppointmentTimeAlternative(appointmentId, date, time);
                    }
                } else {
                    console.error('診断: ドロップデータが取得できませんでした');
                }
            });
        });
    }
    
    console.log('=== 診断設定完了 ===');
}

// 代替の予約時間更新関数
async function updateAppointmentTimeAlternative(appointmentId, newDate, newTime) {
    console.log(`診断: 予約時間更新開始 ID=${appointmentId}, 日付=${newDate}, 時間=${newTime}`);
    
    // CSRFトークンを取得
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) {
        alert('CSRFトークンが見つかりません');
        return false;
    }
    
    const csrfToken = csrfMeta.content;
    
    try {
        const requestData = {
            appointment_id: appointmentId,
            new_date: newDate,
            new_time: newTime,
            csrf_token: csrfToken
        };
        
        console.log('診断: APIリクエスト送信', requestData);
        
        const response = await fetch('appointment_manager.php?action=update_time', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(requestData)
        });
        
        console.log(`診断: レスポンス状態 ${response.status} ${response.statusText}`);
        
        // レスポンステキストを取得
        const responseText = await response.text();
        console.log('診断: レスポンステキスト', responseText);
        
        if (responseText.trim()) {
            try {
                const data = JSON.parse(responseText);
                console.log('診断: レスポンスJSON', data);
                
                if (data.success) {
                    alert('診断: 予約時間が更新されました！');
                    window.location.reload();
                    return true;
                } else {
                    alert('診断: 予約時間の更新に失敗しました。' + (data.message || ''));
                    return false;
                }
            } catch (e) {
                alert('診断: レスポンスの解析に失敗しました。');
                console.error('診断: JSONパースエラー', e);
                return false;
            }
        } else {
            alert('診断: サーバーからの応答が空です。');
            return false;
        }
    } catch (error) {
        console.error('診断: 予約時間更新エラー', error);
        alert('診断: 予約時間の更新中にエラーが発生しました。' + error.message);
        return false;
    }
}
