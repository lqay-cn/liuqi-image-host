<?php
// config.php
session_start();
date_default_timezone_set('Asia/Shanghai');

// ==================== 基础路径配置 ====================
define('DATA_DIR', __DIR__ . '/data/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('MAX_SIZE', 8388608); // 8MB
define('MAX_FILES', 8);              // 最多同时上传8张

// ==================== WebDAV 配置 ====================
// 【请修改】是否启用 WebDAV（true=上传到远程，false=上传到本地）
define('USE_WEBDAV', false);

// 【请修改】WebDAV 服务器配置（USE_WEBDAV 为 true 时需填写）
define('WEBDAV_URL', '');           // 例如: https://dav.jianguoyun.com/dav/
define('WEBDAV_USERNAME', '');       // 用户名
define('WEBDAV_PASSWORD', '');       // 密码
define('WEBDAV_IMG_DIR', 'liuqi-img');  // 图片存储目录

// 图片代理缓存时间（秒）
define('PROXY_CACHE_TIME', 86400); // 24小时

// ==================== 初始化目录 ====================
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

// 初始化用户文件
if (!file_exists(DATA_DIR . 'users.json')) {
    $defaultUsers = [
        'admin' => password_hash('123456', PASSWORD_DEFAULT),
    ];
    file_put_contents(DATA_DIR . 'users.json', json_encode($defaultUsers, JSON_PRETTY_PRINT));
}

// 初始化图片文件
if (!file_exists(DATA_DIR . 'images.json')) {
    file_put_contents(DATA_DIR . 'images.json', json_encode([]));
}

// ==================== WebDAV 函数 ====================
/**
 * 通过 WebDAV 上传文件
 * @param string $localPath 本地临时文件路径
 * @param string $remotePath 远程路径（相对于 WEBDAV_URL）
 * @return bool
 */
function webdav_upload($localPath, $remotePath) {
    if (!USE_WEBDAV) return false;
    
    $url = rtrim(WEBDAV_URL, '/') . '/' . ltrim($remotePath, '/');
    $fp = fopen($localPath, 'r');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, WEBDAV_USERNAME . ':' . WEBDAV_PASSWORD);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($localPath));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    // 201 Created 或 204 No Content 表示成功
    return $httpCode === 201 || $httpCode === 204;
}

/**
 * 通过 WebDAV 删除文件
 * @param string $remotePath 远程路径
 * @return bool
 */
function webdav_delete($remotePath) {
    if (!USE_WEBDAV) return false;
    
    $url = rtrim(WEBDAV_URL, '/') . '/' . ltrim($remotePath, '/');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, WEBDAV_USERNAME . ':' . WEBDAV_PASSWORD);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200 || $httpCode === 204;
}

/**
 * 通过 WebDAV 创建目录
 * @param string $dirPath 目录路径
 * @return bool
 */
function webdav_mkdir($dirPath) {
    if (!USE_WEBDAV) return true;
    
    $url = rtrim(WEBDAV_URL, '/') . '/' . ltrim($dirPath, '/');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, WEBDAV_USERNAME . ':' . WEBDAV_PASSWORD);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'MKCOL');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 201 Created 或 405 Method Not Allowed（目录已存在）都算成功
    return $httpCode === 201 || $httpCode === 405;
}

/**
 * 通过 WebDAV 获取文件内容
 * @param string $remotePath 远程路径
 * @return string|false
 */
function webdav_get($remotePath) {
    if (!USE_WEBDAV) return false;
    
    $url = rtrim(WEBDAV_URL, '/') . '/' . ltrim($remotePath, '/');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, WEBDAV_USERNAME . ':' . WEBDAV_PASSWORD);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: image/jpeg,image/png,image/gif,image/webp,*/*'
    ]);
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return $data;
    }
    return false;
}

// ==================== 核心函数 ====================
/**
 * 安全过滤函数
 * @param string $input
 * @return string
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * 获取用户角色
 * @param string $username
 * @return string
 */
function getUserRole($username) {
    return ($username === 'admin') ? 'admin' : 'user';
}

/**
 * 检查登录状态
 */
function checkLogin() {
    if (empty($_SESSION['user'])) {
        header('Location: login.html');
        exit;
    }
}

/**
 * 检查管理员权限
 */
function checkAdmin() {
    checkLogin();
    if ($_SESSION['user'] !== 'admin') {
        http_response_code(403);
        die(json_encode(['error' => '权限不足，仅管理员可访问']));
    }
}

/**
 * 获取所有图片
 * @return array
 */
function getAllImages() {
    $json = file_get_contents(DATA_DIR . 'images.json');
    return json_decode($json, true) ?? [];
}

/**
 * 获取用户有权限查看的图片
 * @param string $username
 * @return array
 */
function getUserImages($username) {
    $all = getAllImages();
    if ($username === 'admin') return $all;
    return array_filter($all, fn($img) => $img['uploader'] === $username);
}

/**
 * 保存图片列表
 * @param array $images
 */
function saveImages($images) {
    file_put_contents(DATA_DIR . 'images.json', json_encode($images, JSON_PRETTY_PRINT));
}

/**
 * 记录操作日志
 * @param string $action
 * @param string $details
 */
function logAction($action, $details = '') {
    $log = date('Y-m-d H:i:s') . " | {$_SESSION['user']} | $action | $details\n";
    file_put_contents(DATA_DIR . 'actions.log', $log, FILE_APPEND);
}

/**
 * 获取 MIME 类型（兼容无 finfo、无 exif、无 getimagesize 的环境）
 * @param string $filePath
 * @return string
 */
function getMimeType($filePath) {
    // 方法1: 使用 finfo
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        if ($mime && $mime !== 'application/octet-stream') {
            return $mime;
        }
    }
    
    // 方法2: 使用 exif_imagetype
    if (function_exists('exif_imagetype')) {
        $type = exif_imagetype($filePath);
        $map = [
            1 => 'image/gif',
            2 => 'image/jpeg',
            3 => 'image/png',
            6 => 'image/webp',
            18 => 'image/webp'
        ];
        if (isset($map[$type])) {
            return $map[$type];
        }
    }
    
    // 方法3: 使用 getimagesize
    if (function_exists('getimagesize')) {
        $info = getimagesize($filePath);
        if ($info && isset($info['mime']) && $info['mime'] !== 'application/octet-stream') {
            return $info['mime'];
        }
    }
    
    // 方法4: 读取文件头（最可靠的方法）
    $handle = fopen($filePath, 'rb');
    if ($handle) {
        $bytes = fread($handle, 512);
        fclose($handle);
        
        // PNG
        if (preg_match('/\x89PNG\r\n\x1a\n/', $bytes)) {
            return 'image/png';
        }
        // JPEG
        if (preg_match('/\xFF\xD8\xFF/', $bytes)) {
            return 'image/jpeg';
        }
        // GIF
        if (preg_match('/GIF8[79]a/', $bytes)) {
            return 'image/gif';
        }
        // WebP
        if (preg_match('/RIFF.{4}WEBP/', $bytes)) {
            return 'image/webp';
        }
        // BMP
        if (preg_match('/BM/', $bytes)) {
            return 'image/bmp';
        }
    }
    
    // 方法5: 根据扩展名判断（最后的备胎方案）
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp'
    ];
    
    return $mimeMap[$ext] ?? 'application/octet-stream';
}
?>