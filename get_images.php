<?php
// get_images.php - 获取图片列表（支持混合存储）
require 'config.php';
checkLogin();
header('Content-Type: application/json');

$images = getUserImages($_SESSION['user']);
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

foreach ($images as &$img) {
    // 统一使用代理（代理会自动处理本地/WebDAV 回退）
    $img['proxy_url'] = $baseUrl . $scriptDir . '/image_proxy.php?file=' . urlencode($img['filename']);
    $img['url'] = $img['proxy_url'];
    $img['markdown'] = "![{$img['name']}]({$img['proxy_url']})";
    $img['bbcode'] = "[img]{$img['proxy_url']}[/img]";
    $img['html'] = "<img src=\"{$img['proxy_url']}\" alt=\"{$img['name']}\">";
    $img['direct_link'] = $img['proxy_url'];
}
echo json_encode(array_values($images));
?>