<?php
// upload.php - 登录验证 & 图片上传（含验证码）
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
        // 记录失败尝试（可选：防暴力破解）
        logAction('login_failed', "失败尝试 - 用户名: $username, IP: {$_SERVER['REMOTE_ADDR']}");
    }
    exit;
}

// 以下为图片上传
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $uploaded = [];
    $errors = [];
    $files = $_FILES['images'];
    $totalFiles = count($files['name']);
    
    if ($totalFiles > 8) {
        http_response_code(400);
        echo json_encode(['error' => '最多上传8张图片']);
        exit;
    }
    
    for ($i = 0; $i < $totalFiles; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "{$files['name'][$i]} 上传失败";
            continue;
        }
        
        $tmpName = $files['tmp_name'][$i];
        $originalName = sanitize(pathinfo($files['name'][$i], PATHINFO_FILENAME));
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        $safeExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $safeExts)) {
            $errors[] = "{$files['name'][$i]} 不允许的扩展名";
            continue;
        }
        
        $mime = getMimeType($tmpName);
        if (!in_array($mime, ALLOWED_TYPES)) {
            $errors[] = "{$files['name'][$i]} 不是有效的图片文件";
            continue;
        }
        
        // 深度检测：重绘验证最安全
        $img = @imagecreatefromstring(file_get_contents($tmpName));
        if ($img === false) {
            $errors[] = "{$files['name'][$i]} 图片损坏或包含恶意代码";
            continue;
        }
        imagedestroy($img);
        
        if ($files['size'][$i] > MAX_SIZE) {
            $errors[] = "{$files['name'][$i]} 超过8MB";
            continue;
        }
        
        $newName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = UPLOAD_DIR . $newName;
        
        if (move_uploaded_file($tmpName, $target)) {
            $images = getAllImages();
            $newImage = [
                'id' => uniqid(),
                'name' => $originalName ?: '未命名',
                'filename' => $newName,
                'uploader' => $_SESSION['user'],
                'time' => date('Y-m-d H:i:s'),
                'size' => $files['size'][$i]
            ];
            array_unshift($images, $newImage);
            saveImages($images);
            $uploaded[] = $files['name'][$i];
            logAction('upload', "上传 {$files['name'][$i]}");
        } else {
            $errors[] = "{$files['name'][$i]} 保存失败";
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => count($uploaded), 'uploaded' => $uploaded, 'errors' => $errors]);
    exit;
}

http_response_code(405);
echo '只支持POST上传';
?>