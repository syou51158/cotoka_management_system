$(document).ready(function() {
    // チェックボックスの状態に基づいて行のスタイルを更新
    function updateRowStyle(checkbox) {
        const row = $(checkbox).closest('tr');
        if ($(checkbox).is(':checked')) {
            row.addClass('selected');
        } else {
            row.removeClass('selected');
        }
    }
    
    // 全てのチェックボックスの初期スタイルを設定
    $('.service-select').each(function() {
        updateRowStyle(this);
    });
    
    // チェックボックス変更時の処理
    $('.service-select').change(function() {
        updateRowStyle(this);
        updateSubmitButton();
        updateTotalSummary();
    });
    
    // 行クリック時の処理（チェックボックス以外の部分）
    $('.service-table tr').click(function(e) {
        // チェックボックス自体のクリックは除外（そのイベントは上のハンドラで処理）
        if (!$(e.target).is('.service-select') && !$(e.target).is('label')) {
            const checkbox = $(this).find('.service-select');
            checkbox.prop('checked', !checkbox.prop('checked')).change();
        }
    });
    
    // 送信ボタンの更新
    function updateSubmitButton() {
        const checkedServices = $('.service-select:checked').length;
        $('#submit-button').prop('disabled', checkedServices === 0);
    }
    
    // サービス選択の合計サマリーを更新
    function updateTotalSummary() {
        const checkedServices = $('.service-select:checked').length;
        let totalPrice = 0;
        let totalDuration = 0;
        
        $('.service-select:checked').each(function() {
            totalPrice += parseFloat($(this).data('price'));
            totalDuration += parseInt($(this).data('duration'));
        });
        
        // サマリー要素がなければ作成
        if ($('#service-summary').length === 0) {
            $('<div id="service-summary" class="service-summary"></div>').insertBefore('#submit-button');
        }
        
        if (checkedServices > 0) {
            $('#service-summary').html(
                '<div class="summary-item">' +
                    '<span>選択したメニュー:</span>' +
                    '<span>' + checkedServices + '個</span>' +
                '</div>' +
                '<div class="summary-item">' +
                    '<span>合計時間:</span>' +
                    '<span>' + totalDuration + '分</span>' +
                '</div>' +
                '<div class="summary-item total">' +
                    '<span>合計金額:</span>' +
                    '<span>¥' + totalPrice.toLocaleString() + '</span>' +
                '</div>'
            ).fadeIn(300);
        } else {
            $('#service-summary').fadeOut(300);
        }
    }
    
    // 初期状態の更新
    updateSubmitButton();
    updateTotalSummary();
}); 