<?php
// ロゴとなる画像を生成
$width = 200;
$height = 60;
$image = imagecreatetruecolor($width, $height);

// 背景色（ピンク）
$bg_color = imagecolorallocate($image, 233, 30, 99);
// テキスト色（白）
$text_color = imagecolorallocate($image, 255, 255, 255);

// 背景を塗りつぶす
imagefill($image, 0, 0, $bg_color);

// テキストを追加
$font = 5; // 組み込みフォント
$text = "COTOKA SALON";
$text_width = imagefontwidth($font) * strlen($text);
$text_height = imagefontheight($font);
$x = ($width - $text_width) / 2;
$y = ($height - $text_height) / 2;

imagestring($image, $font, $x, $y, $text, $text_color);

// 保存先のディレクトリが存在することを確認
if (!file_exists('assets/images')) {
    mkdir('assets/images', 0777, true);
}

// 画像を保存
imagepng($image, 'assets/images/logo.png');
imagedestroy($image);

echo "ロゴ画像が生成されました: assets/images/logo.png";
?> 