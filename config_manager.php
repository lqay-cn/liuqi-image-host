<?php
// config_manager.php - 配置管理器
require 'config.php';
checkAdmin();

$configFile = __DIR__ . '/config.php';

// 处理配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_config') {
        // 读取当前 config.php 内容
        $configContent = file_get_contents($configFile);
        
        // 更新 USE_WEBDAV
        $useWebdav = isset($_POST['use_webdav']) ? 'true' : 'false';
        $configContent = preg_replace(
            "/define\('USE_WEBDAV',\s*(true|false)\);/",
            "define('USE_WEBDAV', " . $useWebdav . ");",
            $configContent
        );
        
        // 更新 WEBDAV_URL
        $webdavUrl = $_POST['webdav_url'] ?? '';
        $configContent = preg_replace(
            "/define\('WEBDAV_URL',\s*'[^']*'\);/",
            "define('WEBDAV_URL', '" . addslashes($webdavUrl) . "');",
            $configContent
        );
        
        // 更新 WEBDAV_USERNAME
        $webdavUsername = $_POST['webdav_username'] ?? '';
        $configContent = preg_replace(
            "/define\('WEBDAV_USERNAME',\s*'[^']*'\);/",
            "define('WEBDAV_USERNAME', '" . addslashes($webdavUsername) . "');",
            $configContent
        );
        
        // 更新 WEBDAV_PASSWORD（只有填写了才更新）
        $webdavPassword = $_POST['webdav_password'] ?? '';
        if (!empty($webdavPassword)) {
            $configContent = preg_replace(
                "/define\('WEBDAV_PASSWORD',\s*'[^']*'\);/",
                "define('WEBDAV_PASSWORD', '" . addslashes($webdavPassword) . "');",
                $configContent
            );
        }
        
        // 更新 WEBDAV_IMG_DIR
        $webdavImgDir = $_POST['webdav_img_dir'] ?? 'images';
        $configContent = preg_replace(
            "/define\('WEBDAV_IMG_DIR',\s*'[^']*'\);/",
            "define('WEBDAV_IMG_DIR', '" . addslashes($webdavImgDir) . "');",
            $configContent
        );
        
        // 更新 MAX_SIZE
        $maxSizeMb = (int)($_POST['max_size'] ?? 8);
        $maxSize = $maxSizeMb * 1024 * 1024;
        $configContent = preg_replace(
            "/define\('MAX_SIZE',\s*\d+\);/",
            "define('MAX_SIZE', " . $maxSize . ");",
            $configContent
        );
        
        // 更新 MAX_FILES
        $maxFiles = (int)($_POST['max_files'] ?? 8);
        $configContent = preg_replace(
            "/define\('MAX_FILES',\s*\d+\);/",
            "define('MAX_FILES', " . $maxFiles . ");",
            $configContent
        );
        
        // 直接写入新配置（不备份）
        file_put_contents($configFile, $configContent);
        
        // 重定向刷新
        header('Location: config_manager.php?updated=1');
        exit;
    }
    
    if ($_POST['action'] === 'test_webdav') {
        $testUrl = rtrim($_POST['test_url'], '/') . '/';
        $testUsername = $_POST['test_username'];
        $testPassword = $_POST['test_password'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_USERPWD, $testUsername . ':' . $testPassword);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Depth: 0']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 207) {
            $testResult = "✅ WebDAV 连接成功！";
        } else {
            $testResult = "❌ WebDAV 连接失败 (HTTP $httpCode)，请检查配置";
        }
    }
}

// 获取当前配置值（用于显示）
$currentConfig = [
    'USE_WEBDAV' => USE_WEBDAV,
    'WEBDAV_URL' => WEBDAV_URL,
    'WEBDAV_USERNAME' => WEBDAV_USERNAME,
    'WEBDAV_PASSWORD' => defined('WEBDAV_PASSWORD') && !empty(WEBDAV_PASSWORD) ? str_repeat('•', 8) : '',
    'WEBDAV_IMG_DIR' => WEBDAV_IMG_DIR,
    'MAX_SIZE_MB' => MAX_SIZE / 1024 / 1024,
    'MAX_FILES' => MAX_FILES,
];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>流欺图床 · 配置管理</title>
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

        .glass-panel {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(20, 15, 45, 0.65);
            backdrop-filter: blur(16px);
            border-radius: 48px;
            padding: 2rem;
            border: 1px solid rgba(255, 210, 240, 0.3);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
        }

        h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #FFD0E8, #D8B2FF);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: rgba(255, 220, 240, 0.7);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 200, 230, 0.2);
        }

        .config-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: rgba(10, 8, 22, 0.7);
            backdrop-filter: blur(8px);
            border-radius: 32px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 200, 230, 0.25);
        }

        .card h3 {
            color: #ffd0e8;
            font-size: 1.2rem;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 3px solid #ff9eb5;
            padding-left: 12px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            color: #c8b0e8;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 200, 220, 0.3);
            border-radius: 60px;
            color: white;
            font-size: 0.9rem;
            transition: 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #ff9ec0;
            background: rgba(255, 255, 255, 0.15);
        }

        .form-group .hint {
            font-size: 0.7rem;
            color: #a890c0;
            margin-top: 4px;
            margin-left: 12px;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.05);
            padding: 12px 16px;
            border-radius: 60px;
        }

        .toggle-switch input {
            width: 50px;
            height: 24px;
            appearance: none;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            position: relative;
            cursor: pointer;
            transition: 0.2s;
        }

        .toggle-switch input:checked {
            background: #ff9eb5;
        }

        .toggle-switch input::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 3px;
            transition: 0.2s;
        }

        .toggle-switch input:checked::before {
            left: 27px;
        }

        .toggle-switch .toggle-label {
            color: #ffd0e8;
            font-weight: bold;
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

        .btn-secondary {
            background: rgba(100, 150, 255, 0.6);
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            font-size: 0.85rem;
            transition: 0.2s;
        }

        .btn-secondary:hover {
            background: rgba(100, 150, 255, 0.9);
        }

        .alert-success {
            background: rgba(80, 200, 120, 0.25);
            border: 1px solid #88ffaa;
            color: #ccffdd;
            padding: 12px 20px;
            border-radius: 40px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .alert-info {
            background: rgba(100, 150, 255, 0.2);
            border: 1px solid #88aaff;
            color: #ccddff;
            padding: 12px 20px;
            border-radius: 40px;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
        }

        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
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

        .nav-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        @media (max-width: 768px) {
            body {
                padding: 16px;
            }
            .glass-panel {
                padding: 1.2rem;
            }
            .config-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="glass-panel">
    <h1>⚙️ 系统配置</h1>
    <div class="subtitle">流欺图床 · 配置管理中心</div>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert-success">✅ 配置已保存并立即生效！</div>
    <?php endif; ?>

    <?php if (isset($testResult)): ?>
        <div class="alert-info">🔍 <?= $testResult ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="update_config">
        
        <div class="config-section">
            <!-- 基础配置 -->
            <div class="card">
                <h3>📦 基础配置</h3>
                
                <div class="form-group">
                    <label>📸 最大上传大小 (MB)</label>
                    <input type="number" name="max_size" value="<?= $currentConfig['MAX_SIZE_MB'] ?>" min="1" max="50" step="1">
                    <div class="hint">单个文件最大限制，默认 8MB</div>
                </div>
                
                <div class="form-group">
                    <label>🖼️ 同时上传数量</label>
                    <input type="number" name="max_files" value="<?= $currentConfig['MAX_FILES'] ?>" min="1" max="20" step="1">
                    <div class="hint">最多同时上传图片数量，默认 8 张</div>
                </div>
            </div>

            <!-- WebDAV 配置 -->
            <div class="card">
                <h3>☁️ WebDAV 配置</h3>
                
                <div class="form-group">
                    <div class="toggle-switch">
                        <input type="checkbox" name="use_webdav" id="use_webdav" <?= $currentConfig['USE_WEBDAV'] ? 'checked' : '' ?>>
                        <label for="use_webdav" class="toggle-label">启用 WebDAV 存储</label>
                    </div>
                    <div class="hint">关闭后将使用本地 uploads 目录存储图片</div>
                </div>
                
                <div class="form-group" id="webdav_fields" style="<?= $currentConfig['USE_WEBDAV'] ? '' : 'opacity:0.6;' ?>">
                    <label>🔗 WebDAV 服务器地址</label>
                    <input type="text" name="webdav_url" value="<?= htmlspecialchars($currentConfig['WEBDAV_URL']) ?>" placeholder="https://dav.example.com/dav/">
                    <div class="hint">例如：https://dav.jianguoyun.com/dav/ 或 https://your-alist.com/dav/</div>
                </div>
                
                <div class="form-group" id="webdav_username_group" style="<?= $currentConfig['USE_WEBDAV'] ? '' : 'opacity:0.6;' ?>">
                    <label>👤 用户名</label>
                    <input type="text" name="webdav_username" value="<?= htmlspecialchars($currentConfig['WEBDAV_USERNAME']) ?>" placeholder="用户名">
                </div>
                
                <div class="form-group" id="webdav_password_group" style="<?= $currentConfig['USE_WEBDAV'] ? '' : 'opacity:0.6;' ?>">
                    <label>🔑 密码</label>
                    <input type="password" name="webdav_password" value="" placeholder="留空则保持原密码">
                    <div class="hint">当前密码已隐藏，留空则不修改</div>
                </div>
                
                <div class="form-group" id="webdav_dir_group" style="<?= $currentConfig['USE_WEBDAV'] ? '' : 'opacity:0.6;' ?>">
                    <label>📁 图片存储目录</label>
                    <input type="text" name="webdav_img_dir" value="<?= htmlspecialchars($currentConfig['WEBDAV_IMG_DIR']) ?>" placeholder="images">
                    <div class="hint">图片将上传到此目录下</div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary">💾 保存所有配置</button>
    </form>

    <!-- WebDAV 测试工具 -->
    <div class="card" style="margin-top: 1.5rem;">
        <h3>🔧 WebDAV 连接测试</h3>
        <form method="POST" style="display: flex; gap: 12px; flex-wrap: wrap;">
            <input type="hidden" name="action" value="test_webdav">
            <input type="text" name="test_url" placeholder="WebDAV 地址" style="flex: 2; padding: 10px 16px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,200,220,0.3); border-radius: 60px; color: white;">
            <input type="text" name="test_username" placeholder="用户名" style="flex: 1; padding: 10px 16px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,200,220,0.3); border-radius: 60px; color: white;">
            <input type="password" name="test_password" placeholder="密码" style="flex: 1; padding: 10px 16px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,200,220,0.3); border-radius: 60px; color: white;">
            <button type="submit" class="btn-secondary">🔍 测试连接</button>
        </form>
        <div class="hint" style="margin-top: 8px;">💡 测试连接不会保存配置，仅用于验证 WebDAV 服务器是否可用</div>
    </div>

    <!-- 导航区 -->
    <div class="card" style="margin-top: 1rem;">
        <h3>🧭 导航</h3>
        <div class="nav-links">
            <a href="admin.php" class="back-link">← 返回管理面板</a>
            <a href="index.html" class="back-link">🏠 返回首页</a>
        </div>
    </div>
</div>

<script>
    // WebDAV 字段联动
    const webdavToggle = document.getElementById('use_webdav');
    const webdavFields = ['webdav_fields', 'webdav_username_group', 'webdav_password_group', 'webdav_dir_group'];
    
    function toggleWebdavFields() {
        const isEnabled = webdavToggle.checked;
        webdavFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (isEnabled) {
                    field.style.opacity = '1';
                    field.querySelectorAll('input').forEach(input => input.disabled = false);
                } else {
                    field.style.opacity = '0.6';
                    field.querySelectorAll('input').forEach(input => input.disabled = true);
                }
            }
        });
    }
    
    if (webdavToggle) {
        webdavToggle.addEventListener('change', toggleWebdavFields);
        toggleWebdavFields();
    }
</script>
</body>
</html>