<?php
// get_link.php - 获取图片直链（修复坚果云等 WebDAV 兼容问题）
require 'config.php';
checkLogin();

$id = isset($_GET['id']) ? sanitize($_GET['id']) : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'url';

if (empty($id)) {
    die('缺少图片ID');
}

$images = getAllImages();
$targetImage = null;

foreach ($images as $img) {
    if ($img['id'] === $id) {
        $targetImage = $img;
        break;
    }
}

if (!$targetImage) {
    die('图片不存在');
}

// 获取图片 URL - 统一使用代理模式，确保所有 WebDAV 都兼容
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$proxyUrl = $baseUrl . '/image_proxy.php?file=' . urlencode($targetImage['filename']);

$imageName = $targetImage['name'];

// 根据格式返回
switch ($format) {
    case 'markdown':
        $output = "![{$imageName}]({$proxyUrl})";
        break;
    case 'bbcode':
        $output = "[img]{$proxyUrl}[/img]";
        break;
    case 'html':
        $output = "<img src=\"{$proxyUrl}\" alt=\"{$imageName}\">";
        break;
    case 'json':
        header('Content-Type: application/json');
        $output = json_encode([
            'url' => $proxyUrl,
            'markdown' => "![{$imageName}]({$proxyUrl})",
            'bbcode' => "[img]{$proxyUrl}[/img]",
            'html' => "<img src=\"{$proxyUrl}\" alt=\"{$imageName}\">",
            'filename' => $targetImage['filename'],
            'name' => $imageName
        ]);
        break;
    default:
        $output = $proxyUrl;
}

echo $output;
?>