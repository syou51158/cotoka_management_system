document.addEventListener('DOMContentLoaded', function() {
    // レポートタイプの切り替え
    initReportTypeNav();
    
    // 日付ピッカーの初期化
    initDatePickers();
    
    // チャートの初期化
    initCharts();
    
    // イベントリスナーの設定
    setupEventListeners();
    
    // デフォルトレポートの読み込み
    loadReport('sales');
});

/**
 * レポートタイプナビゲーションの初期化
 */
function initReportTypeNav() {
    const reportNavLinks = document.querySelectorAll('.report-type-nav .nav-link');
    
    reportNavLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // アクティブクラスの切り替え
            reportNavLinks.forEach(el => el.classList.remove('active'));
            this.classList.add('active');
            
            // データ属性からレポートタイプを取得
            const reportType = this.dataset.reportType;
            
            // レポートの読み込み
            loadReport(reportType);
        });
    });
}

/**
 * 日付ピッカーの初期化
 */
function initDatePickers() {
    // 日付範囲ピッカー
    if (document.getElementById('date-range-picker')) {
        flatpickr('#date-range-picker', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: 'ja',
            maxDate: 'today',
            defaultDate: [
                new Date(new Date().getFullYear(), new Date().getMonth(), 1),
                new Date()
            ],
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    // 日付範囲が選択されたときの処理
                    const startDate = formatDate(selectedDates[0]);
                    const endDate = formatDate(selectedDates[1]);
                    
                    // 現在のレポートを再読み込み
                    const activeReportType = document.querySelector('.report-type-nav .nav-link.active').dataset.reportType;
                    loadReport(activeReportType, startDate, endDate);
                }
            }
        });
    }
    
    // 年月ピッカー
    if (document.getElementById('month-picker')) {
        flatpickr('#month-picker', {
            dateFormat: 'Y-m',
            locale: 'ja',
            maxDate: 'today',
            plugins: [
                new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: 'Y-m',
                    altFormat: 'Y年m月'
                })
            ],
            onChange: function(selectedDates) {
                if (selectedDates.length > 0) {
                    const year = selectedDates[0].getFullYear();
                    const month = selectedDates[0].getMonth() + 1;
                    
                    // 現在のレポートを再読み込み
                    const activeReportType = document.querySelector('.report-type-nav .nav-link.active').dataset.reportType;
                    loadReport(activeReportType, `${year}-${month}-01`, null);
                }
            }
        });
    }
}

/**
 * チャートの初期化
 */
function initCharts() {
    // 売上トレンドチャート
    if (document.getElementById('sales-trend-chart')) {
        const ctx = document.getElementById('sales-trend-chart').getContext('2d');
        salesTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: '売上',
                    data: [],
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    pointBackgroundColor: '#4e73df',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '¥' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '¥' + context.raw.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                            }
                        }
                    }
                }
            }
        });
    }
    
    // サービス別売上チャート
    if (document.getElementById('service-sales-chart')) {
        const ctx = document.getElementById('service-sales-chart').getContext('2d');
        serviceSalesChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#6f42c1', '#5a5c69', '#858796', '#f8f9fc', '#d1d3e2'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const percentage = (value / context.dataset.data.reduce((a, b) => a + b, 0) * 100).toFixed(1);
                                return `¥${value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 顧客分析チャート
    if (document.getElementById('customer-analysis-chart')) {
        const ctx = document.getElementById('customer-analysis-chart').getContext('2d');
        customerAnalysisChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: '新規顧客',
                    data: [],
                    backgroundColor: '#1cc88a',
                    borderColor: '#1cc88a',
                    borderWidth: 1
                },
                {
                    label: 'リピート顧客',
                    data: [],
                    backgroundColor: '#4e73df',
                    borderColor: '#4e73df',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        stacked: true
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        stacked: true
                    }
                }
            }
        });
    }
}

/**
 * イベントリスナーの設定
 */
function setupEventListeners() {
    // フィルター適用ボタン
    const applyFilterBtn = document.getElementById('apply-filter');
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', function() {
            applyFilters();
        });
    }
    
    // フィルターリセットボタン
    const resetFilterBtn = document.getElementById('reset-filter');
    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', function() {
            resetFilters();
        });
    }
    
    // 日付ナビゲーションボタン
    const prevPeriodBtn = document.getElementById('prev-period');
    const nextPeriodBtn = document.getElementById('next-period');
    const currentPeriodBtn = document.getElementById('current-period');
    
    if (prevPeriodBtn) {
        prevPeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            navigatePeriod('prev');
        });
    }
    
    if (nextPeriodBtn) {
        nextPeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            navigatePeriod('next');
        });
    }
    
    if (currentPeriodBtn) {
        currentPeriodBtn.addEventListener('click', function(e) {
            e.preventDefault();
            navigatePeriod('current');
        });
    }
    
    // CSVエクスポートボタン
    const exportCsvBtn = document.getElementById('export-csv');
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', function() {
            exportReportData('csv');
        });
    }
    
    // PDFエクスポートボタン
    const exportPdfBtn = document.getElementById('export-pdf');
    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', function() {
            exportReportData('pdf');
        });
    }
    
    // 印刷ボタン
    const printBtn = document.getElementById('print-report');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }
}

/**
 * レポートの読み込み
 * @param {string} reportType - レポートタイプ
 * @param {string} startDate - 開始日 (オプション)
 * @param {string} endDate - 終了日 (オプション)
 */
function loadReport(reportType, startDate = null, endDate = null) {
    // レポートコンテナの表示切り替え
    document.querySelectorAll('.report-container').forEach(container => {
        container.style.display = 'none';
    });
    
    const targetContainer = document.getElementById(`${reportType}-report-container`);
    if (targetContainer) {
        targetContainer.style.display = 'block';
    }
    
    // レポートのタイトル更新
    const reportTitle = document.getElementById('report-title');
    if (reportTitle) {
        const titles = {
            'sales': '売上レポート',
            'customers': '顧客レポート',
            'services': 'サービスレポート',
            'staff': 'スタッフレポート',
            'comparison': '比較レポート'
        };
        reportTitle.textContent = titles[reportType] || 'レポート';
    }
    
    // 日付パラメータがない場合、デフォルトを使用
    if (!startDate) {
        const today = new Date();
        startDate = `${today.getFullYear()}-${today.getMonth() + 1}-01`;
        endDate = formatDate(today);
    }
    
    // Ajax リクエストを送信
    const params = new URLSearchParams();
    params.append('action', 'get_report_data');
    params.append('report_type', reportType);
    params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    
    // 他のフィルターを追加
    const filters = getActiveFilters();
    Object.keys(filters).forEach(key => {
        params.append(key, filters[key]);
    });
    
    fetch('ajax/reports_ajax.php', {
        method: 'POST',
        body: params,
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateReportData(reportType, data);
        } else {
            showNotification('データの取得に失敗しました。', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('データの取得中にエラーが発生しました。', 'error');
    });
}

/**
 * レポートデータの更新
 * @param {string} reportType - レポートタイプ
 * @param {object} data - レスポンスデータ
 */
function updateReportData(reportType, data) {
    // 期間表示の更新
    updatePeriodDisplay(data.period);
    
    // サマリーカードの更新
    updateSummaryCards(data.summary);
    
    // チャートデータの更新
    updateCharts(reportType, data);
    
    // テーブルデータの更新
    updateTables(reportType, data.table_data);
    
    // KPI指標の更新
    if (data.kpi) {
        updateKpiIndicators(data.kpi);
    }
}

/**
 * 期間表示の更新
 * @param {object} period - 期間情報
 */
function updatePeriodDisplay(period) {
    const periodDisplay = document.getElementById('current-period');
    if (periodDisplay && period) {
        periodDisplay.textContent = period.display || '';
    }
}

/**
 * サマリーカードの更新
 * @param {object} summary - サマリーデータ
 */
function updateSummaryCards(summary) {
    if (!summary) return;
    
    Object.keys(summary).forEach(key => {
        const card = document.getElementById(`${key}-summary`);
        if (card) {
            const valueEl = card.querySelector('.summary-value');
            const changeEl = card.querySelector('.summary-change');
            
            if (valueEl) {
                valueEl.textContent = formatValue(summary[key].value, key);
            }
            
            if (changeEl && summary[key].change !== undefined) {
                const change = summary[key].change;
                changeEl.textContent = `${change > 0 ? '+' : ''}${change}%`;
                changeEl.className = 'summary-change';
                changeEl.classList.add(change >= 0 ? 'positive' : 'negative');
            }
        }
    });
}

/**
 * チャートの更新
 * @param {string} reportType - レポートタイプ
 * @param {object} data - チャートデータ
 */
function updateCharts(reportType, data) {
    if (reportType === 'sales' && data.charts && data.charts.trend) {
        // 売上トレンドチャート
        if (salesTrendChart) {
            salesTrendChart.data.labels = data.charts.trend.labels;
            salesTrendChart.data.datasets[0].data = data.charts.trend.data;
            salesTrendChart.update();
        }
        
        // サービス別売上チャート
        if (serviceSalesChart && data.charts.services) {
            serviceSalesChart.data.labels = data.charts.services.labels;
            serviceSalesChart.data.datasets[0].data = data.charts.services.data;
            serviceSalesChart.update();
        }
    } else if (reportType === 'customers' && data.charts && data.charts.customer) {
        // 顧客分析チャート
        if (customerAnalysisChart) {
            customerAnalysisChart.data.labels = data.charts.customer.labels;
            customerAnalysisChart.data.datasets[0].data = data.charts.customer.new;
            customerAnalysisChart.data.datasets[1].data = data.charts.customer.repeat;
            customerAnalysisChart.update();
        }
    }
}

/**
 * テーブルの更新
 * @param {string} reportType - レポートタイプ
 * @param {array} tableData - テーブルデータ
 */
function updateTables(reportType, tableData) {
    if (!tableData) return;
    
    const tableId = `${reportType}-data-table`;
    const tableBody = document.querySelector(`#${tableId} tbody`);
    
    if (tableBody) {
        tableBody.innerHTML = '';
        
        if (tableData.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 10;
            cell.textContent = 'データがありません';
            cell.className = 'text-center';
            row.appendChild(cell);
            tableBody.appendChild(row);
            return;
        }
        
        tableData.forEach(item => {
            const row = document.createElement('tr');
            
            // レポートタイプに応じたテーブル行を作成
            if (reportType === 'sales') {
                createSalesTableRow(row, item);
            } else if (reportType === 'customers') {
                createCustomerTableRow(row, item);
            } else if (reportType === 'services') {
                createServiceTableRow(row, item);
            } else if (reportType === 'staff') {
                createStaffTableRow(row, item);
            }
            
            tableBody.appendChild(row);
        });
    }
}

/**
 * 売上テーブルの行を作成
 * @param {HTMLElement} row - 行要素
 * @param {object} item - 行データ
 */
function createSalesTableRow(row, item) {
    const fields = [
        'date', 'total_sales', 'transaction_count', 'average_sales', 'new_customers', 'repeat_customers'
    ];
    
    fields.forEach(field => {
        const cell = document.createElement('td');
        
        if (field === 'date') {
            cell.textContent = item[field] || '';
        } else if (field === 'total_sales' || field === 'average_sales') {
            cell.textContent = formatCurrency(item[field] || 0);
        } else {
            cell.textContent = item[field] || '0';
        }
        
        row.appendChild(cell);
    });
}

/**
 * 顧客テーブルの行を作成
 * @param {HTMLElement} row - 行要素
 * @param {object} item - 行データ
 */
function createCustomerTableRow(row, item) {
    const fields = [
        'customer_name', 'visit_count', 'last_visit', 'total_spent', 'average_spent', 'favorite_service'
    ];
    
    fields.forEach(field => {
        const cell = document.createElement('td');
        
        if (field === 'total_spent' || field === 'average_spent') {
            cell.textContent = formatCurrency(item[field] || 0);
        } else {
            cell.textContent = item[field] || '';
        }
        
        row.appendChild(cell);
    });
}

/**
 * サービステーブルの行を作成
 * @param {HTMLElement} row - 行要素
 * @param {object} item - 行データ
 */
function createServiceTableRow(row, item) {
    const fields = [
        'service_name', 'total_sales', 'service_count', 'percentage', 'customer_count', 'average_price'
    ];
    
    fields.forEach(field => {
        const cell = document.createElement('td');
        
        if (field === 'total_sales' || field === 'average_price') {
            cell.textContent = formatCurrency(item[field] || 0);
        } else if (field === 'percentage') {
            cell.textContent = `${item[field] || 0}%`;
        } else {
            cell.textContent = item[field] || '';
        }
        
        row.appendChild(cell);
    });
}

/**
 * スタッフテーブルの行を作成
 * @param {HTMLElement} row - 行要素
 * @param {object} item - 行データ
 */
function createStaffTableRow(row, item) {
    const fields = [
        'staff_name', 'total_sales', 'customer_count', 'service_count', 'average_sales', 'top_service'
    ];
    
    fields.forEach(field => {
        const cell = document.createElement('td');
        
        if (field === 'total_sales' || field === 'average_sales') {
            cell.textContent = formatCurrency(item[field] || 0);
        } else {
            cell.textContent = item[field] || '';
        }
        
        row.appendChild(cell);
    });
}

/**
 * KPI指標の更新
 * @param {object} kpiData - KPIデータ
 */
function updateKpiIndicators(kpiData) {
    if (!kpiData) return;
    
    Object.keys(kpiData).forEach(key => {
        const kpiEl = document.getElementById(`${key}-kpi`);
        if (kpiEl) {
            const valueEl = kpiEl.querySelector('.kpi-value');
            const progressEl = kpiEl.querySelector('.progress-bar');
            
            if (valueEl) {
                valueEl.textContent = formatKpiValue(kpiData[key].value, key);
            }
            
            if (progressEl) {
                const percentage = Math.min(100, Math.max(0, kpiData[key].percentage || 0));
                progressEl.style.width = `${percentage}%`;
                progressEl.setAttribute('aria-valuenow', percentage);
                
                // 達成率に応じてクラスを変更
                progressEl.className = 'progress-bar';
                if (percentage >= 80) {
                    progressEl.classList.add('bg-success');
                } else if (percentage >= 50) {
                    progressEl.classList.add('bg-warning');
                } else {
                    progressEl.classList.add('bg-danger');
                }
            }
        }
    });
}

/**
 * フィルターの適用
 */
function applyFilters() {
    const activeReportType = document.querySelector('.report-type-nav .nav-link.active').dataset.reportType;
    
    // 日付範囲の取得
    let startDate, endDate;
    if (document.getElementById('date-range-picker')) {
        const dateRange = document.getElementById('date-range-picker').value;
        if (dateRange) {
            const dates = dateRange.split(' to ');
            startDate = dates[0];
            endDate = dates.length > 1 ? dates[1] : dates[0];
        }
    }
    
    // 現在のレポートを再読み込み
    loadReport(activeReportType, startDate, endDate);
}

/**
 * フィルターのリセット
 */
function resetFilters() {
    // 日付ピッカーをリセット
    if (document.getElementById('date-range-picker') && typeof flatpickr !== 'undefined') {
        const datePicker = document.getElementById('date-range-picker')._flatpickr;
        if (datePicker) {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            datePicker.setDate([firstDay, today]);
        }
    }
    
    // その他のフィルターをリセット
    document.querySelectorAll('.filter-select').forEach(select => {
        select.value = '';
    });
    
    // フィルターを適用
    applyFilters();
}

/**
 * アクティブなフィルターの取得
 * @returns {object} アクティブなフィルター
 */
function getActiveFilters() {
    const filters = {};
    
    document.querySelectorAll('.filter-select').forEach(select => {
        if (select.value) {
            filters[select.name] = select.value;
        }
    });
    
    return filters;
}

/**
 * 期間のナビゲーション
 * @param {string} direction - ナビゲーション方向
 */
function navigatePeriod(direction) {
    // 現在の日付範囲を取得
    let startDate, endDate;
    if (document.getElementById('date-range-picker')) {
        const dateRange = document.getElementById('date-range-picker').value;
        if (dateRange) {
            const dates = dateRange.split(' to ');
            startDate = new Date(dates[0]);
            endDate = new Date(dates.length > 1 ? dates[1] : dates[0]);
        }
    }
    
    if (!startDate || !endDate) {
        return;
    }
    
    // 期間の長さを計算
    const periodLength = Math.round((endDate - startDate) / (24 * 60 * 60 * 1000));
    
    // 方向に応じて日付を更新
    if (direction === 'prev') {
        endDate = new Date(startDate.getTime() - 24 * 60 * 60 * 1000);
        startDate = new Date(endDate.getTime() - periodLength * 24 * 60 * 60 * 1000);
    } else if (direction === 'next') {
        startDate = new Date(endDate.getTime() + 24 * 60 * 60 * 1000);
        endDate = new Date(startDate.getTime() + periodLength * 24 * 60 * 60 * 1000);
        
        // 未来の日付は今日まで
        const today = new Date();
        if (endDate > today) {
            endDate = today;
        }
    } else if (direction === 'current') {
        // 今月の範囲
        const today = new Date();
        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
        endDate = today;
    }
    
    // 日付ピッカーを更新
    if (document.getElementById('date-range-picker') && typeof flatpickr !== 'undefined') {
        const datePicker = document.getElementById('date-range-picker')._flatpickr;
        if (datePicker) {
            datePicker.setDate([startDate, endDate]);
        }
    }
    
    // レポートの再読み込み
    const activeReportType = document.querySelector('.report-type-nav .nav-link.active').dataset.reportType;
    loadReport(activeReportType, formatDate(startDate), formatDate(endDate));
}

/**
 * レポートデータのエクスポート
 * @param {string} format - エクスポート形式
 */
function exportReportData(format) {
    const activeReportType = document.querySelector('.report-type-nav .nav-link.active').dataset.reportType;
    
    // 日付範囲の取得
    let startDate, endDate;
    if (document.getElementById('date-range-picker')) {
        const dateRange = document.getElementById('date-range-picker').value;
        if (dateRange) {
            const dates = dateRange.split(' to ');
            startDate = dates[0];
            endDate = dates.length > 1 ? dates[1] : dates[0];
        }
    }
    
    // フィルターの取得
    const filters = getActiveFilters();
    
    // エクスポートリクエストのURL作成
    const params = new URLSearchParams();
    params.append('action', 'export_report');
    params.append('report_type', activeReportType);
    params.append('format', format);
    params.append('start_date', startDate || '');
    params.append('end_date', endDate || '');
    
    // フィルターを追加
    Object.keys(filters).forEach(key => {
        params.append(key, filters[key]);
    });
    
    // エクスポートURLを開く
    window.location.href = `ajax/reports_export.php?${params.toString()}`;
}

/**
 * 日付フォーマット
 * @param {Date} date - 日付オブジェクト
 * @returns {string} フォーマットされた日付
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * 通貨フォーマット
 * @param {number} value - 数値
 * @returns {string} フォーマットされた通貨
 */
function formatCurrency(value) {
    return `¥${value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",")}`;
}

/**
 * 値のフォーマット
 * @param {*} value - フォーマットする値
 * @param {string} type - 値のタイプ
 * @returns {string} フォーマットされた値
 */
function formatValue(value, type) {
    if (type.includes('sales') || type.includes('revenue') || type.includes('amount')) {
        return formatCurrency(value);
    }
    
    if (type.includes('percentage') || type.includes('rate')) {
        return `${value}%`;
    }
    
    return value.toString();
}

/**
 * KPI値のフォーマット
 * @param {*} value - KPI値
 * @param {string} type - KPIタイプ
 * @returns {string} フォーマットされた値
 */
function formatKpiValue(value, type) {
    if (type.includes('sales') || type.includes('revenue') || type.includes('amount')) {
        return formatCurrency(value);
    }
    
    if (type.includes('percentage') || type.includes('rate') || type.includes('achievement')) {
        return `${value}%`;
    }
    
    return value.toString();
}

/**
 * 通知の表示
 * @param {string} message - 通知メッセージ
 * @param {string} type - 通知タイプ
 */
function showNotification(message, type = 'info') {
    // トーストライブラリを使用
    if (typeof Toastify === 'function') {
        Toastify({
            text: message,
            duration: 3000,
            gravity: "top",
            position: 'right',
            backgroundColor: type === 'error' ? '#e74a3b' : 
                             type === 'success' ? '#1cc88a' : 
                             type === 'warning' ? '#f6c23e' : '#4e73df'
        }).showToast();
    } else {
        // フォールバック：アラートを使用
        alert(message);
    }
}
