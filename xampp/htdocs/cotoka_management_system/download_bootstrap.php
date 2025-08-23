<?php
// Bootstrap CSSとJSをダウンロードするスクリプト

// CSSディレクトリの確認・作成
$cssDir = __DIR__ . '/assets/css';
if (!file_exists($cssDir)) {
    mkdir($cssDir, 0755, true);
}

// JSディレクトリの確認・作成
$jsDir = __DIR__ . '/assets/js';
if (!file_exists($jsDir)) {
    mkdir($jsDir, 0755, true);
}

// Bootstrapの最新バージョンをCDNからダウンロード
$bootstrapCssUrl = 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css';
$bootstrapJsUrl = 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js';

// CSSファイルのダウンロード
$cssContent = file_get_contents($bootstrapCssUrl);
if ($cssContent !== false) {
    file_put_contents($cssDir . '/bootstrap.min.css', $cssContent);
    echo "Bootstrap CSS がダウンロードされました。<br>";
} else {
    echo "Bootstrap CSS のダウンロードに失敗しました。<br>";
}

// JSファイルのダウンロード
$jsContent = file_get_contents($bootstrapJsUrl);
if ($jsContent !== false) {
    file_put_contents($jsDir . '/bootstrap.bundle.min.js', $jsContent);
    echo "Bootstrap JS がダウンロードされました。<br>";
} else {
    echo "Bootstrap JS のダウンロードに失敗しました。<br>";
}

echo "<p>完了しました。<a href='login.php'>ログイン画面へ進む</a></p>";
?> 