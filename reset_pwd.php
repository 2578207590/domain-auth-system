<?php
/**
 * 紧急重置管理员密码
 * ⚠️ 使用后请立即删除此文件！
 */
if (!file_exists('config.php')) die('系统未安装');

if (isset($_POST['reset'])) {
    require 'config.php';
    $pwd = trim($_POST['pwd'] ?? '');
    if (empty($pwd) || strlen($pwd) < 6) {
        $msg = '<p style="color:#dc2626">密码至少 6 位</p>';
    } else {
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $safeHash = addcslashes($hash, '$\\');
        $cfg = file_get_contents('config.php');
        $cfg = preg_replace("/define\('ADMIN_USER',\s*'([^']*)'\);/", "define('ADMIN_USER', '$1');", $cfg);
        $cfg = preg_replace("/define\('ADMIN_PWD',\s*'[^']*'\);/", "define('ADMIN_PWD', '{$safeHash}');", $cfg);
        if (file_put_contents('config.php', $cfg)) {
            $msg = '<p style="color:#10b981;font-size:18px">✅ 密码已重置为：<strong>' . htmlspecialchars($pwd) . '</strong></p>
            <p style="color:#dc2626;margin-top:16px">⚠️ 请立即删除此文件！然后 <a href="admin.php">进入后台</a></p>';
        } else {
            $msg = '<p style="color:#dc2626">❌ config.php 写入失败，请检查权限</p>';
        }
    }
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>重置密码</title>
    <style>body{font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f1f5f9}
    .card{background:#fff;padding:40px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);max-width:420px;text-align:center}
    h2{color:#111827;margin-bottom:24px}input{width:100%;height:48px;border:1px solid #d1d5db;border-radius:10px;padding:0 14px;font-size:15px;margin-bottom:16px}
    button{width:100%;height:48px;background:#6366f1;color:#fff;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer}
    a{color:#6366f1}</style></head><body><div class="card"><h2>🔑 重置管理员密码</h2>'.$msg.'</div></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>重置管理员密码</title>
<style>body{font-family:system-ui;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f1f5f9}
.card{background:#fff;padding:40px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);max-width:420px;text-align:center}
h2{color:#111827;margin-bottom:8px}.sub{color:#64748b;font-size:14px;margin-bottom:24px}
input{width:100%;height:48px;border:1px solid #d1d5db;border-radius:10px;padding:0 14px;font-size:15px;margin-bottom:16px;outline:none}
input:focus{border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
button{width:100%;height:48px;background:#6366f1;color:#fff;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer}
button:hover{background:#4f46e5}
.warn{margin-top:20px;padding:12px;background:#fef2f2;border-radius:8px;color:#dc2626;font-size:13px}
</style></head><body><div class="card">
<h2>🔑 重置管理员密码</h2>
<div class="sub">设置新密码，将使用 bcrypt 加密存储</div>
<form method="post">
<input type="password" name="pwd" placeholder="输入新密码（至少6位）" required minlength="6">
<input type="password" placeholder="再次输入确认" required oninput="if(this.value!==document.querySelector('[name=pwd]').value)this.setCustomValidity('两次密码不一致');else this.setCustomValidity('')">
<button name="reset">重 置 密 码</button>
</form>
<div class="warn">⚠️ 重置后请立即删除此文件！</div>
</div></body></html>
