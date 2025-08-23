<?php
/**
 * 予約ページ - URLスラッグ処理の修正版
 * 
 * このファイルはindex.phpの修正版です。
 * URLの処理方法を改善し、様々な形式のURLに対応します。
 * 
 * 使用方法: 
 * 1. このファイルの内容を確認
 * 2. 問題がなければindex.phpに適用
 */

// 現在の実行ファイルと実際のリクエストURI
$actual_uri = $_SERVER['REQUEST_URI'];
$current_file = basename(__FILE__);

// パスの解析
$path_parts = explode('/', trim($actual_uri, '/'));
$last_part = end($path_parts);

// URLからスラッグを抽出する様々な方法を試す
$found_slug = null;
$found_source = null;
$found_salon_id = null;

// デバッグモード (本番環境ではコメントアウト)
$debug = true;
$debug_info = [];

// 1. GETパラメータから直接取得
if (isset($_GET['slug'])) {
    $found_slug = $_GET['slug'];
    $debug_info[] = "GETパラメータからスラッグを取得: " . $found_slug;
}

if (isset($_GET['source'])) {
    $found_source = $_GET['source'];
    $debug_info[] = "GETパラメータからソースを取得: " . $found_source;
}

if (isset($_GET['salon_id'])) {
    $found_salon_id = intval($_GET['salon_id']);
    $debug_info[] = "GETパラメータからサロンIDを取得: " . $found_salon_id;
}

// 2. URLパスから抽出 (.htaccessを使用する場合)
if (!$found_slug && $last_part != $current_file && $last_part != 'index.php') {
    // URLからファイル名や拡張子を除去
    $path_slug = $last_part;
    if (!empty($path_slug)) {
        $found_slug = $path_slug;
        $debug_info[] = "URLパスからスラッグを取得: " . $found_slug;
    }
}

// 結果を表示（デバッグ用）
if ($debug) {
    echo "<h1>URLスラッグ処理テスト</h1>";
    echo "<pre>";
    echo "リクエストURI: " . $actual_uri . "\n";
    echo "現在のファイル: " . $current_file . "\n";
    echo "URLの最後の部分: " . $last_part . "\n\n";
    
    echo "検出されたスラッグ: " . ($found_slug ?? "なし") . "\n";
    echo "検出されたソース: " . ($found_source ?? "なし") . "\n";
    echo "検出されたサロンID: " . ($found_salon_id ?? "なし") . "\n\n";
    
    echo "デバッグ情報:\n";
    foreach ($debug_info as $info) {
        echo "- " . $info . "\n";
    }
    echo "</pre>";
    
    echo "<h2>次のステップ</h2>";
    echo "<p>このテストが成功したら、同じ処理をindex.phpに適用してください。</p>";
    
    echo "<h2>オリジナルのindex.phpの処理</h2>";
    echo "<pre>";
    echo <<<'EOD'
// URLスラッグがある場合はスラッグからサロンIDを取得
if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    try {
        $stmt = $conn->prepare("SELECT salon_id FROM salons WHERE url_slug = :slug");
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $salon_id = $result['salon_id'];
        } else {
            // スラッグが見つからない場合はエラーページを表示
            header("HTTP/1.0 404 Not Found");
            echo "<h1>サロンが見つかりません</h1>";
            echo "<p>指定されたURLに該当するサロンが見つかりませんでした。URLを確認して再度アクセスしてください。</p>";
            echo "<p><a href='/'>トップページに戻る</a></p>";
            exit;
        }
    } catch (Exception $e) {
        $error_message = "サロン情報取得エラー：" . $e->getMessage();
        exit($error_message);
    }
} else if (isset($_GET['salon_id'])) {
    // 従来の方法（salon_idパラメータ）でのアクセス
    $salon_id = intval($_GET['salon_id']);
} else {
    // どちらのパラメータもない場合はデフォルトは1
    $salon_id = 1;
}
EOD;
    echo "</pre>";
    
    echo "<h2>推奨される修正</h2>";
    echo "<pre>";
    echo <<<'EOD'
// スラッグまたはサロンIDの取得
$salon_id = null;
$slug = null;

// 1. GETパラメータから直接取得
if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
}

// 2. URLパスから抽出 (.htaccessを使用する場合)
if (!$slug) {
    $path_parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    $last_part = end($path_parts);
    if ($last_part != 'index.php' && $last_part != '') {
        // URLからファイル名や拡張子を除去
        $path_slug = $last_part;
        if (!empty($path_slug)) {
            $slug = $path_slug;
        }
    }
}

// 3. スラッグからサロンIDを取得
if ($slug) {
    try {
        $stmt = $conn->prepare("SELECT salon_id FROM salons WHERE url_slug = :slug");
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $salon_id = $result['salon_id'];
        } else {
            // スラッグが見つからない場合はエラーページを表示
            header("HTTP/1.0 404 Not Found");
            echo "<h1>サロンが見つかりません</h1>";
            echo "<p>指定されたURLに該当するサロンが見つかりませんでした。URLを確認して再度アクセスしてください。</p>";
            echo "<p><a href='/'>トップページに戻る</a></p>";
            exit;
        }
    } catch (Exception $e) {
        $error_message = "サロン情報取得エラー：" . $e->getMessage();
        exit($error_message);
    }
} else if (isset($_GET['salon_id'])) {
    // 従来の方法（salon_idパラメータ）でのアクセス
    $salon_id = intval($_GET['salon_id']);
} else {
    // どちらのパラメータもない場合はデフォルトは1
    $salon_id = 1;
}
EOD;
    echo "</pre>";
    
    exit; // デバッグモードでは処理を終了
}

// ここから下は実際の処理
// ここからは通常のindex.phpと同じ処理を入れる
// デバッグモードがfalseの場合のみここに到達する

?> 