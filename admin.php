<?php
// Session 安全加固
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
define('IN_SYSTEM', true);
$config_file = 'config.php';
if (!file_exists($config_file)) { header("Location: install.php"); exit; }
require $config_file;
require 'common.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PWD, DB_NAME);
$conn->set_charset('utf8mb4');

// ─── 会话超时（30 分钟无操作自动退出）───────
$sessionTimeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_destroy();
    header("Location: admin.php");
    exit;
}
$_SESSION['last_activity'] = time();

// ─── XSS 安全输出函数 ─────────────────────
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$msg = '';
if (isset($_SESSION['msg'])) { $msg = $_SESSION['msg']; unset($_SESSION['msg']); }
$isLogin = $_SESSION['admin_login'] ?? false;

// ─── 登录 ═══════════════════════════════════
if (!$isLogin) {
    // 读取登录错误（session 传递，避免表单重复提交）
    $loginErr = $_SESSION['login_err'] ?? '';
    unset($_SESSION['login_err']);
    if (isset($_POST['login'])) {
        $ip = getClientIP();
        $failCount = 0;
        if (!$conn->connect_error) {
            $r = $conn->query("SELECT COUNT(*) as c FROM login_log WHERE ip='login:{$ip}' AND status=0 AND create_time > DATE_SUB(NOW(),INTERVAL 15 MINUTE)");
            if ($r) $failCount = (int)$r->fetch_assoc()['c'];
        }
        if ($failCount >= 5) {
            $_SESSION['login_err'] = '<div class="al err">登录尝试过多，请 15 分钟后再试</div>';
        } else {
            $u = trim($_POST['username'] ?? ''); $p = trim($_POST['password'] ?? '');
            $hashPwd = (substr(ADMIN_PWD,0,4)==='$2y$');
            $ok = $hashPwd ? ($u===ADMIN_USER && password_verify($p, ADMIN_PWD)) : ($u===ADMIN_USER && $p===ADMIN_PWD);
            if ($ok) {
                if (!$conn->connect_error) $conn->query("DELETE FROM login_log WHERE ip='login:{$ip}'");
                $_SESSION['admin_login'] = true; $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['last_activity'] = time();
                session_regenerate_id(true); header("Location: admin.php"); exit;
            } else {
                if (!$conn->connect_error) $conn->query("INSERT INTO login_log(ip,status) VALUES('login:{$ip}',0)");
                // 统一提示，不泄露用户名/密码格式信息
                $_SESSION['login_err'] = '<div class="al err">账号或密码错误</div>';
            }
        }
        header("Location: admin.php"); exit;
    }
    ?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>登录</title>
<style>*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,sans-serif}
body{min-height:100vh;background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);display:grid;place-items:center;padding:16px}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(99,102,241,.12),transparent 50%),radial-gradient(ellipse at 70% 30%,rgba(139,92,246,.12),transparent 50%);animation:bg 15s ease-in-out infinite}
@keyframes bg{0%,100%{transform:translate(0,0)}50%{transform:translate(3%,-2%)}}
.card{background:rgba(255,255,255,.97);border-radius:24px;box-shadow:0 25px 80px rgba(0,0,0,.3);width:100%;max-width:400px;padding:40px 32px;position:relative;z-index:1;animation:in .4s ease-out}
@keyframes in{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.ico{width:56px;height:56px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:16px;margin:0 auto 20px;animation:pulse 3s ease-in-out infinite;position:relative}
.ico::after{content:'🛡️';position:absolute;inset:0;display:grid;place-items:center;font-size:28px}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.4)}50%{box-shadow:0 0 0 18px rgba(99,102,241,0)}}
h1{font-size:24px;font-weight:700;text-align:center;color:#111827;margin-bottom:4px}
.sub{text-align:center;color:#64748b;margin-bottom:28px;font-size:14px}
.al{padding:12px 16px;border-radius:12px;margin-bottom:16px;text-align:center;font-size:14px}
.al.err{background:#fef2f2;color:#dc2626;border:1px solid #fca5a5}
.fg{margin-bottom:18px}
.fg label{display:block;font-size:14px;color:#374151;margin-bottom:6px;font-weight:500}
.fg input{width:100%;height:50px;border:2px solid #e2e8f0;border-radius:14px;padding:0 16px;outline:none;font-size:15px;background:#f8fafc;transition:.3s}
.fg input:focus{border-color:#6366f1;box-shadow:0 0 0 4px rgba(99,102,241,.1);background:#fff}
.btn{width:100%;height:50px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:14px;font-size:16px;font-weight:600;cursor:pointer;transition:.3s}
.btn:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(99,102,241,.4)}
</style></head><body><div class="card"><div class="ico"></div><h1>管理后台</h1><div class="sub">授权系统 · 安全登录</div><?=$loginErr?>
<form method="post"><div class="fg"><label>账号</label><input name="username" required></div>
<div class="fg"><label>密码</label><input type="password" name="password" required></div>
<button name="login" class="btn">登 录</button></form></div></body></html><?php
    exit;
}

// ─── 退出 ═══════════════════════════════════
if (isset($_GET['act']) && $_GET['act']==='logout') { session_destroy(); header("Location: admin.php"); exit; }

// ─── CSRF ═══════════════════════════════════
function csrf() { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function ck() { return isset($_POST['t'],$_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$_POST['t']); }

// ─── 域名单个操作 ═══════════════════════════
if (isset($_POST['act'],$_POST['id'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=domain"); exit; }
    $id=(int)$_POST['id'];
    if($_POST['act']==='ban'){ $conn->query("UPDATE auth SET status=0 WHERE id=$id"); addLog($conn,'封禁域名',"ID:{$id}"); }
    if($_POST['act']==='unban'){ $conn->query("UPDATE auth SET status=1 WHERE id=$id"); addLog($conn,'解封域名',"ID:{$id}"); }
    if($_POST['act']==='del'){ $conn->query("DELETE FROM auth WHERE id=$id"); addLog($conn,'删除域名',"ID:{$id}"); }
    $_SESSION['msg']='<div class="al ok">操作成功</div>'; header("Location: admin.php?t=domain"); exit;
}

// ─── 域名续费 ═══════════════════════════════
if (isset($_POST['renew'],$_POST['rid'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=domain"); exit; }
    $rid=(int)$_POST['rid']; $days=(int)$_POST['days'];
    $days=in_array($days,[30,90,365,9999])?$days:365;
    if($days==9999){ $conn->query("UPDATE auth SET expire_time=NULL WHERE id=$rid"); addLog($conn,'永久授权',"ID:{$rid}"); }
    else{ $conn->query("UPDATE auth SET expire_time=DATE_ADD(IFNULL(expire_time,NOW()), INTERVAL {$days} DAY) WHERE id={$rid}"); addLog($conn,'续费',"ID:{$rid} +{$days}天"); }
    $_SESSION['msg']='<div class="al ok">续费成功</div>'; header("Location: admin.php?t=domain"); exit;
}

// ─── 批量域名 ═══════════════════════════════
if (isset($_POST['bd'])&&!empty($_POST['dids'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=domain"); exit; }
    $a=$_POST['ba']??''; $ids=implode(',',array_map('intval',$_POST['dids']));
    if($a==='del'){ $conn->query("DELETE FROM auth WHERE id IN($ids)"); addLog($conn,'批量删域名',$ids); }
    if($a==='ban'){ $conn->query("UPDATE auth SET status=0 WHERE id IN($ids)"); addLog($conn,'批量封禁',$ids); }
    if($a==='unban'){ $conn->query("UPDATE auth SET status=1 WHERE id IN($ids)"); addLog($conn,'批量解封',$ids); }
    $_SESSION['msg']='<div class="al ok">批量操作成功</div>'; header("Location: admin.php?t=domain"); exit;
}

// ─── 添加域名 ═══════════════════════════════
if (isset($_POST['add'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=domain"); exit; }
    $d=trim($_POST['dom']??''); if(empty($d)){ $_SESSION['msg']='<div class="al err">域名不能为空</div>'; header("Location: admin.php?t=domain"); exit; }
    $dy=(int)($_POST['dc']??0) ? (int)$_POST['dc'] : (int)($_POST['dd']??365);
    if($dy>0){ $exp=date('Y-m-d H:i:s',strtotime("+{$dy} days")); $s=$conn->prepare("INSERT IGNORE INTO auth(domain,status,expire_time) VALUES(?,1,?)"); $s->bind_param("ss",$d,$exp); $s->execute(); }
    else{ $conn->query("INSERT IGNORE INTO auth(domain,status,expire_time) VALUES('".$conn->real_escape_string($d)."',1,NULL)"); }
    addLog($conn,'添加域名',"{$d} 天数:{$dy}"); $_SESSION['msg']='<div class="al ok">添加成功</div>'; header("Location: admin.php?t=domain"); exit;
}

// ─── 修改域名（含到期时间）══════════════════
if (isset($_POST['save'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=domain"); exit; }
    $id=(int)$_POST['eid']; $nd=trim($_POST['nd']); $et=$_POST['et']??'keep';
    if($id && $nd){
        $s=$conn->prepare("UPDATE auth SET domain=? WHERE id=?"); $s->bind_param("si",$nd,$id); $s->execute();
        if($et==='now'){ $conn->query("UPDATE auth SET expire_time=NULL WHERE id=$id"); }
        elseif($et==='today'){ $conn->query("UPDATE auth SET expire_time=NOW() WHERE id=$id"); }
        elseif($et==='custom'){
            $cd=(int)($_POST['ec']??365);
            if($cd>0){ $conn->query("UPDATE auth SET expire_time=DATE_ADD(NOW(), INTERVAL {$cd} DAY) WHERE id={$id}"); }
            else{ $conn->query("UPDATE auth SET expire_time=NULL WHERE id=$id"); }
        }
        elseif(in_array($et,['30','90','180','365'])){
            $dy=(int)$et;
            $conn->query("UPDATE auth SET expire_time=DATE_ADD(NOW(), INTERVAL {$dy} DAY) WHERE id={$id}");
        }
        addLog($conn,'修改域名',"ID:{$id} → {$nd} 到期:{$et}"); $_SESSION['msg']='<div class="al ok">修改成功</div>';
    }
    header("Location: admin.php?t=domain"); exit;
}

// ─── 生成卡密 ═══════════════════════════════
if (isset($_POST['gen'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=card"); exit; }
    $n=min((int)$_POST['cn'],100); $px=trim($_POST['px']??'');
    $ed=trim($_POST['cc']??''); $ed=($ed!=='')?$ed:($_POST['ce']??'default');
    if($ed==='default') $ed='default';
    $ev=($ed==='default')?'NULL':(($ed==='0')?'0':(int)$ed);
    $arr=[]; for($i=0;$i<$n;$i++){ $c=generateCardCode($px); $arr[]="('".$conn->real_escape_string($c)."',{$ev})"; }
    if($arr) $conn->query("INSERT IGNORE INTO cards(code,expire_days) VALUES ".implode(',',$arr));
    $lb=$ed==='default'?'默认365天':($ed==='0'?'永久':"{$ed}天");
    addLog($conn,'生成卡密',"数量:{$n} 有效期:{$lb}"); $_SESSION['msg']='<div class="al ok">生成 '.$n.' 个卡密（'.$lb.'）</div>'; header("Location: admin.php?t=card"); exit;
}

// ─── 导入卡密 ═══════════════════════════════
if (isset($_POST['imp'])&&!empty($_POST['cl'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=card"); exit; }
    $ed=trim($_POST['ic']??''); $ed=($ed!=='')?$ed:($_POST['ie']??'default');
    if($ed==='default') $ed='default';
    $ev=($ed==='default')?'NULL':(($ed==='0')?'0':(int)$ed);
    $s=$conn->prepare("INSERT IGNORE INTO cards(code,expire_days) VALUES(?,{$ev})"); $cnt=0;
    foreach(explode("\n",$_POST['cl']) as $l){ $c=trim($l); if($c){ $s->bind_param("s",$c); $s->execute(); $cnt++; } }
    addLog($conn,'导入卡密',"数量:{$cnt}"); $_SESSION['msg']='<div class="al ok">导入 '.$cnt.' 个卡密</div>'; header("Location: admin.php?t=card"); exit;
}

// ─── 批量删卡密 ═════════════════════════════
if (isset($_POST['bc'])&&!empty($_POST['cids'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=card"); exit; }
    $ids=implode(',',array_map('intval',$_POST['cids'])); $conn->query("DELETE FROM cards WHERE id IN($ids)"); addLog($conn,'批量删卡密',$ids);
    $_SESSION['msg']='<div class="al ok">删除成功</div>'; header("Location: admin.php?t=card"); exit;
}

// ─── 批量改有效期 ═══════════════════════════
if (isset($_POST['be'])&&!empty($_POST['cids'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=card"); exit; }
    $ids=implode(',',array_map('intval',$_POST['cids'])); 
    $nd=trim($_POST['sc']??''); $nd=($nd!=='')?$nd:($_POST['se']??'default');
    if($nd==='default') $nd='default';
    $v=($nd==='default')?'NULL':(($nd==='0')?'0':(int)$nd); $conn->query("UPDATE cards SET expire_days={$v} WHERE id IN($ids)"); addLog($conn,'批量改有效期',"{$ids} → {$nd}");
    $_SESSION['msg']='<div class="al ok">批量更新成功</div>'; header("Location: admin.php?t=card"); exit;
}

// ─── 清空已使用 ═════════════════════════════
if (isset($_POST['clr'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=card"); exit; }
    $conn->query("DELETE FROM cards WHERE status=1"); addLog($conn,'清空已使用','');
    $_SESSION['msg']='<div class="al ok">清空成功</div>'; header("Location: admin.php?t=card"); exit;
}

// ─── 清理日志 ═══════════════════════════════
if (isset($_POST['clr_logs'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; $_SESSION['log_msg']='安全校验失败'; header("Location: admin.php?t=logs"); exit; }
    $conn->query("TRUNCATE TABLE logs"); addLog($conn,'清空日志','全部日志已清空');
    $_SESSION['msg']='<div class="al ok">日志已全部清空</div>'; header("Location: admin.php?t=logs"); exit;
}
if (isset($_POST['clr_logs_30d'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=logs"); exit; }
    $affected = $conn->query("DELETE FROM logs WHERE create_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $cnt = $conn->affected_rows;
    addLog($conn,'清理日志',"清理 {$cnt} 条 30 天前日志");
    $_SESSION['msg']="<div class='al ok'>已清理 {$cnt} 条 30 天前的日志</div>"; header("Location: admin.php?t=logs"); exit;
}

// ─── 导出 ═══════════════════════════════════
if (isset($_GET['exp'])) {
    $sc=$_GET['exp']??'all'; $fm=$_GET['fmt']??'code';
    $wh="WHERE 1=1"; if($sc==='unused') $wh.=" AND status=0"; if($sc==='used') $wh.=" AND status=1";
    $fd="code"; if($fm==='full') $fd="code,domain,use_time"; if($fm==='detail') $fd="code,domain,use_time,create_time";
    $r=$conn->query("SELECT {$fd} FROM cards {$wh} ORDER BY id DESC"); $t='';
    while($rw=$r->fetch_assoc()){ if($fm==='code') $t.=$rw['code']."\r\n"; else $t.=implode("\t",$rw)."\r\n"; }
    header("Content-type:text/plain; charset=utf-8"); header("Content-Disposition:attachment;filename=cards_{$sc}_".date("Ymd").".txt"); echo $t; exit;
}

// ─── 修改管理员账号密码 ══════════════════════
if (isset($_POST['acct'])) {
    if(!ck()){ $_SESSION['msg']='<div class="al err">安全校验失败</div>'; header("Location: admin.php?t=settings"); exit; }
    $nu=trim($_POST['nu']??''); $np=trim($_POST['np']??''); $nc=trim($_POST['nc']??'');
    if(empty($nu)||empty($np)){ $_SESSION['msg']='<div class="al err">账号和新密码不能为空</div>'; header("Location: admin.php?t=settings"); exit; }
    if($np!==$nc){ $_SESSION['msg']='<div class="al err">两次输入的新密码不一致</div>'; header("Location: admin.php?t=settings"); exit; }
    $hash=password_hash($np, PASSWORD_BCRYPT);
    $cfg=file_get_contents($config_file);
    $cfg=preg_replace("/define\('ADMIN_USER',\s*'[^']*'\);/","define('ADMIN_USER', '{$nu}');",$cfg);
    $safeHash = addcslashes($hash, '$\\');
    $cfg=preg_replace("/define\('ADMIN_PWD',\s*'[^']*'\);/","define('ADMIN_PWD', '{$safeHash}');",$cfg);
    if(file_put_contents($config_file, $cfg)){
        addLog($conn,'修改账号',"新账号:{$nu}"); $_SESSION['msg']='<div class="al ok">账号密码已更新，新密码已 bcrypt 加密存储</div>';
    }else{ $_SESSION['msg']='<div class="al err">config.php 写入失败，请检查权限</div>'; }
    header("Location: admin.php?t=settings"); exit;
}

// ─── 参数 ═══════════════════════════════════
$tp=$_GET['t']??'card'; $pg=max(1,(int)($_GET['pg']??1));
$lm=(int)($_GET['lm']??15); $lm=in_array($lm,[15,30,50])?$lm:15;

$kw=$_GET['kw']??''; $cs=$_GET['cs']??''; $cex=$_GET['cex']??'';
$cw="WHERE 1=1"; if($kw) $cw.=" AND code LIKE '%".$conn->real_escape_string($kw)."%'"; if($cs!=='') $cw.=" AND status=".(int)$cs;
if($cex==='default') $cw.=" AND expire_days IS NULL"; if($cex==='0') $cw.=" AND expire_days=0"; if($cex==='custom') $cw.=" AND expire_days IS NOT NULL AND expire_days>0";

$dk=$_GET['dk']??''; $ds=$_GET['ds']??'';
$dw="WHERE 1=1"; if($dk) $dw.=" AND domain LIKE '%".$conn->real_escape_string($dk)."%'";
if($ds==='ok') $dw.=" AND status=1 AND (expire_time IS NULL OR expire_time > NOW())";
if($ds==='expired') $dw.=" AND status=1 AND expire_time IS NOT NULL AND expire_time <= NOW()";
if($ds==='banned') $dw.=" AND status=0";

function gp($tb,$wh,$pg,$lm){ global $conn; $tt=$conn->query("SELECT COUNT(*) FROM $tb $wh")->fetch_row()[0]; return [($pg-1)*$lm, ceil($tt/$lm), $tt]; }

$eid=(int)($_GET['edit']??0); $er=$eid?$conn->query("SELECT * FROM auth WHERE id=$eid")->fetch_assoc():null;

// ─── 统计 ═══════════════════════════════════
$sdo=$conn->query("SELECT COUNT(*) FROM auth WHERE status=1 AND (expire_time IS NULL OR expire_time > NOW())")->fetch_row()[0];
$sdx=$conn->query("SELECT COUNT(*) FROM auth WHERE status=1 AND expire_time IS NOT NULL AND expire_time <= NOW()")->fetch_row()[0];
$sdb=$conn->query("SELECT COUNT(*) FROM auth WHERE status=0")->fetch_row()[0];
$sdt=$sdo+$sdx+$sdb;
$sct=$conn->query("SELECT COUNT(*) FROM cards")->fetch_row()[0];
$scu=$conn->query("SELECT COUNT(*) FROM cards WHERE status=0")->fetch_row()[0];
$scd=$conn->query("SELECT COUNT(*) FROM cards WHERE status=1")->fetch_row()[0];
$scr=$sct>0?round($scd/$sct*100,1):0;

$tk=csrf();
?><!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>授权管理系统</title>
<style>
:root{--bg:#0b1120;--bg2:#151d30;--bg3:#1b2540;--tx:#dde4f0;--tx2:#8899b4;--bd:rgba(255,255,255,.06);--ac:#6366f1;--ac2:#8b5cf6;--gn:#10b981;--rd:#ef4444;--am:#f59e0b;--cy:#06b6d4;--r:10px}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--tx);font-family:system-ui,-apple-system,'Segoe UI',sans-serif;font-size:14px;line-height:1.5;min-height:100vh}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse at 30% 20%,rgba(99,102,241,.05),transparent 60%),radial-gradient(ellipse at 70% 80%,rgba(139,92,246,.05),transparent 60%);pointer-events:none;z-index:0}

/* header */
.hd{position:sticky;top:0;z-index:20;background:rgba(11,17,32,.92);border-bottom:1px solid rgba(99,102,241,.15);backdrop-filter:blur(12px);padding:0 20px}
.hd-in{max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;height:56px}
.hd .lo{font-size:20px;font-weight:700;background:linear-gradient(135deg,#818cf8,#c4b5fd);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.hd .out a{color:var(--tx2);background:rgba(255,255,255,.06);padding:7px 14px;border-radius:8px;text-decoration:none;font-size:13px;transition:.2s}
.hd .out a:hover{background:rgba(255,255,255,.12);color:#fff}

/* wrap */
.wp{max-width:1240px;margin:0 auto;padding:24px 20px;position:relative;z-index:1}

/* stats */
.st{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px}
.sti{background:var(--bg2);border-radius:14px;padding:18px 20px;border:1px solid var(--bd);transition:.2s}
.sti:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,0,0,.3)}
.sti .v{font-size:28px;font-weight:800}
.sti .l{font-size:12px;color:var(--tx2);margin-top:2px}
.sti.g .v{color:#34d399}.sti.w .v{color:#fbbf24}.sti.d .v{color:#f87171}.sti.i .v{color:#818cf8}

/* tabs */
.tb-wrap{position:sticky;top:56px;z-index:15;background:var(--bg);padding:12px 20px;border-bottom:1px solid var(--bd)}
.tb{display:flex;gap:6px;max-width:1240px;margin:0 auto}
.tb a{padding:10px 20px;background:var(--bg2);border:1px solid var(--bd);border-radius:12px;text-decoration:none;color:var(--tx2);font-weight:500;font-size:15px;transition:.2s}
.tb a:hover{background:rgba(99,102,241,.1);color:#a5b4fc;border-color:rgba(99,102,241,.3)}
.tb a.on{background:linear-gradient(135deg,var(--ac),var(--ac2));color:#fff;border-color:transparent}

/* card */
.cd{background:var(--bg2);border-radius:16px;border:1px solid var(--bd);padding:24px;margin-bottom:20px}
.cd-t{font-size:16px;font-weight:600;color:#e2e8f0;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--bd)}
.cd-t+.cd-t{margin-top:28px}

/* form grid */
.fg2{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:14px;align-items:end}
.fg2 .fi{display:flex;flex-direction:column}
.fg2 label{font-size:12px;color:var(--tx2);margin-bottom:5px;font-weight:500}
.fg2 input,.fg2 select{height:42px;background:var(--bg3);border:1px solid rgba(255,255,255,.08);border-radius:var(--r);padding:0 12px;color:var(--tx);font-size:14px;outline:none;transition:.2s;width:100%}
.fg2 input:focus,.fg2 select:focus{border-color:var(--ac);box-shadow:0 0 0 3px rgba(99,102,241,.12)}
.fg2 select option{background:var(--bg3);color:var(--tx)}
.fg2 .act{align-self:end}
textarea{width:100%;height:100px;background:var(--bg3);border:1px solid rgba(255,255,255,.08);border-radius:var(--r);padding:12px;color:var(--tx);font-size:14px;outline:none;resize:vertical;margin-top:10px}
textarea:focus{border-color:var(--ac);box-shadow:0 0 0 3px rgba(99,102,241,.12)}

/* btn */
.bt{display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:8px 16px;border-radius:var(--r);border:none;cursor:pointer;font-weight:500;font-size:13px;text-decoration:none;transition:.2s;white-space:nowrap;line-height:1.4}
.bt:hover{transform:translateY(-1px)}
.bt1{background:linear-gradient(135deg,var(--ac),#4f46e5);color:#fff}.bt1:hover{box-shadow:0 4px 16px rgba(99,102,241,.35)}
.bt2{background:linear-gradient(135deg,var(--gn),#059669);color:#fff}.bt2:hover{box-shadow:0 4px 16px rgba(16,185,129,.3)}
.bt3{background:linear-gradient(135deg,var(--am),#d97706);color:#fff}
.bt4{background:linear-gradient(135deg,var(--rd),#dc2626);color:#fff}
.bt5{background:linear-gradient(135deg,var(--cy),#0891b2);color:#fff}
.bt6{background:rgba(255,255,255,.06);color:var(--tx2);border:1px solid var(--bd)}
.bt-sm{padding:4px 10px;font-size:11px;border-radius:7px}

/* table */
.tw{overflow-x:auto}
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th{padding:12px 14px;text-align:left;font-weight:600;color:var(--tx2);background:rgba(11,17,32,.5);border-bottom:1px solid var(--bd);font-size:12px;text-transform:uppercase;letter-spacing:.5px}
.tbl td{padding:10px 14px;border-bottom:1px solid var(--bd);color:#cbd5e1}
.tbl tr:hover td{background:rgba(99,102,241,.04)}
.tbl tr.ex td{background:rgba(239,68,68,.06)}
.tbl tr.nw td{background:rgba(245,158,11,.04)}

/* badge */
.bg{display:inline-block;padding:3px 8px;border-radius:5px;font-size:11px;font-weight:500}
.bg-ok{background:rgba(16,185,129,.15);color:#34d399}
.bg-no{background:rgba(239,68,68,.15);color:#f87171}
.bg-warn{background:rgba(245,158,11,.15);color:#fbbf24}
.bg-inf{background:rgba(99,102,241,.15);color:#a5b4fc}

/* bar */
.br{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
.br select{width:auto;min-width:100px;height:34px;background:var(--bg3);border:1px solid rgba(255,255,255,.08);border-radius:7px;padding:0 10px;color:var(--tx);font-size:13px;outline:none}
.br select:focus{border-color:var(--ac)}
.br select option{background:var(--bg3);color:var(--tx)}
.br .sp{color:var(--tx2);margin:0 2px}

/* pagination */
.pg{display:flex;gap:5px;margin-top:18px;flex-wrap:wrap}
.pg a{padding:7px 12px;background:var(--bg3);border:1px solid var(--bd);border-radius:7px;text-decoration:none;color:var(--tx2);font-size:13px;transition:.2s}
.pg a:hover{background:rgba(99,102,241,.1);color:#a5b4fc}
.pg a.on{background:linear-gradient(135deg,var(--ac),#4f46e5);color:#fff;border-color:transparent}

/* alerts */
.al{padding:12px 16px;border-radius:var(--r);margin-bottom:16px;font-size:13px;animation:sd .3s ease-out}
.al.ok{background:rgba(16,185,129,.1);color:#34d399;border:1px solid rgba(16,185,129,.2)}
.al.err{background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.2)}
@keyframes sd{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* renew select in table */
.rs{width:auto!important;height:auto!important;padding:4px 6px!important;font-size:10px!important;background:rgba(6,182,212,.12)!important;color:#22d3ee!important;border:1px solid rgba(6,182,212,.2)!important;border-radius:6px!important}
.rs option{background:var(--bg3);color:var(--tx);font-size:12px}

/* misc */
.ti{color:var(--tx2);font-size:13px;margin-bottom:4px}
td form{display:inline}
td .bt-sm{margin:0 2px}
select.mini{width:auto;height:32px;min-width:80px;font-size:12px;padding:0 8px}

@media(max-width:768px){
  .fg2{grid-template-columns:1fr}
  .st{grid-template-columns:repeat(2,1fr)}
  .tbl{font-size:12px}.tbl td,.tbl th{padding:8px 10px}
  .br{flex-direction:column;align-items:stretch}.br select{width:100%}
}
</style>
</head>
<body>
<div class="hd"><div class="hd-in"><div class="lo">🛡️ 授权管理系统</div><div class="out"><a href="?act=logout">退出</a></div></div></div>
<div class="tb-wrap"><div class="tb">
  <a href="?t=card" class="<?=$tp=='card'?'on':''?>">🎫 卡密管理</a>
  <a href="?t=domain" class="<?=$tp=='domain'?'on':''?>">🌐 域名管理</a>
  <a href="?t=settings" class="<?=$tp=='settings'?'on':''?>">⚙️ 系统设置</a>
  <a href="?t=logs" class="<?=$tp=='logs'?'on':''?>">📋 操作日志</a>
</div></div>
<div class="wp"><?=$msg?>
<div class="st">
  <div class="sti g"><div class="v"><?=$sdo?></div><div class="l">授权正常</div></div>
  <div class="sti w"><div class="v"><?=$sdx?></div><div class="l">已到期</div></div>
  <div class="sti d"><div class="v"><?=$sdb?></div><div class="l">已封禁</div></div>
  <div class="sti i"><div class="v"><?=$sdt?></div><div class="l">域名总数</div></div>
  <div class="sti g"><div class="v"><?=$scu?></div><div class="l">可用卡密</div></div>
  <div class="sti i"><div class="v"><?=$scr?>%</div><div class="l">使用率</div></div>
</div>

<?php if($tp==='card'): ?>
<div class="cd">
  <div class="cd-t">✨ 生成卡密</div>
  <form method="post" class="fg2">
    <input type="hidden" name="t" value="<?=$tk?>">
    <div class="fi"><label>前缀（可选）</label><input name="px" placeholder="如 VIP"></div>
    <div class="fi"><label>数量</label><input type="number" name="cn" value="10" min="1" max="100" required></div>
    <div class="fi"><label>授权天数</label><select name="ce" onchange="var v=this.value;document.getElementById('ccin').style.display=v==='custom'?'block':'none';if(v!=='custom')document.getElementById('ccin').value=''">
      <option value="default">系统默认 (365天)</option><option value="30">30 天</option><option value="90">90 天</option>
      <option value="180">180 天</option><option value="365">365 天</option><option value="0">🟢 永久</option>
      <option value="custom">自定义...</option>
    </select>
    <input type="number" id="ccin" name="cc" placeholder="输入天数" min="1" style="display:none;margin-top:6px;height:36px;width:100%"></div>
    <div class="fi act"><button name="gen" class="bt bt1">⚡ 生成</button></div>
  </form>

  <div class="cd-t">📥 导入卡密</div>
  <form method="post">
    <input type="hidden" name="t" value="<?=$tk?>">
    <div class="fg2">
      <div class="fi"><label>授权天数</label><select name="ie" onchange="var v=this.value;document.getElementById('icin').style.display=v==='custom'?'block':'none';if(v!=='custom')document.getElementById('icin').value=''">
        <option value="default">系统默认 (365天)</option><option value="30">30天</option><option value="90">90天</option><option value="180">180天</option><option value="365">365天</option><option value="0">🟢 永久</option>
        <option value="custom">自定义...</option>
      </select>
      <input type="number" id="icin" name="ic" placeholder="输入天数" min="1" style="display:none;margin-top:6px;height:36px;width:100%"></div>
    </div>
    <textarea name="cl" placeholder="一行一个卡密"></textarea>
    <div style="margin-top:10px"><button name="imp" class="bt bt2">📦 导入</button></div>
  </form>

  <div class="cd-t">🔍 筛选与导出</div>
  <form method="get" class="fg2">
    <input type="hidden" name="t" value="card">
    <div class="fi"><label>搜索</label><input name="kw" value="<?=htmlspecialchars($kw)?>"></div>
    <div class="fi"><label>状态</label><select name="cs"><option value="">全部</option><option value="0" <?=$cs==='0'?'selected':''?>>未使用</option><option value="1" <?=$cs==='1'?'selected':''?>>已使用</option></select></div>
    <div class="fi"><label>有效期</label><select name="cex"><option value="">全部</option><option value="default" <?=$cex=='default'?'selected':''?>>默认(365天)</option><option value="custom" <?=$cex=='custom'?'selected':''?>>自定义</option><option value="0" <?=$cex=='0'?'selected':''?>>永久</option></select></div>
    <div class="fi"><label>每页</label><select name="lm" onchange="this.form.submit()"><option value="15" <?=$lm==15?'selected':''?>>15</option><option value="30" <?=$lm==30?'selected':''?>>30</option><option value="50" <?=$lm==50?'selected':''?>>50</option></select></div>
    <div class="fi act"><button class="bt bt1">🔍 搜索</button></div>
  </form>

  <div class="br">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center" onsubmit="return syncCheckboxes(this,'cids')">
      <input type="hidden" name="t" value="<?=$tk?>">
      <select name="ba"><option value="del">批量删除</option></select>
      <button name="bc" class="bt bt4 bt-sm" onclick="return confirm('确定删除？')">执行</button>
      <select name="se" onchange="var v=this.value;document.getElementById('scin').style.display=v==='custom'?'inline-block':'none';if(v!=='custom')document.getElementById('scin').value=''"><option value="default">设为默认(365天)</option><option value="30">30天</option><option value="90">90天</option><option value="180">180天</option><option value="365">365天</option><option value="0">永久</option><option value="custom">自定义...</option></select>
      <input type="number" id="scin" name="sc" placeholder="天数" min="1" style="display:none;width:70px;height:34px;background:var(--bg3);border:1px solid rgba(255,255,255,.08);border-radius:7px;padding:0 8px;color:var(--tx);font-size:13px">
      <button name="be" class="bt bt5 bt-sm" onclick="return confirm('确定修改？')">改有效期</button>
      <span class="sp">|</span>
      <a href="?t=card&exp=all&fmt=code" class="bt bt2 bt-sm">导出纯码</a>
      <a href="?t=card&exp=all&fmt=full" class="bt bt5 bt-sm">含域名</a>
      <a href="?t=card&exp=unused&fmt=code" class="bt bt2 bt-sm">未使用</a>
      <a href="?t=card&exp=used&fmt=full" class="bt bt3 bt-sm">已使用</a>
      <span class="sp">|</span>
      <form method="post" style="display:inline"><input type="hidden" name="t" value="<?=$tk?>"><button name="clr" class="bt bt4 bt-sm" onclick="return confirm('确定清空所有已使用卡密？')">清空已使用</button></form>
    </form>
  </div>
  <?php list($of,$pp,$tt)=gp('cards',$cw,$pg,$lm); ?>
  <div class="ti">共 <?=$tt?> 条 &nbsp;|&nbsp; 使用率 <?=$scr?>%</div>
  <form method="post" id="cf">
  <input type="hidden" name="t" value="<?=$tk?>">
  <div class="tw"><table class="tbl">
    <tr><th><input type="checkbox" id="ca"></th><th>卡密</th><th>状态</th><th>有效期</th><th>域名</th><th>使用时间</th></tr>
    <?php $rs=$conn->query("SELECT * FROM cards $cw ORDER BY id DESC LIMIT $of,$lm");
    while($r=$rs->fetch_assoc()):
      $el=''; if(!isset($r['expire_days'])||$r['expire_days']===null) $el='默认(365天)';
      elseif($r['expire_days']==0) $el='🟢 永久'; else $el=$r['expire_days'].'天';
    ?><tr>
      <td><input type="checkbox" name="cids[]" value="<?=$r['id']?>"></td>
      <td style="font-family:monospace"><?=h($r['code'])?></td>
      <td><span class="bg <?=!$r['status']?'bg-ok':'bg-no'?>"><?=!$r['status']?'未使用':'已使用'?></span></td>
      <td><span class="bg bg-inf"><?=$el?></span></td>
      <td><?=$r['domain']?h($r['domain']):'-'?></td>
      <td><?=$r['use_time']?:'-'?></td>
    </tr><?php endwhile; ?>
  </table></div></form>
  <div class="pg"><?php for($i=1;$i<=$pp;$i++): ?>
    <a href="?t=card&kw=<?=urlencode($kw)?>&cs=<?=$cs?>&cex=<?=$cex?>&lm=<?=$lm?>&pg=<?=$i?>" class="<?=$i==$pg?'on':''?>"><?=$i?></a>
  <?php endfor ?></div>
</div>

<?php elseif($tp === 'domain'): ?>

<?php if($er): ?>
<div class="cd">
  <div class="cd-t">✏️ 修改域名（含到期时间）</div>
  <form method="post">
    <input type="hidden" name="t" value="<?=$tk?>">
    <input type="hidden" name="eid" value="<?=$eid?>">
    <div class="fg2">
      <div class="fi"><label>原域名</label><input value="<?=$er['domain']?>" disabled></div>
      <div class="fi"><label>新域名</label><input name="nd" value="<?=$er['domain']?>" required></div>
      <div class="fi"><label>到期时间</label><select name="et" onchange="var v=this.value;document.getElementById('ecustom').style.display=v==='custom'?'block':'none';if(v!=='custom')document.getElementById('ecustom').value=''">
        <option value="keep">保持原到期时间</option>
        <option value="now">🟢 设为永久</option>
        <option value="today">⏰ 立即到期</option>
        <option value="30">重置 +30 天</option>
        <option value="90">重置 +90 天</option>
        <option value="180">重置 +180 天</option>
        <option value="365">重置 +365 天</option>
        <option value="custom">自定义天数...</option>
      </select>
      <input type="number" id="ecustom" name="ec" placeholder="输入天数" min="0" style="display:none;margin-top:6px;height:36px;width:100%"></div>
      <div class="fi act"><button name="save" class="bt bt1">💾 保存</button>
        <a href="?t=domain" class="bt bt6 bt-sm" style="margin-left:8px">取消</a></div>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="cd">
  <div class="cd-t">➕ 手动添加域名（支持泛域名）</div>
  <form method="post" class="fg2">
    <input type="hidden" name="t" value="<?=$tk?>">
    <div class="fi"><label>域名</label><input name="dom" placeholder="abc.com / *.abc.com" required></div>
    <div class="fi"><label>授权天数</label><select name="dd" onchange="var v=this.value;document.getElementById('dcustom').style.display=v==='custom'?'block':'none';if(v!=='custom')document.getElementById('dcustom').value=''">
      <option value="30">30天</option><option value="90">90天</option><option value="180">180天</option><option value="365" selected>365天</option><option value="0">永久</option><option value="custom">自定义...</option>
    </select>
    <input type="number" id="dcustom" name="dc" placeholder="输入天数" min="1" style="display:none;margin-top:6px;height:36px;width:100%"></div>
    <div class="fi act"><button name="add" class="bt bt1">➕ 添加</button></div>
  </form>

  <div class="cd-t">🔍 域名搜索</div>
  <form method="get" class="fg2">
    <input type="hidden" name="t" value="domain">
    <div class="fi"><label>搜索</label><input name="dk" value="<?=htmlspecialchars($dk)?>"></div>
    <div class="fi"><label>状态</label><select name="ds"><option value="">全部</option><option value="ok" <?=$ds=='ok'?'selected':''?>>正常</option><option value="expired" <?=$ds=='expired'?'selected':''?>>已到期</option><option value="banned" <?=$ds=='banned'?'selected':''?>>已封禁</option></select></div>
    <div class="fi"><label>每页</label><select name="lm" onchange="this.form.submit()"><option value="15" <?=$lm==15?'selected':''?>>15</option><option value="30" <?=$lm==30?'selected':''?>>30</option><option value="50" <?=$lm==50?'selected':''?>>50</option></select></div>
    <div class="fi act"><button class="bt bt1">🔍 搜索</button></div>
  </form>

  <div class="br">
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center" onsubmit="return syncCheckboxes(this,'dids')">
      <input type="hidden" name="t" value="<?=$tk?>">
      <select name="ba"><option value="del">批量删除</option><option value="ban">封禁</option><option value="unban">解封</option></select>
      <button name="bd" class="bt bt3 bt-sm" onclick="return confirm('确定执行？')">执行</button>
    </form>
  </div>
  <?php list($od,$ppd,$ttd)=gp('auth',$dw,$pg,$lm); ?>
  <div class="ti">共 <?=$ttd?> 条</div>
  <form method="post" id="df">
  <input type="hidden" name="t" value="<?=$tk?>">
  <div class="tw"><table class="tbl">
    <tr><th><input type="checkbox" id="da"></th><th>ID</th><th>域名</th><th>状态</th><th>到期时间</th><th>操作</th></tr>
    <?php $rd=$conn->query("SELECT * FROM auth $dw ORDER BY id DESC LIMIT $od,$lm");
    while($d=$rd->fetch_assoc()):
      $hasExpire = !empty($d['expire_time']) && $d['expire_time'] !== '0000-00-00 00:00:00';
      $isExpired = $d['status']==1 && $hasExpire && strtotime($d['expire_time']) < time();
      $isNearExpiry = $d['status']==1 && $hasExpire && !$isExpired && strtotime($d['expire_time']) < strtotime('+30 days');
    ?><tr class="<?=$isExpired?'ex':($isNearExpiry?'nw':'')?>">
      <td><input type="checkbox" name="dids[]" value="<?=$d['id']?>"></td>
      <td><?=$d['id']?></td>
      <td style="font-weight:500"><?=h($d['domain'])?></td>
      <td><?php if($d['status']==0):?><span class="bg bg-no">🚫 封禁</span>
        <?php elseif($isExpired):?><span class="bg bg-no">⏰ 已到期</span>
        <?php elseif($isNearExpiry):?><span class="bg bg-warn">⚠️ 即将到期</span>
        <?php else:?><span class="bg bg-ok">✅ 正常</span><?php endif?></td>
      <td><?php if($d['status']==0):?><span style="color:#475569">-</span>
        <?php elseif(!$hasExpire):?><span class="bg bg-inf">🟢 永久</span>
        <?php else:?><span style="font-size:12px;<?=$isExpired?'color:#f87171;font-weight:600':($isNearExpiry?'color:#fbbf24':'');?>"><?=date('Y-m-d',strtotime($d['expire_time']))?></span><?php endif?></td>
      <td>
        <a href="?t=domain&edit=<?=$d['id']?>" class="bt bt1 bt-sm">修改</a>
        <?php if($d['status']==1):?>
        <select class="rs" onchange="var v=this.value;if(v){document.getElementById('rf<?=$d['id']?>').querySelector('[name=days]').value=v;if(confirm('确定续费？'))document.getElementById('rf<?=$d['id']?>').submit();this.value=''}">
          <option value="">续费</option><option value="30">+30天</option><option value="90">+90天</option><option value="365">+365天</option><option value="9999">永久</option>
        </select>
        <form method="post" id="rf<?=$d['id']?>" style="display:none"><input type="hidden" name="t" value="<?=$tk?>"><input type="hidden" name="renew" value="1"><input type="hidden" name="rid" value="<?=$d['id']?>"><input type="hidden" name="days"></form>
        <form method="post" style="display:inline"><input type="hidden" name="t" value="<?=$tk?>"><input type="hidden" name="act" value="ban"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="bt bt3 bt-sm" onclick="return confirm('封禁？')">封禁</button></form>
        <?php else:?>
        <form method="post" style="display:inline"><input type="hidden" name="t" value="<?=$tk?>"><input type="hidden" name="act" value="unban"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="bt bt2 bt-sm" onclick="return confirm('解封？')">解封</button></form>
        <?php endif?>
        <form method="post" style="display:inline"><input type="hidden" name="t" value="<?=$tk?>"><input type="hidden" name="act" value="del"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="bt bt4 bt-sm" onclick="return confirm('删除？')">删除</button></form>
      </td>
    </tr><?php endwhile; ?>
  </table></div></form>
  <div class="pg"><?php for($i=1;$i<=$ppd;$i++): ?>
    <a href="?t=domain&dk=<?=urlencode($dk)?>&ds=<?=$ds?>&lm=<?=$lm?>&pg=<?=$i?>" class="<?=$i==$pg?'on':''?>"><?=$i?></a>
  <?php endfor ?></div>
</div>
<?php endif ?>

<?php if($tp==='settings'): ?>
<div class="cd">
  <div class="cd-t">🔑 修改管理员账号密码</div>
  <form method="post">
    <input type="hidden" name="t" value="<?=$tk?>">
    <div class="fg2">
      <div class="fi"><label>当前账号</label><input value="<?=htmlspecialchars(ADMIN_USER)?>" disabled></div>
      <div class="fi"><label>新账号</label><input name="nu" value="<?=htmlspecialchars(ADMIN_USER)?>" required></div>
    </div>
    <div class="fg2" style="margin-top:14px">
      <div class="fi"><label>新密码</label><input type="password" name="np" placeholder="输入新密码" required></div>
      <div class="fi"><label>确认密码</label><input type="password" name="nc" placeholder="再次输入新密码" required></div>
      <div class="fi act"><button name="acct" class="bt bt1">💾 保存修改</button></div>
    </div>
  </form>
  <div style="margin-top:16px;padding:12px 16px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.2);border-radius:10px;font-size:13px;color:#fbbf24">
    ⚠️ 密码使用 bcrypt 加密存储，保存后旧密码将失效。请妥善保管新密码。
  </div>
</div>
<?php endif ?>

<?php if($tp==='logs'): ?>
<?php $lkw=$_GET['lk']??''; $lwh="WHERE 1=1"; if($lkw) $lwh.=" AND CONCAT(action,content,ip) LIKE '%".$conn->real_escape_string($lkw)."%'";
list($lo,$lp,$lt)=gp('logs',$lwh,$pg,30);
$lr=$conn->query("SELECT * FROM logs {$lwh} ORDER BY id DESC LIMIT {$lo},30"); ?>
<div class="cd">
  <div class="cd-t">📋 操作日志</div>
  <form method="get" class="fg2">
    <input type="hidden" name="t" value="logs">
    <div class="fi"><label>搜索</label><input name="lk" value="<?=h($lkw)?>"></div>
    <div class="fi"><label>每页</label><select name="lm" onchange="this.form.submit()"><option value="15" <?=$lm==15?'selected':''?>>15</option><option value="30" <?=$lm==30?'selected':''?>>30</option><option value="50" <?=$lm==50?'selected':''?>>50</option></select></div>
    <div class="fi act"><button class="bt bt1">🔍 搜索</button></div>
  </form>
  <div class="ti">共 <?=$lt?> 条记录</div>
  <form method="post" style="margin-bottom:12px">
    <input type="hidden" name="t" value="<?=$tk?>">
    <button name="clr_logs" class="bt bt3 bt-sm" onclick="return confirm('确定清空所有日志？此操作不可恢复！')">🗑️ 清空全部日志</button>
    <button name="clr_logs_30d" class="bt bt4 bt-sm" onclick="return confirm('确定删除 30 天前的日志？')">🗑️ 清理 30 天前日志</button>
  </form>
  <div class="tw"><table class="tbl">
    <tr><th>ID</th><th>操作</th><th>内容</th><th>IP</th><th>时间</th></tr>
    <?php while($l=$lr->fetch_assoc()): ?>
    <tr><td><?=$l['id']?></td><td><span class="bg bg-inf"><?=h($l['action'])?></span></td><td style="max-width:300px;word-break:break-all"><?=h($l['content'])?></td><td style="font-family:monospace;font-size:12px"><?=h($l['ip'])?></td><td style="white-space:nowrap;font-size:12px;color:var(--tx2)"><?=$l['create_time']?></td></tr>
    <?php endwhile; ?>
  </table></div>
  <div class="pg"><?php for($i=1;$i<=$lp;$i++): ?>
    <a href="?t=logs&lk=<?=urlencode($lkw)?>&pg=<?=$i?>" class="<?=$i==$pg?'on':''?>"><?=$i?></a>
  <?php endfor ?></div>
</div>
<?php endif ?>

</div>
<script>
// 全选
document.getElementById('ca')?.addEventListener('click',function(e){document.querySelectorAll('#cf [name="cids[]"]').forEach(i=>i.checked=e.target.checked)})
document.getElementById('da')?.addEventListener('click',function(e){document.querySelectorAll('#df [name="dids[]"]').forEach(i=>i.checked=e.target.checked)})

// 批量操作时从表格 form 同步勾选值到批量 form
function syncCheckboxes(batchForm, name) {
  var existing = batchForm.querySelectorAll('input[type=hidden][data-synced]');
  existing.forEach(function(el){ el.remove() });
  var tableForm = (name==='cids') ? document.getElementById('cf') : document.getElementById('df');
  if(!tableForm) return true;
  var checked = tableForm.querySelectorAll('input[name="'+name+'[]"]:checked');
  if(checked.length===0){ alert('请先勾选要操作的项'); return false; }
  checked.forEach(function(cb){
    var h = document.createElement('input');
    h.type='hidden'; h.name=name+'[]'; h.value=cb.value;
    h.setAttribute('data-synced','1');
    batchForm.appendChild(h);
  });
  return true;
}
</script>
</body></html>
<?php $conn->close();