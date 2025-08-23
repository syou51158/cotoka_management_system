// グローバルスコープで定義
function submitBooking(event) {
    // 処理内容
    return true; // フォームの通常送信を許可
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('確認ページのJSが読み込まれました');
    
    // 規約同意チェックボックスの状態に応じて予約確定ボタンの有効/無効を切り替え
    const $agreeTerms = $('#agree_terms');
    const $confirmBtn = $('#confirmBtn');
    
    // 初期状態の設定
    $confirmBtn.prop('disabled', !$agreeTerms.prop('checked'));
    
    // チェックボックスの状態が変わったときの処理
    $agreeTerms.on('change', function() {
        console.log('Checkbox changed:', $agreeTerms.prop('checked'));
        $confirmBtn.prop('disabled', !$agreeTerms.prop('checked'));
    });
    
    // 最もシンプルなフォーム送信処理
    $('#bookingForm').on('submit', function(e) {
        console.log('Form submitted');
        
        if (!$agreeTerms.prop('checked')) {
            e.preventDefault();
            alert('予約を確定するには、利用規約とプライバシーポリシーに同意する必要があります。');
            return false;
        }
        
        // ボタンを無効化して二重送信を防止
        $confirmBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 処理中...');
        console.log('Form submission in progress...');
        
        // フォームを通常通り送信
        return true;
    });
    
    // 利用規約とプライバシーポリシーのリンクがクリックされたとき新しいウィンドウで開く
    $('.terms-agreement a').on('click', function(e) {
        e.preventDefault();
        window.open($(this).attr('href'), '_blank', 'width=800,height=600');
    });
}); 