document.addEventListener('DOMContentLoaded', function() {
    // 基本設定
    const dateRange = document.getElementById('date-range');
    const staffFilter = document.getElementById('staff-filter');
    const serviceFilter = document.getElementById('service-filter');
    const paymentMethodFilter = document.getElementById('payment-method-filter');
    const applyFiltersBtn = document.getElementById('apply-filters');
    const resetFiltersBtn = document.getElementById('reset-filters');
    
    // エクスポートボタン
    const exportCsvBtn = document.getElementById('export-csv-btn');
    const exportPdfBtn = document.getElementById('export-pdf-btn');
    const printReportBtn = document.getElementById('print-report-btn');
    
    // タブ要素
    const salesTabs = document.querySelectorAll('#salesTabs .nav-link');
    
    // グラフコンテキスト
    const salesChartCtx = document.getElementById('salesChart').getContext('2d');
    const salesCompositionChartCtx = document.getElementById('salesCompositionChart').getContext('2d');
    
    // グラフインスタンス
    let salesChart;
    let salesCompositionChart;
    
    // 期間オプション
    const periodOptions = document.querySelectorAll('.period-option');
    
    // 初期化
    initialize();
    
    // 初期化処理
    function initialize() {
        // フラットピッカーの初期化
        flatpickr(dateRange, {
            locale: 'ja',
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: [CONFIG.start_date, CONFIG.end_date]
        });
        
        // イベントリスナーの設定
        setupEventListeners();
        
        // グラフの初期化
        initializeCharts();
        
        // デバッグ情報の出力
        console.log('初期化完了:', CONFIG);
    }
    
    // イベントリスナーの設定
    function setupEventListeners() {
        // フィルター適用ボタン
        applyFiltersBtn.addEventListener('click', function() {
            applyFilters();
        });
        
        // フィルターリセットボタン
        resetFiltersBtn.addEventListener('click', function() {
            resetFilters();
        });
        
        // CSVエクスポートボタン
        exportCsvBtn.addEventListener('click', function() {
            exportData('csv');
        });
        
        // PDFエクスポートボタン
        exportPdfBtn.addEventListener('click', function() {
            exportData('pdf');
        });
        
        // 印刷ボタン
        printReportBtn.addEventListener('click', function() {
            printReport();
        });
        
        // 期間オプション
        periodOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                const period = this.dataset.period;
                setPeriod(period);
            });
        });
        
        // タブ切り替え
        salesTabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                // タブ切り替え時にグラフを更新
                if (e.target.id === 'service-tab' || e.target.id === 'staff-tab') {
                    updateCompositionChart(e.target.id);
                }
            });
        });
        
        // 日別売上の行クリック
        document.querySelectorAll('.sale-row').forEach(row => {
            row.addEventListener('click', function() {
                const date = this.dataset.date;
                if (date) {
                    showDailySalesDetails(date);
                }
            });
        });
        
        // 取引履歴の行クリック
        document.querySelectorAll('.transaction-row').forEach(row => {
            row.addEventListener('click', function() {
                const id = this.dataset.id;
                if (id) {
                    showTransactionDetails(id);
                }
            });
        });
    }
    
    // グラフの初期化
    function initializeCharts() {
        // 売上推移グラフの初期化
        initializeSalesChart();
        
        // 売上構成グラフの初期化
        initializeCompositionChart();
    }
    
    // 売上推移グラフの初期化
    function initializeSalesChart() {
        if (salesChart) {
            salesChart.destroy();
        }
        
        // 日付と売上データの抽出
        const labels = CONFIG.monthly_sales.map(sale => formatChartDate(sale.date));
        const salesData = CONFIG.monthly_sales.map(sale => sale.total_amount);
        const customerData = CONFIG.monthly_sales.map(sale => sale.customer_count);
        
        // グラフの作成
        salesChart = new Chart(salesChartCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '売上',
                        data: salesData,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        pointBackgroundColor: '#4e73df',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: true,
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: '顧客数',
                        data: customerData,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0)',
                        pointBackgroundColor: '#1cc88a',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label === '売上') {
                                    return label + ': ¥' + numberWithCommas(context.raw);
                                } else {
                                    return label + ': ' + numberWithCommas(context.raw) + '人';
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return '¥' + numberWithCommas(value);
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '人';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // 売上構成グラフの初期化
    function initializeCompositionChart() {
        if (salesCompositionChart) {
            salesCompositionChart.destroy();
        }
        
        // サービス別売上データの抽出
        const labels = CONFIG.service_sales.map(service => service.service_name);
        const data = CONFIG.service_sales.map(service => service.total_amount);
        
        // 色の生成
        const colors = generateChartColors(data.length);
        
        // グラフの作成
        salesCompositionChart = new Chart(salesCompositionChartCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: '#ffffff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ¥${numberWithCommas(value)} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }
    
    // 売上構成グラフの更新
    function updateCompositionChart(tabId) {
        if (salesCompositionChart) {
            salesCompositionChart.destroy();
        }
        
        let labels, data;
        
        // タブに応じてデータを選択
        if (tabId === 'service-tab' || tabId === 'services') {
            labels = CONFIG.service_sales.map(service => service.service_name);
            data = CONFIG.service_sales.map(service => service.total_amount);
        } else if (tabId === 'staff-tab' || tabId === 'staff') {
            labels = CONFIG.staff_sales.map(staff => staff.staff_name);
            data = CONFIG.staff_sales.map(staff => staff.total_amount);
        } else {
            // デフォルトはサービス別
            labels = CONFIG.service_sales.map(service => service.service_name);
            data = CONFIG.service_sales.map(service => service.total_amount);
        }
        
        // 色の生成
        const colors = generateChartColors(data.length);
        
        // グラフの作成
        salesCompositionChart = new Chart(salesCompositionChartCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: '#ffffff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ¥${numberWithCommas(value)} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }
    
    // フィルターの適用
    function applyFilters() {
        const filters = getFilters();
        
        // APIリクエストパラメータの構築
        let queryParams = new URLSearchParams();
        queryParams.append('salon_id', CONFIG.salon_id);
        
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                queryParams.append(key, filters[key]);
            }
        });
        
        // リダイレクト
        window.location.href = `sales.php?${queryParams.toString()}`;
    }
    
    // 現在のフィルター設定を取得
    function getFilters() {
        const dateRangeValue = dateRange.value;
        let startDate = '';
        let endDate = '';
        
        if (dateRangeValue) {
            const dates = dateRangeValue.split(' to ');
            startDate = dates[0];
            endDate = dates.length > 1 ? dates[1] : dates[0];
        }
        
        return {
            start_date: startDate,
            end_date: endDate,
            staff_id: staffFilter.value,
            service_id: serviceFilter.value,
            payment_method: paymentMethodFilter.value
        };
    }
    
    // フィルターのリセット
    function resetFilters() {
        dateRange.value = '';
        staffFilter.value = '';
        serviceFilter.value = '';
        paymentMethodFilter.value = '';
        
        // 現在の月に戻る
        window.location.href = `sales.php`;
    }
    
    // データのエクスポート
    function exportData(format) {
        const filters = getFilters();
        
        // APIリクエストパラメータの構築
        let queryParams = new URLSearchParams();
        queryParams.append('salon_id', CONFIG.salon_id);
        queryParams.append('format', format);
        
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                queryParams.append(key, filters[key]);
            }
        });
        
        // タブの状態を追加
        const activeTab = document.querySelector('#salesTabs .nav-link.active').id;
        queryParams.append('tab', activeTab.replace('-tab', ''));
        
        // エクスポートAPIを呼び出す
        const apiEndpoint = format === 'csv' ? API_ENDPOINTS.EXPORT_CSV : API_ENDPOINTS.EXPORT_PDF;
        window.open(`${apiEndpoint}?${queryParams.toString()}`, '_blank');
    }
    
    // レポートの印刷
    function printReport() {
        window.print();
    }
    
    // 期間の設定
    function setPeriod(period) {
        const today = new Date();
        let startDate, endDate;
        
        switch (period) {
            case 'day':
                // 今日
                startDate = formatDate(today);
                endDate = formatDate(today);
                break;
            case 'week':
                // 今週（月曜日から日曜日）
                startDate = formatDate(getStartOfWeek(today));
                endDate = formatDate(getEndOfWeek(today));
                break;
            case 'month':
                // 今月
                startDate = formatDate(getStartOfMonth(today));
                endDate = formatDate(getEndOfMonth(today));
                break;
            case 'year':
                // 今年
                startDate = formatDate(getStartOfYear(today));
                endDate = formatDate(getEndOfYear(today));
                break;
            case 'custom':
                // カスタム期間は何もせず、ユーザーが選択できるようにする
                return;
            default:
                // デフォルトは今月
                startDate = formatDate(getStartOfMonth(today));
                endDate = formatDate(getEndOfMonth(today));
        }
        
        // 日付範囲を設定
        dateRange.value = `${startDate} to ${endDate}`;
        
        // フィルターを適用
        applyFilters();
    }
    
    // 日別売上の詳細表示
    function showDailySalesDetails(date) {
        const formattedDate = formatDisplayDate(date);
        alert(`${formattedDate}の詳細売上機能は準備中です。`);
        
        // TODO: 日別売上の詳細を表示するモーダルを実装
    }
    
    // 取引の詳細表示
    function showTransactionDetails(transactionId) {
        alert(`取引ID: ${transactionId}の詳細機能は準備中です。`);
        
        // TODO: 取引詳細を表示するモーダルを実装
    }
    
    // ユーティリティ関数
    
    // 日付フォーマット (YYYY-MM-DD)
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // グラフ用日付フォーマット (M/D)
    function formatChartDate(dateString) {
        const date = new Date(dateString);
        return `${date.getMonth() + 1}/${date.getDate()}`;
    }
    
    // 表示用日付フォーマット
    function formatDisplayDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    // 週の開始日（月曜日）を取得
    function getStartOfWeek(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // 日曜日の場合は前の月曜日
        return new Date(d.setDate(diff));
    }
    
    // 週の終了日（日曜日）を取得
    function getEndOfWeek(date) {
        const d = new Date(getStartOfWeek(date));
        d.setDate(d.getDate() + 6);
        return d;
    }
    
    // 月の開始日を取得
    function getStartOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1);
    }
    
    // 月の終了日を取得
    function getEndOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth() + 1, 0);
    }
    
    // 年の開始日を取得
    function getStartOfYear(date) {
        return new Date(date.getFullYear(), 0, 1);
    }
    
    // 年の終了日を取得
    function getEndOfYear(date) {
        return new Date(date.getFullYear(), 11, 31);
    }
    
    // 数値のカンマ区切り
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    
    // グラフ用の色の生成
    function generateChartColors(count) {
        const baseColors = [
            '#4e73df', // 青
            '#1cc88a', // 緑
            '#36b9cc', // 水色
            '#f6c23e', // 黄色
            '#e74a3b', // 赤
            '#6f42c1', // 紫
            '#fd7e14', // オレンジ
            '#20c9a6', // ティール
            '#5a5c69', // グレー
            '#858796'  // 薄いグレー
        ];
        
        let colors = [];
        
        for (let i = 0; i < count; i++) {
            if (i < baseColors.length) {
                colors.push(baseColors[i]);
            } else {
                // 基本色が足りない場合はランダムな色を生成
                const r = Math.floor(Math.random() * 200);
                const g = Math.floor(Math.random() * 200);
                const b = Math.floor(Math.random() * 200);
                colors.push(`rgb(${r}, ${g}, ${b})`);
            }
        }
        
        return colors;
    }
}); 