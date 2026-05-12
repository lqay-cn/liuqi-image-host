<?php
// config.php - 所有安全配置
session_start();
date_default_timezone_set('Asia/Shanghai');

// 路径常量
define('DATA_DIR', __DIR__ . '/data/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('MAX_SIZE', 8 * 1024 * 1024); // 8MB

// 初始化目录
foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
    if (!file_exists($dir)) mkdir($dir, 0755, true);
}

// 初始化用户文件（包含默认管理员）
if (!file_exists(DATA_DIR . 'users.json')) {
    $defaultUsers = [
        'admin' => password_hash('123456', PASSWORD_DEFAULT),
        'test'  => password_hash('test123', PASSWORD_DEFAULT)
    ];
    file_put_contents(DATA_DIR . 'users.json', json_encode($defaultUsers, JSON_PRETTY_PRINT));
}

// 初始化图片文件
if (!file_exists(DATA_DIR . 'images.json')) {
    file_put_contents(DATA_DIR . 'images.json', json_encode([]));
}

// 安全过滤函数
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// 获取用户角色（普通用户/管理员）
function getUserRole($username) {
    return ($username === 'admin') ? 'admin' : 'user';
}

// 检查登录
function checkLogin() {
    if (empty($_SESSION['user'])) {
        header('Location: login.html');
        exit;
    }
}

// 检查管理员权限（仅admin账户）
function checkAdmin() {
    checkLogin();
    if ($_SESSION['user'] !== 'admin') {
        http_response_code(403);
        die(json_encode(['error' => '权限不足，仅管理员可访问']));
    }
}

// 获取所有图片
function getAllImages() {
    $json = file_get_contents(DATA_DIR . 'images.json');
    return json_decode($json, true) ?? [];
}

// 获取用户有权限查看的图片（管理员看全部，普通用户看自己的）
function getUserImages($username) {
    $all = getAllImages();
    if ($username === 'admin') return $all;
    return array_filter($all, fn($img) => $img['uploader'] === $username);
}

// 保存图片列表
function saveImages($images) {
    file_put_contents(DATA_DIR . 'images.json', json_encode($images, JSON_PRETTY_PRINT));
}

// 记录日志
function logAction($action, $details = '') {
    $log = date('Y-m-d H:i:s') . " | {$_SESSION['user']} | $action | $details\n";
    file_put_contents(DATA_DIR . 'actions.log', $log, FILE_APPEND);
}

// 获取MIME类型（兼容无finfo）
function getMimeType($filePath) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        return $mime;
    }
    if (function_exists('exif_imagetype')) {
        $type = exif_imagetype($filePath);
        $map = [1=>'image/gif',2=>'image/jpeg',3=>'image/png',6=>'image/webp',18=>'image/webp'];
        return $map[$type] ?? '';
    }
    if (function_exists('getimagesize')) {
        $info = getimagesize($filePath);
        return $info['mime'] ?? '';
    }
    return '';
}
?>