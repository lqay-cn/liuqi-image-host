<?php
// upload.php - 登录验证 & 图片上传
require 'config.php';

// 处理登录请求
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $captcha = strtolower(trim($input['captcha'] ?? ''));
    
    // 验证码校验
    if (empty($captcha) || !isset($_SESSION['captcha']) || $captcha !== $_SESSION['captcha']) {
        echo json_encode(['success' => false, 'message' => '验证码错误']);
        exit;
    }
    
    // 验证通过后清除验证码，防止重复使用
    unset($_SESSION['captcha']);
    
    $users = json_decode(file_get_contents(DATA_DIR . 'users.json'), true);
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['user'] = $username;
        $_SESSION['role'] = getUserRole($username);
        echo json_encode(['success' => true]);
        logAction('login', "登录成功 (IP: {$_SERVER['REMOTE_ADDR']})");
    } else {
        echo json_encode(['success' => false, 'message' => '账号或密码错误']);
        logAction('login_failed', "失败尝试 - 用户名: $username, IP: {$_SERVER['REMOTE_ADDR']}");
    }
    exit;
}

// 登出请求
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.html');
    exit;
}

// 以下为图片上传
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $uploaded = [];
    $errors = [];
    $files = $_FILES['images'];
    $totalFiles = count($files['name']);
    
    // 限制上传数量
    $maxFiles = defined('MAX_FILES') ? MAX_FILES : 8;
    if ($totalFiles > $maxFiles) {
        echo json_encode(['error' => "最多上传 {$maxFiles} 张图片"]);
        exit;
    }
    
    // 如果启用 WebDAV，先确保目录存在
    if (USE_WEBDAV) {
        webdav_mkdir(WEBDAV_IMG_DIR);
    }
    
    for ($i = 0; $i < $totalFiles; $i++) {
        // 检查上传错误
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "{$files['name'][$i]} 上传失败 (错误码: {$files['error'][$i]})";
            continue;
        }
        
        $tmpName = $files['tmp_name'][$i];
        $originalName = sanitize(pathinfo($files['name'][$i], PATHINFO_FILENAME));
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        $safeExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // 1. 检查扩展名
        if (!in_array($ext, $safeExts)) {
            $errors[] = "{$files['name'][$i]} 不允许的扩展名 (仅支持: " . implode(', ', $safeExts) . ")";
            continue;
        }
        
        // 2. 检查 MIME 类型
        $mime = getMimeType($tmpName);
        if (!in_array($mime, ALLOWED_TYPES)) {
            $errors[] = "{$files['name'][$i]} 不是有效的图片文件 (MIME: {$mime})";
            continue;
        }
        
        // 3. 深度检测：使用 GD 库重绘验证（防恶意文件最有效）
        $img = @imagecreatefromstring(file_get_contents($tmpName));
        if ($img === false) {
            $errors[] = "{$files['name'][$i]} 图片损坏或包含恶意代码";
            continue;
        }
        imagedestroy($img);
        
        // 4. 检查文件大小
        if ($files['size'][$i] > MAX_SIZE) {
            $maxSizeMB = MAX_SIZE / 1024 / 1024;
            $errors[] = "{$files['name'][$i]} 超过 " . $maxSizeMB . "MB 限制";
            continue;
        }
        
        // 5. 生成唯一文件名
        $newName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        
        // 6. 根据配置选择存储方式
        $saveSuccess = false;
        if (USE_WEBDAV) {
            // 上传到 WebDAV
            $saveSuccess = webdav_upload($tmpName, WEBDAV_IMG_DIR . '/' . $newName);
            if (!$saveSuccess) {
                $errors[] = "{$files['name'][$i]} WebDAV 上传失败，请检查配置";
            }
        } else {
            // 上传到本地
            $target = UPLOAD_DIR . $newName;
            $saveSuccess = move_uploaded_file($tmpName, $target);
            if (!$saveSuccess) {
                $errors[] = "{$files['name'][$i]} 本地保存失败，请检查目录权限";
            }
        }
        
        if ($saveSuccess) {
            $images = getAllImages();
            $newImage = [
                'id' => uniqid(),
                'name' => $originalName ?: '未命名',
                'filename' => $newName,
                'uploader' => $_SESSION['user'],
                'time' => date('Y-m-d H:i:s'),
                'size' => $files['size'][$i],
                'storage' => USE_WEBDAV ? 'webdav' : 'local'  // 记录存储类型
            ];
            array_unshift($images, $newImage);
            saveImages($images);
            $uploaded[] = $files['name'][$i];
            logAction('upload', "上传 {$files['name'][$i]} -> " . (USE_WEBDAV ? 'WebDAV' : '本地') . " -> {$newName}");
        }
    }
    
    // 返回结果
    header('Content-Type: application/json');
    $response = ['success' => count($uploaded), 'uploaded' => $uploaded];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
    exit;
}

// 如果不是 POST 上传请求，返回错误
http_response_code(405);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>请求错误</title>
    <style>
        body {
            background: linear-gradient(135deg, #0f0c1f 0%, #1a1535 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: system-ui, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .error-card {
            background: rgba(20,15,45,0.7);
            backdrop-filter: blur(16px);
            border-radius: 48px;
            padding: 40px;
            text-align: center;
            border: 1px solid rgba(255,200,230,0.3);
            max-width: 400px;
        }
        h1 { color: #ff9eb5; margin-bottom: 16px; }
        p { color: #c8b0e8; margin-bottom: 24px; }
        a {
            background: linear-gradient(120deg, #ff9eb5, #b77cff);
            padding: 10px 24px;
            border-radius: 60px;
            color: #1e1a2f;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="error-card">
    <h1>⚠️ 请求错误</h1>
    <p>只支持 POST 上传请求<br>请返回首页使用上传功能</p>
    <a href="index.html">← 返回首页</a>
</div>
</body>
</html>
<?php
exit;
?>