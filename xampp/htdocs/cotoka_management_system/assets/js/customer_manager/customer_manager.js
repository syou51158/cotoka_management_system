document.addEventListener('DOMContentLoaded', function() {
    // 基本設定
    const customersList = document.getElementById('customers-list');
    const loadingIndicator = document.getElementById('loading-customers');
    const newCustomerBtn = document.getElementById('new-customer-btn');
    const customerModal = new bootstrap.Modal(document.getElementById('customer-modal'));
    const customerForm = document.getElementById('customer-form');
    const saveCustomerBtn = document.getElementById('save-customer-btn');
    const deleteCustomerBtn = document.getElementById('delete-customer-btn');
    const detailsModal = new bootstrap.Modal(document.getElementById('customer-details-modal'));
    const editCustomerBtn = document.getElementById('edit-customer-btn');
    
    // フィルター関連
    const searchTermInput = document.getElementById('search-term');
    const statusFilter = document.getElementById('status-filter');
    const genderFilter = document.getElementById('gender-filter');
    const dateFilter = document.getElementById('date-filter');
    const applyFiltersBtn = document.getElementById('apply-filters');
    const resetFiltersBtn = document.getElementById('reset-filters');
    
    // ページネーション
    const pagination = document.getElementById('pagination');
    const totalCountDisplay = document.getElementById('total-count-display');
    const filteredCountDisplay = document.getElementById('filtered-count-display');
    
    // 統計情報要素
    const totalCountElement = document.getElementById('total-count');
    const monthCountElement = document.getElementById('month-count');
    const repeatRateElement = document.getElementById('repeat-rate');
    const avgValueElement = document.getElementById('avg-value');
    
    // ページネーション設定
    let currentPage = 1;
    const itemsPerPage = 10;
    let totalCustomers = 0;
    let filteredCustomers = [];
    
    // フラットピッカーの初期化
    flatpickr(dateFilter, {
        locale: 'ja',
        mode: 'range',
        dateFormat: 'Y-m-d'
    });
    
    // 初期化処理
    async function initialize() {
        try {
            // 統計情報の取得と表示（PHPから渡された初期値を使用）
            loadStatistics();
            
            // 顧客データの読み込み
            if (CONFIG.customers && CONFIG.customers.length > 0) {
                console.log('PHPから渡された顧客データを表示します');
                await displayCustomers(CONFIG.customers);
            } else {
                console.log('APIから顧客データを取得します');
                await loadCustomers();
            }
            
            // イベントリスナーの設定
            setupEventListeners();
            
        } catch (error) {
            console.error('初期化エラー:', error);
            alert('データの読み込み中にエラーが発生しました: ' + error.message);
        }
    }
    
    // イベントリスナーの設定
    function setupEventListeners() {
        // 新規顧客ボタン
        newCustomerBtn.addEventListener('click', function() {
            resetCustomerForm();
            document.getElementById('customerModalLabel').textContent = '新規顧客登録';
            deleteCustomerBtn.style.display = 'none';
            customerModal.show();
        });
        
        // 保存ボタン
        saveCustomerBtn.addEventListener('click', saveCustomer);
        
        // 削除ボタン
        deleteCustomerBtn.addEventListener('click', deleteCustomer);
        
        // 編集ボタン
        editCustomerBtn.addEventListener('click', function() {
            const customerId = this.dataset.customerId;
            if (customerId) {
                editCustomer(customerId);
            }
            detailsModal.hide();
        });
        
        // フィルター適用ボタン
        applyFiltersBtn.addEventListener('click', function() {
            currentPage = 1;
            loadCustomers(getCurrentFilters());
        });
        
        // フィルターリセットボタン
        resetFiltersBtn.addEventListener('click', function() {
            searchTermInput.value = '';
            statusFilter.value = '';
            genderFilter.value = '';
            dateFilter.value = '';
            currentPage = 1;
            loadCustomers();
        });
        
        // インポートボタン
        document.getElementById('import-customers-btn').addEventListener('click', function() {
            alert('この機能は現在開発中です。');
        });
        
        // エクスポートボタン
        document.getElementById('export-customers-btn').addEventListener('click', function() {
            alert('この機能は現在開発中です。');
        });
    }
    
    // 現在のフィルター設定を取得
    function getCurrentFilters() {
        const dateRange = dateFilter.value.split(' to ');
        return {
            search: searchTermInput.value,
            status: statusFilter.value,
            gender: genderFilter.value,
            date_from: dateRange[0] || '',
            date_to: dateRange[1] || dateRange[0] || '',
            page: currentPage,
            limit: itemsPerPage
        };
    }
    
    // 顧客データの取得とリスト表示
    async function loadCustomers(filters = {}) {
        // デフォルトのフィルター
        const defaultFilters = {
            search: '',
            status: '',
            gender: '',
            date_from: '',
            date_to: '',
            page: 1,
            limit: itemsPerPage
        };
        
        // フィルターのマージ
        const activeFilters = { ...defaultFilters, ...filters };
        currentPage = activeFilters.page;
        
        // ローディング表示
        loadingIndicator.style.display = 'table-row';
        
        // 顧客リストをクリア
        const customerRows = customersList.querySelectorAll('tr:not(#loading-customers)');
        customerRows.forEach(row => row.remove());
        
        try {
            // URLパラメータの構築
            let queryParams = new URLSearchParams();
            queryParams.append('salon_id', CONFIG.salon_id);
            
            Object.keys(activeFilters).forEach(key => {
                if (activeFilters[key]) {
                    queryParams.append(key, activeFilters[key]);
                }
            });
            
            // 顧客データの取得
            console.log('API呼び出し:', `${API_ENDPOINTS.GET_CUSTOMERS}?${queryParams.toString()}`);
            const response = await fetch(`${API_ENDPOINTS.GET_CUSTOMERS}?${queryParams.toString()}`);
            if (!response.ok) throw new Error('顧客情報の取得に失敗しました');
            
            const data = await response.json();
            console.log('取得した顧客データ:', data);
            
            if (data.success) {
                const customers = data.customers || [];
                totalCustomers = data.total_count || 0;
                filteredCustomers = customers;
                
                // 顧客リストの表示
                displayCustomers(customers);
                
                // ページネーションの更新
                updatePagination(totalCustomers, currentPage, itemsPerPage);
                
                // カウント表示の更新
                totalCountDisplay.textContent = totalCustomers;
                filteredCountDisplay.textContent = customers.length;
            } else {
                throw new Error(data.message || '顧客データの取得に失敗しました');
            }
        } catch (error) {
            console.error('顧客データ取得エラー:', error);
            
            // エラーメッセージ表示
            loadingIndicator.style.display = 'none';
            const errorRow = document.createElement('tr');
            errorRow.innerHTML = `
                <td colspan="9" class="text-center text-danger py-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    顧客データの取得中にエラーが発生しました: ${error.message}
                </td>
            `;
            customersList.appendChild(errorRow);
        }
    }
    
    // 顧客リストの表示
    async function displayCustomers(customers) {
        // ローディング表示を非表示
        loadingIndicator.style.display = 'none';
        
        // 顧客データがない場合
        if (!customers || customers.length === 0) {
            const noDataRow = document.createElement('tr');
            noDataRow.innerHTML = `
                <td colspan="9" class="text-center py-3">
                    <i class="fas fa-info-circle me-2"></i>
                    表示する顧客データがありません
                </td>
            `;
            customersList.appendChild(noDataRow);
            return;
        }
        
        // 顧客データの表示
        customers.forEach(customer => {
            const row = document.createElement('tr');
            row.dataset.customerId = customer.customer_id;
            
            // 顧客名のフォーマット
            const fullName = `${customer.last_name || ''} ${customer.first_name || ''}`.trim() || '名前未設定';
            
            // 性別の日本語表示
            let genderText = '未設定';
            if (customer.gender === 'male') genderText = '男性';
            else if (customer.gender === 'female') genderText = '女性';
            else if (customer.gender === 'other') genderText = 'その他';
            
            // ステータスバッジ
            const statusBadge = customer.status === 'active' 
                ? '<span class="badge badge-status badge-active">アクティブ</span>' 
                : '<span class="badge badge-status badge-inactive">非アクティブ</span>';
            
            row.innerHTML = `
                <td>${customer.customer_id}</td>
                <td>${fullName}</td>
                <td>${customer.phone || '-'}</td>
                <td>${customer.email || '-'}</td>
                <td>${genderText}</td>
                <td>${customer.last_visit_date ? formatDate(customer.last_visit_date) : '-'}</td>
                <td>${customer.appointment_count || 0}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="table-actions">
                        <button class="btn btn-view btn-action view-customer" data-customer-id="${customer.customer_id}"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-edit btn-action edit-customer" data-customer-id="${customer.customer_id}"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-delete btn-action delete-customer" data-customer-id="${customer.customer_id}"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            `;
            
            customersList.appendChild(row);
        });
        
        // アクションボタンのイベント設定
        setupActionButtons();
    }
    
    // アクションボタンのイベント設定
    function setupActionButtons() {
        // 表示ボタン
        document.querySelectorAll('.view-customer').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const customerId = this.dataset.customerId;
                showCustomerDetails(customerId);
            });
        });
        
        // 編集ボタン
        document.querySelectorAll('.edit-customer').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const customerId = this.dataset.customerId;
                editCustomer(customerId);
            });
        });
        
        // 削除ボタン
        document.querySelectorAll('.delete-customer').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const customerId = this.dataset.customerId;
                confirmDeleteCustomer(customerId);
            });
        });
        
        // 行クリックで顧客詳細を表示
        document.querySelectorAll('#customers-list tr[data-customer-id]').forEach(row => {
            row.addEventListener('click', function() {
                const customerId = this.dataset.customerId;
                showCustomerDetails(customerId);
            });
        });
    }
    
    // 顧客詳細を表示
    async function showCustomerDetails(customerId) {
        try {
            // 顧客データの取得
            const response = await fetch(`${API_ENDPOINTS.GET_CUSTOMERS}?customer_id=${customerId}&salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('顧客情報の取得に失敗しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.message || '顧客情報の取得に失敗しました');
            
            const customer = data.customers && data.customers.length > 0 ? data.customers[0] : null;
            if (!customer) throw new Error('顧客データが見つかりません');
            
            // 顧客詳細の表示
            document.getElementById('customer-name').textContent = `${customer.last_name || ''} ${customer.first_name || ''}`.trim() || '名前未設定';
            document.getElementById('customer-phone').textContent = customer.phone || '未設定';
            document.getElementById('customer-email').textContent = customer.email || '未設定';
            
            // 基本情報の設定
            let genderText = '未設定';
            if (customer.gender === 'male') genderText = '男性';
            else if (customer.gender === 'female') genderText = '女性';
            else if (customer.gender === 'other') genderText = 'その他';
            
            document.getElementById('customer-gender').textContent = genderText;
            document.getElementById('customer-birthday').textContent = customer.birthday ? formatDate(customer.birthday) : '未設定';
            document.getElementById('customer-address').textContent = customer.address || '未設定';
            document.getElementById('customer-status').textContent = customer.status === 'active' ? 'アクティブ' : '非アクティブ';
            
            // 来店情報の設定
            document.getElementById('customer-first-visit').textContent = customer.first_visit_date ? formatDate(customer.first_visit_date) : '未来店';
            document.getElementById('customer-last-visit').textContent = customer.last_visit_date ? formatDate(customer.last_visit_date) : '未来店';
            document.getElementById('customer-visit-count').textContent = customer.appointment_count || '0';
            document.getElementById('customer-total-spent').textContent = customer.total_spent ? `¥${Number(customer.total_spent).toLocaleString()}` : '¥0';
            
            // 備考の設定
            document.getElementById('customer-notes').textContent = customer.notes || '備考情報がありません。';
            
            // 予約履歴の取得と表示
            await loadCustomerAppointments(customerId);
            
            // 編集ボタンのカスタマーID設定
            editCustomerBtn.dataset.customerId = customerId;
            
            // モーダルを表示
            detailsModal.show();
            
        } catch (error) {
            console.error('顧客詳細取得エラー:', error);
            alert('顧客詳細の取得中にエラーが発生しました: ' + error.message);
        }
    }
    
    // 顧客の予約履歴を取得
    async function loadCustomerAppointments(customerId) {
        try {
            const response = await fetch(`${API_ENDPOINTS.GET_CUSTOMER_APPOINTMENTS}?customer_id=${customerId}&salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('予約履歴の取得に失敗しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.message || '予約履歴の取得に失敗しました');
            
            const appointments = data.appointments || [];
            const appointmentsContainer = document.getElementById('customer-appointments');
            
            // 予約履歴をクリア
            appointmentsContainer.innerHTML = '';
            
            // 予約がない場合
            if (appointments.length === 0) {
                appointmentsContainer.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center">予約履歴がありません。</td>
                    </tr>
                `;
                return;
            }
            
            // 予約履歴の表示
            appointments.forEach(appointment => {
                const statusText = getStatusText(appointment.status);
                const statusClass = getStatusClass(appointment.status);
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${formatDate(appointment.appointment_date)}</td>
                    <td>${appointment.start_time} - ${appointment.end_time}</td>
                    <td>${appointment.service_name || '-'}</td>
                    <td>${appointment.staff_name || '-'}</td>
                    <td><span class="badge ${statusClass}">${statusText}</span></td>
                `;
                appointmentsContainer.appendChild(row);
            });
            
        } catch (error) {
            console.error('予約履歴取得エラー:', error);
            document.getElementById('customer-appointments').innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-danger">
                        予約履歴の取得中にエラーが発生しました: ${error.message}
                    </td>
                </tr>
            `;
        }
    }
    
    // 予約ステータスのテキストを取得
    function getStatusText(status) {
        switch (status) {
            case 'scheduled': return '予約済み';
            case 'confirmed': return '確定';
            case 'completed': return '完了';
            case 'cancelled': return 'キャンセル';
            case 'no-show': return '無断キャンセル';
            default: return '不明';
        }
    }
    
    // 予約ステータスのクラスを取得
    function getStatusClass(status) {
        switch (status) {
            case 'scheduled': return 'bg-primary';
            case 'confirmed': return 'bg-success';
            case 'completed': return 'bg-info';
            case 'cancelled': return 'bg-warning';
            case 'no-show': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }
    
    // 顧客編集フォームを表示
    async function editCustomer(customerId) {
        try {
            // 顧客データの取得
            const response = await fetch(`${API_ENDPOINTS.GET_CUSTOMERS}?customer_id=${customerId}&salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('顧客情報の取得に失敗しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.message || '顧客情報の取得に失敗しました');
            
            const customer = data.customers && data.customers.length > 0 ? data.customers[0] : null;
            if (!customer) throw new Error('顧客データが見つかりません');
            
            // フォームリセット
            resetCustomerForm();
            
            // フォームに顧客データを設定
            document.getElementById('customer_id').value = customer.customer_id;
            document.getElementById('last_name').value = customer.last_name || '';
            document.getElementById('first_name').value = customer.first_name || '';
            document.getElementById('phone').value = customer.phone || '';
            document.getElementById('email').value = customer.email || '';
            document.getElementById('birthday').value = customer.birthday || '';
            document.getElementById('gender').value = customer.gender || '';
            document.getElementById('address').value = customer.address || '';
            document.getElementById('status').value = customer.status || 'active';
            document.getElementById('notes').value = customer.notes || '';
            
            // モーダルのタイトルと削除ボタンの設定
            document.getElementById('customerModalLabel').textContent = '顧客情報の編集';
            deleteCustomerBtn.style.display = 'block';
            
            // モーダルを表示
            customerModal.show();
            
        } catch (error) {
            console.error('顧客データ取得エラー:', error);
            alert('顧客データの取得中にエラーが発生しました: ' + error.message);
        }
    }
    
    // 顧客フォームのリセット
    function resetCustomerForm() {
        customerForm.reset();
        document.getElementById('customer_id').value = '';
        document.getElementById('status').value = 'active';
    }
    
    // 顧客データの保存
    async function saveCustomer() {
        try {
            // フォームのバリデーション
            if (!validateCustomerForm()) {
                return;
            }
            
            // フォームデータの収集
            const formData = new FormData(customerForm);
            formData.append('salon_id', CONFIG.salon_id);
            
            // サーバーに送信
            const response = await fetch(API_ENDPOINTS.SAVE_CUSTOMER, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) throw new Error('サーバーエラーが発生しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.message || '顧客情報の保存に失敗しました');
            
            // モーダルを閉じる
            customerModal.hide();
            
            // 顧客リストを更新
            loadCustomers(getCurrentFilters());
            
            // 統計情報も更新
            loadStatistics();
            
            // 成功メッセージを表示
            alert(data.message || '顧客情報が保存されました');
            
        } catch (error) {
            console.error('顧客保存エラー:', error);
            alert('顧客情報の保存中にエラーが発生しました: ' + error.message);
        }
    }
    
    // 顧客削除の確認
    function confirmDeleteCustomer(customerId) {
        if (confirm('この顧客情報を削除してもよろしいですか？この操作は元に戻せません。')) {
            deleteCustomer(customerId);
        }
    }
    
    // 顧客の削除
    async function deleteCustomer(customerId = null) {
        try {
            // 顧客IDの取得（直接指定かフォームから）
            const id = customerId || document.getElementById('customer_id').value;
            
            if (!id) {
                alert('顧客IDが見つかりません');
                return;
            }
            
            if (!customerId && !confirm('この顧客情報を削除してもよろしいですか？この操作は元に戻せません。')) {
                return;
            }
            
            // サーバーに送信
            const formData = new FormData();
            formData.append('customer_id', id);
            formData.append('salon_id', CONFIG.salon_id);
            
            const response = await fetch(API_ENDPOINTS.DELETE_CUSTOMER, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) throw new Error('サーバーエラーが発生しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.message || '顧客情報の削除に失敗しました');
            
            // モーダルを閉じる
            customerModal.hide();
            
            // 顧客リストを更新
            loadCustomers(getCurrentFilters());
            
            // 統計情報も更新
            loadStatistics();
            
            // 成功メッセージを表示
            alert(data.message || '顧客情報が削除されました');
            
        } catch (error) {
            console.error('顧客削除エラー:', error);
            alert('顧客情報の削除中にエラーが発生しました: ' + error.message);
        }
    }
    
    // 顧客フォームのバリデーション
    function validateCustomerForm() {
        // 必須フィールドのチェック
        const requiredFields = [
            { id: 'last_name', message: '姓を入力してください' },
            { id: 'first_name', message: '名を入力してください' }
        ];
        
        for (const field of requiredFields) {
            const element = document.getElementById(field.id);
            if (!element.value.trim()) {
                alert(field.message);
                element.focus();
                return false;
            }
        }
        
        // 電話番号のフォーマットチェック（任意だが入力された場合）
        const phoneInput = document.getElementById('phone');
        if (phoneInput.value.trim() && !isValidPhone(phoneInput.value.trim())) {
            alert('有効な電話番号を入力してください');
            phoneInput.focus();
            return false;
        }
        
        // メールアドレスのフォーマットチェック（任意だが入力された場合）
        const emailInput = document.getElementById('email');
        if (emailInput.value.trim() && !isValidEmail(emailInput.value.trim())) {
            alert('有効なメールアドレスを入力してください');
            emailInput.focus();
            return false;
        }
        
        return true;
    }
    
    // ページネーションの更新
    function updatePagination(totalItems, currentPage, itemsPerPage) {
        pagination.innerHTML = '';
        
        if (totalItems <= itemsPerPage) {
            return;
        }
        
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        
        // 前へボタン
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" ${currentPage === 1 ? 'tabindex="-1" aria-disabled="true"' : ''}>前へ</a>`;
        
        if (currentPage > 1) {
            prevLi.addEventListener('click', function(e) {
                e.preventDefault();
                goToPage(currentPage - 1);
            });
        }
        
        pagination.appendChild(prevLi);
        
        // ページボタン
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageLi = document.createElement('li');
            pageLi.className = `page-item ${i === currentPage ? 'active' : ''}`;
            pageLi.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            
            pageLi.addEventListener('click', function(e) {
                e.preventDefault();
                goToPage(i);
            });
            
            pagination.appendChild(pageLi);
        }
        
        // 次へボタン
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" ${currentPage === totalPages ? 'tabindex="-1" aria-disabled="true"' : ''}>次へ</a>`;
        
        if (currentPage < totalPages) {
            nextLi.addEventListener('click', function(e) {
                e.preventDefault();
                goToPage(currentPage + 1);
            });
        }
        
        pagination.appendChild(nextLi);
    }
    
    // ページ移動
    function goToPage(page) {
        currentPage = page;
        const filters = getCurrentFilters();
        loadCustomers(filters);
    }
    
    // 統計情報の取得と表示
    async function loadStatistics() {
        try {
            // PHPから渡された初期値があれば使用する
            if (typeof INITIAL_STATS !== 'undefined') {
                totalCountElement.textContent = INITIAL_STATS.total_count;
                monthCountElement.textContent = INITIAL_STATS.month_count;
                repeatRateElement.textContent = `${INITIAL_STATS.repeat_rate}%`;
                avgValueElement.textContent = `¥${Number(INITIAL_STATS.avg_value).toLocaleString()}`;
                return;
            }
            
            // 初期値がない場合はAPIから取得
            const response = await fetch(`${API_ENDPOINTS.GET_CUSTOMER_STATS}?salon_id=${CONFIG.salon_id}`);
            if (!response.ok) throw new Error('統計情報の取得に失敗しました');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.message || '統計情報の取得に失敗しました');
            
            // 統計情報の表示
            totalCountElement.textContent = data.total_count || '0';
            monthCountElement.textContent = data.month_count || '0';
            repeatRateElement.textContent = data.repeat_rate ? `${data.repeat_rate}%` : '0%';
            avgValueElement.textContent = data.avg_value ? `¥${Number(data.avg_value).toLocaleString()}` : '¥0';
            
        } catch (error) {
            console.error('統計情報取得エラー:', error);
            
            // エラー時は現在の値を保持（初期値で表示済みのため）
            console.log('統計情報取得エラー - 現在の表示値を保持します');
        }
    }
    
    // ユーティリティ関数
    
    // 日付フォーマット
    function formatDate(dateString) {
        if (!dateString) return '';
        
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        
        return date.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        });
    }
    
    // 電話番号のバリデーション
    function isValidPhone(phone) {
        // シンプルな電話番号バリデーション（日本の電話番号形式）
        return /^(0\d{1,4}-\d{1,4}-\d{4}|\d{10,11})$/.test(phone);
    }
    
    // メールアドレスのバリデーション
    function isValidEmail(email) {
        // シンプルなメールアドレスバリデーション
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    // 初期化処理の実行
    initialize();
}); 