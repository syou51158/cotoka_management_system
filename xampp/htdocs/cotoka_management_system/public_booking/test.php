<?php
// デバッグ情報表示用テストファイル
echo "<h1>テストページ</h1>";
echo "<h2>URLパラメータ</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>現在のパス</h2>";
echo "ドキュメントルート: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "スクリプトのパス: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "リクエストURI: " . $_SERVER['REQUEST_URI'] . "<br>";

// サロンのリストを表示（テスト用）
echo "<h2>利用可能なサロン</h2>";
try {
    require_once '../includes/config.php';
    require_once '../includes/database.php';

    $query = "SELECT salon_id, name, slug FROM salons";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $salons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>サロン名</th><th>スラッグ</th><th>テストリンク</th></tr>";
    foreach ($salons as $salon) {
        echo "<tr>";
        echo "<td>" . $salon['salon_id'] . "</td>";
        echo "<td>" . $salon['name'] . "</td>";
        echo "<td>" . $salon['slug'] . "</td>";
        echo "<td><a href='index.php?salon_id=" . $salon['salon_id'] . "&source=instagram'>テスト (ID)</a> | ";
        echo "<a href='index.php?slug=" . $salon['slug'] . "&source=instagram'>テスト (スラッグ)</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "データベースエラー: " . $e->getMessage();
}
?> 