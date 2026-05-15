<?php
// image_proxy.php - 图片代理，根据实际存储位置获取图片
require 'config.php';

// 获取文件名
$file = isset($_GET['file']) ? sanitize($_GET['file']) : '';

if (empty($file)) {
    http_response_code(400);
    die('Missing file parameter');
}

// 获取 MIME 类型的兼容函数
function getImageMimeType($filePath) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mime;
    }
    
    if (function_exists('exif_imagetype')) {
        $type = exif_imagetype($filePath);
        $map = [1=>'image/gif', 2=>'image/jpeg', 3=>'image/png', 6=>'image/webp', 18=>'image/webp'];
        return $map[$type] ?? '';
    }
    
    if (function_exists('getimagesize')) {
        $info = getimagesize($filePath);
        return $info['mime'] ?? '';
    }
    
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeMap = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png', 'gif'=>'image/gif', 'webp'=>'image/webp'];
    return $mimeMap[$ext] ?? 'application/octet-stream';
}

// 先尝试从 WebDAV 获取（如果启用的话）
if (USE_WEBDAV) {
    $url = rtrim(WEBDAV_URL, '/') . '/' . WEBDAV_IMG_DIR . '/' . ltrim($file, '/');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, WEBDAV_USERNAME . ':' . WEBDAV_PASSWORD);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($imageData)) {
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=86400');
        echo $imageData;
        exit;
    }
}

// 如果 WebDAV 失败或未启用，尝试从本地获取
$localPath = UPLOAD_DIR . $file;
if (file_exists($localPath)) {
    $mime = getImageMimeType($localPath);
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');
    readfile($localPath);
    exit;
}

// 都失败了，显示错误图片
http_response_code(404);
header('Content-Type: image/svg+xml');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#2a1e3e"/><text x="100" y="100" text-anchor="middle" fill="#ff9eb5" font-size="14">图片加载失败</text></svg>';
?>