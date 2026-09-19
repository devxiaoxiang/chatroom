# 小象聊天室

<p align="center">
  <img src="https://cdn.smallelephant.ccwu.cc/logo.jpg" alt="小象" width="120" height="120">
</p>

<p align="center">一个基于 PHP + JSON 文件存储的轻量级网页聊天室</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-%3E%3D7.4-777bb4" alt="PHP">
  <img src="https://img.shields.io/badge/license-MIT-blue" alt="License">
  <img src="https://img.shields.io/badge/database-JSON-lightgrey" alt="Database">
</p>

一个基于 PHP + JSON 文件存储的轻量级网页聊天室。单文件部署，无需数据库，适合学习、个人项目、内网小工具或轻量聊天场景。

> 注意：当前版本使用 JSON 文件存储数据，不适合高并发生产环境。如需正式商用，建议迁移到 MySQL / SQLite / Redis，并使用 WebSocket 或长轮询优化实时性。

- 项目名：`chatroom`
- 作者：小象
- GitHub：[@devxiaoxiang](https://github.com/devxiaoxiang)
- 仓库地址：https://github.com/devxiaoxiang/chatroom

---

## 功能特性

- 用户注册 / 登录 / 退出登录
- 密码使用 `password_hash()` 哈希存储
- 发送聊天消息
- 消息列表自动轮询刷新，默认 2 秒一次
- 最多保留最近 100 条消息
- 可撤回自己的消息
- 可注销账号，并删除该用户的所有消息
- 响应式界面，支持手机和电脑浏览器
- 单文件 `index.php`，无需框架、无需数据库
- 数据保存为 JSON 文件，方便查看和备份

---

## 技术栈

- 后端：PHP
- 前端：原生 HTML / CSS / JavaScript
- 存储：JSON 文件
- 通信：`fetch` + 轮询

---

## 环境要求

- PHP >= 7.4
- 开启 PHP Session
- Web 服务器：Apache / Nginx / PHP 内置服务器均可
- 项目目录需要可写权限，用于创建和修改：
  - `users.json`
  - `messages.json`

---

## 快速开始

### 1. 克隆或下载项目

```bash
git clone https://github.com/devxiaoxiang/chatroom.git
cd chatroom
```

### 2. 上传到 Web 目录

将 `index.php` 放到网站根目录或子目录，例如：

```text
/var/www/html/chatroom/index.php
```

### 3. 设置目录可写

```bash
chmod 755 .
chmod 666 users.json messages.json 2>/dev/null || true
```

如果文件还不存在，请确保当前目录允许 PHP 创建文件。

### 4. 访问聊天室

浏览器打开：

```text
http://你的域名/index.php
```

首次使用请点击“没有账号？立即注册”，注册后重新登录即可。

---

## 目录结构

```text
chatroom/
├── index.php          # 主程序：后端接口 + 前端页面
├── users.json         # 用户数据，运行时自动生成
├── messages.json      # 消息数据，运行时自动生成
├── README.md          # 项目说明
└── LICENSE            # 开源许可证
```

> 建议将 `users.json` 和 `messages.json` 加入 `.gitignore`，不要提交真实用户数据到仓库。

`.gitignore` 示例：

```gitignore
users.json
messages.json
*.log
.DS_Store
```

---

## 配置说明

当前版本没有独立配置文件，主要参数写在 `index.php` 中。

### 修改消息保留数量

找到：

```php
if (count($messages) > 100) {
    $messages = array_slice($messages, -100);
}
```

将 `100` 改成你想要的数量。

### 修改轮询间隔

找到：

```javascript
pollInterval = setInterval(() => loadMessages(false), 2000);
```

`2000` 表示 2000 毫秒，即 2 秒。可改为 `1000` 或 `3000`。

### 修改数据文件名

在 PHP 顶部：

```php
$dataFile = 'messages.json';
$usersFile = 'users.json';
```

可以改成你喜欢的路径或文件名。

### 替换 Logo

页面中 Logo 使用 CDN 图片：

```html
<img src="https://cdn.smallelephant.ccwu.cc/logo.jpg" alt="小象">
```

如需完全离线部署，可下载到本地并替换为：

```html
<img src="./assets/logo.jpg" alt="小象">
```

避免依赖第三方资源。

---

## API 说明

后端接口都通过 `index.php` 处理。

### POST 接口

请求头：

```http
Content-Type: application/json
```

#### 注册

```json
{
  "action": "register",
  "nickname": "test",
  "password": "123456"
}
```

成功响应：

```json
{
  "status": "success",
  "message": "注册成功！请重新登录"
}
```

#### 登录

```json
{
  "action": "login",
  "nickname": "test",
  "password": "123456"
}
```

成功响应：

```json
{
  "status": "success",
  "nickname": "test"
}
```

失败响应：

```json
{
  "status": "error",
  "message": "昵称或密码错误"
}
```

#### 退出登录

```json
{
  "action": "logout"
}
```

成功响应：

```json
{
  "status": "success"
}
```

#### 发送消息

```json
{
  "action": "sendMessage",
  "message": "大家好"
}
```

成功响应：

```json
{
  "status": "success"
}
```

#### 撤回消息

```json
{
  "action": "recallMessage",
  "messageId": "消息ID"
}
```

成功响应：

```json
{
  "status": "success"
}
```

#### 注销账号

```json
{
  "action": "deleteAccount",
  "password": "123456"
}
```

成功响应：

```json
{
  "status": "success",
  "message": "账号已注销"
}
```

### GET 接口

#### 获取消息列表

```http
GET ?action=getMessages
```

响应示例：

```json
[
  {
    "id": "65f1c2d3e4b0a",
    "nickname": "test",
    "message": "大家好",
    "recalled": false
  }
]
```

#### 检查登录状态

```http
GET ?action=checkLogin
```

响应示例：

```json
{
  "loggedIn": true,
  "nickname": "test"
}
```

---

## 安全说明

- 密码使用 `password_hash()` 和 `password_verify()` 处理，不保存明文。
- 消息和昵称在存储和输出时使用 `htmlspecialchars()` 转义，降低 XSS 风险。
- 撤回消息和注销账号会校验当前登录用户身份。
- 当前版本未实现 CSRF Token、频率限制和文件锁，生产环境请自行加固。
- 建议使用 HTTPS 部署，避免密码和消息在传输中被窃听。

---

## 常见问题

### 1. 注册或发送消息失败？

检查项目目录是否有写权限，确保 PHP 可以创建和修改 `users.json`、`messages.json`。

### 2. 页面一直显示“加载失败”？

检查浏览器控制台和 PHP 错误日志，确认接口返回正常。

### 3. 能否用于多人高并发？

不建议。JSON 文件存储在高并发下容易出现读写冲突，建议改用数据库。

### 4. 如何修改 Logo？

替换 `index.php` 中的 Logo 图片地址，或下载到本地后改为相对路径。

---

## 贡献指南

欢迎提交 Issue 和 Pull Request。

1. Fork 本仓库
2. 创建分支：`git checkout -b feature/your-feature`
3. 提交修改：`git commit -m "Add some feature"`
4. 推送分支：`git push origin feature/your-feature`
5. 提交 Pull Request

---

## 开源协议

本项目基于 [MIT](LICENSE) 协议开源。

---

## 作者

- 昵称：小象
- GitHub：[@devxiaoxiang](https://github.com/devxiaoxiang)
- 项目：`chatroom`

---

如果这个项目对你有帮助，欢迎点个 Star ⭐
