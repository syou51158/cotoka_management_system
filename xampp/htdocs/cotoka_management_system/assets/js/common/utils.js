/**
 * Cotoka Management System - 共通ユーティリティスクリプト
 */

// 通貨フォーマット
function formatCurrency(amount, locale = 'ja-JP', currency = 'JPY') {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency
    }).format(amount);
}

// 日付フォーマット
function formatDate(date, options = { year: 'numeric', month: 'long', day: 'numeric' }, locale = 'ja-JP') {
    if (!(date instanceof Date)) {
        date = new Date(date);
    }
    return new Intl.DateTimeFormat(locale, options).format(date);
}

// 時間フォーマット
function formatTime(date, options = { hour: '2-digit', minute: '2-digit' }, locale = 'ja-JP') {
    if (!(date instanceof Date)) {
        date = new Date(date);
    }
    return new Intl.DateTimeFormat(locale, options).format(date);
}

// URLパラメータの取得
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// デバウンス関数
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// スロットル関数
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Cookieの設定
function setCookie(name, value, days = 30) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = "expires=" + date.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

// Cookieの取得
function getCookie(name) {
    const cname = name + "=";
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(cname) === 0) {
            return c.substring(cname.length, c.length);
        }
    }
    return "";
}

// Cookieの削除
function deleteCookie(name) {
    document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
}

// ローカルストレージに保存
function saveToLocalStorage(key, value) {
    try {
        const serializedValue = JSON.stringify(value);
        localStorage.setItem(key, serializedValue);
    } catch (e) {
        console.error('ローカルストレージへの保存に失敗しました:', e);
    }
}

// ローカルストレージから取得
function getFromLocalStorage(key, defaultValue = null) {
    try {
        const serializedValue = localStorage.getItem(key);
        if (serializedValue === null) {
            return defaultValue;
        }
        return JSON.parse(serializedValue);
    } catch (e) {
        console.error('ローカルストレージからの取得に失敗しました:', e);
        return defaultValue;
    }
}

// ローカルストレージから削除
function removeFromLocalStorage(key) {
    try {
        localStorage.removeItem(key);
    } catch (e) {
        console.error('ローカルストレージからの削除に失敗しました:', e);
    }
}

// フォームデータの検証
function validateForm(formElement, validationRules) {
    const errors = {};
    
    for (const field in validationRules) {
        const element = formElement.elements[field];
        const rules = validationRules[field];
        
        if (!element) continue;
        
        const value = element.value.trim();
        
        if (rules.required && value === '') {
            errors[field] = '必須項目です';
            continue;
        }
        
        if (rules.minLength && value.length < rules.minLength) {
            errors[field] = `${rules.minLength}文字以上入力してください`;
        }
        
        if (rules.maxLength && value.length > rules.maxLength) {
            errors[field] = `${rules.maxLength}文字以下で入力してください`;
        }
        
        if (rules.pattern && !rules.pattern.test(value)) {
            errors[field] = rules.message || '正しい形式で入力してください';
        }
    }
    
    return errors;
}

// フォームデータをオブジェクトに変換
function formToObject(formElement) {
    const formData = new FormData(formElement);
    const result = {};
    
    for (const [key, value] of formData.entries()) {
        result[key] = value;
    }
    
    return result;
} 