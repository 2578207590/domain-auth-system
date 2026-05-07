(function() {
    // 这里改成你自己的 API 地址
    const API = "https://your-domain.com/api.php";

    function getCurrentDomain() {
        let host = location.host.toLowerCase().trim();
        host = host.split(':')[0];
        return host;
    }

    function isDomainMatch(authDomain, host) {
        let domain = authDomain.trim().toLowerCase();
        if (domain === host) return true;
        if (domain.startsWith('*.')) {
            let suffix = domain.substring(2);
            if (host.endsWith('.' + suffix)) return true;
        }
        return false;
    }

    function createAuthBox(status) {
        const host = getCurrentDomain();
        const isExpired = status === 'expired';
        const isBanned = status === 'banned';
        const div = document.createElement("div");
        div.id = "auth-modal";
        div.innerHTML = `<style>
#auth-modal{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999999;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Microsoft YaHei,sans-serif}
.auth-box{background:#fff;border-radius:20px;width:90%;max-width:420px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:fadeIn .3s ease-out}
@keyframes fadeIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.auth-title{font-size:22px;font-weight:700;text-align:center;margin-bottom:8px;color:#111827}
.auth-desc{text-align:center;font-size:14px;color:#64748b;margin-bottom:20px}
.domain-info{background:#f1f5f9;padding:14px;border-radius:12px;text-align:center;margin-bottom:24px;color:#334155;font-size:15px}
.auth-input{width:100%;height:50px;border:1px solid #d1d5db;border-radius:12px;padding:0 16px;margin-bottom:16px;outline:0;font-size:15px}
.auth-btn{width:100%;height:50px;background:#3b82f6;color:#fff;border:none;border-radius:12px;font-weight:600;cursor:pointer;margin-bottom:12px;font-size:16px}
.auth-btn:disabled{opacity:0.6;cursor:not-allowed}
.get-card-btn{width:100%;height:50px;background:#f8fafc;color:#334155;border:1px solid #d1d5db;border-radius:12px;font-weight:500;cursor:pointer;font-size:16px}
.auth-msg{margin-top:12px;text-align:center;font-size:14px;color:#ef4444;min-height:20px}
.auth-msg.success{color:#10b981}
.ban-msg{color:#ef4444;font-weight:600;text-align:center;margin-bottom:20px;padding:12px;background:#fef2f2;border-radius:10px}
</style>
<div class="auth-box">
<div class="auth-title">${isBanned ? '域名已封禁' : isExpired ? '域名已到期' : '域名未授权'}</div>
<div class="auth-desc">${isBanned ? '该域名已被禁止接入授权服务' : isExpired ? '授权已到期，请输入卡密续费' : '请输入卡密激活此域名'}</div>
<div class="domain-info">当前域名：${host}</div>
<div id="banMsg" class="ban-msg" style="display:${isBanned?'block':'none'}">${isBanned?'该域名已被封禁，无法接入授权服务':''}</div>
<input class="auth-input" id="code" placeholder="请输入授权卡密" autocomplete="off" ${isBanned?'disabled':''}>
<button class="auth-btn" id="submit" ${isBanned?'disabled':''}>${isExpired ? '续费激活' : '立即激活'}</button>
<button class="get-card-btn" id="getCard">获取授权卡密</button>
<div class="auth-msg" id="msg"></div>
</div>`;
        document.body.appendChild(div);

        if (isBanned) return;

        document.getElementById("submit").onclick = async function () {
            const code = document.getElementById("code").value.trim();
            const msgEl = document.getElementById("msg");
            if (!code) { msgEl.textContent = "请输入卡密"; return; }
            msgEl.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid #6366f1;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;margin-right:6px;vertical-align:middle"></span>激活中...';
            msgEl.className = "auth-msg";
            const btn = document.getElementById("submit");
            btn.disabled = true;
            btn.textContent = "处理中...";
            try {
                const res = await fetch(`${API}?act=active&domain=${encodeURIComponent(host)}&code=${encodeURIComponent(code)}`);
                const data = await res.json();
                if (data.code === 1) {
                    msgEl.className = "auth-msg success";
                    var expireInfo = '';
                    if (data.expire_time) {
                        expireInfo = '，到期时间：' + data.expire_time.substr(0, 10);
                    } else if (data.expire_time === null) {
                        expireInfo = '，永久有效';
                    }
                    msgEl.textContent = "✅ 激活成功" + expireInfo + "，正在刷新...";
                    setTimeout(() => location.reload(), 1200);
                } else {
                    msgEl.textContent = data.msg || "激活失败";
                    btn.disabled = false;
                    btn.textContent = isExpired ? '续费激活' : '立即激活';
                }
            } catch (e) {
                msgEl.textContent = "网络异常，请重试";
                btn.disabled = false;
                btn.textContent = isExpired ? '续费激活' : '立即激活';
            }
        };;

        document.getElementById("getCard").onclick = function () {
            window.open("https://your-store.com", "_blank");
        };
    }

    async function checkAuth() {
        const host = getCurrentDomain();
        if (host === 'localhost' || host.match(/^127\.0\.0\.1$/) || host.match(/^192\.168\./)) return;

        try {
            const res = await fetch(`${API}?act=check&domain=${encodeURIComponent(host)}`);
            const data = await res.json();

            if (data.code === -1) { createAuthBox('banned'); return; }
            if (data.code === 2) { createAuthBox('expired'); return; }
            if (data.code === 1) return;

            // 二次泛域名兜底
            const listRes = await fetch(`${API}?act=list`);
            const list = await listRes.json();
            let isAuth = false;
            for (let d of list) {
                if (isDomainMatch(d, host)) { isAuth = true; break; }
            }
            if (!isAuth) { createAuthBox('unauthorized'); }
        } catch (e) {
            console.error('授权检查失败:', e);
        }
    }

    if (document.readyState === 'complete') {
        checkAuth();
    } else {
        window.addEventListener('load', checkAuth);
    }
})();
