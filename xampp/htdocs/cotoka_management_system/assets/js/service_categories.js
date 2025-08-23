/**
 * サービスカテゴリー管理ページのJavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('カテゴリー管理ページが読み込まれました');
    
    // カラー選択の連動（追加モーダル）
    setupColorInputSync('color', 'color_hex');
    
    // カラー選択の連動（編集モーダル）
    setupColorInputSync('edit_color', 'edit_color_hex');
    
    // モーダル処理の初期化
    initializeModals();
    
    // 削除確認ボタンの設定
    setupDeleteConfirmation();
    
    // デバッグ情報の表示
    logDebugInfo();
});

/**
 * カラー入力の同期設定
 */
function setupColorInputSync(colorInputId, hexInputId) {
    const colorInput = document.getElementById(colorInputId);
    const hexInput = document.getElementById(hexInputId);
    
    if (colorInput && hexInput) {
        colorInput.addEventListener('input', function() {
            hexInput.value = this.value;
            console.log(`カラー変更: ${colorInputId} → ${this.value}`);
        });
        
        hexInput.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                colorInput.value = this.value;
                console.log(`ヘキサ値変更: ${hexInputId} → ${this.value}`);
            }
        });
    }
}

/**
 * モーダルの初期化
 */
function initializeModals() {
    // 編集ボタンのイベントリスナーを設定
    const editButtons = document.querySelectorAll('.edit-category-btn');
    console.log(`編集ボタン数: ${editButtons.length}`);
    
    editButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const categoryId = this.getAttribute('data-id');
            const categoryName = this.getAttribute('data-name');
            const categoryColor = this.getAttribute('data-color');
            const categoryOrder = this.getAttribute('data-order');
            
            console.log('編集ボタンクリック:');
            console.log(`- ID: ${categoryId}`);
            console.log(`- 名前: ${categoryName}`);
            console.log(`- カラー: ${categoryColor}`);
            console.log(`- 表示順: ${categoryOrder}`);
            
            // データ属性の存在確認
            if (!categoryId) {
                console.error('カテゴリーIDが取得できません');
                return;
            }
            
            // フォームに値をセット
            document.getElementById('edit_category_id').value = categoryId;
            document.getElementById('edit_name').value = categoryName;
            document.getElementById('edit_color').value = categoryColor;
            document.getElementById('edit_color_hex').value = categoryColor;
            document.getElementById('edit_display_order').value = categoryOrder;
            
            // モーダル表示は自動的に行われるためここでは何もしない
        });
    });
    
    // 削除ボタンのイベント処理
    document.querySelectorAll('.delete-category-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const categoryId = this.getAttribute('data-id');
            const categoryName = this.getAttribute('data-name');
            
            console.log(`削除ボタンクリック: ID=${categoryId}, name=${categoryName}`);
            
            // モーダルにデータをセット
            document.getElementById('delete_category_name').textContent = categoryName;
            document.getElementById('confirm_delete_btn').setAttribute('href', `service_categories.php?action=delete&id=${categoryId}`);
        });
    });
    
    // フォーム送信前の検証
    const editForm = document.getElementById('editCategoryForm');
    if (editForm) {
        editForm.addEventListener('submit', function(event) {
            const nameField = document.getElementById('edit_name');
            if (!nameField || !nameField.value.trim()) {
                alert('カテゴリー名を入力してください');
                event.preventDefault();
                return false;
            }
            
            console.log('編集フォーム送信:');
            console.log(`- ID: ${document.getElementById('edit_category_id').value}`);
            console.log(`- 名前: ${nameField.value}`);
            console.log(`- カラー: ${document.getElementById('edit_color').value}`);
            
            return true; // フォーム送信を続行
        });
    }
}

/**
 * 削除確認ボタンの設定
 */
function setupDeleteConfirmation() {
    const confirmDeleteBtn = document.getElementById('confirm_delete_btn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function(event) {
            const url = this.getAttribute('href');
            if (!url || url === '#') {
                event.preventDefault();
                alert('削除URLが正しく設定されていません。');
                return false;
            }
            // 通常のリンクとして機能させる - 何もしない
        });
    }
}

/**
 * デバッグ情報をコンソールに出力
 */
function logDebugInfo() {
    console.log('--- デバッグ情報 ---');
    
    // 編集ボタン情報
    const editButtons = document.querySelectorAll('.edit-category-btn');
    console.log(`編集ボタン総数: ${editButtons.length}`);
    editButtons.forEach((btn, index) => {
        console.log(`編集ボタン ${index + 1}:`);
        console.log(`- ID: ${btn.getAttribute('data-id')}`);
        console.log(`- 名前: ${btn.getAttribute('data-name')}`);
        console.log(`- 色: ${btn.getAttribute('data-color')}`);
        console.log(`- 順序: ${btn.getAttribute('data-order')}`);
    });
    
    // フォーム要素の存在確認
    console.log('フォーム要素チェック:');
    ['edit_category_id', 'edit_name', 'edit_color', 'edit_color_hex', 'edit_display_order'].forEach(id => {
        const element = document.getElementById(id);
        console.log(`${id}: ${element ? '存在します' : '見つかりません'}`);
    });
}

/**
 * レスポンシブテーブルの設定
 */
function setupResponsiveTable() {
    // モバイル端末向けのテーブル最適化
    const isMobile = window.innerWidth < 768;
    if (isMobile) {
        const table = document.querySelector('.table-responsive table');
        if (table) {
            // スワイプでスクロールできることを示すヒントを追加
            const swipeHint = document.createElement('div');
            swipeHint.className = 'text-muted text-center mb-2';
            swipeHint.innerHTML = '<i class="fas fa-arrow-left"></i> スワイプでテーブルをスクロール <i class="fas fa-arrow-right"></i>';
            table.parentNode.insertBefore(swipeHint, table);
        }
    }
} 