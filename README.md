# 🛡️ 域名授权管理系统

> 一款轻量级、高性能的 PHP 域名/IP 授权管理系统，支持卡密生成、域名激活、到期管理、API 对接及离线缓存，适用于软件授权、SaaS 服务、API 网关等场景。

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.0-777BB4?style=flat&logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.6%2B-4479A1?style=flat&logo=mysql)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/UI-Bootstrap5-7952B3?style=flat&logo=bootstrap)](https://getbootstrap.com/)

---

## 📋 目录

- [功能特性](#-功能特性)
- [技术栈](#-技术栈)
- [项目结构](#-项目结构)
- [安装部署](#-安装部署)
- [快速开始](#-快速开始)
- [管理后台指南](#-管理后台指南)
- [API 文档](#-api-文档)
- [前端授权 SDK](#-前端授权-sdk)
- [数据库结构](#-数据库结构)
- [升级与维护](#-升级与维护)
- [安全说明](#-安全说明)
- [常见问题](#-常见问题)
- [许可协议](#-许可协议)

---

## ✨ 功能特性

### 🎫 卡密管理
- **生成卡密**：支持自定义前缀、数量（最多 100 个/次）、有效期（默认 365 天 / 自定义天数 / 永久）
- **导入卡密**：批量导入已生成的卡密，并统一设置有效期
- **卡密查询**：按状态（未使用/已使用）、有效期类型筛选；支持关键字搜索
- **批量操作**：批量删除、批量修改有效期
- **导出卡密**：支持纯码导出、含域名/使用时间详细导出、未使用/已使用分类导出
- **清空已使用**：一键删除所有已使用卡密

### 🌐 域名授权管理
- **手动添加**：支持添加域名、IP 地址、泛域名（如 `*.example.com`）
- **卡密激活**：用户可通过卡密自助激活域名
- **到期管理**：支持设置永久授权、自定义天数
- **续费授权**：管理员可对域名快速续费（+30/90/365 天/永久）；用户可通过卡密自助续费
- **状态标记**：正常 / 已到期 / 即将到期 / 封禁 多状态显示
- **批量操作**：批量删除、批量封禁/解封
- **搜索过滤**：按域名关键字、状态筛选
- **授权修改**：支持修改域名及重置到期时间

### 📊 统计面板
- 授权正常数量 / 已到期数量 / 已封禁数量 / 域名总数
- 可用卡密数量 / 卡密使用率（百分比）

### 📋 操作日志
- 自动记录所有关键操作（添加/封禁/续费/激活等）
- 每日重复日志自动去重
- 支持关键字搜索，按时间排序
- 清空全部日志 / 清理 30 天前历史日志

### 🔔 到期提醒
- 后台域名列表自动高亮：红色标记已到期、黄色标记 30 天内即将到期
- 前端用户界面显示到期时间

### 🛡️ 安全机制
- **Session 超期自动退出**（30 分钟无操作）
- **CSRF 令牌校验**：所有 POST 操作均校验（使用 `hash_equals` 防时序攻击）
- **bcrypt 密码加密**：管理员密码以 bcrypt 加密存储
- **登录频率限制**：15 分钟内 5 次错误即锁定
- **API 频率限制**：查询限速 60 次/分钟，激活限速 10 次/5 分钟
- **SQL 注入防护**：关键操作使用 prepared statements
- **XSS 防护**：所有输出通过 `htmlspecialchars` 过滤

### 📦 前端授权 SDK（JavaScript）
- 自动检测域名授权状态，未授权/已到期/被封禁时弹出拦截遮罩
- **防 DevTools 反调试**：禁用右键菜单、F12/Ctrl+Shift+I/J/U 等快捷键
- **离线缓存**：联网成功时缓存授权结果，断网时自动读取缓存
- **遮罩防移除轮询**：3 秒轮询检查遮罩是否存在，防止用户绕过
- **卡密激活 + 跳转购卡链接**
- 支持中文和英文两种 SDK 版本

### 🔌 RESTful API
- 授权状态查询接口
- 卡密激活接口
- 授权域名列表接口
- 请求频率限制 + 请求来源域名校验

### 🎨 UI/UX
- 暗色主题管理后台（渐变色 + 毛玻璃效果）
- 响应式设计，完美适配移动端
- 平滑动画过渡，优雅的交互反馈
- 前台查询页面：清新亮色主题，域名智能识别

---

## 🛠 技术栈

| 组件 | 技术选型 |
|------|---------|
| 后端语言 | PHP >= 7.0 |
| 数据库 | MySQL 5.6+（InnoDB，支持事务） |
| Web 服务器 | Nginx / Apache / OpenLiteSpeed |
| 前端 | 原生 JavaScript (ES6+) |
| UI 样式 | 纯 CSS（暗色主题 / 毛玻璃 / 渐变） |
| 认证方式 | Session + CSRF Token |
| 密码加密 | bcrypt (`PASSWORD_BCRYPT`) |
| JSON 接口 | RESTful JSON API |
| 跨域 | CORS 头支持 |

---

## 📁 项目结构

```
授权系统/
├── index.php          # 前台授权查询页面（用户端）
├── admin.php          # 管理后台（卡密/域名/日志/设置）
├── api.php            # RESTful JSON API 接口
├── common.php         # 公共函数库（核心业务逻辑）
├── config.php         # 数据库与管理员配置（安装时生成，已加入 .gitignore）
├── install.php        # 一键安装脚本
├── reset_pwd.php      # 紧急密码重置工具（使用后请立即删除）
├── opt_logs.php       # 日志表索引优化脚本
├── auth.js            # 前端授权 SDK（中文版）
├── auth_en.js         # 前端授权 SDK（英文版）
├── 授权代码           # 引入 SDK 的使用说明
├── README.md          # 本文档
└── .gitignore         # Git 忽略规则
```

---

## 🚀 安装部署

### 环境要求

| 要求 | 说明 |
|------|------|
| PHP | >= 7.0（推荐 7.4+ 或 8.0+） |
| MySQL | 5.6 及以上 |
| 扩展 | mysqli、json、random_bytes 支持 |
| Web 服务器 | Nginx / Apache / OpenLiteSpeed |
| 目录权限 | Web 用户可写 |

### 安装步骤

#### 方式一：Web 安装向导（推荐）

1. 将全部文件上传至网站目录（如 `/ym/`）
2. 确保目录对 Web 用户可写
3. 浏览器访问 `http://你的域名/ym/install.php`
4. 填写数据库信息和管理员账号
5. 点击「立即安装」即可完成

安装完成后会自动跳转到后台登录页。

#### 方式二：手动配置

1. 上传全部文件至网站目录
2. 手动创建 `config.php`（参考下方模板）：
```php
<?php
define('IN_SYSTEM', true);
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PWD', 'your_db_password');
define('DB_NAME', 'your_db_name');
define('ADMIN_USER', 'admin');
define('ADMIN_PWD', '$2y$10$...'); // bcrypt 加密后的密码
$install_lock = true;
?>
```
3. 手动执行数据库建表 SQL（见[数据库结构](#-数据库结构)）
4. 访问 `admin.php` 登录后台

> **注意**：`config.php` 包含敏感配置信息，已加入 `.gitignore`，切勿提交到公开仓库！

---

## 🔰 快速开始

### 1. 登录后台
访问 `http://你的域名/ym/admin.php`，使用安装时设置的管理员账号登录。

### 2. 生成卡密
在「卡密管理」页，设置前缀、数量、有效期，点击生成即可。

### 3. 添加授权域名
在「域名管理」页，手动添加需要授权的域名/IP，或导出的卡密供用户自行激活。

### 4. 集成前端 SDK
在你的项目页面引入授权脚本：
```html
<script src="https://你的域名/ym/auth.js"></script>
```
页面加载后会自动检测域名授权状态，未授权用户会看到激活弹窗。

---

## 🖥 管理后台指南

### 卡密管理 (`?t=card`)
| 功能 | 说明 |
|------|------|
| 生成卡密 | 自定义前缀、数量（1-100）、有效期（默认/自定义/永久） |
| 导入卡密 | 批量粘贴卡密，统一设置有效期 |
| 搜索筛选 | 按卡密码、状态、有效期类型搜索 |
| 批量操作 | 批量删除 / 批量修改有效期 |
| 导出 | 纯码导出 / 详细导出（含域名+时间） |
| 清空已使用 | 一键清除所有已使用卡密 |

### 域名管理 (`?t=domain`)
| 功能 | 说明 |
|------|------|
| 添加域名 | 支持普通域名、IP、泛域名（`*.example.com`） |
| 搜索筛选 | 按域名关键字、状态（正常/到期/封禁） |
| 修改域名 | 可修改域名本身及重置到期时间 |
| 续费操作 | 行内快速续费（+30/90/365天/永久） |
| 封禁/解封 | 针对单个域名或批量操作 |
| 删除域名 | 单条或批量删除 |

### 系统设置 (`?t=settings`)
- 修改管理员账号和密码（bcrypt 加密存储）

### 操作日志 (`?t=logs`)
- 查看所有关键操作记录
- 支持关键字搜索
- 清空全部日志 / 清理 30 天前历史日志

---

## 📡 API 文档

所有 API 返回 JSON 格式，支持跨域请求。

### 基础地址

```
http://你的域名/ym/api.php
```

### 公共参数

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| act | string | 是 | 接口动作：`check` / `active` / `list` |
| domain | string | 视接口而定 | 域名或 IP |
| code | string | 视接口而定 | 卡密 |

### 接口列表

#### 1️⃣ 查询授权状态

```
GET /ym/api.php?act=check&domain=example.com
```

**来源校验**：请求必须来自目标域名本身（通过 `Origin` / `Referer` 验证）

**返回示例**：
```json
{
  "code": 1,
  "expire_time": "2026-12-31 23:59:59"
}
```

| code | 含义 |
|------|------|
| 1 | 已授权，正常使用 |
| 2 | 已到期，需要续费 |
| 0 | 未授权 |
| -1 | 域名已被封禁 |

#### 2️⃣ 卡密激活

```
GET /ym/api.php?act=active&domain=example.com&code=ABC123DEF456GHIJ
```

**返回示例**（成功）：
```json
{
  "code": 1,
  "msg": "激活成功",
  "expire_time": "2027-05-10 12:00:00"
}
```

**返回示例**（失败）：
```json
{
  "code": 0,
  "msg": "卡密无效或已使用"
}
```

> **续费逻辑**：若域名已存在且未过期，到期时间自动叠加延长；若已过期，从当前时间重新计算。

#### 3️⃣ 获取授权域名列表

```
GET /ym/api.php?act=list
```

**返回示例**：
```json
[
  "example.com",
  "*.example.com",
  "192.168.1.100"
]
```

---

## 📦 前端授权 SDK

### 中文版 (`auth.js`)

自动注入页面，实现：
- 页面加载后自动检查当前域名授权状态
- 未授权时弹出全屏遮罩，阻止用户使用页面
- 支持卡密输入和在线激活
- 提供跳转购卡链接
- 断网时自动读取 localStorage 离线缓存
- 防 DevTools 调试（禁用 F12、右键菜单等）

### 英文版 (`auth_en.js`)

功能与中文版一致，文案为英文。

### 使用方式

在 `<head>` 或页面底部引入：
```html
<!-- 中文 -->
<script src="https://你的域名/ym/auth.js"></script>

<!-- 或英文 -->
<script src="https://你的域名/ym/auth_en.js"></script>
```

### 离线缓存机制

| 机制 | 说明 |
|------|------|
| 缓存 key | `auth_cache_` + 域名 |
| 缓存内容 | 授权状态、到期时间、缓存时间戳 |
| 有效条件 | 未到期且未封禁 |
| 网络恢复 | 重新联网后自动更新缓存 |

---

## 🗄 数据库结构

系统使用 4 张数据表，均使用 InnoDB 引擎，utf8mb4 字符集。

### `cards` - 卡密表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int(11) PRIMARY KEY AUTO_INCREMENT | 自增 ID |
| code | varchar(64) UNIQUE | 卡密码 |
| status | tinyint(1) DEFAULT 0 | 0=未使用, 1=已使用 |
| use_time | datetime DEFAULT NULL | 使用时间 |
| domain | varchar(128) DEFAULT '' | 激活的域名 |
| expire_days | int(11) DEFAULT NULL | 授权天数(NULL=默认365天, 0=永久) |
| create_time | datetime DEFAULT CURRENT_TIMESTAMP | 创建时间 |

### `auth` - 授权域名表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int(11) PRIMARY KEY AUTO_INCREMENT | 自增 ID |
| domain | varchar(128) UNIQUE | 域名/IP/泛域名 |
| status | tinyint(1) DEFAULT 1 | 1=正常, 0=封禁 |
| expire_time | datetime DEFAULT NULL | 到期时间(NULL=永久) |
| create_time | datetime DEFAULT CURRENT_TIMESTAMP | 创建时间 |

### `logs` - 操作日志表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int(11) PRIMARY KEY AUTO_INCREMENT | 自增 ID |
| action | varchar(64) | 操作类型（激活域名/续费/封禁等） |
| content | text | 操作内容详情 |
| ip | varchar(64) | 操作者 IP |
| create_time | datetime DEFAULT CURRENT_TIMESTAMP | 操作时间 |

索引：`idx_daily` (action, content, create_time) - 用于每日去重查询

### `login_log` - 登录日志表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int(11) PRIMARY KEY AUTO_INCREMENT | 自增 ID |
| ip | varchar(64) | IP 地址（含端点标识） |
| status | tinyint(1) DEFAULT 0 | 状态 |
| lock_time | datetime DEFAULT NULL | 锁定时间 |
| create_time | datetime DEFAULT CURRENT_TIMESTAMP | 创建时间 |

---

## 🔄 升级与维护

### 升级步骤

1. 备份数据库和 `config.php`
2. 备份当前所有文件
3. 上传新版本文件覆盖（保留 `config.php`）
4. 如需数据库变更，执行对应的升级 SQL
5. 登录后台验证功能正常

### 升级脚本示例

```sql
-- 示例：为日志表添加索引（2024-05-06 更新）
ALTER TABLE logs ADD INDEX idx_daily (action(32), content(64), create_time);
```

### 文件更新清单

每次升级后，需要上传替换的文件列表以及数据库变更 SQL 会随版本发布。

---

## 🔒 安全说明

### 已知安全措施
- ✅ 管理员密码 bcrypt 加密存储
- ✅ 所有 POST 操作 CSRF Token 校验
- ✅ Session 超时自动退出（30 分钟）
- ✅ 登录错误 5 次后 15 分钟锁定
- ✅ API 接口请求频率限制
- ✅ API 请求来源域名校验
- ✅ Prepared Statement 防 SQL 注入
- ✅ 输出过滤防 XSS
- ✅ config.php 不纳入版本控制

### ⚠️ 注意事项
- `reset_pwd.php` 使用后**必须立即删除**，避免被恶意利用
- `config.php` 包含敏感信息，确保不会被 Web 直接访问
- 建议定期更换管理员密码
- 生产环境建议启用 HTTPS
- 建议配置 Web 服务器层面禁止直接访问 `config.php`

---

## ❓ 常见问题

**Q: 为什么卡密激活后到期时间显示 1970 年？**
A: 这是已到期域名使用已过期卡密续费时出现的 bug，已在最新版本中修复。核心原因是续费计算时混淆了卡密自身的 `expire_days` 和卡密的到期状态逻辑。修复后：续费时始终使用卡密的 `expire_days` 字段重新计算到期时间，不再依赖卡密自身的创建时间。

**Q: 泛域名（*.example.com）如何激活？**
A: 泛域名仅支持管理员在后台手动添加，不支持通过卡密自助激活。

**Q: 如何修改 API 请求频率限制？**
A: 在 `common.php` 的 `checkRateLimit` 函数中调整 `$maxRequests` 和 `$windowSeconds` 参数。

**Q: 授权前端 SDK 为什么没生效？**
A: 请检查：① `auth.js` 中 `API` 地址是否配置正确 ② 服务器是否已部署本系统 ③ 浏览器控制台是否有跨域错误

---

## 📄 许可协议

本项目仅供学习和合法商业使用。未经授权不得用于违法活动。

---

*Made with ❤️ by [炫联网络](https://bbs.257820.xyz)*
