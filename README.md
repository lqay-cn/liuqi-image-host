# 🍃 流欺图床 · LiuQi Image Host

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/lqay-cn/liuqi-image-host/pulls)

> 浮光掠影，流光欺梦。一款轻量、安全、好看的 PHP 图床系统，无需数据库，开箱即用。

<p align="center">
  <img src="https://s41.ax1x.com/2026/05/12/peOjBZj.png" alt="预览截图" width="800">
</p>

---

## ✨ 特性

| 特性 | 说明 |
|------|------|
| ☁️ **双存储模式** | 支持本地存储 + WebDAV（坚果云/Alist/Nextcloud） |
| 📸 **拖拽上传** | 支持拖拽/点击选择，最多 8 张批量上传，实时进度条 |
| 🔐 **验证码登录** | 图形验证码 + 密码哈希存储，防暴力破解 |
| 👥 **用户分级** | 管理员可创建/删除用户、重置密码、管理所有图片 |
| 🎨 **玻璃态 UI** | 液态玻璃质感 + 二次元风格，完美适配手机/平板/PC |
| 📁 **纯文件存储** | 无需 MySQL，数据存储为 JSON 文件 |
| 🛡️ **安全防护** | 防 XSS / 防恶意文件上传（图片重绘验证） |
| 🖼️ **画廊模式** | 图片网格展示，一键复制链接（URL/Markdown/BBCode/HTML） |
| ⚙️ **在线配置** | 后台可视化配置 WebDAV 参数，无需手动编辑文件 |

---

## 📦 存储模式说明

### 本地存储（默认）
图片保存在服务器的 `uploads/` 目录，适合小规模使用。

### WebDAV 存储
图片保存到远程 WebDAV 服务器（如坚果云、Alist、Nextcloud），**不占用服务器空间**。

**支持的 WebDAV 服务：**
- ☁️ 坚果云（推荐，免费用户每月 1G 上传流量）
- 🚀 Alist（支持挂载多种网盘）
- 📁 Nextcloud / Owncloud
- 🔗 任何标准 WebDAV 服务

---

## 🚀 快速开始

### 环境要求

- PHP 7.4+
- 扩展：GD（必需）、cURL（WebDAV 模式必需）、fileinfo（可选）

### 安装步骤

1. **克隆项目**
```bash
git clone https://github.com/lqay-cn/liuqi-image-host.git
cd liuqi-image-host
```

2.**设置权限目录**
```
chmod 755 data/
chmod 755 uploads/
```

3.**配置 Web 服务器（Nginx 示例）**
```
# 禁止访问 data 目录
location ^~ /data {
    deny all;
    return 403;
}

# 禁止访问 uploads 下的 PHP 文件
location ~ ^/uploads/.*\.(php|php5|phtml)$ {
    deny all;
}
```

4.**编辑配置文件 config.php**
```
// 存储模式选择
define('USE_WEBDAV', false);  // true=WebDAV模式，false=本地模式

// WebDAV 配置（USE_WEBDAV=true 时需填写）
define('WEBDAV_URL', 'https://dav.jianguoyun.com/dav/');
define('WEBDAV_USERNAME', 'your-email@example.com');
define('WEBDAV_PASSWORD', 'your-app-password');
define('WEBDAV_IMG_DIR', 'liuqi-img');
```
💡 坚果云用户：需要使用「应用密码」，在坚果云「账户信息」→「安全选项」中生成

访问网站

默认管理员账号：admin

默认密码：123456

⚠️ 登录后请立即修改密码！

⚙️ 配置管理
登录管理员账号后，可通过 config_manager.php 在线修改配置：

切换本地/WebDAV 存储模式

修改 WebDAV 服务器地址、用户名、密码

调整上传大小限制、同时上传数量

支持 WebDAV 连接测试

📁 目录结构
```
liuqi-image-host/
├── config.php           # 配置文件（需手动编辑）
├── index.html           # 首页
├── login.html           # 登录页
├── admin.php            # 管理面板
├── config_manager.php   # 配置管理界面
├── upload.php           # 上传处理 + 登录API
├── image_proxy.php      # 图片代理（支持本地/WebDAV）
├── captcha.php          # 验证码生成
├── get_images.php       # 获取图片列表API
├── get_link.php         # 获取图片链接API
├── get_user.php         # 获取用户信息API
├── logout.php           # 登出
├── data/                # 数据目录（自动创建）
│   ├── users.json       # 用户数据
│   ├── images.json      # 图片索引
│   └── actions.log      # 操作日志
├── uploads/             # 本地图片存储（自动创建）
└── README.md
```
🔧 常见问题

1. 图片上传后无法显示？
检查 data/images.json 中图片的 storage 字段是否与当前模式匹配

切换存储模式后，旧图片可能无法加载，可通过 image_proxy.php 自动适配

检查 uploads/ 或 WebDAV 目录权限

2. WebDAV 连接失败？
确认使用应用密码（不是登录密码）

检查 WEBDAV_URL 是否以 / 结尾

在后台 config_manager.php 中使用「测试连接」功能验证

3. 验证码不显示？
确保 PHP 已启用 GD 扩展

4. 上传提示“文件类型不允许”？
检查 config.php 中的 ALLOWED_TYPES 配置

确保图片未被损坏或包含恶意代码

📝 更新日志
v1.0.0 (2026-05-15)
✨ 初始版本发布

☁️ 支持本地 + WebDAV 双存储模式

🔐 图形验证码登录

👥 多用户管理

⚙️ 可视化配置管理

🎨 玻璃态响应式 UI
