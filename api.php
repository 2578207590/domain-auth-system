<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

header('Content-Type: application/json; charset=utf-8');

if (!file_exists('config.php')) {
    echo json_encode(['code' => 0, 'msg' => '系统未安装']);
    exit;
}

require 'config.php';
require 'common.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PWD, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['code' => 0, 'msg' => '数据库连接失败']);
    exit;
}
$conn->set_charset('utf8mb4');

$act = isset($_GET['act']) ? $_GET['act'] : '';
$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';
$code = isset($_GET['code']) ? trim($_GET['code']) : '';

// 1. 获取授权域名列表（供前端泛域名校验 + 频率限制）
if ($act === 'list') {
    if (!checkRateLimit($conn, 'api_list', 30, 60)) {
        echo json_encode(['code' => 0, 'msg' => '请求过于频繁，请稍候']);
        exit;
    }
    $res = $conn->query("SELECT domain FROM auth WHERE status=1 AND (expire_time IS NULL OR expire_time > NOW())");
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = $row['domain'];
    }
    echo json_encode($list);
    exit;
}

// 2. 检查当前域名授权状态（含到期 + 频率限制）
if ($act === 'check') {
    if (empty($domain) || !isValidAddress($domain)) {
        echo json_encode(['code' => 0]);
        exit;
    }

    // 频率限制：同一 IP 每 60 秒最多 60 次 check
    if (!checkRateLimit($conn, 'api_check', 60, 60)) {
        echo json_encode(['code' => 0, 'msg' => '请求过于频繁，请稍候']);
        exit;
    }

    $current = cleanDomain($domain);
    $result = checkDomainAuth($conn, $current);
    addLog($conn, 'API查询', "域名:{$current} 状态:{$result['code']}", true);
    echo json_encode($result);
    exit;
}

// 3. 卡密激活域名（含频率限制）
if ($act === 'active') {
    if (empty($domain) || empty($code)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    if (!isValidAddress($domain)) {
        echo json_encode(['code' => 0, 'msg' => '域名格式不正确']);
        exit;
    }

    if (!checkRateLimit($conn, 'api_active', 10, 300)) {
        echo json_encode(['code' => 0, 'msg' => '激活过于频繁，请 5 分钟后再试']);
        exit;
    }

    $current = cleanDomain($domain);
    $result = activateDomain($conn, $current, $code);
    echo json_encode($result);
    exit;
}

// 非法请求
echo json_encode(['code' => 0, 'msg' => '非法请求']);
$conn->close();
