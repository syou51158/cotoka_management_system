/**
 * Cotoka Management System - ヘッダーとサイドバー
 * 
 * このファイルはヘッダーとサイドバーの機能を提供します。
 * - ドロップダウンメニュー
 * - サイドバーの表示/非表示
 * - レスポンシブデザイン対応
 */

// DOM読み込み完了時に実行
document.addEventListener('DOMContentLoaded', function() {
    console.log('ヘッダーとサイドバー初期化開始');
    
    // ヘッダーとサイドバーを初期化
    initHeader();
    initSidebar();
    
    // スマホ表示のサイドバー問題を修正
    fixMobileSidebarIssues();
});

/**
 * ヘッダー初期化
 */
function initHeader() {
    // ドロップダウンメニュー初期化
    initDropdowns();
    
    // ツールチップ初期化（Bootstrapを使用）
    initTooltips();
    
    console.log('ヘッダー初期化完了');
}

/**
 * サイドバー初期化
 */
function initSidebar() {
    // サイドバートグルボタン
    initSidebarToggle();
    
    // 保存された状態を復元
    restoreSidebarState();
    
    // ウィンドウサイズ変更時の処理
    handleWindowResize();
    
    // モバイル対応の強化
    enhanceMobileExperience();
    
    // モバイルデバイスのタッチイベント対応強化
    enhanceTouchSupport();
    
    // 現在のページをアクティブに
    highlightCurrentPage();
    
    console.log('サイドバー初期化完了');
}

/**
 * ドロップダウンメニュー初期化
 */
function initDropdowns() {
    console.log('ドロップダウン初期化開始');
    
    // Bootstrap 5のデータ属性を使ったドロップダウンを初期化
    if (typeof bootstrap !== 'undefined') {
        var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
        console.log('Bootstrap5ドロップダウン初期化:', dropdownList.length);
    }
    
    // 手動実装のドロップダウン（Bootstrap不使用の場合）
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        // data-bs-toggleがない場合のみ手動処理を追加
        if (!toggle.hasAttribute('data-bs-toggle')) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const dropdown = this.nextElementSibling;
                
                if (!dropdown || !dropdown.classList.contains('dropdown-menu')) {
                    console.warn('ドロップダウンメニューが見つかりません');
                    return;
                }
                
                const isOpen = dropdown.classList.contains('show');
                
                // 他の開いているドロップダウンを閉じる
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
                
                // クリックされたドロップダウンの状態を切り替え
                if (!isOpen) {
                    dropdown.classList.add('show');
                    
                    // 画面からはみ出す場合の調整
                    const dropdownRect = dropdown.getBoundingClientRect();
                    const windowHeight = window.innerHeight;
                    
                    if (dropdownRect.bottom > windowHeight) {
                        dropdown.style.top = 'auto';
                        dropdown.style.bottom = '100%';
                    }
                }
            });
        }
    });
    
    // ドキュメント内の任意の場所をクリックしたときにドロップダウンを閉じる
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                // data-bs-toggleで制御されていないメニューのみ閉じる
                if (!menu.previousElementSibling || !menu.previousElementSibling.hasAttribute('data-bs-toggle')) {
                    menu.classList.remove('show');
                }
            });
        }
    });
    
    // ESCキーでドロップダウンを閉じる
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                // data-bs-toggleで制御されていないメニューのみ閉じる
                if (!menu.previousElementSibling || !menu.previousElementSibling.hasAttribute('data-bs-toggle')) {
                    menu.classList.remove('show');
                }
            });
        }
    });
    
    console.log('ドロップダウン初期化完了');
}

/**
 * ツールチップ初期化
 */
function initTooltips() {
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover'
            });
        });
    }
}

/**
 * サイドバートグル機能
 */
function initSidebarToggle() {
    const toggleButtons = document.querySelectorAll('.sidebar-toggle-btn');
    console.log('サイドバートグルボタン数:', toggleButtons.length);
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('サイドバートグルボタンクリック');
            toggleSidebar();
        });
    });
}

/**
 * サイドバー表示/非表示の切り替え
 */
function toggleSidebar() {
    const body = document.body;
    const isSidebarCollapsed = body.classList.contains('sidebar-collapsed');
    
    // サイドバーの状態を切り替え
    body.classList.toggle('sidebar-collapsed');
    
    // アニメーション効果を追加
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.add('animating');
        setTimeout(function() {
            sidebar.classList.remove('animating');
        }, 300);
    }
    
    // モバイル表示時のオーバーレイ処理を削除し、全ての解像度で同じ動作に
    // オーバーレイを削除（もし存在していれば）
    removeOverlay();
    
    // ローカルストレージに状態を保存
    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
    
    console.log('サイドバー状態変更: ', body.classList.contains('sidebar-collapsed') ? '非表示' : '表示');
}

/**
 * オーバーレイクリック時の処理
 */
function handleOverlayClick(e) {
    // サイドバー表示が完全に統一されたのでこの関数は不要になりましたが、互換性のために残しておきます
    toggleSidebar();
}

/**
 * オーバーレイを削除
 */
function removeOverlay() {
    const overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.removeEventListener('click', handleOverlayClick);
        overlay.removeEventListener('touchend', handleOverlayClick);
        overlay.remove();
    }
}

/**
 * 保存された状態を復元
 */
function restoreSidebarState() {
    const savedState = localStorage.getItem('sidebarCollapsed');
    
    // モバイルの場合はデフォルトで閉じる
    if (window.innerWidth < 992) {
        document.body.classList.add('sidebar-collapsed');
    } 
    // デスクトップで保存された状態がある場合はその状態を復元
    else if (savedState === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
}

/**
 * ウィンドウサイズ変更時の処理
 */
function handleWindowResize() {
    window.addEventListener('resize', function() {
        const width = window.innerWidth;
        
        if (width >= 992) {
            // デスクトップモードの場合はオーバーレイを削除
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) {
                overlay.remove();
            }
        } else {
            // モバイルモードの場合はサイドバーを閉じる
            document.body.classList.add('sidebar-collapsed');
        }
    });
}

/**
 * モバイル対応の強化
 */
function enhanceMobileExperience() {
    // サイドバーメニュー項目のクリック時、モバイルでは自動的にサイドバーを閉じる
    const menuItems = document.querySelectorAll('.sidebar-menu a');
    
    menuItems.forEach(item => {
        // クリックとタッチの両方に対応
        const handleMenuItemClick = function(e) {
            if (window.innerWidth < 992) {
                // サイドバーを閉じる
                if (!document.body.classList.contains('sidebar-collapsed')) {
                    // リンク先に移動する前に少し遅延を入れる
                    setTimeout(function() {
                        toggleSidebar();
                    }, 50);
                }
            }
        };
        
        // 既存のイベントリスナーを削除（重複防止）
        item.removeEventListener('click', handleMenuItemClick);
        item.removeEventListener('touchend', handleMenuItemClick);
        
        // 新しいイベントリスナーを追加
        item.addEventListener('click', handleMenuItemClick);
        item.addEventListener('touchend', handleMenuItemClick);
    });
}

/**
 * モバイルデバイスでのタッチサポート強化
 */
function enhanceTouchSupport() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    
    // タッチデバイスの判定
    const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    if (isTouchDevice) {
        console.log('タッチデバイスを検出しました');
        
        // サイドバーのスクロールを有効にする
        sidebar.style.overflow = 'auto';
        sidebar.style.webkitOverflowScrolling = 'touch';
        
        // z-indexと位置を強制的に設定
        sidebar.style.zIndex = '1200';
        sidebar.style.position = 'fixed';
        
        // ポインターイベントを強制的に有効にする
        sidebar.style.pointerEvents = 'auto';
        
        // サイドバー内のすべての要素にポインターイベントを強制的に有効にする
        const allElements = sidebar.querySelectorAll('*');
        allElements.forEach(el => {
            el.style.pointerEvents = 'auto';
        });
        
        // スクロール検出用の変数
        let touchStartY = 0;
        let touchStartX = 0;
        let isScrolling = false;
        let scrollThreshold = 10; // スクロールと判定する移動量（ピクセル）
        
        // サイドバー全体にスクロール検出リスナーを設定
        sidebar.addEventListener('touchstart', function(e) {
            touchStartY = e.touches[0].clientY;
            touchStartX = e.touches[0].clientX;
            isScrolling = false;
        }, {passive: true});
        
        sidebar.addEventListener('touchmove', function(e) {
            if (!isScrolling) {
                const touchY = e.touches[0].clientY;
                const touchX = e.touches[0].clientX;
                const deltaY = Math.abs(touchY - touchStartY);
                const deltaX = Math.abs(touchX - touchStartX);
                
                // 縦または横に一定以上の移動があればスクロールと判定
                if (deltaY > scrollThreshold || deltaX > scrollThreshold) {
                    isScrolling = true;
                }
            }
        }, {passive: true});
        
        // サイドバー内のリンクにタッチ対応のスタイルを追加
        const links = sidebar.querySelectorAll('a');
        links.forEach(link => {
            link.style.display = 'block';
            link.style.padding = '15px 20px';
            
            // iOS Safariでのタップハイライト問題を修正
            link.style.webkitTapHighlightColor = 'rgba(0,0,0,0)';
            
            // すべてのイベントリスナーを一度削除してクリーンな状態にする
            const clonedLink = link.cloneNode(true);
            link.parentNode.replaceChild(clonedLink, link);
            
            // タッチ開始時の処理
            clonedLink.addEventListener('touchstart', function(e) {
                console.log('タッチスタート: ', this.innerText);
                this.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
            }, {passive: true});
            
            // タッチ終了時の処理
            clonedLink.addEventListener('touchend', function(e) {
                console.log('タッチエンド: ', this.innerText);
                this.style.backgroundColor = '';
                
                // スクロール中であればリンクを無効化
                if (isScrolling) {
                    console.log('スクロール中のタップを検出: リンクをキャンセル');
                    e.preventDefault();
                    return false;
                }
                
                // リンク先に移動（スクロールしていない場合のみ）
                const href = this.getAttribute('href');
                if (href) {
                    window.location.href = href;
                }
                
                e.preventDefault();
            }, {passive: false});
            
            // スクロール中の不要なイベント発火を防止
            clonedLink.addEventListener('click', function(e) {
                if (isScrolling) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, {passive: false});
        });
        
        // オーバーレイ要素を探して、ポインターイベントを設定
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.style.pointerEvents = 'auto';
        }
    }
}

/**
 * 現在のページをアクティブにする
 */
function highlightCurrentPage() {
    const currentPath = window.location.pathname;
    const filename = currentPath.substring(currentPath.lastIndexOf('/') + 1);
    
    const menuItems = document.querySelectorAll('.sidebar-menu li');
    menuItems.forEach(function(item) {
        const link = item.querySelector('a');
        if (link) {
            // 現在のページをハイライト
            if (link.getAttribute('href').includes(filename)) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        }
    });
    
    // ダッシュボードページの場合はデフォルトでアクティブ
    if (filename === '' || filename === 'index.php' || filename === 'dashboard.php') {
        const dashboardItem = document.querySelector('.sidebar-menu li:first-child');
        if (dashboardItem) {
            dashboardItem.classList.add('active');
        }
    }
}

/**
 * スマホ表示のサイドバー問題を修正する
 */
function fixMobileSidebarIssues() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    
    // サイドバーにz-indexと位置を強制設定
    sidebar.style.zIndex = '1200';
    sidebar.style.pointerEvents = 'auto';
    
    // サイドバー内のリンクを強化
    const links = sidebar.querySelectorAll('a');
    links.forEach(link => {
        // タップ領域を広げる
        link.style.padding = '15px 20px';
        link.style.display = 'block';
        
        // iOSのタップハイライト問題修正
        link.style.webkitTapHighlightColor = 'rgba(0,0,0,0)';
        
        // ポインターイベント有効化
        link.style.pointerEvents = 'auto';
        link.style.cursor = 'pointer';
    });
    
    // サイドバーメニュー全体を強化
    const sidebarMenu = sidebar.querySelector('.sidebar-menu');
    if (sidebarMenu) {
        sidebarMenu.style.zIndex = '1201';
        sidebarMenu.style.position = 'relative';
        sidebarMenu.style.pointerEvents = 'auto';
    }
    
    // 初期状態でサイドメニューを開いている場合、レイアウトを適切に設定
    if (!document.body.classList.contains('sidebar-collapsed') && window.innerWidth < 992) {
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.style.marginLeft = '260px';
            mainContent.style.width = 'calc(100% - 260px)';
        }
        
        const mainHeader = document.querySelector('.main-header');
        if (mainHeader) {
            mainHeader.style.left = '260px';
            mainHeader.style.width = 'calc(100% - 260px)';
        }
    }
} 