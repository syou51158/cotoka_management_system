document.addEventListener('DOMContentLoaded', function() {
    // 基本設定
    const appointmentsList = document.getElementById('appointment-list');
    const loadingIndicator = document.getElementById('loading-appointments');
    const noAppointmentsMessage = document.getElementById('no-appointments');
    const newAppointmentBtn = document.getElementById('new-appointment-btn');
    const appointmentModal = new bootstrap.Modal(document.getElementById('appointment-modal'));
    const appointmentForm = document.getElementById('appointment-form');
    const saveAppointmentBtn = document.getElementById('save-appointment-btn');
    const deleteAppointmentBtn = document.getElementById('delete-appointment-btn');
    const detailsModal = new bootstrap.Modal(document.getElementById('appointment-details-modal'));
    const editAppointmentBtn = document.getElementById('edit-appointment-btn');
    
    // 表示切り替え関連
    const viewToggleBtn = document.getElementById('view-toggle-btn');
    const calendarView = document.getElementById('calendar-view');
    const listView = document.getElementById('list-view');
    
    // カレンダー関連
    const calendarTitle = document.getElementById('calendar-title');
    const dayHeaders = document.getElementById('day-headers');
    const calendarBody = document.getElementById('calendar-body');
    const prevWeekBtn = document.getElementById('prev-week');
    const nextWeekBtn = document.getElementById('next-week');
    const todayBtn = document.getElementById('today-btn');
    
    // カレンダー表示の状態管理
    let currentStartDate = new Date();
    currentStartDate.setDate(currentStartDate.getDate() - currentStartDate.getDay()); // 今週の日曜日に設定
    
    // フィルター関連
    const dateFilter = document.getElementById('date-filter');
    const statusFilter = document.getElementById('status-filter');
    const staffFilter = document.getElementById('staff-filter');
    const searchTermInput = document.getElementById('search-term');
    const applyFiltersBtn = document.getElementById('apply-filters');
    const resetFiltersBtn = document.getElementById('reset-filters');
    
    // ドロップダウンの初期化
    let staffMembers = [];
    let services = [];
    let customers = [];
    
    // フラットピッカーの初期化
    flatpickr(dateFilter, {
        locale: 'ja',
        mode: 'range',
        dateFormat: 'Y-m-d'
    });
    
    flatpickr("#start_time, #end_time", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",
        time_24hr: true,
        minuteIncrement: 15
    });
    
    flatpickr("#appointment_date", {
        locale: 'ja',
        dateFormat: 'Y-m-d'
    });
    
    // 表示切り替え機能
    viewToggleBtn.addEventListener('click', function() {
        const currentView = this.getAttribute('data-view');
        
        if (currentView === 'list') {
            // リストビューからカレンダービューへ
            listView.classList.add('d-none');
            calendarView.classList.remove('d-none');
            this.setAttribute('data-view', 'calendar');
            this.innerHTML = '<i class="fas fa-list"></i> リスト表示';
            renderCalendar();
        } else {
            // カレンダービューからリストビューへ
            calendarView.classList.add('d-none');
            listView.classList.remove('d-none');
            this.setAttribute('data-view', 'list');
            this.innerHTML = '<i class="fas fa-calendar-alt"></i> カレンダー表示';
        }
    });
    
    // カレンダーナビゲーション
    prevWeekBtn.addEventListener('click', function() {
        currentStartDate.setDate(currentStartDate.getDate() - 7);
        renderCalendar();
    });
    
    nextWeekBtn.addEventListener('click', function() {
        currentStartDate.setDate(currentStartDate.getDate() + 7);
        renderCalendar();
    });
    
    todayBtn.addEventListener('click', function() {
        const today = new Date();
        currentStartDate = new Date(today);
        currentStartDate.setDate(currentStartDate.getDate() - currentStartDate.getDay());
        renderCalendar();
    });
    
    // カレンダーの描画
    function renderCalendar() {
        // カレンダータイトルの更新
        const endDate = new Date(currentStartDate);
        endDate.setDate(endDate.getDate() + 6);
        
        const monthNames = ["1月", "2月", "3月", "4月", "5月", "6月", "7月", "8月", "9月", "10月", "11月", "12月"];
        const startMonth = monthNames[currentStartDate.getMonth()];
        const endMonth = monthNames[endDate.getMonth()];
        
        if (startMonth === endMonth) {
            calendarTitle.textContent = `${currentStartDate.getFullYear()}年${startMonth} ${currentStartDate.getDate()}日～${endDate.getDate()}日`;
        } else {
            calendarTitle.textContent = `${currentStartDate.getFullYear()}年${startMonth}${currentStartDate.getDate()}日～${endMonth}${endDate.getDate()}日`;
        }
        
        // 曜日ヘッダーの生成
        dayHeaders.innerHTML = '';
        const dayNames = ["日", "月", "火", "水", "木", "金", "土"];
        
        // 時間列のヘッダー
        const timeColumnHeader = document.createElement('div');
        timeColumnHeader.className = 'time-column';
        dayHeaders.appendChild(timeColumnHeader);
        
        // 曜日列のヘッダー
        const daysHeaderContainer = document.createElement('div');
        daysHeaderContainer.className = 'day-headers';
        dayHeaders.appendChild(daysHeaderContainer);
        
        for (let i = 0; i < 7; i++) {
            const date = new Date(currentStartDate);
            date.setDate(date.getDate() + i);
            
            const dayHeader = document.createElement('div');
            dayHeader.className = 'day-header';
            
            // 今日の日付を強調表示
            const today = new Date();
            if (date.toDateString() === today.toDateString()) {
                dayHeader.classList.add('today');
            }
            
            // 土日の色分け
            if (i === 0) {
                dayHeader.style.color = '#dc3545'; // 日曜日は赤
            } else if (i === 6) {
                dayHeader.style.color = '#0d6efd'; // 土曜日は青
            }
            
            dayHeader.innerHTML = `
                ${dayNames[i]}曜日<br>
                <span class="day-number">${date.getDate()}</span>
            `;
            daysHeaderContainer.appendChild(dayHeader);
        }
        
        // カレンダー本体の生成
        calendarBody.innerHTML = '';
        
        // 時間列
        const timeSlots = document.createElement('div');
        timeSlots.className = 'time-slots';
        
        // 営業時間（6:00～24:00を想定）
        const startHour = 6;
        const endHour = 24;
        
        for (let hour = startHour; hour < endHour; hour++) {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = `${hour}:00`;
            timeSlots.appendChild(timeSlot);
        }
        
        calendarBody.appendChild(timeSlots);
        
        // 日付列
        const daysContainer = document.createElement('div');
        daysContainer.className = 'days-container';
        
        // 各日付列を生成
        for (let i = 0; i < 7; i++) {
            const date = new Date(currentStartDate);
            date.setDate(date.getDate() + i);
            const formattedDate = date.toISOString().split('T')[0]; // YYYY-MM-DD形式
            
            const dayColumn = document.createElement('div');
            dayColumn.className = 'day-column';
            dayColumn.setAttribute('data-date', formattedDate);
            
            // 今日の列を強調表示
            const today = new Date();
            if (date.toDateString() === today.toDateString()) {
                dayColumn.classList.add('today');
            }
            
            // 時間マーカーを追加
            for (let hour = startHour; hour < endHour; hour++) {
                // 1時間ごとの区切り線
                const hourMarker = document.createElement('div');
                hourMarker.className = 'hour-marker';
                hourMarker.style.top = `${(hour - startHour) * 60}px`;
                dayColumn.appendChild(hourMarker);
                
                // 30分ごとの区切り線
                const halfHourMarker = document.createElement('div');
                halfHourMarker.className = 'half-hour-marker';
                halfHourMarker.style.top = `${(hour - startHour) * 60 + 30}px`;
                dayColumn.appendChild(halfHourMarker);
            }
            
            // 現在時刻のマーカー（今日の列のみ）
            if (date.toDateString() === today.toDateString()) {
                const hours = today.getHours();
                const minutes = today.getMinutes();
                const topPosition = (hours - startHour) * 60 + minutes;
                
                if (hours >= startHour && hours < endHour) {
                    const currentTimeMarker = document.createElement('div');
                    currentTimeMarker.className = 'current-time-marker';
                    currentTimeMarker.style.top = `${topPosition}px`;
                    dayColumn.appendChild(currentTimeMarker);
                }
            }
            
            // 予約イベントの追加
            renderAppointmentsForDate(dayColumn, formattedDate);
            
            daysContainer.appendChild(dayColumn);
        }
        
        calendarBody.appendChild(daysContainer);
    }
    
    // 特定の日付の予約を描画
    function renderAppointmentsForDate(dayColumn, date) {
        // その日の予約をフィルタリング
        const appointments = CONFIG.appointments.filter(appointment => 
            appointment.appointment_date === date
        );
        
        // 開始時間
        const startHour = 6;
        
        // 各予約をカレンダーに追加
        appointments.forEach(appointment => {
            // 時間の計算
            const startTimeParts = appointment.start_time.split(':');
            const startHours = parseInt(startTimeParts[0], 10);
            const startMinutes = parseInt(startTimeParts[1], 10);
            
            const endTimeParts = appointment.end_time.split(':');
            const endHours = parseInt(endTimeParts[0], 10);
            const endMinutes = parseInt(endTimeParts[1], 10);
            
            // カレンダー上の位置とサイズを計算
            const topPosition = (startHours - startHour) * 60 + startMinutes;
            const height = (endHours - startHours) * 60 + (endMinutes - startMinutes);
            
            // 予約イベント要素の作成
            const appointmentEvent = document.createElement('div');
            appointmentEvent.className = `appointment-event event-${appointment.status} event-${appointment.appointment_type}`;
            appointmentEvent.style.top = `${topPosition}px`;
            appointmentEvent.style.height = `${height}px`;
            appointmentEvent.setAttribute('data-appointment-id', appointment.appointment_id);
            
            // 予約内容に応じた表示
            let eventContent = '';
            
            if (appointment.appointment_type === 'customer') {
                const customerName = appointment.customer_last_name ? 
                    `${appointment.customer_last_name} ${appointment.customer_first_name}` : 
                    '顧客未設定';
                
                eventContent = `
                    <div class="event-customer">${customerName}</div>
                    <div class="event-service">${appointment.service_name || '未設定'}</div>
                    <div class="event-time">${startHours}:${startMinutes.toString().padStart(2, '0')} - ${endHours}:${endMinutes.toString().padStart(2, '0')}</div>
                `;
            } else {
                // 顧客以外の予約（業務、休憩など）
                const typeLabels = {
                    'internal': '業務',
                    'break': '休憩',
                    'other': 'その他'
                };
                
                eventContent = `
                    <div class="event-customer">${typeLabels[appointment.appointment_type] || appointment.appointment_type}</div>
                    <div class="event-service">${appointment.task_description || ''}</div>
                    <div class="event-time">${startHours}:${startMinutes.toString().padStart(2, '0')} - ${endHours}:${endMinutes.toString().padStart(2, '0')}</div>
                `;
            }
            
            appointmentEvent.innerHTML = eventContent;
            
            // クリックイベントの追加
            appointmentEvent.addEventListener('click', function() {
                openAppointmentDetails(appointment);
            });
            
            dayColumn.appendChild(appointmentEvent);
        });
    }
    
    // 予約タイプによるフィールド表示切り替え
    document.getElementById('appointment_type').addEventListener('change', function() {
        const customerFields = document.querySelectorAll('.customer-field');
        const internalFields = document.querySelectorAll('.internal-field');
        
        if (this.value === 'customer') {
            customerFields.forEach(field => field.style.display = 'block');
            internalFields.forEach(field => field.style.display = 'none');
        } else if (this.value === 'internal') {
            customerFields.forEach(field => field.style.display = 'none');
            internalFields.forEach(field => field.style.display = 'block');
        } else {
            customerFields.forEach(field => field.style.display = 'none');
            internalFields.forEach(field => field.style.display = 'none');
        }
    });
    
    // 統計情報の取得と表示
    async function loadStatistics() {
        try {
            // 初期値があればそれを表示
            if (typeof INITIAL_STATS !== 'undefined') {
                document.getElementById('today-count').textContent = INITIAL_STATS.today;
                document.getElementById('week-count').textContent = INITIAL_STATS.week;
                document.getElementById('pending-count').textContent = INITIAL_STATS.pending;
                document.getElementById('month-count').textContent = INITIAL_STATS.month;
                return;
            }
            
            // 初期値がなければAPIから取得
            const response = await fetch(API_ENDPOINTS.GET_APPOINTMENT_STATS + `?salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('統計情報の取得に失敗しました');
            
            const stats = await response.json();
            
            document.getElementById('today-count').textContent = stats.today || '0';
            document.getElementById('week-count').textContent = stats.week || '0';
            document.getElementById('pending-count').textContent = stats.pending || '0';
            document.getElementById('month-count').textContent = stats.month || '0';
        } catch (error) {
            console.error('統計情報取得エラー:', error);
        }
    }
    
    // スタッフ情報の取得
    async function loadStaffMembers() {
        try {
            const response = await fetch(API_ENDPOINTS.GET_STAFF + `?salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('スタッフ情報の取得に失敗しました');
            
            staffMembers = await response.json();
            
            // スタッフフィルターの更新
            const staffFilterSelect = document.getElementById('staff-filter');
            staffFilterSelect.innerHTML = '<option value="">すべて</option>';
            
            staffMembers.forEach(staff => {
                const option = document.createElement('option');
                option.value = staff.staff_id;
                option.textContent = `${staff.last_name} ${staff.first_name}`;
                staffFilterSelect.appendChild(option);
            });
            
            // 予約モーダルのスタッフドロップダウン更新
            const staffSelect = document.getElementById('staff_id');
            staffSelect.innerHTML = '<option value="">-- 選択してください --</option>';
            
            staffMembers.forEach(staff => {
                const option = document.createElement('option');
                option.value = staff.staff_id;
                option.textContent = `${staff.last_name} ${staff.first_name}`;
                staffSelect.appendChild(option);
            });
        } catch (error) {
            console.error('スタッフ情報取得エラー:', error);
        }
    }
    
    // サービス情報の取得
    async function loadServices() {
        try {
            const response = await fetch(API_ENDPOINTS.GET_SERVICES + `?salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('サービス情報の取得に失敗しました');
            
            services = await response.json();
            
            // 予約モーダルのサービスドロップダウン更新
            const serviceSelect = document.getElementById('service_id');
            serviceSelect.innerHTML = '<option value="">-- 選択してください --</option>';
            
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.service_id;
                option.dataset.duration = service.duration;
                option.textContent = `${service.name} (${service.duration}分)`;
                serviceSelect.appendChild(option);
            });
        } catch (error) {
            console.error('サービス情報取得エラー:', error);
        }
    }
    
    // 顧客情報の取得
    async function loadCustomers() {
        try {
            const response = await fetch(API_ENDPOINTS.GET_CUSTOMERS + `?salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('顧客情報の取得に失敗しました');
            
            customers = await response.json();
            
            // 予約モーダルの顧客ドロップダウン更新
            const customerSelect = document.getElementById('customer_id');
            customerSelect.innerHTML = '<option value="">-- 選択してください --</option>';
            
            customers.forEach(customer => {
                const option = document.createElement('option');
                option.value = customer.customer_id;
                option.textContent = `${customer.last_name} ${customer.first_name}`;
                customerSelect.appendChild(option);
            });
        } catch (error) {
            console.error('顧客情報取得エラー:', error);
        }
    }
    
    // 予約情報の取得とリスト表示
    async function loadAppointments(filters = {}) {
        // デフォルトのフィルター
        const defaultFilters = {
            date_from: '',
            date_to: '',
            staff_id: '',
            status: '',
            search: '',
            page: 1,
            limit: 10
        };
        
        // フィルターのマージ
        const activeFilters = { ...defaultFilters, ...filters };
        
        // ローディング表示
        loadingIndicator.classList.remove('d-none');
        noAppointmentsMessage.classList.add('d-none');
        
        // 予約リストをクリア
        const appointmentsContainer = document.querySelectorAll('.appointment-card');
        appointmentsContainer.forEach(card => card.parentElement.remove());
        
        try {
            // URLパラメータの構築
            let queryParams = new URLSearchParams();
            queryParams.append('salon_id', CONFIG.salon_id);
            
            Object.keys(activeFilters).forEach(key => {
                if (activeFilters[key]) {
                    queryParams.append(key, activeFilters[key]);
                }
            });
            
            // 予約データの取得
            console.log('API呼び出し:', `${API_ENDPOINTS.GET_APPOINTMENTS}?${queryParams.toString()}`);
            const response = await fetch(`${API_ENDPOINTS.GET_APPOINTMENTS}?${queryParams.toString()}`);
            if (!response.ok) throw new Error('予約情報の取得に失敗しました');
            
            const data = await response.json();
            console.log('取得した予約データ:', data);
            const appointments = data.appointments || [];
            
            // ローディング表示を非表示
            loadingIndicator.classList.add('d-none');
            
            // 予約がない場合のメッセージ表示
            if (appointments.length === 0) {
                noAppointmentsMessage.classList.remove('d-none');
                return;
            }
            
            // 予約リストの表示
            appointments.forEach(appointment => {
                const appointmentCard = createAppointmentCard(appointment);
                appointmentsList.appendChild(appointmentCard);
            });
            
            // ページネーションの更新
            updatePagination(data.total_count, activeFilters.page, activeFilters.limit);
        } catch (error) {
            console.error('予約情報取得エラー:', error);
            loadingIndicator.classList.add('d-none');
            
            // エラーメッセージ表示
            const errorDiv = document.createElement('div');
            errorDiv.className = 'col-12';
            errorDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> 
                    予約情報の取得中にエラーが発生しました: ${error.message}
                </div>
            `;
            appointmentsList.appendChild(errorDiv);
        }
    }
    
    // 予約カードの作成
    function createAppointmentCard(appointment) {
        // 日付と時間のフォーマット
        const appointmentDate = new Date(appointment.appointment_date);
        const formattedDate = appointmentDate.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            weekday: 'short'
        });
        
        // ステータスに応じたバッジとカードカラー
        let statusBadgeClass = 'bg-secondary';
        let statusText = '不明';
        
        switch(appointment.status) {
            case 'scheduled':
                statusBadgeClass = 'bg-warning text-dark';
                statusText = '予約済み';
                break;
            case 'confirmed':
                statusBadgeClass = 'bg-info text-dark';
                statusText = '確定';
                break;
            case 'completed':
                statusBadgeClass = 'bg-success';
                statusText = '完了';
                break;
            case 'cancelled':
                statusBadgeClass = 'bg-danger';
                statusText = 'キャンセル';
                break;
            case 'no-show':
                statusBadgeClass = 'bg-secondary';
                statusText = '無断キャンセル';
                break;
        }
        
        // カードの作成
        const cardDiv = document.createElement('div');
        cardDiv.className = `col-md-6 col-xl-4 mb-4`;
        
        cardDiv.innerHTML = `
            <div class="card appointment-card status-${appointment.status}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">${formattedDate}</h5>
                        <span class="badge ${statusBadgeClass} status-badge">${statusText}</span>
                    </div>
                    <div class="card-text">
                        <p class="mb-1"><i class="fas fa-clock text-muted me-2"></i> ${appointment.start_time} - ${appointment.end_time}</p>
                        ${appointment.staff_first_name ? `
                            <p class="mb-1"><i class="fas fa-user-tie text-muted me-2"></i> ${appointment.staff_last_name} ${appointment.staff_first_name}</p>
                        ` : ''}
                        ${appointment.customer_id ? `
                            <p class="mb-1"><i class="fas fa-user text-muted me-2"></i> ${appointment.customer_last_name || ''} ${appointment.customer_first_name || ''}</p>
                        ` : ''}
                        ${appointment.service_name ? `
                            <p class="mb-1"><i class="fas fa-concierge-bell text-muted me-2"></i> ${appointment.service_name}</p>
                        ` : ''}
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button class="btn btn-sm btn-outline-info view-details-btn">
                            <i class="fas fa-eye"></i> 詳細
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // 詳細ボタンのイベントリスナー
        cardDiv.querySelector('.view-details-btn').addEventListener('click', async () => {
            try {
                // 詳細表示のために最新のデータを取得
                const response = await fetch(`${API_ENDPOINTS.GET_APPOINTMENTS}?salon_id=${CONFIG.salon_id}&appointment_id=${appointment.appointment_id}`);
                if (!response.ok) throw new Error('予約データの取得に失敗しました');
                
                const data = await response.json();
                if (!data.appointments || data.appointments.length === 0) {
                    throw new Error('予約データが見つかりませんでした');
                }
                
                // 最新のデータで詳細モーダルを開く
                openAppointmentDetails(data.appointments[0]);
            } catch (error) {
                console.error('予約詳細取得エラー:', error);
                alert('予約詳細の取得中にエラーが発生しました: ' + error.message);
            }
        });
        
        return cardDiv;
    }
    
    // 予約詳細を開く
    function openAppointmentDetails(appointment) {
        // 予約詳細モーダルの内容を設定
        const detailsContent = document.getElementById('appointment-details-content');
        
        // 予約タイプに応じた表示内容を変更
        let contentHTML = '';
        
        if (appointment.appointment_type === 'customer') {
            // 顧客予約の場合
            contentHTML = `
                <div class="appointment-detail-header status-${appointment.status}">
                    <h4 class="mb-2">${appointment.customer_name || '顧客未設定'}</h4>
                    <p class="mb-0">${new Date(appointment.appointment_date).toLocaleDateString('ja-JP')} ${appointment.start_time} - ${appointment.end_time}</p>
                </div>
                <div class="appointment-detail-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>サービス:</strong> ${appointment.service_name || '未設定'}
                        </div>
                        <div class="col-md-6">
                            <strong>担当スタッフ:</strong> ${appointment.staff_name || '未設定'}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>ステータス:</strong> 
                            <span class="badge status-badge 
                                ${appointment.status === 'scheduled' ? 'bg-secondary' : ''}
                                ${appointment.status === 'confirmed' ? 'bg-primary' : ''}
                                ${appointment.status === 'completed' ? 'bg-success' : ''}
                                ${appointment.status === 'cancelled' ? 'bg-danger' : ''}
                                ${appointment.status === 'no-show' ? 'bg-warning' : ''}
                            ">
                                ${formatStatus(appointment.status)}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>予約ID:</strong> ${appointment.appointment_id}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>メモ:</strong> 
                            <p class="mb-0">${appointment.notes ? nl2br(appointment.notes) : '特になし'}</p>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // 顧客以外の予約（業務、休憩など）
            const typeLabels = {
                'internal': '業務',
                'break': '休憩',
                'other': 'その他'
            };
            
            contentHTML = `
                <div class="appointment-detail-header status-${appointment.status}">
                    <h4 class="mb-2">${typeLabels[appointment.appointment_type] || appointment.appointment_type}</h4>
                    <p class="mb-0">${new Date(appointment.appointment_date).toLocaleDateString('ja-JP')} ${appointment.start_time} - ${appointment.end_time}</p>
                </div>
                <div class="appointment-detail-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>担当スタッフ:</strong> ${appointment.staff_name || '未設定'}
                        </div>
                        <div class="col-md-6">
                            <strong>予約ID:</strong> ${appointment.appointment_id}
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>内容:</strong> 
                            <p class="mb-0">${appointment.task_description || '特になし'}</p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong>メモ:</strong> 
                            <p class="mb-0">${appointment.notes ? nl2br(appointment.notes) : '特になし'}</p>
                        </div>
                    </div>
                </div>
            `;
        }
        
        detailsContent.innerHTML = contentHTML;
        
        // 編集ボタンに予約IDを設定
        document.getElementById('edit-appointment-btn').setAttribute('data-appointment-id', appointment.appointment_id);
        
        // モーダルを表示
        detailsModal.show();
    }

    // 予約の保存処理
    async function saveAppointment() {
        try {
            // フォームデータの取得
            const formData = new FormData(appointmentForm);
            
            // バリデーション
            let isValid = true;
            let errorMessage = "";
            
            // 必須項目のチェック
            const appointment_type = formData.get('appointment_type');
            const appointment_date = formData.get('appointment_date');
            const start_time = formData.get('start_time');
            const end_time = formData.get('end_time');
            const staff_id = formData.get('staff_id');
            
            if (!appointment_date) {
                isValid = false;
                errorMessage += "予約日は必須です。";
            }
            
            if (!start_time || !end_time) {
                isValid = false;
                errorMessage += "開始時間と終了時間は必須です。";
            }
            
            if (!staff_id) {
                isValid = false;
                errorMessage += "担当スタッフは必須です。";
            }
            
            // 顧客予約の場合、顧客とサービスは必須
            if (appointment_type === 'customer') {
                const customer_id = formData.get('customer_id');
                const service_id = formData.get('service_id');
                
                if (!customer_id) {
                    isValid = false;
                    errorMessage += "顧客は必須です。";
                }
                
                if (!service_id) {
                    isValid = false;
                    errorMessage += "サービスは必須です。";
                }
            } else if (appointment_type === 'internal' && !formData.get('task_description')) {
                isValid = false;
                errorMessage += "業務内容は必須です。";
            }
            
            // 時間の妥当性をチェック
            if (start_time && end_time) {
                const start = new Date(`${appointment_date}T${start_time}`);
                const end = new Date(`${appointment_date}T${end_time}`);
                
                if (start >= end) {
                    isValid = false;
                    errorMessage += "終了時間は開始時間より後である必要があります。";
                }
            }
            
            if (!isValid) {
                alert(errorMessage);
                return;
            }
            
            // 保存処理中はボタンを無効化
            saveAppointmentBtn.disabled = true;
            saveAppointmentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
            
            // APIリクエスト
            const response = await fetch(API_ENDPOINTS.SAVE_APPOINTMENT, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 成功時はモーダルを閉じて予約リストを更新
                appointmentModal.hide();
                
                // 成功メッセージを表示
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i> 予約が保存されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                `;
                
                appointmentsList.insertAdjacentElement('beforebegin', alertDiv);
                
                // 5秒後に自動的にアラートを消す
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
                
                // 予約リストを更新
                loadAppointments(getCurrentFilters());
                
                // カレンダーも更新
                if (calendarView && !calendarView.classList.contains('d-none')) {
                    renderCalendar();
                }
                
                // 統計情報も更新
                // await loadStatistics(); // 一時的に無効化
                console.log('統計情報の初期化はPHPに任せます');
                
            } else {
                // エラーメッセージを表示
                alert(`エラー: ${result.message || '予約の保存に失敗しました。'}`);
            }
        } catch (error) {
            console.error('予約保存エラー:', error);
            alert('予約の保存中にエラーが発生しました。');
        } finally {
            // ボタンを元に戻す
            saveAppointmentBtn.disabled = false;
            saveAppointmentBtn.innerHTML = '保存';
        }
    }

    // 予約の削除処理
    async function deleteAppointment() {
        try {
            // 確認ダイアログ
            if (!confirm('この予約を削除してもよろしいですか？この操作は元に戻せません。')) {
                return;
            }
            
            const appointmentId = document.getElementById('appointment_id').value;
            
            if (!appointmentId) {
                alert('予約IDが見つかりません。');
                return;
            }
            
            // 削除処理中はボタンを無効化
            deleteAppointmentBtn.disabled = true;
            deleteAppointmentBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 削除中...';
            
            // APIリクエスト
            const formData = new FormData();
            formData.append('appointment_id', appointmentId);
            
            const response = await fetch(API_ENDPOINTS.DELETE_APPOINTMENT, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 成功時はモーダルを閉じて予約リストを更新
                appointmentModal.hide();
                
                // 成功メッセージを表示
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show mt-3';
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i> 予約が削除されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="閉じる"></button>
                `;
                
                appointmentsList.insertAdjacentElement('beforebegin', alertDiv);
                
                // 5秒後に自動的にアラートを消す
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
                
                // 予約リストを更新
                loadAppointments(getCurrentFilters());
                
                // カレンダーも更新
                if (calendarView && !calendarView.classList.contains('d-none')) {
                    renderCalendar();
                }
                
                // 統計情報も更新
                // await loadStatistics(); // 一時的に無効化
                console.log('統計情報の初期化はPHPに任せます');
                
            } else {
                // エラーメッセージを表示
                alert(`エラー: ${result.message || '予約の削除に失敗しました。'}`);
            }
        } catch (error) {
            console.error('予約削除エラー:', error);
            alert('予約の削除中にエラーが発生しました。');
        } finally {
            // ボタンを元に戻す
            deleteAppointmentBtn.disabled = false;
            deleteAppointmentBtn.innerHTML = '削除';
        }
    }

    // ステータスのフォーマット
    function formatStatus(status) {
        const statusMap = {
            'scheduled': '予約済み',
            'confirmed': '確定',
            'completed': '完了',
            'cancelled': 'キャンセル',
            'no-show': '無断キャンセル'
        };
        
        return statusMap[status] || status;
    }

    // 改行をBRタグに変換
    function nl2br(str) {
        if (!str) return '';
        return str.replace(/\n/g, '<br>');
    }
    
    // ページネーションの更新
    function updatePagination(totalCount, currentPage, limit) {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';
        
        const totalPages = Math.ceil(totalCount / limit);
        
        if (totalPages <= 1) return;
        
        // 前のページボタン
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a>`;
        paginationContainer.appendChild(prevLi);
        
        // ページ番号
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
            pageLi.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
            paginationContainer.appendChild(pageLi);
        }
        
        // 次のページボタン
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a>`;
        paginationContainer.appendChild(nextLi);
        
        // ページネーションのクリックイベント
        document.querySelectorAll('#pagination .page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (!this.parentElement.classList.contains('disabled')) {
                    const page = parseInt(this.dataset.page);
                    
                    // 現在のフィルター状態を取得
                    const filters = getCurrentFilters();
                    filters.page = page;
                    
                    // 予約を再読み込み
                    loadAppointments(filters);
                }
            });
        });
    }
    
    // 現在のフィルター値を取得
    function getCurrentFilters() {
        const filters = {};
        
        // 日付フィルター
        if (dateFilter.value) {
            const dates = dateFilter.value.split(' to ');
            filters.date_from = dates[0];
            filters.date_to = dates.length > 1 ? dates[1] : dates[0];
        }
        
        // ステータスフィルター
        if (statusFilter.value) {
            filters.status = statusFilter.value;
        }
        
        // スタッフフィルター
        if (staffFilter.value) {
            filters.staff_id = staffFilter.value;
        }
        
        // 検索ワード
        if (searchTermInput.value.trim()) {
            filters.search = searchTermInput.value.trim();
        }
        
        return filters;
    }
    
    // 新規予約ボタンのイベントリスナー
    newAppointmentBtn.addEventListener('click', function() {
        // フォームをリセット
        appointmentForm.reset();
        
        // 予約IDをクリア
        document.getElementById('appointment_id').value = '';
        
        // フィールドの表示状態を初期化
        const customerFields = document.querySelectorAll('.customer-field');
        const internalFields = document.querySelectorAll('.internal-field');
        
        customerFields.forEach(field => field.style.display = 'block');
        internalFields.forEach(field => field.style.display = 'none');
        
        // 削除ボタンを非表示
        deleteAppointmentBtn.style.display = 'none';
        
        // 日付を今日に設定
        document.getElementById('appointment_date').value = CONFIG.today;
        
        // モーダルのタイトルを変更
        document.getElementById('appointmentModalLabel').textContent = '新規予約';
        
        // モーダルを表示
        appointmentModal.show();
    });
    
    // 編集ボタンのイベントリスナー
    editAppointmentBtn.addEventListener('click', function() {
        const appointmentId = this.dataset.appointmentId;
        
        // 詳細モーダルを閉じる
        detailsModal.hide();
        
        // 削除ボタンを表示
        deleteAppointmentBtn.style.display = 'block';
        
        // モーダルのタイトルを変更
        document.getElementById('appointmentModalLabel').textContent = '予約編集';
        
        // 編集のためのデータロードを開始
        loadEditData(appointmentId);
    });
    
    // 編集用のデータをロードする関数
    async function loadEditData(appointmentId) {
        try {
            // まず必要なリストデータを確実に読み込む
            await Promise.all([
                loadStaffMembers(),
                loadServices(),
                loadCustomers()
            ]);
            
            // 予約データを取得
            const response = await fetch(`${API_ENDPOINTS.GET_APPOINTMENTS}?salon_id=${CONFIG.salon_id}&appointment_id=${appointmentId}`);
            if (!response.ok) throw new Error('予約データの取得に失敗しました');
            
            const data = await response.json();
            if (!data.appointments || data.appointments.length === 0) {
                throw new Error('予約データが見つかりませんでした');
            }
            
            const appointment = data.appointments[0];
            
            // デバッグ用にデータを出力
            console.log('編集用データ:', appointment);
            
            // 予約IDを設定
            document.getElementById('appointment_id').value = appointment.appointment_id;
            
            // 予約タイプを設定
            const appointmentTypeSelect = document.getElementById('appointment_type');
            appointmentTypeSelect.value = appointment.appointment_type || 'customer';
            
            // 予約タイプに応じたフィールドの表示/非表示切り替えをトリガー
            const event = new Event('change');
            appointmentTypeSelect.dispatchEvent(event);
            
            // 各フィールドに値を設定
            const customerSelect = document.getElementById('customer_id');
            if (appointment.customer_id) {
                customerSelect.value = appointment.customer_id;
            }
            
            const staffSelect = document.getElementById('staff_id');
            if (appointment.staff_id) {
                staffSelect.value = appointment.staff_id;
            }
            
            const serviceSelect = document.getElementById('service_id');
            if (appointment.service_id) {
                serviceSelect.value = appointment.service_id;
            }
            
            document.getElementById('appointment_date').value = appointment.appointment_date || '';
            document.getElementById('start_time').value = appointment.start_time || '';
            document.getElementById('end_time').value = appointment.end_time || '';
            document.getElementById('task_description').value = appointment.task_description || '';
            document.getElementById('notes').value = appointment.notes || '';
            
            const statusSelect = document.getElementById('status');
            if (appointment.status) {
                statusSelect.value = appointment.status;
            }
            
            // フォームのデータ設定後、ログ出力
            console.log('フォーム設定完了:', {
                'appointment_id': document.getElementById('appointment_id').value,
                'customer_id': customerSelect.value,
                'staff_id': staffSelect.value,
                'service_id': serviceSelect.value,
                'appointment_date': document.getElementById('appointment_date').value,
                'start_time': document.getElementById('start_time').value,
                'end_time': document.getElementById('end_time').value
            });
            
            // モーダルを表示
            appointmentModal.show();
        } catch (error) {
            console.error('予約データ取得エラー:', error);
            alert('予約データの取得中にエラーが発生しました: ' + error.message);
        }
    }
    
    // ページの初期化
    async function initializePage() {
        try {
            // スタッフ、サービス、顧客情報の取得
            await Promise.all([
                CONFIG.staff_members.length === 0 ? loadStaffMembers() : Promise.resolve(),
                CONFIG.service_categories.length === 0 ? loadServices() : Promise.resolve(),
                loadCustomers()
            ]);
            
            // 統計情報の初期表示（PHPから渡された値を優先）
            loadStatistics();
            
            // URL検索パラメータの読み取り
            const urlParams = new URLSearchParams(window.location.search);
            
            // 日付パラメータがある場合は日付フィルターに設定
            if (urlParams.has('date')) {
                dateFilter.value = urlParams.get('date');
            }
            
            // ステータスパラメータがある場合はステータスフィルターに設定
            if (urlParams.has('status')) {
                statusFilter.value = urlParams.get('status');
            }
            
            // スタッフパラメータがある場合はスタッフフィルターに設定
            if (urlParams.has('staff_id')) {
                staffFilter.value = urlParams.get('staff_id');
            }
            
            // フィルター適用（URL引数があれば）
            if (urlParams.has('date') || urlParams.has('status') || urlParams.has('staff_id')) {
                const filters = getCurrentFilters();
                await loadAppointments(filters);
            } else {
                // 初期予約リストはサーバーサイドで取得済みなので、ローディングを隠す
                loadingIndicator.classList.add('d-none');
                if (CONFIG.appointments.length === 0) {
                    noAppointmentsMessage.classList.remove('d-none');
                }
            }
            
            // カレンダー初期化
            renderCalendar();
            
            // 新規予約ボタンのイベントハンドラー
            newAppointmentBtn.addEventListener('click', function() {
                // フォームをリセット
                appointmentForm.reset();
                document.getElementById('appointment_id').value = '';
                document.getElementById('appointment_type').value = 'customer';
                document.getElementById('appointment_type').dispatchEvent(new Event('change'));
                
                // 現在の日時をデフォルト値に設定
                const today = new Date();
                document.getElementById('appointment_date').value = today.toISOString().substr(0, 10);
                
                // 時間の設定（営業時間内で次の30分区切りに）
                const roundedMinutes = Math.ceil(today.getMinutes() / 30) * 30;
                let hours = today.getHours();
                if (roundedMinutes === 60) {
                    hours += 1;
                }
                
                // 開始時間
                const startTime = `${hours.toString().padStart(2, '0')}:${(roundedMinutes % 60).toString().padStart(2, '0')}`;
                document.getElementById('start_time').value = startTime;
                
                // 終了時間（1時間後）
                const endHours = hours + 1;
                document.getElementById('end_time').value = `${endHours.toString().padStart(2, '0')}:${(roundedMinutes % 60).toString().padStart(2, '0')}`;
                
                // 削除ボタンを非表示
                deleteAppointmentBtn.style.display = 'none';
                
                // モーダルタイトル変更
                document.getElementById('appointmentModalLabel').textContent = '新規予約';
                
                // ステータスを予約済みに設定
                document.getElementById('status').value = 'scheduled';
                
                // モーダル表示
                appointmentModal.show();
            });
            
            // 保存ボタンのイベントハンドラー
            saveAppointmentBtn.addEventListener('click', saveAppointment);
            
            // 編集ボタンのイベントハンドラー
            editAppointmentBtn.addEventListener('click', function() {
                const appointmentId = this.getAttribute('data-appointment-id');
                if (appointmentId) {
                    loadEditData(appointmentId);
                }
            });
            
            // 削除ボタンのイベントハンドラー
            deleteAppointmentBtn.addEventListener('click', deleteAppointment);
            
            // フィルター適用ボタン
            applyFiltersBtn.addEventListener('click', function() {
                const filters = getCurrentFilters();
                loadAppointments(filters);
            });
            
            // フィルターリセットボタン
            resetFiltersBtn.addEventListener('click', function() {
                dateFilter.value = '';
                statusFilter.value = '';
                staffFilter.value = '';
                searchTermInput.value = '';
                
                loadAppointments();
            });
            
            // 予約カードのクリックイベント（動的生成された要素）
            appointmentsList.addEventListener('click', function(e) {
                const card = e.target.closest('.appointment-card');
                if (card) {
                    const appointmentId = card.getAttribute('data-appointment-id');
                    const appointment = CONFIG.appointments.find(a => a.appointment_id == appointmentId);
                    
                    if (appointment) {
                        openAppointmentDetails(appointment);
                    }
                }
                
                // 詳細ボタンクリック
                const viewBtn = e.target.closest('.view-appointment-btn');
                if (viewBtn) {
                    e.stopPropagation();
                    const appointmentId = viewBtn.getAttribute('data-appointment-id');
                    const appointment = CONFIG.appointments.find(a => a.appointment_id == appointmentId);
                    
                    if (appointment) {
                        openAppointmentDetails(appointment);
                    }
                }
                
                // 編集ボタンクリック
                const editBtn = e.target.closest('.edit-appointment-btn');
                if (editBtn) {
                    e.stopPropagation();
                    const appointmentId = editBtn.getAttribute('data-appointment-id');
                    loadEditData(appointmentId);
                }
            });
        } catch (error) {
            console.error('ページ初期化エラー:', error);
            
            // ローディング表示を非表示
            loadingIndicator.classList.add('d-none');
            
            // エラーメッセージ表示
            const errorDiv = document.createElement('div');
            errorDiv.className = 'col-12';
            errorDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> 
                    ページの初期化中にエラーが発生しました: ${error.message}
                </div>
            `;
            appointmentsList.appendChild(errorDiv);
        }
    }
    
    // ページ初期化実行
    initializePage();
}); 