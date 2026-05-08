<?php
session_start();
$config_file = 'config.php';
if (!file_exists($config_file)) {
    die('<div style="max-width:400px;margin:60px auto;padding:24px;background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.1);text-align:center;font-family:system-ui;">
    <p style="color:#dc2626;font-size:16px;">请先访问 install.php 完成安装</p>
    <a href="install.php" style="display:inline-block;padding:12px 24px;background:#4f46e5;color:#fff;border-radius:12px;margin-top:16px;text-decoration:none;font-weight:500;">前往安装</a></div>');
}
require $config_file;
require 'common.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PWD, DB_NAME);
$conn->set_charset('utf8mb4');

$msg = '';
$domain = '';
$authStatus = null; // null=未查, 0=未授权, 1=已授权, -1=封禁, 2=已到期
$authExpireTime = null;
$isWildcardQuery = false;

if (isset($_POST['check']) || isset($_POST['active'])) {
    $domainRaw = trim($_POST['domain'] ?? '');
    $isWildcard = (strpos($domainRaw, '*') !== false);
    $isWildcardQuery = $isWildcard;
    $domain = $isWildcard ? $domainRaw : cleanDomain($domainRaw);

    // 校验域名/IP 格式
    if (!isValidAddress($domain)) {
        setcookie('msg', 'invalid_address', time() + 5, '/');
        setcookie('domain_raw', $domainRaw, time() + 60, '/');
        setcookie('domain', '', time() - 100, '/');
        header('Location: index.php');
        exit;
    }

    // 格式合法才保留域名到 cookie
    setcookie('domain', $domain, time() + 60, '/');

    if (isset($_POST['check'])) {
        $result = checkDomainAuth($conn, $domain);
        $authStatus = $result['code'];
        $authExpireTime = $result['expire_time'] ?? null;
        addLog($conn, '查询域名', "域名:{$domain} 状态:{$result['code']}", true);
        setcookie('domain_expire', $authExpireTime ?? '', time() + 60, '/');
        setcookie('domain_raw', $domainRaw, time() + 60, '/');
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['active'])) {
        if ($isWildcard) {
            setcookie('msg', 'wildcard_blocked', time() + 5, '/');
        } else {
            $code = trim($_POST['code'] ?? '');
            if (!$code) {
                setcookie('msg', 'empty_code', time() + 5, '/');
            } else {
                $result = activateDomain($conn, $domain, $code);
                if ($result['code'] === 1) {
                    setcookie('msg', 'active_success', time() + 5, '/');
                    setcookie('expire_info', $result['expire_time'] ?? '', time() + 5, '/');
                    $recheck = checkDomainAuth($conn, $domain);
                    setcookie('domain_expire', $recheck['expire_time'] ?? '', time() + 60, '/');
                    setcookie('domain', $domain, time() + 60, '/');
                } else {
                    setcookie('msg', 'invalid_code', time() + 5, '/');
                }
            }
        }
        header('Location: index.php');
        exit;
    }
}

// ─── 消息渲染（仅从 cookie 读取，单一路径）─
$msg = '';
if (isset($_COOKIE['msg'])) {
    $m = $_COOKIE['msg'];
    $ein = $_COOKIE['expire_info'] ?? '';
    $expireDisplay = '';
    if ($ein && $ein !== '') {
        $expireDisplay = '，到期时间：' . date('Y-m-d', strtotime($ein));
    } elseif ($ein === '') {
        $expireDisplay = '，永久有效';
    }
    $map = [
        'empty_code' => '<div class="msg error">请输入卡密</div>',
        'invalid_code' => '<div class="msg error">卡密无效或已使用</div>',
        'invalid_domain' => '<div class="msg error">请输入正确域名</div>',
        'invalid_address' => '<div class="msg error">请输入正确的域名或IP地址</div>',
        'wildcard_blocked' => '<div class="msg error">泛域名需管理员手动添加，无法通过卡密激活</div>',
        'active_success' => '<div class="msg success">✅ 激活成功' . $expireDisplay . '</div>'
    ];
    $msg = $map[$m] ?? '';
    setcookie('msg', '', time() - 100, '/');
    setcookie('expire_info', '', time() - 100, '/');
    // 清除可能残留的 session 消息
    unset($_SESSION['activation_err']);
}

// 保留用户输入的域名
if (isset($_COOKIE['domain_raw'])) {
    $domain = $_COOKIE['domain_raw'];
    setcookie('domain_raw', '', time() - 100, '/');
}

// 读取查询结果（合法域名授权状态）
if (isset($_COOKIE['domain'])) {
    $domain = $_COOKIE['domain'];
    $isWildcardQuery = (strpos($domain, '*') !== false);
    $result = checkDomainAuth($conn, $domain);
    $authStatus = $result['code'];
    $authExpireTime = $_COOKIE['domain_expire'] ?? ($result['expire_time'] ?? null);
    setcookie('domain', '', time() - 100, '/');
    setcookie('domain_expire', '', time() - 100, '/');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>授权查询中心</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Microsoft YaHei,sans-serif}
body{min-height:100vh;background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);display:flex;align-items:center;justify-content:center;padding:16px;overflow-x:hidden}
body::before{content:'';position:fixed;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle at 30% 50%,rgba(59,130,246,.08) 0%,transparent 50%),radial-gradient(circle at 70% 50%,rgba(139,92,246,.08) 0%,transparent 50%);animation:bgShift 20s ease-in-out infinite;pointer-events:none;z-index:0}
@keyframes bgShift{0%,100%{transform:translate(0,0)}33%{transform:translate(2%,-1%)}66%{transform:translate(-1%,2%)}}
.container{background:rgba(255,255,255,.97);border-radius:24px;box-shadow:0 25px 80px rgba(0,0,0,.35),0 0 0 1px rgba(255,255,255,.1);width:100%;max-width:520px;padding:44px 36px;position:relative;z-index:1;backdrop-filter:blur(20px)}
.header{text-align:center;margin-bottom:32px}
.header .icon{display:inline-block;width:56px;height:56px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);border-radius:16px;margin-bottom:16px;position:relative;animation:iconPulse 3s ease-in-out infinite}
.header .icon::after{content:'🔐';position:absolute;inset:0;display:grid;place-items:center;font-size:28px}
@keyframes iconPulse{0%,100%{box-shadow:0 0 0 0 rgba(59,130,246,.4)}50%{box-shadow:0 0 0 16px rgba(59,130,246,0)}}
.title{font-size:28px;font-weight:700;color:#111827;margin-bottom:6px}
.subtitle{color:#6b7280;font-size:15px}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:14px;color:#374151;font-weight:500;margin-bottom:8px}
.form-input{width:100%;height:52px;border:2px solid #e2e8f0;border-radius:14px;padding:0 16px;font-size:15px;outline:none;transition:.3s;background:#f8fafc}
.form-input:focus{border-color:#3b82f6;box-shadow:0 0 0 4px rgba(59,130,246,.12);background:#fff}
.btn{width:100%;height:52px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:#fff;border:none;border-radius:14px;font-size:16px;font-weight:600;cursor:pointer;transition:.3s;margin-bottom:12px;position:relative;overflow:hidden}
.btn:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(59,130,246,.4)}
.btn:active{transform:translateY(0)}
.btn::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent,rgba(255,255,255,.15),transparent);transform:translateX(-100%);transition:.6s}
.btn:hover::after{transform:translateX(100%)}
.btn-active{background:linear-gradient(135deg,#10b981,#059669)}
.msg{padding:14px 16px;border-radius:14px;margin-bottom:16px;text-align:center;font-size:14px;font-weight:500;animation:slideDown .3s ease-out}
.msg.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
.msg.error{background:#fef2f2;color:#dc2626;border:1px solid #fca5a5}
@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.status{padding:20px;border-radius:16px;text-align:center;font-weight:600;margin:20px 0;font-size:15px;animation:statusIn .4s ease-out}
.status .emoji{font-size:36px;display:block;margin-bottom:8px}
.status .domain-name{font-size:18px;margin-bottom:6px;word-break:break-all}
.status .hint{font-size:13px;margin-top:4px;opacity:.7}
.status.ok{background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#065f46;border:2px solid #6ee7b7}
.status.expired{background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;border:2px solid #fcd34d}
.status.ban{background:linear-gradient(135deg,#fee2e2,#fecaca);color:#dc2626;border:2px solid #fca5a5}
.status.no{background:linear-gradient(135deg,#f1f5f9,#e2e8f0);color:#475569;border:2px solid #cbd5e1}
@keyframes statusIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.card-input-wrap{position:relative}
.card-input-wrap .form-input{padding-right:48px}
.card-input-wrap .key-icon{position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:20px;pointer-events:none}
@media(max-width:480px){.container{padding:32px 20px;border-radius:20px}.title{font-size:24px}}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="icon"></div>
        <div class="title">授权查询中心</div>
        <div class="subtitle">查询域名授权状态，使用卡密快速激活</div>
    </div>
    <?php echo $msg?>
    <form method="post" onsubmit="return checkDomain()">
        <div class="form-group">
            <label class="form-label">域名 / 服务器IP</label>
            <input type="text" class="form-input" name="domain" placeholder="如 abc.com、192.168.1.1 或输入网址自动识别" value="<?php echo htmlspecialchars($domain)?>" required autocomplete="off" onblur="checkDomain()">
        </div>
        <button type="submit" name="check" class="btn">🔍 查询授权状态</button>

        <?php if($authStatus !== null && $domain):?>
            <?php if($authStatus === -1):?>
                <div class="status ban">
                    <span class="emoji">🚫</span>
                    <div class="domain-name"><?php echo htmlspecialchars($domain)?></div>
                    已被封禁，无法接入授权服务
                </div>
            <?php elseif($authStatus === 1):?>
                <?php $isPermanent = empty($authExpireTime); ?>
                <div class="status ok">
                    <span class="emoji">✅</span>
                    <div class="domain-name"><?php echo htmlspecialchars($domain)?></div>
                    已授权 · 正常使用中
                    <?php if($isPermanent): ?>
                    <div class="hint">永久授权，无需续费</div>
                    <?php else: ?>
                    <div class="hint">到期时间：<?=date('Y-m-d', strtotime($authExpireTime))?></div>
                    <?php endif; ?>
                </div>
                <?php if(!$isPermanent && !$isWildcardQuery): ?>
                <div style="margin-top:16px;padding:16px;background:#f0fdf4;border-radius:12px;border:1px solid #bbf7d0">
                    <div style="font-size:14px;color:#065f46;margin-bottom:10px;font-weight:500">🔐 提前续费授权（到期时间将叠加延长）</div>
                    <div class="card-input-wrap" style="margin-bottom:10px">
                        <input type="text" class="form-input" name="code" placeholder="请输入续费卡密" autocomplete="off" style="background:#fff">
                        <span class="key-icon">🔑</span>
                    </div>
                    <button type="submit" name="active" class="btn btn-active">🔄 续费授权</button>
                </div>
                <?php endif; ?>
            <?php elseif($authStatus === 2):?>
                <div class="status expired">
                    <span class="emoji">⏰</span>
                    <div class="domain-name"><?php echo htmlspecialchars($domain)?></div>
                    授权已到期
                    <?php if(!$isWildcardQuery): ?><div class="hint">请输入卡密续费授权</div><?php else: ?><div class="hint">泛域名需管理员续费</div><?php endif; ?>
                </div>
                <?php if(!$isWildcardQuery): ?>
                <div class="form-group">
                    <label class="form-label">续费卡密</label>
                    <div class="card-input-wrap">
                        <input type="text" class="form-input" name="code" placeholder="请输入激活卡密" autocomplete="off">
                        <span class="key-icon">🔑</span>
                    </div>
                </div>
                <button type="submit" name="active" class="btn btn-active">🔄 续费域名</button>
                <?php endif; ?>
            <?php else:?>
                <div class="status no">
                    <span class="emoji">❓</span>
                    <div class="domain-name"><?php echo htmlspecialchars($domain)?></div>
                    未授权
                    <?php if(!$isWildcardQuery): ?><div class="hint">请输入卡密激活此域名</div><?php else: ?><div class="hint">泛域名需管理员手动添加</div><?php endif; ?>
                </div>
                <?php if(!$isWildcardQuery): ?>
                <div class="form-group">
                    <label class="form-label">授权卡密</label>
                    <div class="card-input-wrap">
                        <input type="text" class="form-input" name="code" placeholder="请输入激活卡密" autocomplete="off">
                        <span class="key-icon">🔑</span>
                    </div>
                </div>
                <button type="submit" name="active" class="btn btn-active">🚀 立即激活域名</button>
                <?php endif; ?>
            <?php endif?>
        <?php endif?>
    </form>
</div>

<script>
function checkDomain() {
    let el = document.querySelector('[name=domain]');
    let v = el.value.trim();
    let isWildcard = v.startsWith('*.');
    if (!isWildcard) {
        v = v.replace(/^https?:\/\//i, '');
        v = v.replace(/\/.*$/, '');
        v = v.replace(/^www\./i, '');
    }
    el.value = v.trim();
    return true; // 前端只做清理，不弹窗，全由服务端校验和提示
}
</script>
</body>
</html>
<?php $conn->close()?>
