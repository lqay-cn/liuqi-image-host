<?php
// captcha.php - 生成验证码图片（字母完美居中）
session_start();

$width = 130;
$height = 45;
$image = imagecreatetruecolor($width, $height);

// 颜色定义
$bgColor = imagecolorallocate($image, 30, 20, 50);      // 深紫色背景
$textColor = imagecolorallocate($image, 255, 220, 240); // 粉白色文字
$lineColor = imagecolorallocate($image, 180, 120, 200); // 干扰线颜色

// 填充背景
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// 生成随机验证码（4位，排除易混淆字符）
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$captcha = '';
for ($i = 0; $i < 4; $i++) {
    $captcha .= $chars[random_int(0, strlen($chars) - 1)];
}

// 存储到 session（转小写用于比对）
$_SESSION['captcha'] = strtolower($captcha);

// 添加干扰线（3条，不干扰文字阅读）
for ($i = 0; $i < 3; $i++) {
    imageline($image, random_int(0, $width), random_int(0, $height), 
              random_int(0, $width), random_int(0, $height), $lineColor);
}

// 添加少量噪点
for ($i = 0; $i < 60; $i++) {
    imagesetpixel($image, random_int(0, $width), random_int(0, $height), $lineColor);
}

// 绘制验证码文字 - 使用 imagestring 获得更好的居中效果
// 获取每个字符的宽度，计算居中位置
$charWidth = 22;  // 每个字符的大致宽度
$startX = 15;     // 起始X坐标
$y = 16;          // Y坐标（调整这个值可以让文字上下移动）

for ($i = 0; $i < 4; $i++) {
    $x = $startX + ($i * $charWidth);
    // 使用 imagestring，字号 5 比较大且清晰
    // 可选字号: 1-5，5 是最大的内置字体
    imagestring($image, 5, $x, $y, $captcha[$i], $textColor);
}

// 可选：添加轻微倾斜效果（使用 imagettftext，如果有字体文件）
// 如果有字体文件，取消下面的注释可以获得更好的效果
/*
$font = __DIR__ . '/assets/arial.ttf';
if (file_exists($font)) {
    // 清空之前的文字
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
    // 重新绘制带倾斜的文字
    for ($i = 0; $i < 4; $i++) {
        $angle = random_int(-15, 15);
        $x = 18 + ($i * 26);
        $y = random_int(30, 35);
        imagettftext($image, 18, $angle, $x, $y, $textColor, $font, $captcha[$i]);
    }
    // 重新添加干扰线
    for ($i = 0; $i < 3; $i++) {
        imageline($image, random_int(0, $width), random_int(0, $height), 
                  random_int(0, $width), random_int(0, $height), $lineColor);
    }
}
*/

// 输出图片
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($image);
imagedestroy($image);
?>