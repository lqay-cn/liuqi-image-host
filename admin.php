<?php
// admin.php - 管理员面板（支持 WebDAV + 批量删除 + 直链复制）
require 'config.php';
checkAdmin();

$users = json_decode(file_get_contents(DATA_DIR . 'users.json'), true);
$allImages = getAllImages();
$currentAdmin = $_SESSION['user'];

// 处理各种POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. 创建用户
    if ($_POST['action'] === 'create_user') {
        $newUser = sanitize($_POST['new_username']);
        $newPass = $_POST['new_password'];
        if (empty($newUser) || empty($newPass)) {
            $error = "用户名和密码不能为空";
        } elseif (isset($users[$newUser])) {
            $error = "用户已存在";
        } else {
            $users[$newUser] = password_hash($newPass, PASSWORD_DEFAULT);
            file_put_contents(DATA_DIR . 'users.json', json_encode($users, JSON_PRETTY_PRINT));
            logAction('create_user', "创建用户 $newUser");
            $success = "用户 $newUser 创建成功";
            $users = json_decode(file_get_contents(DATA_DIR . 'users.json'), true);
        }
    }
    
    // 2. 删除用户
    if ($_POST['action'] === 'delete_user' && isset($_POST['delete_username'])) {
        $delUser = sanitize($_POST['delete_username']);
        if ($delUser === 'admin') {
            $error = "不能删除超级管理员账号";
        } elseif ($delUser === $currentAdmin) {
            $error = "不能删除自己的账号";
        } elseif (isset($users[$delUser])) {
            unset($users[$delUser]);
            file_put_contents(DATA_DIR . 'users.json', json_encode($users, JSON_PRETTY_PRINT));
            
            // 删除该用户的所有图片
            $images = getAllImages();
            $remainingImages = [];
            foreach ($images as $img) {
                if ($img['uploader'] === $delUser) {
                    if (isset($img['storage']) && $img['storage'] === 'webdav') {
                        webdav_delete(WEBDAV_IMG_DIR . '/' . $img['filename']);
                    } else {
                        @unlink(UPLOAD_DIR . $img['filename']);
                    }
                } else {
                    $remainingImages[] = $img;
                }
            }
            saveImages($remainingImages);
            
            logAction('delete_user', "删除用户 $delUser 及其所有图片");
            $success = "用户 $delUser 已删除，其上传的图片也已清理";
            $users = json_decode(file_get_contents(DATA_DIR . 'users.json'), true);
        } else {
            $error = "用户不存在";
        }
    }
    
    // 3. 修改自己的密码
    if ($_POST['action'] === 'change_my_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
            $error = "请填写完整信息";
        } elseif ($newPass !== $confirmPass) {
            $error = "两次输入的新密码不一致";
        } elseif (strlen($newPass) < 4) {
            $error = "新密码长度至少4位";
        } elseif (!password_verify($oldPass, $users[$currentAdmin])) {
            $error = "原密码错误";
        } else {
            $users[$currentAdmin] = password_hash($newPass, PASSWORD_DEFAULT);
            file_put_contents(DATA_DIR . 'users.json', json_encode($users, JSON_PRETTY_PRINT));
            logAction('change_password', "修改了自己的密码");
            $success = "密码修改成功，请使用新密码重新登录";
        }
    }
    
    // 4. 修改普通用户的密码
    if ($_POST['action'] === 'change_user_password') {
        $targetUser = sanitize($_POST['target_user'] ?? '');
        $newPass = $_POST['new_user_password'] ?? '';
        
        if (empty($targetUser) || empty($newPass)) {
            $error = "请选择用户并填写新密码";
        } elseif ($targetUser === 'admin') {
            $error = "不能通过此方式修改管理员密码，请使用上方的修改密码功能";
        } elseif (!isset($users[$targetUser])) {
            $error = "用户不存在";
        } elseif (strlen($newPass) < 4) {
            $error = "新密码长度至少4位";
        } else {
            $users[$targetUser] = password_hash($newPass, PASSWORD_DEFAULT);
            file_put_contents(DATA_DIR . 'users.json', json_encode($users, JSON_PRETTY_PRINT));
            logAction('change_user_password', "修改了用户 $targetUser 的密码");
            $success = "用户 $targetUser 的密码已重置";
            $users = json_decode(file_get_contents(DATA_DIR . 'users.json'), true);
        }
    }
    
    // 5. 批量删除图片
    if ($_POST['action'] === 'batch_delete' && isset($_POST['delete_ids'])) {
        $deleteIds = $_POST['delete_ids'];
        if (is_array($deleteIds) && !empty($deleteIds)) {
            $images = getAllImages();
            $remainingImages = [];
            $deletedCount = 0;
            
            foreach ($images as $img) {
                if (in_array($img['id'], $deleteIds)) {
                    if (isset($img['storage']) && $img['storage'] === 'webdav') {
                        webdav_delete(WEBDAV_IMG_DIR . '/' . $img['filename']);
                    } else {
                        @unlink(UPLOAD_DIR . $img['filename']);
                    }
                    $deletedCount++;
                    logAction('batch_delete', "批量删除图片 {$img['filename']}");
                } else {
                    $remainingImages[] = $img;
                }
            }
            saveImages($remainingImages);
            $success = "✅ 成功删除 {$deletedCount} 张图片";
            $allImages = $remainingImages;
        } else {
            $error = "请至少选择一张图片";
        }
    }
}

// 处理单个删除图片
if (isset($_GET['del_id'])) {
    $delId = sanitize($_GET['del_id']);
    $images = getAllImages();
    foreach ($images as $k => $img) {
        if ($img['id'] === $delId) {
            if (isset($img['storage']) && $img['storage'] === 'webdav') {
                webdav_delete(WEBDAV_IMG_DIR . '/' . $img['filename']);
            } else {
                @unlink(UPLOAD_DIR . $img['filename']);
            }
            array_splice($images, $k, 1);
            saveImages($images);
            logAction('delete', "删除图片 {$img['filename']}");
            header('Location: admin.php');
            exit;
        }
    }
}

// 辅助函数：获取图片 URL
function getAdminImageUrl($filename) {
    if (USE_WEBDAV) {
        return 'image_proxy.php?file=' . urlencode($filename);
    }
    return 'uploads/' . $filename;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>流欺图床 · 管理之境</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #0f0c1f 0%, #1a1535 100%);
            font-family: 'Segoe UI', 'Poppins', system-ui, sans-serif;
            padding: 24px;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 40%, rgba(255, 140, 200, 0.08), transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .glass-panel {
            max-width: 1600px;
            margin: 0 auto;
            background: rgba(20, 15, 45, 0.65);
            backdrop-filter: blur(16px);
            border-radius: 48px;
            padding: 2rem;
            border: 1px solid rgba(255, 210, 240, 0.3);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #FFD0E8, #D8B2FF);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .subtitle {
            color: rgba(255, 220, 240, 0.7);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 200, 230, 0.2);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: rgba(10, 8, 22, 0.7);
            backdrop-filter: blur(8px);
            border-radius: 32px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 200, 230, 0.25);
            transition: all 0.2s;
        }

        .card:hover {
            border-color: rgba(255, 160, 200, 0.5);
            transform: translateY(-2px);
        }

        .card h3 {
            color: #ffd0e8;
            font-size: 1.3rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 3px solid #ff9eb5;
            padding-left: 12px;
        }

        .card input, .card select {
            width: 100%;
            padding: 12px 16px;
            margin: 8px 0;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 200, 220, 0.3);
            border-radius: 60px;
            color: white;
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .card input:focus, .card select:focus {
            outline: none;
            border-color: #ff9ec0;
            background: rgba(255, 255, 255, 0.15);
        }

        .card input::placeholder, .card select {
            color: rgba(255, 220, 240, 0.6);
        }

        .card select option {
            background: #1a1535;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(120deg, #ff9eb5, #b77cff);
            border: none;
            padding: 12px 24px;
            border-radius: 60px;
            font-size: 0.95rem;
            font-weight: bold;
            color: #1e1a2f;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            margin-top: 12px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-danger {
            background: rgba(220, 70, 90, 0.8);
            border: none;
            padding: 6px 14px;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            font-size: 0.75rem;
            transition: 0.2s;
        }

        .btn-danger:hover {
            background: #ff4466;
            transform: scale(0.98);
        }

        .btn-warning {
            background: linear-gradient(120deg, #ffaa66, #ff7744);
            border: none;
            padding: 8px 20px;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            font-size: 0.85rem;
            transition: 0.2s;
            font-weight: bold;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 100, 50, 0.4);
        }

        .btn-secondary {
            background: rgba(100, 150, 255, 0.6);
            border: none;
            padding: 8px 18px;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            font-size: 0.85rem;
            transition: 0.2s;
        }

        .btn-secondary:hover {
            background: rgba(100, 150, 255, 0.9);
            transform: translateY(-2px);
        }

        .user-list {
            list-style: none;
            max-height: 300px;
            overflow-y: auto;
        }

        .user-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            margin: 6px 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 60px;
            color: #e0d0f0;
            font-size: 0.9rem;
        }

        .user-list li .user-name {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 图片管理区域 */
        .image-section {
            margin-top: 2rem;
        }

        .image-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            gap: 12px;
        }

        .image-section h3 {
            color: #ffd0e8;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .batch-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            background: rgba(255, 255, 255, 0.08);
            padding: 8px 20px;
            border-radius: 60px;
            backdrop-filter: blur(8px);
        }

        .batch-bar .select-all {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ffd0e8;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.2rem;
            max-height: 60vh;
            overflow-y: auto;
            padding: 4px;
        }

        .img-card {
            background: rgba(0, 0, 0, 0.45);
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.25s;
            border: 2px solid transparent;
            position: relative;
        }

        .img-card:hover {
            transform: translateY(-6px);
            border-color: rgba(255, 160, 200, 0.6);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4);
        }

        .img-card.selected {
            border-color: #ff9eb5;
            box-shadow: 0 0 0 2px #ff9eb5;
        }

        .checkbox-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .img-checkbox {
            display: none;
        }

        .checkbox-label {
            width: 26px;
            height: 26px;
            background: rgba(0, 0, 0, 0.7);
            border: 2px solid rgba(255, 200, 220, 0.9);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            position: relative;
            backdrop-filter: blur(4px);
        }

        .img-checkbox:checked + .checkbox-label {
            background: #ff9eb5;
            border-color: #ff9eb5;
        }

        .img-checkbox:checked + .checkbox-label::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1e1a2f;
            font-size: 14px;
            font-weight: bold;
        }

        .checkbox-label:hover {
            transform: scale(1.1);
            border-color: #ffd0e8;
        }

        .img-card img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
            cursor: pointer;
            background: #1a1535;
        }

        .img-info {
            padding: 12px;
            color: #f0eef6;
        }

        .img-name {
            font-weight: 600;
            font-size: 0.85rem;
            word-break: break-all;
            color: #ffd8e8;
        }

        .img-uploader {
            font-size: 0.7rem;
            color: #c8b0e8;
            margin: 5px 0;
        }

        .img-time {
            font-size: 0.65rem;
            color: #a890c0;
        }

        .img-storage {
            font-size: 0.6rem;
            color: #90c0a8;
            margin-top: 4px;
        }

        .link-btn {
            background: rgba(100, 150, 255, 0.5);
            border: none;
            border-radius: 30px;
            padding: 5px 10px;
            color: white;
            cursor: pointer;
            font-size: 0.65rem;
            transition: 0.2s;
            width: 100%;
            margin-top: 6px;
        }

        .link-btn:hover {
            background: rgba(100, 150, 255, 0.9);
        }

        .del-img-btn {
            background: rgba(200, 50, 70, 0.8);
            border: none;
            border-radius: 40px;
            padding: 6px 12px;
            color: white;
            cursor: pointer;
            font-size: 0.7rem;
            width: 100%;
            margin-top: 8px;
            transition: 0.2s;
        }

        .del-img-btn:hover {
            background: #ff4455;
        }

        .alert-success {
            background: rgba(80, 200, 120, 0.25);
            border: 1px solid #88ffaa;
            color: #ccffdd;
            padding: 10px 16px;
            border-radius: 40px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .alert-error {
            background: rgba(220, 70, 90, 0.25);
            border: 1px solid #ff8899;
            color: #ffccdd;
            padding: 10px 16px;
            border-radius: 40px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .back-link {
            display: inline-block;
            margin-top: 2rem;
            text-align: center;
            color: #ffc0e0;
            text-decoration: none;
            padding: 10px 24px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 60px;
            transition: 0.2s;
        }

        .back-link:hover {
            background: rgba(255, 200, 220, 0.2);
        }

        .stats-badge {
            background: rgba(255, 140, 180, 0.3);
            border-radius: 40px;
            padding: 4px 12px;
            font-size: 0.75rem;
            color: #ffc0e0;
        }

        hr {
            margin: 1rem 0;
            border-color: rgba(255, 200, 230, 0.2);
        }

        .storage-info {
            font-size: 0.7rem;
            color: #b8a0d0;
            margin-top: 8px;
            text-align: center;
        }

        .nav-links {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-link {
            background: rgba(255, 245, 240, 0.15);
            border: 1px solid rgba(255, 230, 200, 0.4);
            border-radius: 60px;
            padding: 6px 18px;
            color: #fff0e5;
            text-decoration: none;
            font-size: 0.85rem;
            transition: 0.2s;
        }

        .nav-link:hover {
            background: rgba(255, 200, 220, 0.3);
            transform: translateY(-2px);
        }

        .toast {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(12px);
            padding: 10px 20px;
            border-radius: 40px;
            color: #ccffdd;
            font-size: 0.85rem;
            z-index: 1001;
            animation: fadeInUp 0.2s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }
            .glass-panel {
                padding: 1.2rem;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .image-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .batch-bar {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
<div class="glass-panel">
    <h1>
        ⚙️ 管理之境
        <span class="stats-badge"><?= count($allImages) ?> 张图片 | <?= count($users) ?> 位用户</span>
        <?php if (USE_WEBDAV): ?>
        <span class="stats-badge" style="background: rgba(100,150,255,0.3);">☁️ WebDAV 模式</span>
        <?php else: ?>
        <span class="stats-badge" style="background: rgba(100,150,255,0.3);">💾 本地存储</span>
        <?php endif; ?>
        <span class="stats-badge" style="background: rgba(255,200,100,0.3);">👑 <?= htmlspecialchars($currentAdmin) ?></span>
    </h1>
    <div class="subtitle">
        流欺图床 · 管理后台
        <div class="nav-links" style="margin-top: 12px;">
            <a href="index.html" class="nav-link">🏠 返回首页</a>
            <a href="config_manager.php" class="nav-link">⚙️ 系统配置</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert-success">✨ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        
        <!-- 卡片1：修改自己的密码 -->
        <div class="card">
            <h3>🔐 修改我的密码</h3>
            <form method="POST">
                <input type="hidden" name="action" value="change_my_password">
                <input type="password" name="old_password" placeholder="当前密码" required autocomplete="off">
                <input type="password" name="new_password" placeholder="新密码（至少4位）" required>
                <input type="password" name="confirm_password" placeholder="确认新密码" required>
                <button type="submit" class="btn-primary">🔄 更新我的密码</button>
            </form>
        </div>

        <!-- 卡片2：修改普通用户密码 -->
        <div class="card">
            <h3>👤 重置用户密码</h3>
            <form method="POST">
                <input type="hidden" name="action" value="change_user_password">
                <select name="target_user" required>
                    <option value="">选择用户</option>
                    <?php foreach ($users as $username => $hash): ?>
                        <?php if ($username !== 'admin'): ?>
                            <option value="<?= htmlspecialchars($username) ?>">🌸 <?= htmlspecialchars($username) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <input type="password" name="new_user_password" placeholder="新密码（至少4位）" required>
                <button type="submit" class="btn-primary">🔑 重置该用户密码</button>
            </form>
        </div>

        <!-- 卡片3：创建账号 -->
        <div class="card">
            <h3>✨ 创建新账号</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <input type="text" name="new_username" placeholder="用户名" required autocomplete="off">
                <input type="password" name="new_password" placeholder="密码" required>
                <button type="submit" class="btn-primary">➕ 创建账号</button>
            </form>
        </div>

        <!-- 卡片4：用户列表与删除 -->
        <div class="card">
            <h3>👥 用户管理</h3>
            <ul class="user-list">
                <?php foreach ($users as $username => $hash): ?>
                    <li>
                        <div class="user-name">
                            <?php if ($username === 'admin'): ?>
                                👑 <?= htmlspecialchars($username) ?>
                            <?php else: ?>
                                🌸 <?= htmlspecialchars($username) ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($username !== 'admin' && $username !== $currentAdmin): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除用户 <?= htmlspecialchars($username) ?> 吗？\n该用户上传的所有图片也会被删除！');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="delete_username" value="<?= htmlspecialchars($username) ?>">
                                    <button type="submit" class="btn-danger">🗑️ 删除</button>
                                </form>
                            <?php elseif ($username !== 'admin' && $username === $currentAdmin): ?>
                                <span class="stats-badge" style="background: rgba(255,140,100,0.3);">当前账户</span>
                            <?php elseif ($username === 'admin'): ?>
                                <span class="stats-badge" style="background: rgba(255,200,100,0.3);">超级管理员</span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <hr>
            <div class="storage-info">
                💡 提示：普通用户的密码可通过上方「重置用户密码」修改
            </div>
        </div>
    </div>

    <!-- 图片管理区域（带批量删除） -->
    <div class="image-section">
        <div class="image-header">
            <h3>🖼️ 图片管理 · 点击图片可放大预览</h3>
            <div class="batch-bar" id="batchBar" style="display: none;">
                <label class="select-all">
                    <input type="checkbox" id="selectAllCheckbox"> 
                    <span>全选</span>
                </label>
                <button class="btn-warning" id="batchDeleteBtn">
                    🗑️ 批量删除 (<span id="selectedCount">0</span>)
                </button>
                <button class="btn-secondary" id="cancelSelectBtn">取消选择</button>
            </div>
        </div>
        
        <form id="batchDeleteForm" method="POST">
            <input type="hidden" name="action" value="batch_delete">
            <div class="image-grid" id="imageGrid">
                <?php if (empty($allImages)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px; color: #c8b0e8;">
                        📭 暂无图片，让用户们上传一些吧
                    </div>
                <?php else: ?>
                    <?php foreach ($allImages as $img): ?>
                        <div class="img-card" data-id="<?= $img['id'] ?>">
                            <div class="checkbox-overlay">
                                <input type="checkbox" name="delete_ids[]" value="<?= $img['id'] ?>" class="img-checkbox" id="cb_<?= $img['id'] ?>">
                                <label for="cb_<?= $img['id'] ?>" class="checkbox-label"></label>
                            </div>
                            <img src="<?= getAdminImageUrl($img['filename']) ?>" loading="lazy" 
                                 onclick="window.open('<?= getAdminImageUrl($img['filename']) ?>', '_blank')" 
                                 style="cursor: pointer;">
                            <div class="img-info">
                                <div class="img-name">📷 <?= htmlspecialchars(mb_substr($img['name'], 0, 20)) ?></div>
                                <div class="img-uploader">👤 <?= htmlspecialchars($img['uploader']) ?></div>
                                <div class="img-time">📅 <?= $img['time'] ?></div>
                                <?php if (isset($img['storage'])): ?>
                                    <div class="img-storage">📦 <?= $img['storage'] === 'webdav' ? 'WebDAV' : '本地' ?></div>
                                <?php endif; ?>
                                <button class="link-btn" onclick="copyLink('<?= getAdminImageUrl($img['filename']) ?>')">🔗 复制链接</button>
                                <button type="button" class="del-img-btn" onclick="deleteSingleImage('<?= $img['id'] ?>')">🗑️ 删除</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
    // 复制链接函数
    async function copyLink(url) {
        try {
            await navigator.clipboard.writeText(url);
            showToast('✅ 链接已复制到剪贴板！');
        } catch (err) {
            const textarea = document.createElement('textarea');
            textarea.value = url;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast('✅ 链接已复制到剪贴板！');
        }
    }
    
    function showToast(msg) {
        let toast = document.querySelector('.toast');
        if (toast) toast.remove();
        toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }
    
    // 单个删除
    async function deleteSingleImage(id) {
        if (!confirm('确认删除这张图片？')) return;
        const res = await fetch(`admin.php?del_id=${encodeURIComponent(id)}`);
        if (res.ok) {
            location.reload();
        } else {
            alert('删除失败');
        }
    }
    
    // 批量删除相关逻辑
    const batchBar = document.getElementById('batchBar');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const batchDeleteBtn = document.getElementById('batchDeleteBtn');
    const cancelSelectBtn = document.getElementById('cancelSelectBtn');
    const selectedCountSpan = document.getElementById('selectedCount');
    const batchDeleteForm = document.getElementById('batchDeleteForm');
    
    function updateBatchBar() {
        const checkboxes = document.querySelectorAll('.img-checkbox');
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        
        if (checkedCount > 0) {
            batchBar.style.display = 'flex';
            selectedCountSpan.textContent = checkedCount;
        } else {
            batchBar.style.display = 'none';
        }
        
        const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
        if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
        
        document.querySelectorAll('.img-card').forEach(card => {
            const checkbox = card.querySelector('.img-checkbox');
            if (checkbox && checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }
    
    document.querySelectorAll('.img-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBatchBar);
    });
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            document.querySelectorAll('.img-checkbox').forEach(cb => {
                cb.checked = isChecked;
            });
            updateBatchBar();
        });
    }
    
    if (cancelSelectBtn) {
        cancelSelectBtn.addEventListener('click', () => {
            document.querySelectorAll('.img-checkbox').forEach(cb => {
                cb.checked = false;
            });
            updateBatchBar();
        });
    }
    
    if (batchDeleteBtn) {
        batchDeleteBtn.addEventListener('click', () => {
            const checkedCount = document.querySelectorAll('.img-checkbox:checked').length;
            if (checkedCount === 0) {
                alert('请至少选择一张图片');
                return;
            }
            if (confirm(`确定要删除选中的 ${checkedCount} 张图片吗？\n此操作不可恢复！`)) {
                batchDeleteForm.submit();
            }
        });
    }
    
    updateBatchBar();
</script>
</body>
</html>