// select_datetime.js - 日時選択ページのJavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('==== select_datetime.js 読み込み完了 ====');
    console.log('カレンダーデータ:', calendarDays);
    console.log('スタッフデータ:', staffData);
    console.log('利用可能時間枠:', availableTimeSlots);
    
    // デバッグモードの状態を復元
    if (localStorage.getItem('debugMode') === 'enabled' || debugMode) {
        var debugSection = document.getElementById('debug-section');
        if (debugSection) {
            debugSection.style.display = 'block';
        }
    }
    
    console.log('ページ読み込み完了');
    console.log('デバッグモード:', localStorage.getItem('debugMode'));
    
    // 日付タブのクリックイベントを追加
    const dateTabs = document.querySelectorAll('.date-tab:not(.disabled)');
    console.log('利用可能な日付タブ数:', dateTabs.length);
    
    dateTabs.forEach(function(tab) {
        const dateValue = tab.getAttribute('data-date');
        console.log('日付タブが見つかりました:', dateValue);
        
        tab.addEventListener('click', function() {
            console.log('日付タブがクリックされました:', dateValue);
            selectDate(dateValue);
        });
    });
    
    // スタッフカードのクリックイベントを追加
    document.querySelectorAll('.staff-card').forEach(function(card) {
        card.addEventListener('click', function() {
            const staffId = this.getAttribute('data-staff-id');
            console.log('スタッフカードがクリックされました:', staffId);
            selectStaff(staffId);
        });
    });
    
    // 自動的に最初の利用可能な日付を選択
    if (dateTabs.length > 0) {
        const firstAvailableDate = dateTabs[0].getAttribute('data-date');
        if (firstAvailableDate) {
            console.log('最初の利用可能な日付を自動選択します:', firstAvailableDate);
            selectDate(firstAvailableDate);
        }
    } else {
        console.error('利用可能な日付タブが見つかりません');
        showError('予約可能な日付が見つかりません。別の日程をお試しください。');
    }
    
    // 次へボタンのクリックイベント
    document.getElementById('next-btn').addEventListener('click', function() {
        if (selectedDate && selectedStaffId && selectedTime) {
            // 選択した情報の確認
            console.log('送信予定の予約情報:', {
                date: selectedDate,
                staffId: selectedStaffId,
                time: selectedTime,
                serviceIds: serviceData.map(s => s.service_id),
                totalDuration: serviceData.reduce((sum, s) => sum + parseInt(s.duration), 0),
                totalPrice: serviceData.reduce((sum, s) => sum + parseInt(s.price), 0)
            });
            
            // エラー表示をクリア
            const errorElement = document.getElementById('error-message');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            
            // 次へボタンを無効化
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 処理中...';
            
            // Ajax通信でセッションに保存
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'save_datetime_session.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                // ボタンを再度有効化
                const nextBtn = document.getElementById('next-btn');
                if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.innerHTML = '次へ <i class="fas fa-arrow-right"></i>';
                }
                
                if (xhr.status === 200) {
                    console.log('応答受信:', xhr.responseText);
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // 次のページへ移動
                            window.location.href = 'input_info.php';
                        } else {
                            showError(response.message || 'セッションの保存に失敗しました。');
                        }
                    } catch (e) {
                        console.error('JSON解析エラー:', e);
                        console.error('受信したレスポンス:', xhr.responseText);
                        
                        // レスポンスの長さを確認
                        if (xhr.responseText.length > 1000) {
                            showError('サーバーからの応答が不正です。ページを再読み込みしてからお試しください。');
                        } else {
                            showError('応答の解析に失敗しました: ' + xhr.responseText);
                        }
                    }
                } else {
                    showError('通信エラーが発生しました (ステータス: ' + xhr.status + ')。再度お試しください。');
                    console.error('HTTPエラー:', xhr.status, xhr.statusText);
                }
            };
            xhr.onerror = function() {
                // ボタンを再度有効化
                const nextBtn = document.getElementById('next-btn');
                if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.innerHTML = '次へ <i class="fas fa-arrow-right"></i>';
                }
                
                showError('通信エラーが発生しました。ネットワーク接続を確認してください。');
                console.error('ネットワークエラー');
            };
            
            const data = `selected_date=${encodeURIComponent(selectedDate)}&selected_staff_id=${encodeURIComponent(selectedStaffId)}&selected_time=${encodeURIComponent(selectedTime)}`;
            xhr.send(data);
            
            console.log('送信データ:', data);
        } else {
            showError('日付、スタッフ、時間をすべて選択してください。');
        }
    });
});

// 日付選択関数
function selectDate(date) {
    console.log('日付選択が呼び出されました:', date);
    
    if (!date) {
        console.error('日付が指定されていません');
        return;
    }
    
    try {
        // 以前の選択を解除
        document.querySelectorAll('.date-tab').forEach(function(tab) {
            tab.classList.remove('selected');
        });
        
        // 新しい選択を適用
        const dateElement = document.querySelector(`.date-tab[data-date="${date}"]`);
        if (dateElement) {
            dateElement.classList.add('selected');
            console.log('日付要素に選択クラスを追加しました:', date);
        } else {
            console.error(`日付要素が見つかりません: ${date}`);
            return;
        }
        
        // 選択された日付を保存
        selectedDate = date;
        
        // デバッグ情報を更新
        const debugDateElement = document.getElementById('debug-selected-date');
        if (debugDateElement) {
            debugDateElement.textContent = date;
        }
        
        // スタッフ選択セクションを表示
        const staffSection = document.getElementById('staff-selection');
        if (staffSection) {
            staffSection.style.display = 'block';
            console.log('スタッフ選択セクションを表示しました');
        } else {
            console.error('スタッフ選択セクションが見つかりません');
        }
        
        // 時間選択セクションを非表示
        const timeSection = document.getElementById('time-selection');
        if (timeSection) {
            timeSection.style.display = 'none';
        }
        
        // 選択をリセット
        selectedStaffId = null;
        selectedTime = null;
        
        // デバッグ表示をリセット
        if (document.getElementById('debug-selected-staff')) {
            document.getElementById('debug-selected-staff').textContent = '未選択';
        }
        if (document.getElementById('debug-selected-time')) {
            document.getElementById('debug-selected-time').textContent = '未選択';
        }
        
        // スタッフカードの選択状態をリセット
        document.querySelectorAll('.staff-card').forEach(function(card) {
            card.classList.remove('selected');
        });
        
        // 次へボタンの状態を更新
        updateNextButtonState();
        
        // この日に利用可能なスタッフだけをハイライト
        updateAvailableStaffForDate(date);
        
        // スクロール
        staffSection.scrollIntoView({behavior: 'smooth'});
        
        console.log('日付選択処理が完了しました:', date);
    } catch (error) {
        console.error('日付選択処理中にエラーが発生しました:', error);
        showError('予期せぬエラーが発生しました。ページを再読み込みしてください。');
    }
}

// 指定された日付に利用可能なスタッフを更新
function updateAvailableStaffForDate(date) {
    console.log('日付に基づくスタッフ可用性更新:', date);
    
    // 予約データが存在しない場合の確認
    if (!availableTimeSlots || Object.keys(availableTimeSlots).length === 0) {
        console.error('利用可能な時間枠データがありません');
        showError('予約データの取得に問題が発生しました。ページを再読み込みしてください。');
        return;
    }
    
    // 利用可能な時間枠を持つスタッフIDを収集
    const availableStaffIds = [];
    if (availableTimeSlots[date]) {
        for (const staffId in availableTimeSlots[date]) {
            if (availableTimeSlots[date][staffId] && availableTimeSlots[date][staffId].length > 0) {
                availableStaffIds.push(staffId);
            }
        }
    }
    
    console.log('利用可能なスタッフID:', availableStaffIds);
    
    // デバッグ用にシフト情報出力
    if (date in availableTimeSlots) {
        console.log(`日付 ${date} の予約可能時間枠:`, availableTimeSlots[date]);
    } else {
        console.log(`日付 ${date} の予約可能時間枠はありません`);
    }
    
    // 処理済みのスタッフIDを追跡
    const processedStaffIds = new Set();
    
    // 各スタッフカードを更新
    document.querySelectorAll('.staff-card').forEach(function(card) {
        // スタッフIDを取得
        const staffId = card.getAttribute('data-staff-id');
        
        if (!staffId) {
            console.error('スタッフIDが取得できません', card);
            return;
        }
        
        // 指名なしは常に選択可能
        if (staffId === '0') {
            // 指名なしカードを利用可能としてマーク
            card.classList.remove('unavailable');
            if (availableStaffIds.length > 0) {
                card.classList.add('available');
            } else {
                card.classList.remove('available');
            }
            return;
        }
        
        // 同じスタッフIDが重複処理されないようにチェック
        if (processedStaffIds.has(staffId)) {
            // このスタッフはすでに処理済み - カードを非表示にする
            card.style.display = 'none';
            return;
        }
        
        // 処理したスタッフIDを記録
        processedStaffIds.add(staffId);
        
        // 利用可能かどうかでクラスを更新
        if (availableStaffIds.includes(staffId)) {
            card.classList.remove('unavailable');
            card.classList.add('available');
            
            // 利用可能な時間枠の数を表示
            const timeSlotsCount = availableTimeSlots[date][staffId].length;
            const slotsCountElement = card.querySelector('.available-slots-count');
            if (slotsCountElement) {
                slotsCountElement.innerHTML = `<i class="far fa-clock"></i> 予約可能枠: ${timeSlotsCount}`;
            }
        } else {
            card.classList.remove('available');
            card.classList.add('unavailable');
            
            const slotsCountElement = card.querySelector('.available-slots-count');
            if (slotsCountElement) {
                slotsCountElement.innerHTML = `<i class="far fa-clock"></i> 予約枠なし`;
            }
        }
    });
}

// スタッフ選択関数
function selectStaff(staffId) {
    console.log('スタッフ選択:', staffId);
    
    // 日付が選択されていない場合はエラー
    if (!selectedDate) {
        console.error('日付が選択されていません');
        showError('最初に日付を選択してください。');
        return;
    }
    
    // 選択不可能なスタッフが選択された場合は処理しない
    if (staffId !== '0') {
        const staffCard = document.querySelector(`.staff-card[data-staff-id="${staffId}"]`);
        if (staffCard && staffCard.classList.contains('unavailable')) {
            console.log('利用不可のスタッフが選択されました');
            showError('選択された日付にはこのスタッフの予約可能枠がありません。別のスタッフを選択してください。');
            return;
        }
    }
    
    // 以前の選択を解除
    document.querySelectorAll('.staff-card').forEach(function(card) {
        card.classList.remove('selected');
    });
    
    // クリックされたスタッフカードを選択状態にする
    if (staffId === '0') {
        // 指名なしの場合
        document.querySelector('.staff-card[data-staff-id="0"]').classList.add('selected');
    } else {
        // 特定のスタッフを選択した場合
        const staffCard = document.querySelector(`.staff-card[data-staff-id="${staffId}"]`);
        if (staffCard) {
            staffCard.classList.add('selected');
        } else {
            console.error(`スタッフカードが見つかりません: ${staffId}`);
            return;
        }
    }
    
    // 選択されたスタッフIDを保存
    selectedStaffId = staffId;
    
    // スタッフ名を特定
    let staffName = '指名なし';
    if (staffId !== '0') {
        const staffObj = staffData.find(s => s.staff_id == staffId);
        if (staffObj) {
            staffName = staffObj.name;
        }
    }
    
    // デバッグ情報を更新
    const debugStaffElement = document.getElementById('debug-selected-staff');
    if (debugStaffElement) {
        debugStaffElement.textContent = staffName;
    }
    
    // 時間選択セクションを表示
    const timeSection = document.getElementById('time-selection');
    if (timeSection) {
        timeSection.style.display = 'block';
    }
    
    // 時間枠を更新
    updateTimeSlots(selectedDate, staffId);
    
    // 次へボタンの状態を更新
    updateNextButtonState();
    
    // スクロール
    if (timeSection) {
        timeSection.scrollIntoView({behavior: 'smooth'});
    }
}

// 時間枠を更新
function updateTimeSlots(date, staffId) {
    console.log('時間枠更新:', { date, staffId });
    
    // 日付またはスタッフIDが選択されていない場合はエラー
    if (!date || !staffId) {
        console.error('日付またはスタッフIDが選択されていません');
        return;
    }
    
    // 時間枠コンテナを取得
    const timeGrid = document.getElementById('time-slots-grid');
    if (!timeGrid) {
        console.error('時間枠グリッド要素が見つかりません');
        return;
    }
    
    // 時間枠コンテナをクリア
    timeGrid.innerHTML = '';
    
    // 時間枠のデータを取得
    let timeSlots = [];
    
    try {
        // 指名なしの場合は、すべてのスタッフの時間枠を集約
        if (staffId === '0') {
            // 全スタッフの時間枠を集約
            const allTimeSlotsSet = new Set();
            
            if (availableTimeSlots[date]) {
                for (const staffId in availableTimeSlots[date]) {
                    if (availableTimeSlots[date][staffId] && availableTimeSlots[date][staffId].length > 0) {
                        availableTimeSlots[date][staffId].forEach(slot => allTimeSlotsSet.add(slot));
                    }
                }
            }
            
            timeSlots = Array.from(allTimeSlotsSet).sort();
        } else {
            // 特定のスタッフの時間枠を取得
            if (availableTimeSlots[date] && availableTimeSlots[date][staffId]) {
                timeSlots = availableTimeSlots[date][staffId].sort();
            }
        }
        
        console.log('利用可能な時間枠:', timeSlots);
    } catch (error) {
        console.error('時間枠の処理中にエラーが発生しました:', error);
        showError('予約データの処理中にエラーが発生しました。ページを再読み込みしてください。');
        return;
    }
    
    // 時間枠が一つもない場合のメッセージ
    if (timeSlots.length === 0) {
        const noSlots = document.createElement('div');
        noSlots.className = 'no-time-slots';
        noSlots.textContent = '選択した日付とスタッフの組み合わせでは、予約可能な時間がありません。';
        timeGrid.appendChild(noSlots);
        return;
    }
    
    // 時間枠ごとに要素を作成
    timeSlots.forEach(time => {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = time;
        timeSlot.onclick = function() {
            selectTime(time);
        };
        timeGrid.appendChild(timeSlot);
    });
}

// 時間選択関数
function selectTime(time) {
    console.log('時間選択:', time);
    
    // 以前の選択を解除
    document.querySelectorAll('.time-slot').forEach(function(slot) {
        slot.classList.remove('selected');
    });
    
    // クリックされた時間を選択状態にする
    document.querySelectorAll('.time-slot').forEach(function(slot) {
        if (slot.textContent === time) {
            slot.classList.add('selected');
        }
    });
    
    // 選択された時間を保存
    selectedTime = time;
    
    // デバッグ情報を更新
    document.getElementById('debug-selected-time').textContent = time;
    
    // 次へボタンの状態を更新
    updateNextButtonState();
}

// 次へボタンの状態を更新
function updateNextButtonState() {
    const isValid = selectedDate && selectedStaffId && selectedTime;
    document.getElementById('next-btn').disabled = !isValid;
    
    console.log('選択状態更新:', { 
        isValid,
        selectedDate,
        selectedStaffId,
        selectedTime
    });
}

// エラーメッセージを表示
function showError(message) {
    document.getElementById('error-text').textContent = message;
    document.getElementById('error-message').style.display = 'block';
    
    console.error('エラーメッセージ表示:', message);
    
    // 5秒後に自動的に消える
    setTimeout(function() {
        document.getElementById('error-message').style.display = 'none';
    }, 5000);
}

// デバッグモードの切り替え
function toggleDebugMode() {
    const debugSection = document.getElementById('debug-section');
    if (debugSection.style.display === 'none') {
        debugSection.style.display = 'block';
        localStorage.setItem('debugMode', 'enabled');
    } else {
        debugSection.style.display = 'none';
        localStorage.setItem('debugMode', 'disabled');
    }
}

// テスト選択実行（デバッグ用）
function testCalendarClick() {
    console.log('テスト選択実行');
    
    // 現在利用可能な日付を探す
    let availableDate = null;
    
    // 日付の取得
    for (const date in availableTimeSlots) {
        if (Object.keys(availableTimeSlots[date]).length > 0) {
            availableDate = date;
            break;
        }
    }
    
    if (!availableDate) {
        console.log('利用可能な日付が見つかりません');
        showError('テスト選択用の利用可能な日付が見つかりません。');
        return;
    }
    
    console.log('利用可能な日付:', availableDate);
    
    // 日付選択
    selectDate(availableDate);
    
    // スタッフを探す
    let availableStaffId = null;
    
    for (const staffId in availableTimeSlots[availableDate]) {
        if (availableTimeSlots[availableDate][staffId] && 
            availableTimeSlots[availableDate][staffId].length > 0) {
            availableStaffId = staffId;
            break;
        }
    }
    
    if (!availableStaffId) {
        console.log('利用可能なスタッフが見つかりません');
        // スタッフが見つからなければ指名なしを選択
        availableStaffId = '0';
    }
    
    console.log('利用可能なスタッフID:', availableStaffId);
    
    // スタッフ選択
    selectStaff(availableStaffId);
    
    // 時間枠を取得
    let availableTime = null;
    
    if (availableStaffId === '0') {
        // 指名なしの場合は全スタッフの時間枠を確認
        for (const staffId in availableTimeSlots[availableDate]) {
            if (availableTimeSlots[availableDate][staffId] && 
                availableTimeSlots[availableDate][staffId].length > 0) {
                availableTime = availableTimeSlots[availableDate][staffId][0];
                break;
            }
        }
    } else {
        // 特定のスタッフの場合
        if (availableTimeSlots[availableDate][availableStaffId] && 
            availableTimeSlots[availableDate][availableStaffId].length > 0) {
            availableTime = availableTimeSlots[availableDate][availableStaffId][0];
        }
    }
    
    if (!availableTime) {
        console.log('利用可能な時間枠が見つかりません');
        showError('テスト選択用の利用可能な時間枠が見つかりません。');
        return;
    }
    
    console.log('利用可能な時間:', availableTime);
    
    // 時間選択
    setTimeout(function() {
        selectTime(availableTime);
        
        console.log('テスト選択完了', {
            date: availableDate,
            staffId: availableStaffId,
            time: availableTime
        });
    }, 500);
}

// 時間枠表示処理
function showTimeSlots() {
    const timeSlotsGrid = document.getElementById('time-slots-grid');
    timeSlotsGrid.innerHTML = '';
    
    if (!selectedDate || !selectedStaffId) {
        return;
    }
    
    // 指名なしの場合は全スタッフの時間枠を統合
    let availableSlots = [];
    
    if (selectedStaffId === '0') {
        // 全スタッフの時間枠を結合
        if (availableTimeSlots[selectedDate]) {
            Object.keys(availableTimeSlots[selectedDate]).forEach(staffId => {
                availableTimeSlots[selectedDate][staffId].forEach(time => {
                    if (!availableSlots.includes(time)) {
                        availableSlots.push(time);
                    }
                });
            });
        }
    } else {
        // 特定のスタッフの時間枠
        if (availableTimeSlots[selectedDate] && availableTimeSlots[selectedDate][selectedStaffId]) {
            availableSlots = availableTimeSlots[selectedDate][selectedStaffId];
        }
    }
    
    // 時間順にソート
    availableSlots.sort();
    
    if (availableSlots.length === 0) {
        // 利用可能な時間枠がない場合
        const noSlots = document.createElement('div');
        noSlots.className = 'no-time-slots';
        noSlots.textContent = '選択した日付とスタッフの組み合わせでは、予約可能な時間がありません。';
        timeSlotsGrid.appendChild(noSlots);
        return;
    }
    
    // 時間枠ごとに要素を作成
    availableSlots.forEach(time => {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        timeSlot.textContent = time;
        timeSlot.onclick = function() {
            selectTime(time);
        };
        timeSlotsGrid.appendChild(timeSlot);
    });
}

// 時間選択処理
function selectTime(time) {
    // 以前の選択を解除
    document.querySelectorAll('.time-slot').forEach(function(slot) {
        slot.classList.remove('selected');
    });
    
    // 新しい選択を適用
    document.querySelectorAll('.time-slot').forEach(function(slot) {
        if (slot.textContent === time) {
            slot.classList.add('selected');
        }
    });
    
    // 選択された時間を保存
    selectedTime = time;
    
    // デバッグ表示を更新
    if (document.getElementById('debug-selected-time')) {
        document.getElementById('debug-selected-time').textContent = time;
    }
    
    // 次へボタンを有効化
    document.getElementById('next-btn').disabled = false;
} 