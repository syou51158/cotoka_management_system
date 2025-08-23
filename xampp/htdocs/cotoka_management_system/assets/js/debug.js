/**
 * Cotoka Management System - デバッグスクリプト
 * 
 * このスクリプトはダッシュボードとドロップダウンメニューのデバッグを支援します。
 * 本番環境では使用せず、デバッグ時のみに読み込んでください。
 */

// デバッグモード
const DEBUG_MODE = true;

// ダッシュボードページでのみ実行
if (location.pathname.includes('dashboard.php') || DEBUG_MODE) {
    // ドキュメントの読み込みが完了したらデバッグを開始
    document.addEventListener('DOMContentLoaded', function() {
        console.log('%c Cotoka Debug Mode Enabled ', 'background: #d4af37; color: #000; font-weight: bold; padding: 5px;');
        console.log('%c ファイル構造整理完了 - 2024年3月4日 ', 'background: #4CAF50; color: white; font-weight: bold; padding: 5px;');
        
        if (DEBUG_MODE) {
            // CSSの読み込み状態を確認
            checkCssLoading();
            
            // サイドバーの状態を確認
            debugSidebar();
            
            // ドロップダウンメニューの状態を確認
            debugDropdowns();
            
            // JavaScriptエラーを監視
            monitorJsErrors();
            
            // レイアウトのブレークポイントを監視
            monitorBreakpoints();
            
            // 不要なファイルをチェック
            checkUnnecessaryFiles();
            
            // ファイル構造の整理結果を表示
            showCleanupResults();
        }
        
        // デバッグパネルを追加（デベロッパーツールを開かなくてもステータスを確認できる）
        appendDebugPanel();
    });
}

/**
 * CSSファイルの読み込み状態を確認
 */
function checkCssLoading() {
    console.group('CSS Loading Check');
    
    const styleSheets = document.styleSheets;
    console.log(`Total stylesheets: ${styleSheets.length}`);
    
    let loadedSheets = [];
    let failedSheets = [];
    
    for (let i = 0; i < styleSheets.length; i++) {
        try {
            const sheet = styleSheets[i];
            const href = sheet.href || 'inline style';
            
            // エラーをチェック
            try {
                // ルールにアクセスしてみる（CORSエラーがあれば例外が投げられる）
                const rules = sheet.cssRules || sheet.rules;
                loadedSheets.push({
                    href: href,
                    rules: rules.length
                });
            } catch (e) {
                // CORSエラーまたはその他の理由でルールにアクセスできない
                failedSheets.push({
                    href: href,
                    error: e.message
                });
            }
        } catch (e) {
            console.error(`Error checking stylesheet ${i}:`, e);
        }
    }
    
    console.log('Successfully loaded stylesheets:', loadedSheets);
    if (failedSheets.length > 0) {
        console.warn('Failed to access rules in these stylesheets (possibly CORS issues):', failedSheets);
    }
    
    // ダッシュボード特有のスタイルが適用されているか確認
    const dashboardStyles = getComputedStyle(document.querySelector('.dashboard-container') || document.body);
    console.log('Dashboard container styles:', {
        display: dashboardStyles.display,
        gridTemplateColumns: dashboardStyles.gridTemplateColumns,
        gap: dashboardStyles.gap
    });
    
    console.groupEnd();
}

/**
 * サイドバーのデバッグ
 */
function debugSidebar() {
    console.group('Sidebar Debug');
    
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) {
        console.warn('Sidebar element not found!');
        console.groupEnd();
        return;
    }
    
    const sidebarStyles = getComputedStyle(sidebar);
    console.log('Sidebar element:', sidebar);
    console.log('Sidebar computed styles:', {
        width: sidebarStyles.width,
        left: sidebarStyles.left,
        zIndex: sidebarStyles.zIndex,
        transform: sidebarStyles.transform,
        transition: sidebarStyles.transition
    });
    
    // サイドバートグルボタン
    const toggleButtons = document.querySelectorAll('.sidebar-toggle-btn');
    console.log(`Sidebar toggle buttons: ${toggleButtons.length}`, toggleButtons);
    
    // イベントリスナーの状態確認（間接的な方法）
    if (toggleButtons.length > 0) {
        console.log('Testing click handling on toggle buttons...');
        toggleButtons.forEach((btn, index) => {
            console.log(`Toggle button ${index} has click handler:`, btn.onclick !== null);
        });
    }
    
    console.groupEnd();
}

/**
 * ドロップダウンメニューのデバッグ
 */
function debugDropdowns() {
    console.group('Dropdown Debug');
    
    const dropdowns = document.querySelectorAll('.dropdown');
    console.log(`Found ${dropdowns.length} dropdown elements:`, dropdowns);
    
    dropdowns.forEach((dropdown, index) => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            const toggleStyles = getComputedStyle(toggle);
            const menuStyles = getComputedStyle(menu);
            
            console.log(`Dropdown #${index}:`, {
                element: dropdown,
                toggle: {
                    element: toggle,
                    position: toggleStyles.position,
                    zIndex: toggleStyles.zIndex
                },
                menu: {
                    element: menu,
                    position: menuStyles.position,
                    zIndex: menuStyles.zIndex,
                    display: menuStyles.display,
                    visibility: menuStyles.visibility,
                    opacity: menuStyles.opacity,
                    transform: menuStyles.transform
                }
            });
            
            // ドロップダウンイベントのテスト
            console.log(`Dropdown #${index} toggle has click handler:`, toggle.onclick !== null);
            
            // Bootstrap data-bs属性の確認
            const hasBootstrapAttrs = toggle.hasAttribute('data-bs-toggle');
            console.log(`Dropdown #${index} uses Bootstrap data attributes:`, hasBootstrapAttrs);
        } else {
            console.warn(`Dropdown #${index} is missing toggle or menu elements`);
        }
    });
    
    console.groupEnd();
}

/**
 * JavaScriptエラーを監視
 */
function monitorJsErrors() {
    window.addEventListener('error', function(event) {
        console.group('JavaScript Error Detected');
        console.error('Error:', event.error);
        console.error('Message:', event.message);
        console.error('Line:', event.lineno, 'Column:', event.colno);
        console.error('File:', event.filename);
        console.groupEnd();
    });
    
    window.addEventListener('unhandledrejection', function(event) {
        console.group('Unhandled Promise Rejection');
        console.error('Reason:', event.reason);
        console.groupEnd();
    });
}

/**
 * レイアウトのブレークポイントを監視
 */
function monitorBreakpoints() {
    const breakpoints = {
        xs: 0,
        sm: 576,
        md: 768,
        lg: 992,
        xl: 1200,
        xxl: 1400
    };
    
    function checkBreakpoint() {
        const width = window.innerWidth;
        let currentBreakpoint = 'xs';
        
        for (const [name, size] of Object.entries(breakpoints)) {
            if (width >= size) {
                currentBreakpoint = name;
            }
        }
        
        console.log(`Current breakpoint: ${currentBreakpoint} (${width}px)`);
        return currentBreakpoint;
    }
    
    // 初期チェック
    let lastBreakpoint = checkBreakpoint();
    
    // ウィンドウのリサイズを監視
    window.addEventListener('resize', function() {
        const currentBreakpoint = checkBreakpoint();
        if (currentBreakpoint !== lastBreakpoint) {
            console.log(`Breakpoint changed: ${lastBreakpoint} -> ${currentBreakpoint}`);
            lastBreakpoint = currentBreakpoint;
        }
    });
}

/**
 * デバッグパネルを追加
 */
function appendDebugPanel() {
    if (!DEBUG_MODE) return;
    
    // パネルを作成
    const panel = document.createElement('div');
    panel.className = 'debug-panel';
    panel.style.cssText = `
        position: fixed;
        bottom: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.8);
        color: #fff;
        padding: 10px;
        border-radius: 5px;
        font-family: monospace;
        font-size: 12px;
        z-index: 10000;
        max-width: 300px;
        max-height: 200px;
        overflow: auto;
        backdrop-filter: blur(5px);
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    `;
    
    // タイトル
    const title = document.createElement('div');
    title.textContent = 'Debug Panel';
    title.style.cssText = `
        font-weight: bold;
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        margin-bottom: 5px;
        padding-bottom: 5px;
    `;
    panel.appendChild(title);
    
    // 情報コンテナ
    const infoContainer = document.createElement('div');
    panel.appendChild(infoContainer);
    
    // パネルを追加
    document.body.appendChild(panel);
    
    // 情報を更新する関数
    function updateInfo() {
        const info = {
            'Viewport': `${window.innerWidth}x${window.innerHeight}`,
            'Sidebar': document.querySelector('.sidebar') ? 'Found' : 'Missing',
            'Dropdowns': document.querySelectorAll('.dropdown').length,
            'Dashboard Container': document.querySelector('.dashboard-container') ? 'Found' : 'Missing',
            'Status Cards': document.querySelectorAll('.status-card').length,
            'CSS Files': document.styleSheets.length,
            'Memory Usage': Math.round(performance.memory?.usedJSHeapSize / 1048576) + 'MB' || 'Unknown'
        };
        
        // 情報を表示
        infoContainer.innerHTML = '';
        for (const [key, value] of Object.entries(info)) {
            const row = document.createElement('div');
            row.style.cssText = `
                display: flex;
                justify-content: space-between;
                margin-bottom: 3px;
            `;
            
            const keyEl = document.createElement('span');
            keyEl.textContent = key + ':';
            keyEl.style.marginRight = '10px';
            
            const valueEl = document.createElement('span');
            valueEl.textContent = value;
            valueEl.style.fontWeight = 'bold';
            
            row.appendChild(keyEl);
            row.appendChild(valueEl);
            infoContainer.appendChild(row);
        }
    }
    
    // 初回更新と定期更新
    updateInfo();
    setInterval(updateInfo, 1000);
    
    // パネルのドラッグ機能
    let isDragging = false;
    let offsetX, offsetY;
    
    title.style.cursor = 'move';
    title.addEventListener('mousedown', startDrag);
    
    function startDrag(e) {
        isDragging = true;
        offsetX = e.clientX - panel.getBoundingClientRect().left;
        offsetY = e.clientY - panel.getBoundingClientRect().top;
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
        e.preventDefault();
    }
    
    function onDrag(e) {
        if (!isDragging) return;
        panel.style.right = 'auto';
        panel.style.left = `${e.clientX - offsetX}px`;
        panel.style.top = `${e.clientY - offsetY}px`;
        panel.style.bottom = 'auto';
    }
    
    function stopDrag() {
        isDragging = false;
        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', stopDrag);
    }
    
    // トグルボタン
    const toggleButton = document.createElement('button');
    toggleButton.textContent = '-';
    toggleButton.style.cssText = `
        position: absolute;
        top: 5px;
        right: 5px;
        background: none;
        border: none;
        color: white;
        font-weight: bold;
        cursor: pointer;
        width: 20px;
        height: 20px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    title.appendChild(toggleButton);
    
    let isCollapsed = false;
    toggleButton.addEventListener('click', function() {
        if (isCollapsed) {
            infoContainer.style.display = 'block';
            toggleButton.textContent = '-';
        } else {
            infoContainer.style.display = 'none';
            toggleButton.textContent = '+';
        }
        isCollapsed = !isCollapsed;
    });
}

/**
 * 不要なファイルをチェック
 */
function checkUnnecessaryFiles() {
    console.group('Unnecessary Files Check');
    
    // CSSファイル
    const cssFiles = [
        'dashboard-enhancements.css',
        'menu-enhancements.css'
    ];
    
    // JSファイル
    const jsFiles = [
        'luxury-background.js',
        'background-animation.js'
    ];
    
    // 読み込まれているCSSファイルをチェック
    const loadedCssFiles = Array.from(document.styleSheets)
        .filter(sheet => sheet.href)
        .map(sheet => {
            const url = new URL(sheet.href);
            return url.pathname.split('/').pop();
        });
    
    console.log('Loaded CSS files:', loadedCssFiles);
    
    // 不要なCSSファイルが読み込まれているかチェック
    const unnecessaryCssFiles = cssFiles.filter(file => 
        loadedCssFiles.some(loadedFile => loadedFile.includes(file))
    );
    
    if (unnecessaryCssFiles.length > 0) {
        console.warn('Unnecessary CSS files loaded:', unnecessaryCssFiles);
    } else {
        console.log('No unnecessary CSS files detected');
    }
    
    // 読み込まれているJSファイルをチェック（部分的な検出）
    const scripts = Array.from(document.scripts)
        .filter(script => script.src)
        .map(script => {
            const url = new URL(script.src);
            return url.pathname.split('/').pop();
        });
    
    console.log('Loaded JS files:', scripts);
    
    // 不要なJSファイルが読み込まれているかチェック
    const unnecessaryJsFiles = jsFiles.filter(file => 
        scripts.some(loadedFile => loadedFile.includes(file))
    );
    
    if (unnecessaryJsFiles.length > 0) {
        console.warn('Unnecessary JS files loaded:', unnecessaryJsFiles);
    } else {
        console.log('No unnecessary JS files detected');
    }
    
    console.groupEnd();
}

/**
 * ファイル構造の整理結果を表示
 */
function showCleanupResults() {
    console.group('ファイル構造整理結果');
    
    // 削除されたファイル
    const deletedFiles = [
        'assets/js/luxury-background.js',
        'assets/js/background-animation.js',
        'assets/css/dashboard-enhancements.css',
        'assets/css/menu-enhancements.css',
        'assets/js/dashboard/main.js',
        'assets/js/dashboard/charts.js',
        'assets/css/dashboard/main.css',
        'assets/css/dashboard/charts.css'
    ];
    
    // 新しく作成されたファイル
    const newFiles = [
        'assets/css/dashboard.css',
        'assets/js/dashboard.js',
        'assets/js/debug.js'
    ];
    
    // 更新されたファイル
    const updatedFiles = [
        'includes/header.php',
        'includes/footer.php',
        'dashboard.php'
    ];
    
    console.log('削除されたファイル:', deletedFiles);
    console.log('新しく作成されたファイル:', newFiles);
    console.log('更新されたファイル:', updatedFiles);
    
    // 現在読み込まれているCSSファイル
    const loadedCssFiles = Array.from(document.styleSheets)
        .filter(sheet => sheet.href)
        .map(sheet => {
            try {
                const url = new URL(sheet.href);
                return url.pathname.split('/').pop();
            } catch (e) {
                return sheet.href;
            }
        });
    
    // 現在読み込まれているJSファイル
    const scripts = Array.from(document.scripts)
        .filter(script => script.src)
        .map(script => {
            try {
                const url = new URL(script.src);
                return url.pathname.split('/').pop();
            } catch (e) {
                return script.src;
            }
        });
    
    console.log('現在読み込まれているCSSファイル:', loadedCssFiles);
    console.log('現在読み込まれているJSファイル:', scripts);
    
    console.groupEnd();
} 