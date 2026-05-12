# 🍃 流欺图床 · LiuQi Image Host

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/yourname/liuqi-image-host/pulls)

> 浮光掠影，流光欺梦。一款轻量、安全、好看的 PHP 图床系统，无需数据库，开箱即用。

<p align="center">
  <img src="https://s41.ax1x.com/2026/05/12/peOjBZj.png" alt="预览截图" width="800">
</p>

---

## ✨ 特性

| 特性 | 说明 |
|------|------|
| 📸 **拖拽上传** | 支持拖拽 / 点击选择，最多 8 张批量上传 |
| 🔐 **验证码登录** | 图形验证码 + 密码哈希存储，防暴力破解 |
| 👥 **用户分级** | 管理员可创建/删除用户、重置密码、管理所有图片 |
| 🎨 **玻璃态 UI** | 液态玻璃质感 + 二次元风格，完美适配手机/平板/PC |
| 🌊 **动态背景** | 背景图联动平移轮播（上下左右方向，5秒自动切换） |
| 📁 **纯文件存储** | 无需 MySQL，数据存储为 JSON 文件 |
| 🛡️ **安全防护** | 防 XSS / 防恶意文件上传（图片重绘验证） |
| 📊 **上传进度** | 实时显示上传进度条 |
| 🖼️ **画廊模式** | 图片网格展示，支持预览和删除 |

---

## 🚀 快速开始

### 环境要求

- PHP 7.4+
- 扩展：GD（图片处理）、fileinfo（MIME检测，可选）

### 安装步骤

1. **克隆项目**
```bash
git clone https://github.com/lqay-cn/liuqi-image-host.git
cd liuqi-image-host
```
2. **克隆项目**
```bash
chmod 755 data/
chmod 755 uploads/
```
4. **配置 Nginx 伪静态（可选但推荐）**
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
4. **配置config.php文件**

**自行更改账号密码等等**
