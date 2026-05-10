<?php
/**
 * 授权系统公共函数库
 * 被 index.php / api.php / admin.php 共享引用
 */
define('IN_SYSTEM', true);

// ─── IP 检测 ──────────────────────────────
function isIPAddress($address) {
    return (bool)filter_var($address, FILTER_VALIDATE_IP);
}

// ─── 地址格式校验（标准域名 或 IP）─────────
function isValidAddress($address) {
    $address = trim(strtolower($address));
    if (empty($address) || strpos($address, ' ') !== false) return false;
    // 允许 IP
    if (filter_var($address, FILTER_VALIDATE_IP)) return true;
    // 允许泛域名 *.xxx.com
    if (substr($address, 0, 2) === '*.') {
        $suffix = substr($address, 2);
        if (empty($suffix)) return false;
        return (bool)preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $suffix);
    }
    // 拒绝 localhost 等
    if (in_array($address, ['localhost', 'localhost.localdomain', 'localhost6', 'localhost6.localdomain6'])) return false;
    if (preg_match('#^https?://#i', $address)) return false;
    if (strpos($address, '/') !== false) return false;
    // 拒绝纯 IP 格式但不符合 filter_var 的（如 999.999.999.999）
    if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $address)) return false;
    // 标准域名正则
    return (bool)preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $address);
}

// ─── 域名清洗 ─────────────────────────────
function cleanDomain($host) {
    $host = strtolower(trim($host));
    $host = preg_replace('/:\d+$/', '', $host);
    $host = preg_replace('#^https?://#i', '', $host);
    $host = preg_replace('#^www\.#i', '', $host);
    $host = preg_replace('#/.*$#', '', $host);
    return $host;
}

// ─── 域名/IP 匹配 ─────────────────────────
function isDomainMatch($authDomain, $currentDomain) {
    $authDomain = strtolower(trim($authDomain));
    $currentDomain = strtolower(trim($currentDomain));
    if ($authDomain === $currentDomain) return true;
    // IP 地址不走泛域名匹配
    if (filter_var($authDomain, FILTER_VALIDATE_IP)) return false;
    // 泛域名匹配
    if (substr($authDomain, 0, 2) === '*.') {
        $suffix = ltrim($authDomain, '*.');
        return (bool)preg_match('/\.' . preg_quote($suffix, '/') . '$/', $currentDomain);
    }
    return false;
}

// ─── 获取授权地址类型标签 ───────────────────
function addressTypeLabel($address) {
    return filter_var($address, FILTER_VALIDATE_IP) ? 'IP' : '域名';
}

// ─── 获取客户端 IP ────────────────────────
function getClientIP($trustedProxy = false) {
    if ($trustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ─── 安全卡密生成 ─────────────────────────
function generateCardCode($prefix = '') {
    return strtoupper($prefix . substr(bin2hex(random_bytes(12)), 0, 16));
}

// ─── 操作日志（每日去重：同 action+content 每天只记一条）──
function addLog($conn, $action, $content, $daily = false) {
    if ($daily) {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("SELECT id FROM logs WHERE action=? AND content=? AND create_time >= ? LIMIT 1");
        $stmt->bind_param("sss", $action, $content, $today);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) { $stmt->close(); return; }
        $stmt->close();
    }
    $ip = getClientIP();
    $action = $conn->real_escape_string($action);
    $content = $conn->real_escape_string($content);
    $ip = $conn->real_escape_string($ip);
    $conn->query("INSERT INTO logs(action,content,ip) VALUES('$action','$content','$ip')");
}

// ─── IP 频率限制 ──────────────────────────
function checkRateLimit($conn, $endpoint, $maxRequests = 60, $windowSeconds = 60) {
    $ip = getClientIP();
    $key = $endpoint . ':' . $ip;
    $now = time();
    $cutoff = $now - $windowSeconds;
    @$conn->query("DELETE FROM login_log WHERE ip='$key' AND create_time < FROM_UNIXTIME($cutoff)");
    $r = @$conn->query("SELECT COUNT(*) FROM login_log WHERE ip='$key'");
    $cnt = $r ? (int)$r->fetch_row()[0] : 0;
    if ($cnt >= $maxRequests) return false;
    @$conn->query("INSERT INTO login_log(ip,status,create_time) VALUES('$key',1,NOW())");
    return true;
}

// ─── 检查域名授权状态（含到期 + 泛域名查询）─
function checkDomainAuth($conn, $domain) {
    $domain = trim(strtolower($domain));
    // 先查封禁
    $stmt = $conn->prepare("SELECT id FROM auth WHERE domain=? AND status=0 LIMIT 1");
    $stmt->bind_param("s", $domain);
    $stmt->execute();
    if ($stmt->get_result()->num_rows) { $stmt->close(); return ['code' => -1, 'msg' => '该域名已被封禁']; }
    $stmt->close();

    $isWildcard = (substr($domain, 0, 2) === '*.');
    $authorized = false;
    $expired = false;
    $expireTime = null;

    if ($isWildcard) {
        $stmt = $conn->prepare("SELECT expire_time FROM auth WHERE domain=? AND status=1 LIMIT 1");
        $stmt->bind_param("s", $domain);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            if (!empty($row['expire_time']) && strtotime($row['expire_time']) < time()) {
                $expired = true;
            } else {
                $authorized = true;
                $expireTime = $row['expire_time'];
            }
        }
    } else {
        $res = $conn->query("SELECT domain,status,expire_time FROM auth WHERE status=1");
        while ($row = $res->fetch_assoc()) {
            if (isDomainMatch($row['domain'], $domain)) {
                if (!empty($row['expire_time']) && strtotime($row['expire_time']) < time()) {
                    $expired = true;
                    continue;
                }
                $authorized = true;
                $expireTime = $row['expire_time'];
                break;
            }
        }
    }

    if ($authorized) return ['code' => 1, 'expire_time' => $expireTime];
    if ($expired) return ['code' => 2, 'msg' => '授权已到期，请续费'];
    return ['code' => 0];
}

// ─── 激活域名（卡密激活，含事务保护）───────
function activateDomain($conn, $domain, $code) {
    $domain = trim(strtolower($domain));
    // 泛域名不允许用户激活
    if (substr($domain, 0, 2) === '*.') {
        return ['code' => 0, 'msg' => '泛域名需管理员手动添加，无法通过卡密激活'];
    }
    // 开启事务
    $conn->begin_transaction();
    try {
        // 查封禁
        $stmt = $conn->prepare("SELECT id FROM auth WHERE domain=? AND status=0 LIMIT 1");
        $stmt->bind_param("s", $domain);
        $stmt->execute();
        if ($stmt->get_result()->num_rows) { $stmt->close(); $conn->rollback(); return ['code' => 0, 'msg' => '该域名已被封禁']; }
        $stmt->close();

        // 查卡密
        $stmt = $conn->prepare("SELECT id,expire_days FROM cards WHERE code=? AND status=0 LIMIT 1 FOR UPDATE");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$card) { $conn->rollback(); return ['code' => 0, 'msg' => '卡密无效或已使用']; }

        // 根据卡密到期天数计算到期时间
        $days = isset($card['expire_days']) && $card['expire_days'] !== null ? (int)$card['expire_days'] : null;
        if ($days === 0) { $expire = null; }
        elseif ($days === null) { $expire = date('Y-m-d H:i:s', strtotime('+365 days')); }
        else { $expire = date('Y-m-d H:i:s', strtotime("+{$days} days")); }

        // 检查域名是否已存在
        $stmt = $conn->prepare("SELECT id,expire_time FROM auth WHERE domain=? LIMIT 1");
        $stmt->bind_param("s", $domain);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $addDays = ($days === null) ? 365 : $days;
            if ($addDays === 0) {
                $stmt = $conn->prepare("UPDATE auth SET status=1,expire_time=NULL WHERE id=?");
                $stmt->bind_param("i", $existing['id']);
            } else {
                $base = !empty($existing['expire_time']) && strtotime($existing['expire_time']) > time()
                    ? $existing['expire_time'] : date('Y-m-d H:i:s');
                $expire = date('Y-m-d H:i:s', strtotime($base . " +{$addDays} days"));
                $stmt = $conn->prepare("UPDATE auth SET status=1,expire_time=? WHERE id=?");
                $stmt->bind_param("si", $expire, $existing['id']);
            }
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO auth(domain,status,expire_time) VALUES(?,1,?)");
            $stmt->bind_param("ss", $domain, $expire);
            $stmt->execute();
            $stmt->close();
        }

        // 标记卡密已使用
        $stmt = $conn->prepare("UPDATE cards SET status=1,domain=?,use_time=NOW() WHERE id=?");
        $stmt->bind_param("si", $domain, $card['id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $expireLabel = $expire ? "到期:{$expire}" : "永久有效";
        addLog($conn, '激活域名', "域名:{$domain} 通过卡密激活, {$expireLabel}");
        return ['code' => 1, 'msg' => '激活成功', 'expire_time' => $expire];
    } catch (Exception $e) {
        $conn->rollback();
        return ['code' => 0, 'msg' => '激活失败，请重试'];
    }
}
