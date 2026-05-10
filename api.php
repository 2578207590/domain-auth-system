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

// ─── 校验请求来源域名是否合法 ──────────────
function getRequestOrigin() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($origin)) return '';
    $parsed = parse_url($origin);
    $host = strtolower(trim($parsed['host'] ?? ''));
    // 本地开发放行
    if (in_array($host, ['localhost', '127.0.0.1', '::1'])) return 'localhost';
    return $host;
}

function checkRequestOrigin($expectedDomain) {
    $host = getRequestOrigin();
    if (empty($host)) return false;
    if ($host === 'localhost') return true;
    // 验证请求来源是授权域名本身或使用现有 isDomainMatch 匹配
    return isDomainMatch($expectedDomain, $host);
}

// 1. 获取授权域名列表
if ($act === 'list') {
    if (!checkRateLimit($conn, 'api_list', 30, 60)) {
        echo json_encode(['code' => 0, 'msg' => '请求过于频繁，请稍候']);
        exit;
    }
    // 必须有合法的请求来源
    if (empty(getRequestOrigin())) {
        echo json_encode([]);
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

// 2. 检查当前域名授权状态
if ($act === 'check') {
    if (empty($domain) || !isValidAddress($domain)) {
        echo json_encode(['code' => 0]);
        exit;
    }
    // 来源校验：请求必须来自目标域名
    if (!checkRequestOrigin($domain)) {
        echo json_encode(['code' => 0]);
        exit;
    }

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

// 3. 卡密激活域名
if ($act === 'active') {
    if (empty($domain) || empty($code)) {
        echo json_encode(['code' => 0, 'msg' => '参数不完整']);
        exit;
    }
    if (!isValidAddress($domain)) {
        echo json_encode(['code' => 0, 'msg' => '域名格式不正确']);
        exit;
    }
    // 来源校验：激活请求必须来自目标域名
    if (!checkRequestOrigin($domain)) {
        echo json_encode(['code' => 0, 'msg' => '来源域名不匹配']);
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
