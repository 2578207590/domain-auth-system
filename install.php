<?php
define('IN_SYSTEM', true);
$selfDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$adminUrl = $selfDir . '/admin.php';

if (file_exists('config.php')) {
    header("Location: $adminUrl");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

$error = '';

// 环境检测
$checks = [];
if (version_compare(PHP_VERSION, '7.0', '<')) $checks[] = '❌ PHP 版本需 >= 7.0，当前: ' . PHP_VERSION;
if (!extension_loaded('mysqli')) $checks[] = '❌ 缺少 mysqli 扩展';
if (!extension_loaded('json')) $checks[] = '❌ 缺少 json 扩展';
if (!is_writable(__DIR__)) $checks[] = "❌ 目录无写入权限，请设置 755/777 权限";
if (!empty($checks)) $error = implode('<br>', $checks);

if (isset($_POST['install'])) {
    $host = trim($_POST['db_host']);
    $user = trim($_POST['db_user']);
    $pwd = trim($_POST['db_pwd']);
    $name = trim($_POST['db_name']);
    $admin_user = trim($_POST['admin_user']);
    $admin_pwd = trim($_POST['admin_pwd']);

    if (empty($host) || empty($user) || empty($name) || empty($admin_user) || empty($admin_pwd)) {
        $error = '❌ 所有字段均不能为空';
    } else {
        $conn = @new mysqli($host, $user, $pwd);
        if ($conn->connect_error) {
            $error = '❌ 数据库连接失败：' . $conn->connect_error;
        } else {
            $conn->query("CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db($name);

            $sqls = [
                "CREATE TABLE IF NOT EXISTS `cards` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `code` varchar(64) NOT NULL,
                    `status` tinyint(1) DEFAULT 0,
                    `use_time` datetime DEFAULT NULL,
                    `domain` varchar(128) DEFAULT '',
                    `expire_days` int(11) DEFAULT NULL COMMENT '激活后授权天数,NULL=默认365天,0=永久',
                    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `code` (`code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
                
                "CREATE TABLE IF NOT EXISTS `auth` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `domain` varchar(128) NOT NULL,
                    `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=正常 0=封禁',
                    `expire_time` datetime DEFAULT NULL COMMENT '到期时间,NULL=永久',
                    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `domain` (`domain`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
                
                "CREATE TABLE IF NOT EXISTS `logs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `action` varchar(64) NOT NULL,
                    `content` text NOT NULL,
                    `ip` varchar(64) NOT NULL,
                    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
                
                "CREATE TABLE IF NOT EXISTS `login_log` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `ip` varchar(64) NOT NULL,
                    `status` tinyint(1) DEFAULT 0,
                    `lock_time` datetime DEFAULT NULL,
                    `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
            ];
            
            foreach ($sqls as $sql) $conn->query($sql);

            $pwd_hash = password_hash($admin_pwd, PASSWORD_BCRYPT);
            $config = "<?php
define('IN_SYSTEM', true);
define('DB_HOST', '$host');
define('DB_USER', '$user');
define('DB_PWD', '$pwd');
define('DB_NAME', '$name');
define('ADMIN_USER', '$admin_user');
define('ADMIN_PWD', '$pwd_hash');
\$install_lock = true;
?>";

            if (file_put_contents('config.php', $config)) {
                echo "<script>alert('✅ 安装成功！正在跳转到后台');location.href='$adminUrl';</script>";
                exit;
            } else {
                $error = '❌ config.php 写入失败，请检查目录权限';
            }
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>系统安装</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Microsoft YaHei,sans-serif}
body{min-height:100vh;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;padding:16px}
.card{background:#fff;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);width:100%;max-width:480px;padding:40px 32px}
.title{font-size:26px;font-weight:700;color:#111827;text-align:center;margin-bottom:8px}
.desc{color:#6b7280;text-align:center;margin-bottom:32px;font-size:15px}
.alert{padding:14px 16px;border-radius:12px;margin-bottom:20px;background:#fef2f2;color:#dc2626;font-size:14px;text-align:center}
.form-item{margin-bottom:16px}
.form-item label{display:block;font-size:14px;color:#374151;margin-bottom:8px;font-weight:500}
input{width:100%;height:52px;border:1px solid #d1d5db;border-radius:12px;padding:0 16px;font-size:15px;outline:none;transition:all .2s}
input:focus{border-color:#4f46e5;box-shadow:0 0 0 4px rgba(79,70,229,0.1)}
.btn{width:100%;height:52px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:12px;font-size:16px;font-weight:600;cursor:pointer;transition:.2s;margin-top:8px}
.btn:hover{transform:translateY(-2px);box-shadow:0 10px 25px -5px rgba(79,70,229,0.4)}
</style>
</head>
<body>
<div class="card">
    <div class="title">授权系统安装</div>
    <div class="desc">填写配置信息，完成一键部署</div>
    <?php if(!empty($error)):?><div class="alert"><?php echo $error?></div><?php endif?>
    <form method="post">
        <div class="form-item">
            <label>数据库地址</label>
            <input name="db_host" value="localhost" required>
        </div>
        <div class="form-item">
            <label>数据库账号</label>
            <input name="db_user" required>
        </div>
        <div class="form-item">
            <label>数据库密码</label>
            <input name="db_pwd" type="password" required>
        </div>
        <div class="form-item">
            <label>数据库名称</label>
            <input name="db_name" required>
        </div>
        <div class="form-item">
            <label>管理员账号</label>
            <input name="admin_user" required>
        </div>
        <div class="form-item">
            <label>管理员密码</label>
            <input name="admin_pwd" type="password" required>
        </div>
        <button type="submit" name="install" class="btn">立即安装</button>
    </form>
</div>
</body>
</html>
