# 域名授权系统

基于 PHP + MySQL 的域名授权管理系统，支持卡密激活、域名续费、泛域名授权。

## 功能

- 🎫 **卡密管理** — 生成/导入/导出卡密，支持自定义授权天数、永久有效
- 🌐 **域名管理** — 添加/修改/删除域名，手动续费、封禁、泛域名授权
- 🔐 **客户端验证** — 前端 JS 自动检测域名授权状态，弹窗激活
- ⚙️ **系统设置** — 修改管理员账号密码
- 📋 **操作日志** — 记录后台操作，支持查看和清理

## 安装

1. 上传所有文件到服务器
2. 访问 `install.php`，填写数据库信息和管理员账号密码
3. 安装完成后自动进入后台 `admin.php`

## 配置

### auth.js

auth.js 和 auth_en.js 中的 API 地址：

```javascript
const API = "https://your-domain.com/api.php";
```

### 卡密购买链接

```javascript
window.open("https://your-store.com", "_blank");
```

## 文件结构

| 文件 | 说明 |
|------|------|
| admin.php | 后台管理 |
| api.php | API 接口 |
| auth.js | 客户端验证（中文） |
| auth_en.js | 客户端验证（英文） |
| common.php | 公共函数库 |
| index.php | 前台查询/激活页 |
| install.php | 安装向导 |

## 数据库

安装过程自动创建以下表：

- `auth` — 域名授权记录
- `cards` — 卡密记录
- `logs` — 操作日志
- `login_log` — 登录日志（频率限制）
