(function() {
    // Configuration
    const API = "https://your-domain.com/api.php";
    const CACHE_PREFIX = 'auth_cache_';

    var _unauthorized = false;
    var _pollingStarted = false;

    // ─── Anti-DevTools ───────────────────────
    function antiDevTools() {
        document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) ||
                (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
                return false;
            }
        });
    }

    // ─── Offline Cache ──────────────────────
    function setOfflineCache(host, data) {
        try {
            localStorage.setItem(CACHE_PREFIX + host, JSON.stringify({
                code: data.code,
                expire_time: data.expire_time || null,
                cached_at: Date.now()
            }));
        } catch(e) {}
    }

    function getOfflineCache(host) {
        try {
            var raw = localStorage.getItem(CACHE_PREFIX + host);
            return raw ? JSON.parse(raw) : null;
        } catch(e) { return null; }
    }

    function checkOfflineAuth(host) {
        var cached = getOfflineCache(host);
        if (!cached) return null;
        if (cached.code === 1 && cached.expire_time) {
            return Date.parse(cached.expire_time) > Date.now()
                ? { authorized: true, expire_time: cached.expire_time }
                : { authorized: false, expired: true };
        }
        if (cached.code === 1 && !cached.expire_time) {
            return { authorized: true, expire_time: null };
        }
        if (cached.code === 2) {
            return { authorized: false, expired: true };
        }
        return null;
    }

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
#auth-modal{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999999;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
#auth-modal ~ *{pointer-events:none!important;user-select:none!important}
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
@keyframes spin{to{transform:rotate(360deg)}}
</style>
<div class="auth-box">
<div class="auth-title">${isBanned ? 'Domain Banned' : isExpired ? 'License Expired' : 'Domain Unauthorized'}</div>
<div class="auth-desc">${isBanned ? 'This domain has been banned.' : isExpired ? 'License expired, enter key to renew.' : 'Enter license key to activate this domain.'}</div>
<div class="domain-info">Current Domain: ${host}</div>
<div id="banMsg" class="ban-msg" style="display:${isBanned?'block':'none'}">${isBanned?'This domain has been banned and cannot access the authorization service.':''}</div>
<input class="auth-input" id="code" placeholder="Enter license key" autocomplete="off" ${isBanned?'disabled':''}>
<button class="auth-btn" id="submit" ${isBanned?'disabled':''}>${isExpired ? 'Renew License' : 'Activate Now'}</button>
<button class="get-card-btn" id="getCard">Get License Key</button>
<div class="auth-msg" id="msg"></div>
</div>`;
        document.body.appendChild(div);
        startPolling();

        if (isBanned) return;

        document.getElementById("submit").onclick = async function () {
            const code = document.getElementById("code").value.trim();
            const msgEl = document.getElementById("msg");
            if (!code) { msgEl.textContent = "Please enter a license key"; return; }
            msgEl.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid #6366f1;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;margin-right:6px;vertical-align:middle"></span>Activating...';
            msgEl.className = "auth-msg";
            const btn = document.getElementById("submit");
            btn.disabled = true;
            btn.textContent = "Processing...";
            try {
                const res = await fetch(`${API}?act=active&domain=${encodeURIComponent(host)}&code=${encodeURIComponent(code)}`);
                const data = await res.json();
                if (data.code === 1) {
                    msgEl.className = "auth-msg success";
                    var expireInfo = '';
                    if (data.expire_time) {
                        expireInfo = ', expires: ' + data.expire_time.substr(0, 10);
                    } else if (data.expire_time === null) {
                        expireInfo = ', permanent';
                    }
                    msgEl.textContent = "✅ Activated" + expireInfo + ", refreshing...";
                    setTimeout(() => location.reload(), 1200);
                } else {
                    msgEl.textContent = data.msg || "Activation failed";
                    btn.disabled = false;
                    btn.textContent = isExpired ? 'Renew License' : 'Activate Now';
                }
            } catch (e) {
                msgEl.textContent = "Network error, please try again";
                btn.disabled = false;
                btn.textContent = isExpired ? 'Renew License' : 'Activate Now';
            }
        };

        document.getElementById("getCard").onclick = function () {
            window.open("https://your-store.com", "_blank");
        };
    }

    async function checkAuth() {
        const host = getCurrentDomain();
        if (host === 'localhost' || host.match(/^127\.0\.0\.1$/) || host.match(/^192\.168\./)) return;

        try {
            const controller = new AbortController();
            const timeout = setTimeout(function() { controller.abort(); }, 5000);
            const res = await fetch(API + '?act=check&domain=' + encodeURIComponent(host) + '&_=' + Date.now(), { signal: controller.signal });
            clearTimeout(timeout);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            setOfflineCache(host, data);

            if (data.code === -1) { createAuthBox('banned'); return; }
            if (data.code === 2) { createAuthBox('expired'); return; }
            if (data.code === 1) return;

            try {
                var listRes = await fetch(API + '?act=list&_=' + Date.now());
                if (listRes.ok) {
                    var list = await listRes.json();
                    for (var i = 0; i < list.length; i++) {
                        if (isDomainMatch(list[i], host)) { return; }
                    }
                }
            } catch(e) {}
            _unauthorized = true;
            createAuthBox('unauthorized');
        } catch (e) {
            var offline = checkOfflineAuth(host);
            if (offline) {
                if (offline.authorized) return;
                _unauthorized = true;
                createAuthBox('expired');
                return;
            }
        }
    }

    // ─── Modal Anti-Removal Polling (3s, only when unauthorized) ─
    function startPolling() {
        if (_pollingStarted) return;
        _pollingStarted = true;
        setInterval(function() {
            if (!_unauthorized) return;
            var modal = document.getElementById('auth-modal');
            if (!modal || !document.body.contains(modal)) {
                createAuthBox('unauthorized');
            }
        }, 3000);
    }

    // ─── Init ──────────────────────────────
    antiDevTools();

    if (document.readyState === 'complete') {
        checkAuth();
    } else {
        window.addEventListener('load', checkAuth);
    }
})();
