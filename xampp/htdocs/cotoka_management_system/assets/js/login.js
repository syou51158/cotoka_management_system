/**
 * Cotoka Management System - ログインページ専用JavaScript
 * 
 * このファイルはログインページの機能を提供します。
 * - 背景エフェクト
 * - 3Dタイトル効果
 * - パスワード表示切替
 */

// DOM読み込み完了時に実行
document.addEventListener('DOMContentLoaded', function() {
    console.log('ログインページ初期化完了');
    
    // 3Dタイトル効果の初期化
    initTitle3DEffect();
    
    // 背景エフェクトの初期化
    initBackgroundEffect();
});

/**
 * 3Dタイトル効果の初期化
 */
function initTitle3DEffect() {
    const brandTitle = document.querySelector('.brand-title');
    
    if (brandTitle) {
        brandTitle.addEventListener('mousemove', function(e) {
            const rect = brandTitle.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            const moveX = (e.clientX - centerX) / 15;
            const moveY = (e.clientY - centerY) / 15;
            
            const title = this.querySelector('h1');
            if (title) {
                title.style.transform = `perspective(800px) rotateX(${-moveY}deg) rotateY(${moveX}deg)`;
            }
        });
        
        brandTitle.addEventListener('mouseleave', function() {
            const title = this.querySelector('h1');
            if (title) {
                title.style.transform = 'perspective(800px) rotateX(0) rotateY(0)';
            }
        });
    } else {
        console.warn('ブランドタイトル要素が見つかりません');
    }
}

/**
 * 背景エフェクトの初期化
 */
function initBackgroundEffect() {
    const background = document.querySelector('.auth-background');
    
    if (!background) {
        console.warn('背景要素が見つかりません');
        return;
    }
    
    // 背景コンテナを作成
    const bgContainer = document.createElement('div');
    bgContainer.className = 'animated-gradient-container';
    
    // グラデーション要素を追加
    for (let i = 0; i < 3; i++) {
        const gradient = document.createElement('div');
        gradient.className = 'animated-gradient';
        gradient.style.background = getRandomGradient();
        gradient.style.animationDelay = `${i * 2}s`;
        gradient.style.opacity = 0.1 + (i * 0.1);
        bgContainer.appendChild(gradient);
    }
    
    // 光の粒子を追加
    for (let i = 0; i < 30; i++) {
        setTimeout(() => {
            createParticle(background);
        }, i * 300);
    }
    
    background.appendChild(bgContainer);
}

/**
 * パスワード表示の切り替え
 */
function togglePasswordVisibility() {
    const password = document.getElementById('password');
    const icon = document.getElementById('password-toggle-icon');
    
    if (!password || !icon) {
        console.warn('パスワード要素またはアイコンが見つかりません');
        return;
    }
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

/**
 * ランダムなグラデーションを生成
 */
function getRandomGradient() {
    const colors = [
        '#F8E9B6', '#E6C66E', '#D4AF37', '#BE9B30',
        '#F8F5EF', '#F0EBE0', '#E0D5C0', '#D9C7A8'
    ];
    
    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];
    const angle = Math.floor(Math.random() * 360);
    
    return `linear-gradient(${angle}deg, ${color1}, ${color2})`;
}

/**
 * パーティクルを作成
 */
function createParticle(container) {
    const particle = document.createElement('div');
    particle.className = 'particle';
    
    // ランダムな位置とサイズ
    const size = Math.random() * 8 + 2;
    const posX = Math.random() * 100;
    const posY = Math.random() * 100;
    const duration = Math.random() * 5 + 3;
    const delay = Math.random() * 5;
    
    particle.style.width = `${size}px`;
    particle.style.height = `${size}px`;
    particle.style.left = `${posX}%`;
    particle.style.top = `${posY}%`;
    particle.style.animationDuration = `${duration}s`;
    particle.style.animationDelay = `${delay}s`;
    
    container.appendChild(particle);
    
    // アニメーション終了後に要素を削除
    setTimeout(() => {
        particle.remove();
        createParticle(container);
    }, (duration + delay) * 1000);
} 