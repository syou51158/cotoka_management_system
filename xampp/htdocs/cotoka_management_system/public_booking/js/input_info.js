$(document).ready(function() {
    console.log('input_info.js loaded');
    
    // 生年月日の年の選択肢を制限
    $('#birth_year, #birth_month').on('change', function() {
        updateDays();
    });

    // 日付の選択肢を更新する関数
    function updateDays() {
        const year = $('#birth_year').val();
        const month = $('#birth_month').val();
        const daySelect = $('#birth_day');
        const selectedDay = daySelect.val();
        
        if (year && month) {
            const daysInMonth = new Date(year, month, 0).getDate();
            
            // 現在選択されている日を保持
            const currentSelection = daySelect.val();
            daySelect.empty().append('<option value="">日</option>');
            
            for (let i = 1; i <= daysInMonth; i++) {
                const selected = (i == selectedDay) ? 'selected' : '';
                daySelect.append(`<option value="${i}" ${selected}>${i}</option>`);
            }
            
            // 選択していた日が新しい月の日数より大きい場合、選択解除
            if (currentSelection > daysInMonth) {
                daySelect.val('');
            } else if (currentSelection) {
                daySelect.val(currentSelection);
            }
        }
    }

    // フォームバリデーション
    $('#customerForm').on('submit', function(e) {
        let valid = true;
        const requiredFields = [
            { id: 'last_name', message: '姓を入力してください' },
            { id: 'first_name', message: '名を入力してください' },
            { id: 'email', message: 'メールアドレスを入力してください' },
            { id: 'phone', message: '電話番号を入力してください' },
            { id: 'birth_year', message: '生年月日（年）を選択してください' },
            { id: 'birth_month', message: '生年月日（月）を選択してください' },
            { id: 'birth_day', message: '生年月日（日）を選択してください' }
        ];
        
        // 必須項目のチェック
        requiredFields.forEach(field => {
            const $field = $(`#${field.id}`);
            if (!$field.val()) {
                $field.addClass('is-invalid');
                valid = false;
            } else {
                $field.removeClass('is-invalid');
            }
        });
        
        // 性別のチェック
        if (!$('input[name="gender"]:checked').length) {
            $('.radio-group').addClass('is-invalid');
            valid = false;
        } else {
            $('.radio-group').removeClass('is-invalid');
        }
        
        // メールアドレスの形式チェック
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if ($('#email').val() && !emailRegex.test($('#email').val())) {
            $('#email').addClass('is-invalid');
            valid = false;
        }
        
        // 電話番号の形式チェック（数字とハイフンのみ）
        const phoneRegex = /^[0-9\-]+$/;
        if ($('#phone').val() && !phoneRegex.test($('#phone').val())) {
            $('#phone').addClass('is-invalid');
            valid = false;
        }
        
        if (!valid) {
            e.preventDefault();
            
            // エラーがある場合、最初のエラー項目までスクロール
            const $firstError = $('.is-invalid').first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        }
    });
    
    // ログインフォームのバリデーション
    $('#loginForm').on('submit', function(e) {
        let valid = true;
        const $email = $('#login_email');
        const $password = $('#login_password');
        
        // 必須項目のチェック
        if (!$email.val().trim()) {
            $email.addClass('is-invalid');
            valid = false;
        } else {
            $email.removeClass('is-invalid');
        }
        
        if (!$password.val().trim()) {
            $password.addClass('is-invalid');
            valid = false;
        } else {
            $password.removeClass('is-invalid');
        }
        
        // メールアドレスの形式チェック
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if ($email.val() && !emailRegex.test($email.val())) {
            $email.addClass('is-invalid');
            valid = false;
        }
        
        if (!valid) {
            e.preventDefault();
            
            // エラーがある場合、最初のエラー項目までスクロール
            const $firstError = $('.is-invalid').first();
            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 500);
            }
        }
    });
    
    // フォーカス時にエラー表示をクリア
    $('input, select').on('focus', function() {
        $(this).removeClass('is-invalid');
    });
    
    // 性別選択時にエラー表示をクリア
    $('input[name="gender"]').on('change', function() {
        $('.radio-group').removeClass('is-invalid');
    });

    // 初期表示時に日付選択肢を更新
    updateDays();
}); 