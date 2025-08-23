/**
 * Cotoka Management System - ダッシュボード専用JavaScript
 * すべてのダッシュボード機能を統合
 */

// ドキュメント読み込み完了時に実行
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard JS initialized');
    
    // 各機能を初期化
    initTooltips();
    initRevenueChart();
    initStatusCards();
    initServiceStats();
    makeTablesResponsive();
    initDatePicker();
    
    // 予約関連機能の初期化
    initAppointmentActions();
    initCancelModals();
});

// Bootstrapツールチップを初期化
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// 売上チャートを初期化
function initRevenueChart() {
    var revenueChartCanvas = document.getElementById('revenueChart');
    if (!revenueChartCanvas) return;
    
    try {
        // チャートデータを取得（PHPから注入される想定）
        let chartLabels = [];
        let chartData = [];
        
        if (typeof revenueData !== 'undefined' && revenueData) {
            chartLabels = revenueData.map(item => item.month);
            chartData = revenueData.map(item => parseInt(item.revenue));
        } else {
            console.warn('Revenue data not found, using placeholder data');
            chartLabels = ['1月', '2月', '3月', '4月', '5月', '6月'];
            chartData = [450000, 380000, 520000, 480000, 520000, 650000];
        }
        
        var ctx = revenueChartCanvas.getContext('2d');
        
        // グラデーションの設定
        var gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(212, 175, 55, 0.6)');
        gradient.addColorStop(1, 'rgba(212, 175, 55, 0.05)');
        
        // チャートの設定
        var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: '月間売上（円）',
                    data: chartData,
                    backgroundColor: gradient,
                    borderColor: '#d4af37',
                    borderWidth: 2,
                    pointBackgroundColor: '#d4af37',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        padding: 12,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        callbacks: {
                            label: function(context) {
                                return '売上: ' + context.parsed.y.toLocaleString() + '円';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            color: '#666'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            color: '#666',
                            callback: function(value) {
                                if (value >= 1000000) {
                                    return (value / 1000000).toFixed(0) + 'M';
                                } else if (value >= 1000) {
                                    return (value / 1000).toFixed(0) + 'K';
                                }
                                return value;
                            }
                        }
                    }
                }
            }
        });
        
        // データが更新された場合にチャートを再描画する関数
        window.updateRevenueChart = function(newData) {
            myChart.data.labels = newData.map(item => item.month);
            myChart.data.datasets[0].data = newData.map(item => parseInt(item.revenue));
            myChart.update();
        }
    } catch (error) {
        console.error('Error initializing revenue chart:', error);
    }
}

// ステータスカードのアニメーションを初期化
function initStatusCards() {
    // ステータスカードに順次フェードインアニメーションを適用
    const statusCards = document.querySelectorAll('.status-card');
    statusCards.forEach((card, index) => {
        // カードが見えるようになったらアニメーションをトリガー
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 100);
                    observer.unobserve(card);
                }
            });
        }, { threshold: 0.1 });
        
        observer.observe(card);
    });
}

// サービス統計の初期化
function initServiceStats() {
    // 人気サービスアイテムのアニメーション
    const serviceItems = document.querySelectorAll('.popular-service-item, .recent-customer-card');
    serviceItems.forEach((item, index) => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 300 + index * 100);
                    observer.unobserve(item);
                }
            });
        }, { threshold: 0.1 });
        
        observer.observe(item);
    });
}

// テーブルをモバイル対応にする
function makeTablesResponsive() {
    const tables = document.querySelectorAll('table.table');
    
    // 画面幅が狭いときにテーブルをスクロール可能にする
    tables.forEach(table => {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
    
    // テーブル行をホバーしたときのエフェクト
    const tableRows = document.querySelectorAll('table.table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(212, 175, 55, 0.05)';
            this.style.transition = 'background-color 0.2s';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
}

// 日付選択機能の初期化（オプション）
function initDatePicker() {
    const datePickerEl = document.getElementById('dashboard-date-picker');
    if (!datePickerEl) return;
    
    try {
        // フラットピッカーが利用可能な場合は初期化
        if (typeof flatpickr === 'function') {
            flatpickr(datePickerEl, {
                dateFormat: "Y年m月d日",
                locale: "ja",
                onChange: function(selectedDates, dateStr) {
                    console.log('Selected date:', dateStr);
                    // ここに日付変更時の処理を追加
                }
            });
        } else {
            console.warn('Flatpickr not available');
        }
    } catch (error) {
        console.error('Error initializing date picker:', error);
    }
}

// ウィンドウサイズ変更時の処理
window.addEventListener('resize', function() {
    // レスポンシブ対応が必要な場合に実行する処理
    if (window.innerWidth <= 768) {
        // モバイル向けの処理
    } else {
        // デスクトップ向けの処理
    }
});

// ページ遷移時のアニメーション
document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('loaded');
});

// AJAX関数（サンプル） - 後で必要に応じて使用
function fetchData(url, callback) {
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            callback(null, data);
        })
        .catch(error => {
            console.error('Fetch error:', error);
            callback(error, null);
        });
}

// 予約ステータス更新機能
function initAppointmentActions() {
    // ステータス変更リンクのイベント処理
    const statusActions = document.querySelectorAll('.status-action');
    statusActions.forEach(action => {
        action.addEventListener('click', function(e) {
            if (!confirm('予約ステータスを変更してもよろしいですか？')) {
                e.preventDefault();
            }
        });
    });
    
    console.log('予約ステータス更新機能を初期化しました');
}

// キャンセル確認モーダル
function initCancelModals() {
    // キャンセルモーダルの初期化（Bootstrapを使用）
    const cancelModals = document.querySelectorAll('.modal');
    
    if (cancelModals.length > 0) {
        // Bootstrap 5のModal初期化
        cancelModals.forEach(modalEl => {
            // モーダルインスタンス作成（すでに初期化されている場合は処理しない）
            if (!modalEl.classList.contains('initialized')) {
                try {
                    // Bootstrap 5のModalインスタンスを作成
                    let modal = new bootstrap.Modal(modalEl);
                    modalEl.classList.add('initialized');
                    
                    // モーダルイベントをバインド
                    modalEl.addEventListener('shown.bs.modal', function() {
                        console.log('モーダルが表示されました: ' + modalEl.id);
                    });
                    
                    modalEl.addEventListener('hidden.bs.modal', function() {
                        console.log('モーダルが閉じられました: ' + modalEl.id);
                    });
                    
                    // キャンセルボタンのイベント処理
                    const cancelButton = modalEl.querySelector('.btn-danger');
                    if (cancelButton) {
                        cancelButton.addEventListener('click', function(e) {
                            console.log('キャンセル処理を実行します: ' + cancelButton.getAttribute('href'));
                        });
                    }
                } catch (error) {
                    console.error('モーダル初期化エラー:', error);
                }
            }
        });
        
        // キャンセルリンクにクリックイベントを追加
        const cancelLinks = document.querySelectorAll('[data-bs-target^="#cancelModal"]');
        cancelLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const targetId = this.getAttribute('data-bs-target');
                console.log('キャンセルリンクがクリックされました: ' + targetId);
            });
        });
        
        console.log('キャンセルモーダルを初期化しました (' + cancelModals.length + ')');
    } else {
        console.log('キャンセルモーダルが見つかりません');
    }
} 