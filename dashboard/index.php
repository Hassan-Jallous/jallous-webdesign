<?php
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /dashboard/');
    exit;
}

$isAuth = isset($_SESSION['dashboard_auth']) && $_SESSION['dashboard_auth'];

if (!$isAuth) {
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — JALLOUS Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:#0a0a0a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:48px 40px;width:100%;max-width:400px;text-align:center}
.login-logo{font-size:24px;font-weight:700;letter-spacing:6px;margin-bottom:40px;color:#fff}
.login-card input{width:100%;padding:14px 18px;background:#0a0a0a;border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:15px;outline:none;transition:border-color .2s;margin-bottom:12px}
.login-card input:focus{border-color:rgba(255,255,255,0.3)}
.login-card input::placeholder{color:rgba(255,255,255,0.3)}
.login-card .login-btn{width:100%;padding:14px;margin-top:4px;background:#fff;color:#0a0a0a;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;cursor:pointer;transition:opacity .2s}
.login-card .login-btn:hover{opacity:.85}
.login-card .login-btn:disabled{opacity:.5;cursor:not-allowed}
.login-switch{margin-top:20px;font-size:13px;color:rgba(255,255,255,0.35)}
.login-switch a{color:rgba(255,255,255,0.55);text-decoration:none;cursor:pointer;transition:color .2s}
.login-switch a:hover{color:#fff}
.login-msg{margin-top:14px;font-size:13px;line-height:1.5;min-height:20px}
.login-msg.error{color:#ef4444}
.login-msg.success{color:#22c55e}
.login-hidden{display:none}
</style>
</head>
<body>
<div class="login-card">
    <div class="login-logo">JALLOUS</div>

    <!-- Password Login Mode -->
    <div id="mode-password">
        <form id="form-password" method="POST" action="/api/auth.php">
            <input type="hidden" name="action" value="login">
            <input type="email" id="pw-email" name="email" placeholder="E-Mail" autofocus required>
            <div class="pw-wrap"><input type="password" id="pw-password" name="password" placeholder="Passwort" required><button type="button" class="pw-toggle" onclick="togglePw(this)"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
            <button type="submit" class="login-btn" id="pw-submit">Login</button>
        </form>
        <div class="login-switch"><a onclick="switchMode('otp')">Login ohne Passwort</a></div>
    </div>

    <!-- OTP Login Mode -->
    <div id="mode-otp" class="login-hidden">
        <form id="form-otp" method="POST" action="/api/auth.php" onsubmit="return false;">
            <input type="email" id="otp-email" placeholder="E-Mail" required>
            <div id="otp-request-wrap">
                <button type="button" class="login-btn" id="otp-request-btn" onclick="requestOTP()">Code anfordern</button>
            </div>
            <div id="otp-verify-wrap" class="login-hidden">
                <input type="text" id="otp-code" placeholder="6-stelliger Code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required>
                <button type="button" class="login-btn" id="otp-verify-btn" onclick="verifyOTP()">Einloggen</button>
            </div>
        </form>
        <div class="login-switch"><a onclick="switchMode('password')">Mit Passwort einloggen</a></div>
    </div>

    <div id="login-msg" class="login-msg"></div>
</div>

<script>
(function() {
    var loginMode = 'password';
    var otpRequested = false;

    window.switchMode = function(mode) {
        loginMode = mode;
        document.getElementById('mode-password').className = mode === 'password' ? '' : 'login-hidden';
        document.getElementById('mode-otp').className = mode === 'otp' ? '' : 'login-hidden';
        clearMsg();
        if (mode === 'otp') {
            document.getElementById('otp-email').focus();
        } else {
            document.getElementById('pw-email').focus();
        }
    };

    function showError(msg) {
        var el = document.getElementById('login-msg');
        el.className = 'login-msg error';
        el.textContent = msg;
    }

    function showSuccess(msg) {
        var el = document.getElementById('login-msg');
        el.className = 'login-msg success';
        el.textContent = msg;
    }

    function clearMsg() {
        var el = document.getElementById('login-msg');
        el.className = 'login-msg';
        el.textContent = '';
    }

    // Intercept password form submit with JS
    document.getElementById('form-password').addEventListener('submit', function(e) {
        e.preventDefault();
        var email = document.getElementById('pw-email').value.trim();
        var password = document.getElementById('pw-password').value;
        if (!email || !password) { showError('Bitte alle Felder ausfüllen.'); return; }
        var btn = document.getElementById('pw-submit');
        btn.disabled = true;
        btn.textContent = 'Wird geprüft...';
        clearMsg();
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', email: email, password: password })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) { location.reload(); }
            else { showError(d.error || 'Login fehlgeschlagen.'); btn.disabled = false; btn.textContent = 'Login'; }
        }).catch(function() {
            showError('Verbindungsfehler.');
            btn.disabled = false;
            btn.textContent = 'Login';
        });
    });

    window.requestOTP = function() {
        var email = document.getElementById('otp-email').value.trim();
        if (!email) { showError('Bitte E-Mail eingeben.'); return; }
        var btn = document.getElementById('otp-request-btn');
        btn.disabled = true;
        btn.textContent = 'Wird gesendet...';
        clearMsg();
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'request_otp', email: email })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) {
                otpRequested = true;
                document.getElementById('otp-verify-wrap').className = '';
                document.getElementById('otp-code').focus();
                showSuccess('Code wurde an deine E-Mail gesendet.');
                btn.textContent = 'Erneut senden';
                btn.disabled = false;
            } else {
                showError(d.error || 'Fehler beim Senden.');
                btn.disabled = false;
                btn.textContent = 'Code anfordern';
            }
        }).catch(function() {
            showError('Verbindungsfehler.');
            btn.disabled = false;
            btn.textContent = 'Code anfordern';
        });
    };

    window.verifyOTP = function() {
        var email = document.getElementById('otp-email').value.trim();
        var otp = document.getElementById('otp-code').value.trim();
        if (!email || !otp) { showError('Bitte E-Mail und Code eingeben.'); return; }
        var btn = document.getElementById('otp-verify-btn');
        btn.disabled = true;
        btn.textContent = 'Wird geprüft...';
        clearMsg();
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'verify_otp', email: email, otp: otp })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) { location.reload(); }
            else { showError(d.error || 'Ungültiger Code.'); btn.disabled = false; btn.textContent = 'Einloggen'; }
        }).catch(function() {
            showError('Verbindungsfehler.');
            btn.disabled = false;
            btn.textContent = 'Einloggen';
        });
    };
})();
</script>
</body>
</html>
    <?php
    exit;
}
$currentEmail = $_SESSION['dashboard_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — JALLOUS</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:#0a0a0a;color:#fff;min-height:100vh}

/* Top Bar */
.topbar{display:flex;align-items:center;justify-content:space-between;padding:20px 32px;border-bottom:1px solid rgba(255,255,255,0.06)}
.topbar-left{display:flex;align-items:center;gap:32px}
.logo{font-size:20px;font-weight:700;letter-spacing:6px}
.range-btns{display:flex;gap:6px}
.range-btns a{padding:8px 16px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.08);color:rgba(255,255,255,0.5);text-decoration:none;font-size:13px;font-weight:500;transition:all .2s}
.range-btns a:hover{border-color:rgba(255,255,255,0.2);color:#fff}
.range-btns a.active{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.15);color:#fff}
.topbar-right a{color:rgba(255,255,255,0.4);text-decoration:none;font-size:13px;transition:color .2s}
.topbar-right a:hover{color:#fff}

/* Main Container */
.container{max-width:100%;margin:0 auto;padding:24px 32px}

/* Section Titles */
.section-title{font-size:13px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,0.35);margin-bottom:16px}

/* KPI Cards */
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:32px}
.kpi-card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px}
.kpi-value{font-size:36px;font-weight:700;line-height:1;margin-bottom:6px}
.kpi-label{font-size:13px;color:rgba(255,255,255,0.4);margin-bottom:10px}
.kpi-change{font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px}
.kpi-change.up{color:#22c55e;background:rgba(34,197,94,0.1)}
.kpi-change.down{color:#ef4444;background:rgba(239,68,68,0.1)}
.kpi-change.neutral{color:rgba(255,255,255,0.4);background:rgba(255,255,255,0.05)}
.kpi-header{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.kpi-info{position:relative;display:inline-flex}
.kpi-info-icon{width:16px;height:16px;border-radius:50%;border:1px solid rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:10px;color:rgba(255,255,255,0.3);cursor:help;flex-shrink:0}
.kpi-info-icon:hover{border-color:rgba(255,255,255,0.4);color:rgba(255,255,255,0.6)}
.kpi-tooltip{display:none;position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);background:#222;color:rgba(255,255,255,0.85);font-size:12px;font-weight:400;line-height:1.5;padding:10px 14px;border-radius:8px;width:220px;box-shadow:0 8px 24px rgba(0,0,0,0.4);z-index:100;pointer-events:none}
.kpi-tooltip::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:#222}
.kpi-info:hover .kpi-tooltip{display:block}

/* Charts Row */
.charts-row{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:32px}
.chart-card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px}
.chart-card h3{font-size:15px;font-weight:600;margin-bottom:20px}

/* SVG Chart */
.chart-wrapper{position:relative;width:100%;overflow-x:auto}
.chart-wrapper svg{display:block;width:100%;height:auto}
.chart-wrapper svg text{font-family:'DM Sans',sans-serif}

/* Funnel */
.funnel-row{display:flex;flex-direction:column;gap:10px}
.funnel-item{display:flex;align-items:center;gap:12px;font-size:13px}
.funnel-item .funnel-label{min-width:90px;color:rgba(255,255,255,0.5);text-align:right;flex-shrink:0}
.funnel-item .funnel-bar-bg{flex:1;height:28px;background:rgba(255,255,255,0.04);border-radius:6px;overflow:hidden;position:relative}
.funnel-item .funnel-bar{height:100%;background:rgba(255,255,255,0.12);border-radius:6px;transition:width .6s ease;display:flex;align-items:center;padding-left:10px;font-size:12px;font-weight:600;color:rgba(255,255,255,0.7);white-space:nowrap}

/* Details Row */
.details-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:32px}
.detail-card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px}
.detail-card h3{font-size:15px;font-weight:600;margin-bottom:16px}
.detail-table{width:100%;border-collapse:collapse}
.detail-table th{text-align:left;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:0 0 10px}
.detail-table th:last-child{text-align:right}
.detail-table td{padding:8px 0;font-size:13px;color:rgba(255,255,255,0.7);border-top:1px solid rgba(255,255,255,0.04)}
.detail-table td:last-child{text-align:right}

/* Scroll Depth */
.scroll-bars{display:flex;flex-direction:column;gap:10px}
.scroll-bar-item{display:flex;align-items:center;gap:10px;font-size:13px}
.scroll-bar-item .sb-label{min-width:40px;text-align:right;color:rgba(255,255,255,0.5)}
.scroll-bar-item .sb-track{flex:1;height:24px;background:rgba(255,255,255,0.04);border-radius:6px;overflow:hidden}
.scroll-bar-item .sb-fill{height:100%;background:rgba(255,255,255,0.1);border-radius:6px;transition:width .6s ease}
.scroll-bar-item .sb-val{min-width:36px;font-size:12px;font-weight:600;color:rgba(255,255,255,0.5)}

/* Recent Leads */
.leads-card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:24px;margin-bottom:32px}
.leads-card h3{font-size:15px;font-weight:600;margin-bottom:16px}

/* Skeleton Loading */
.skeleton{position:relative;overflow:hidden;background:rgba(255,255,255,0.04)!important;border-radius:8px;color:transparent!important}
.skeleton *{visibility:hidden}
.skeleton::after{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.03),transparent);animation:shimmer 1.5s infinite}
@keyframes shimmer{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}

/* Error */
.error-msg{text-align:center;padding:60px 20px;color:rgba(255,255,255,0.3);font-size:15px}

/* Chart Legend */
.chart-legend{display:flex;gap:20px;margin-bottom:16px}
.chart-legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,0.5)}
.chart-legend-dot{width:8px;height:8px;border-radius:50%}

/* Responsive */
@media(max-width:1024px){
    .kpi-row{grid-template-columns:repeat(2,1fr)}
    .charts-row{grid-template-columns:1fr}
    .details-row{grid-template-columns:1fr}
}
@media(max-width:640px){
    .topbar{flex-direction:column;gap:16px;align-items:flex-start;padding:16px 20px}
    .topbar-left{flex-direction:column;gap:12px;width:100%}
    .range-btns{width:100%;justify-content:space-between}
    .topbar-right{align-self:flex-end}
    .container{padding:20px}
    .kpi-row{grid-template-columns:1fr}
    .kpi-value{font-size:28px}
}

/* Tab Bar */
.tab-bar{display:flex;gap:4px;margin-bottom:24px;background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:4px;width:fit-content}
.tab-btn{padding:10px 24px;border:none;background:none;color:rgba(255,255,255,0.4);font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;border-radius:8px;cursor:pointer;transition:all .2s}
.tab-btn.active{background:rgba(255,255,255,0.08);color:#fff}
.tab-btn:hover:not(.active){color:rgba(255,255,255,0.6)}

/* CRM Styles */
.crm-pipeline{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.crm-pipe-card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:14px 20px;cursor:pointer;transition:all .2s;text-align:center;min-width:100px}
.crm-pipe-card:hover{border-color:rgba(255,255,255,0.15)}
.crm-pipe-card.active{border-color:rgba(255,255,255,0.3);background:rgba(255,255,255,0.06)}
.crm-pipe-card .pipe-count{font-size:24px;font-weight:700;line-height:1;margin-bottom:4px}
.crm-pipe-card .pipe-label{font-size:11px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.crm-pipe-card.revenue-card{border-color:rgba(16,185,129,0.2);background:rgba(16,185,129,0.05)}
.crm-pipe-card.revenue-card .pipe-count{color:#10b981}

.crm-table{width:100%;border-collapse:collapse;background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;overflow:hidden}
.crm-table thead th{text-align:left;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:14px 16px;border-bottom:1px solid rgba(255,255,255,0.06)}
.crm-table tbody tr{cursor:pointer;transition:background .15s}
.crm-table tbody tr:hover{background:rgba(255,255,255,0.03)}
.crm-table tbody td{padding:12px 16px;font-size:13px;color:rgba(255,255,255,0.7);border-bottom:1px solid rgba(255,255,255,0.04)}
.crm-table .status-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
.status-neu{background:#3b82f6}
.status-kontaktiert{background:#8b5cf6}
.status-setting_gebucht{background:#f59e0b}
.status-qualifiziert{background:#06b6d4}
.status-nachgespraech{background:#a855f7}
.status-gewonnen{background:#10b981}
.status-followup{background:#f97316}
.status-verloren{background:#ef4444}
.text-red{color:#ef4444!important}

/* CRM View Toggle */
.crm-view-toggle{display:flex;gap:4px;background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:3px}
.crm-view-btn{padding:6px 14px;border:none;background:none;color:rgba(255,255,255,0.4);font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;border-radius:6px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:5px}
.crm-view-btn.active{background:rgba(255,255,255,0.08);color:#fff}
.crm-view-btn:hover:not(.active){color:rgba(255,255,255,0.6)}
.crm-top-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}

/* Schlagzahl KPIs */
.crm-kpi-row{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px}
.crm-kpi-card{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:18px 16px;position:relative;overflow:hidden}
.crm-kpi-card::before{content:'';position:absolute;inset:-1px;border-radius:12px;padding:1px;background:linear-gradient(135deg,rgba(255,255,255,0.12),rgba(255,255,255,0.03));-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
.crm-kpi-val{font-size:28px;font-weight:700;line-height:1;margin-bottom:4px}
.crm-kpi-label{font-size:11px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;font-weight:600}

/* Kanban Board */
.kanban-board{display:flex;gap:10px;overflow-x:auto;padding-bottom:12px;min-height:400px}
.kanban-board::-webkit-scrollbar{height:6px}
.kanban-board::-webkit-scrollbar-track{background:rgba(255,255,255,0.02);border-radius:3px}
.kanban-board::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:3px}
.kanban-col{min-width:140px;flex:1;background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:12px;display:flex;flex-direction:column;max-height:calc(100vh - 320px)}
.kanban-col.followup-col{border-color:rgba(249,115,22,0.3)}
.kanban-col-header{padding:14px 12px 10px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.kanban-col-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.kanban-col-count{font-size:11px;font-weight:700;background:rgba(255,255,255,0.08);border-radius:10px;padding:2px 8px;color:rgba(255,255,255,0.5)}
.kanban-col-body{flex:1;overflow-y:auto;padding:8px;display:flex;flex-direction:column;gap:6px;min-height:60px}
.kanban-col-body::-webkit-scrollbar{width:4px}
.kanban-col-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.08);border-radius:2px}
.kanban-col.drag-over{background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.2)}

/* Kanban Card */
.kanban-card{background:#0a0a0a;border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:10px;cursor:grab;transition:all .15s;user-select:none}
.kanban-card:hover{border-color:rgba(255,255,255,0.15);background:rgba(255,255,255,0.03)}
.kanban-card:active{cursor:grabbing}
.kanban-card.dragging{opacity:.5;transform:scale(.95)}
.kanban-card-name{font-size:13px;font-weight:600;color:#fff;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kanban-card-branche{font-size:11px;color:rgba(255,255,255,0.4);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.kanban-card-meta{display:flex;align-items:center;justify-content:space-between;font-size:10px;color:rgba(255,255,255,0.3)}
.kanban-card-score{background:rgba(255,255,255,0.06);border-radius:4px;padding:2px 6px;font-weight:600}
.kanban-card.warn-no-contact{border-color:rgba(239,68,68,0.5)}
.kanban-card .followup-pulse{width:8px;height:8px;border-radius:50%;background:#f97316;animation:fuPulse 1.5s ease-in-out infinite;display:none}
.kanban-card.warn-followup .followup-pulse{display:inline-block}
@keyframes fuPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(1.3)}}

/* Lead Detail Modal */
.crm-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px}
.crm-modal{background:#141414;border:1px solid rgba(255,255,255,0.08);border-radius:16px;width:100%;max-width:900px;max-height:90vh;overflow-y:auto;position:relative}
.crm-modal-close{position:absolute;top:16px;right:16px;background:none;border:none;color:rgba(255,255,255,0.4);font-size:24px;cursor:pointer;z-index:10;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:all .2s}
.crm-modal-close:hover{color:#fff;background:rgba(255,255,255,0.08)}
.crm-modal-body{display:grid;grid-template-columns:1.5fr 1fr;gap:0}
.crm-modal-left{padding:32px;border-right:1px solid rgba(255,255,255,0.06)}
.crm-modal-right{padding:32px}
.crm-modal-title{font-size:22px;font-weight:700;margin-bottom:4px}
.crm-status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:20px}
.crm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.crm-info-item label{display:block;font-size:11px;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:.5px;font-weight:600;margin-bottom:4px}
.crm-info-item .info-val{font-size:13px;color:rgba(255,255,255,0.7)}
.crm-info-item .info-val a{color:#3b82f6;text-decoration:none}
.crm-info-item .info-val a:hover{text-decoration:underline}
.crm-info-item.full-width{grid-column:1/-1}
/* Custom Dropdown */
.jd-dropdown{position:relative;width:100%;margin-bottom:16px;user-select:none}
.jd-dropdown-trigger{display:flex;align-items:center;justify-content:space-between;background:#0a0a0a;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-family:'DM Sans',sans-serif;font-size:13px;padding:10px 12px;cursor:pointer;transition:border-color .2s}
.jd-dropdown-trigger:hover{border-color:rgba(255,255,255,0.2)}
.jd-dropdown.open .jd-dropdown-trigger{border-color:rgba(255,255,255,0.3);border-radius:8px 8px 0 0}
.jd-dropdown-arrow{width:16px;height:16px;flex-shrink:0;opacity:.4;transition:transform .2s}
.jd-dropdown.open .jd-dropdown-arrow{transform:rotate(180deg)}
.jd-dropdown-menu{display:none;position:absolute;top:100%;left:0;right:0;background:#141414;border:1px solid rgba(255,255,255,0.1);border-top:none;border-radius:0 0 8px 8px;max-height:240px;overflow-y:auto;z-index:50}
.jd-dropdown.open .jd-dropdown-menu{display:block}
.jd-dropdown-menu::-webkit-scrollbar{width:4px}
.jd-dropdown-menu::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:2px}
.jd-dropdown-item{padding:10px 12px;font-size:13px;color:rgba(255,255,255,0.7);cursor:pointer;transition:all .1s;display:flex;align-items:center;gap:8px}
.jd-dropdown-item:hover{background:rgba(255,255,255,0.06);color:#fff}
.jd-dropdown-item.selected{color:#fff;background:rgba(255,255,255,0.04)}
.jd-dropdown-item .jd-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.crm-input{background:#0a0a0a;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-family:'DM Sans',sans-serif;font-size:13px;padding:8px 12px;outline:none;width:100%;transition:border-color .2s}
.crm-input:focus{border-color:rgba(255,255,255,0.3)}
.crm-textarea{background:#0a0a0a;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-family:'DM Sans',sans-serif;font-size:13px;padding:10px 12px;outline:none;width:100%;resize:vertical;min-height:60px;transition:border-color .2s}
.crm-textarea:focus{border-color:rgba(255,255,255,0.3)}
.crm-btn{padding:8px 18px;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s}
.crm-btn:hover{opacity:.85}
.crm-btn-primary{background:#fff;color:#0a0a0a}
.crm-btn-ghost{background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.6)}
.crm-btn-danger{background:none;border:none;color:rgba(255,255,255,0.25);font-size:16px;cursor:pointer;padding:0 4px;transition:color .2s}
.crm-btn-danger:hover{color:#ef4444}

/* Timeline */
.crm-section-title{font-size:14px;font-weight:600;margin-bottom:14px}
.crm-timeline{position:relative;padding-left:20px;margin-bottom:20px}
.crm-timeline::before{content:'';position:absolute;left:5px;top:6px;bottom:6px;width:2px;background:rgba(255,255,255,0.08)}
.crm-tl-item{position:relative;padding-bottom:16px}
.crm-tl-item:last-child{padding-bottom:0}
.crm-tl-dot{position:absolute;left:-20px;top:4px;width:12px;height:12px;border-radius:50%;border:2px solid rgba(255,255,255,0.15);background:#141414}
.crm-tl-dot.first{border-color:#22c55e;background:#22c55e}
.crm-tl-date{font-size:11px;color:rgba(255,255,255,0.3);margin-bottom:2px}
.crm-tl-type{display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.crm-tl-type.anruf{background:rgba(59,130,246,0.15);color:#3b82f6}
.crm-tl-type.e-mail,.crm-tl-type.email{background:rgba(168,85,247,0.15);color:#a855f7}
.crm-tl-type.meeting{background:rgba(245,158,11,0.15);color:#f59e0b}
.crm-tl-type.sonstiges{background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.5)}
.crm-tl-type.system{background:rgba(34,197,94,0.15);color:#22c55e}
.crm-tl-type.setting-call{background:rgba(16,185,129,0.15);color:#10b981}
.crm-tl-type.closing-call{background:rgba(59,130,246,0.15);color:#3b82f6}
.crm-tl-type.nachgespraech{background:rgba(168,85,247,0.15);color:#a855f7}
.crm-tl-note{font-size:13px;color:rgba(255,255,255,0.6);line-height:1.5}
.crm-tl-actions{position:absolute;top:2px;right:0}

/* Notes */
.crm-note-item{background:rgba(255,255,255,0.03);border-radius:8px;padding:12px;margin-bottom:8px;position:relative}
.crm-note-date{font-size:11px;color:rgba(255,255,255,0.3);margin-bottom:4px}
.crm-note-text{font-size:13px;color:rgba(255,255,255,0.6);line-height:1.5}
.crm-note-del{position:absolute;top:10px;right:10px}
.crm-add-contact-form{background:rgba(255,255,255,0.03);border-radius:8px;padding:14px;margin-top:12px;display:flex;flex-direction:column;gap:8px}

/* Revenue input */
.crm-revenue-section{margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.06)}

/* No-stats badge */
.kanban-card.no-stats{border-style:dashed}
.no-stats-badge{font-size:9px;font-weight:600;background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.35);padding:1px 6px;border-radius:4px;text-transform:uppercase;letter-spacing:.3px;vertical-align:middle;margin-left:4px}

/* Add Lead Button */
.crm-add-lead-btn{padding:8px 20px;background:#fff;color:#0a0a0a;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s;display:flex;align-items:center;gap:6px}
.crm-add-lead-btn:hover{opacity:.85}

/* Add Lead Modal */
.add-lead-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:20px}
.add-lead-modal{background:#141414;border:1px solid rgba(255,255,255,0.08);border-radius:16px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;padding:32px;position:relative}
.add-lead-modal h3{font-size:18px;font-weight:700;margin-bottom:24px}
.add-lead-modal .form-row{margin-bottom:14px}
.add-lead-modal .form-row label{display:block;font-size:11px;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:.5px;font-weight:600;margin-bottom:6px}
.add-lead-modal .form-row-half{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.stats-toggle{display:flex;align-items:center;gap:12px;padding:12px 16px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:10px;cursor:pointer;user-select:none;transition:border-color .2s}
.stats-toggle:hover{border-color:rgba(255,255,255,0.15)}
.stats-toggle input[type=checkbox]{display:none}
.stats-toggle .toggle-switch{width:36px;height:20px;border-radius:10px;background:rgba(255,255,255,0.1);position:relative;flex-shrink:0;transition:background .2s}
.stats-toggle .toggle-switch::after{content:'';position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,0.3);transition:all .2s}
.stats-toggle input:checked+.toggle-switch{background:#10b981}
.stats-toggle input:checked+.toggle-switch::after{left:18px;background:#fff}
.stats-toggle .toggle-label{font-size:13px;font-weight:500;color:rgba(255,255,255,0.7)}
.stats-toggle .toggle-hint{font-size:11px;color:rgba(255,255,255,0.3);margin-top:2px}
.add-lead-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:24px}

@media(max-width:1200px){
    .crm-kpi-row{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:768px){
    .crm-modal-body{grid-template-columns:1fr}
    .crm-modal-left{border-right:none;border-bottom:1px solid rgba(255,255,255,0.06)}
    .crm-pipeline{gap:6px}
    .crm-pipe-card{min-width:80px;padding:10px 14px}
    .crm-kpi-row{grid-template-columns:repeat(2,1fr)}
    .kanban-col{min-width:160px}
    .crm-top-row{flex-direction:column;gap:10px;align-items:flex-start}
}

/* Settings */
.settings-section{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:28px;margin-bottom:20px}
.settings-title{font-size:16px;font-weight:600;margin-bottom:20px}
.settings-form{display:flex;flex-direction:column;gap:10px;max-width:420px}
.settings-form .crm-input{margin-bottom:0}
.settings-msg{font-size:13px;min-height:20px;margin-top:4px}
.settings-msg.error{color:#ef4444}
.settings-msg.success{color:#22c55e}
.settings-table{width:100%;border-collapse:collapse}
.settings-table thead th{text-align:left;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:rgba(255,255,255,0.3);padding:0 0 10px}
.settings-table thead th:last-child{text-align:right}
.settings-table td{padding:10px 0;font-size:13px;color:rgba(255,255,255,0.7);border-top:1px solid rgba(255,255,255,0.04)}
.settings-table td:last-child{text-align:right}
.settings-delete-btn{background:none;border:1px solid rgba(239,68,68,0.3);color:#ef4444;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;padding:5px 14px;border-radius:6px;cursor:pointer;transition:all .2s}
.settings-delete-btn:hover{background:rgba(239,68,68,0.1);border-color:#ef4444}
.settings-pw-btn{background:none;border:1px solid rgba(255,255,255,0.15);color:rgba(255,255,255,0.6);font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;padding:5px 14px;border-radius:6px;cursor:pointer;transition:all .2s;margin-right:8px}
.settings-pw-btn:hover{background:rgba(255,255,255,0.06);border-color:rgba(255,255,255,0.3);color:#fff}
.pw-reset-row{display:flex;gap:8px;align-items:center;margin-top:8px;padding:12px 16px;background:rgba(255,255,255,0.03);border-radius:8px}
.pw-reset-row input{flex:1;padding:8px 12px;background:#0a0a0a;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#fff;font-family:'DM Sans',sans-serif;font-size:13px;outline:none}
.pw-reset-row button{padding:8px 16px;background:#fff;color:#0a0a0a;border:none;border-radius:6px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap}
.pw-wrap{position:relative;display:flex;align-items:center}
.pw-wrap input{width:100%;padding-right:42px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:0;line-height:1;color:rgba(255,255,255,0.3);transition:color .2s}
.pw-toggle:hover{color:rgba(255,255,255,0.6)}
.pw-toggle svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
/* ===== Ad Tracking Tab ===== */
.tracking-controls{display:flex;align-items:center;gap:16px;background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:14px 24px;margin-bottom:24px}
.tracking-month-label{font-size:16px;font-weight:700;color:#fff;min-width:160px;text-align:center}
.tracking-nav-btn{background:none;border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);font-size:16px;width:36px;height:36px;border-radius:8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif}
.tracking-nav-btn:hover{background:rgba(255,255,255,0.06);color:#fff;border-color:rgba(255,255,255,0.2)}
.tracking-refresh-btn{margin-left:auto;display:flex;align-items:center;gap:6px;background:none;border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.5);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;padding:8px 16px;border-radius:8px;cursor:pointer;transition:all .2s}
.tracking-refresh-btn:hover{background:rgba(255,255,255,0.06);color:#fff;border-color:rgba(255,255,255,0.2)}
.tracking-refresh-btn.loading{opacity:.5;pointer-events:none}
.tracking-refresh-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.tracking-summary{display:grid;grid-template-columns:repeat(6,1fr);gap:16px;margin-bottom:24px}
.tracking-kpi{background:#141414;border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:20px}
.tracking-kpi-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,0.35);margin-bottom:8px}
.tracking-kpi-value{font-size:22px;font-weight:700;color:#fff;font-variant-numeric:tabular-nums}
.tracking-kpi-sub{font-size:11px;color:rgba(255,255,255,0.3);margin-top:4px}
.tracking-table-wrap{overflow-x:auto;border-radius:14px;border:1px solid rgba(255,255,255,0.06);background:#141414}
.tracking-table{width:100%;border-collapse:collapse;font-size:12px;font-variant-numeric:tabular-nums;white-space:nowrap}
.tracking-table thead th{padding:12px 10px;text-align:right;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;color:rgba(255,255,255,0.35);background:#141414;border-bottom:1px solid rgba(255,255,255,0.08);position:sticky;top:0;z-index:2}
.tracking-table thead th:first-child{text-align:left;position:sticky;left:0;z-index:3;background:#141414}
.tracking-table thead th:nth-child(2){text-align:left}
.tracking-table tbody td{padding:8px 10px;text-align:right;color:rgba(255,255,255,0.7);border-bottom:1px solid rgba(255,255,255,0.04)}
.tracking-table tbody td:first-child{text-align:left;font-weight:500;color:rgba(255,255,255,0.5);position:sticky;left:0;background:#141414;z-index:1}
.tracking-table tbody td:nth-child(2){text-align:left;font-weight:600;color:rgba(255,255,255,0.85)}
.tracking-table .tracking-row-kw td{background:rgba(255,255,255,0.04)!important;font-weight:700;color:#fff;border-top:2px solid rgba(255,255,255,0.1);border-bottom:2px solid rgba(255,255,255,0.1)}
.tracking-table .tracking-row-avg td{background:rgba(255,255,255,0.02)!important;font-weight:500;color:rgba(255,255,255,0.5);font-style:italic;border-bottom:2px solid rgba(255,255,255,0.08)}
.tracking-table .tracking-row-empty td{opacity:.25}
.tracking-table .tracking-row-future td{opacity:.15}
.tracking-table .tracking-row-sum td{background:rgba(255,255,255,0.06)!important;font-weight:700;color:#fff;border-top:3px solid rgba(255,255,255,0.15);font-size:13px}
.t-good{color:#22c55e!important}
.t-ok{color:#f59e0b!important}
.t-bad{color:#ef4444!important}
.tracking-warning{background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#f59e0b}
.tracking-error{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#ef4444}
@media(max-width:1200px){.tracking-summary{grid-template-columns:repeat(3,1fr)}}
@media(max-width:640px){.tracking-summary{grid-template-columns:repeat(2,1fr)}.tracking-table{font-size:11px}}
</style>
</head>
<body>

<?php
$range = isset($_GET['range']) ? intval($_GET['range']) : 7;
$ranges = [
    1 => 'Heute',
    7 => '7 Tage',
    30 => '30 Tage',
    90 => '90 Tage'
];
?>

<div class="topbar">
    <div class="topbar-left">
        <div class="logo">JALLOUS</div>
        <div class="range-btns">
            <?php foreach($ranges as $val => $label): ?>
                <a href="?range=<?= $val ?>" class="<?= $range === $val ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="topbar-right">
        <a href="?logout=1">Abmelden</a>
    </div>
</div>

<div class="container">

    <div class="tab-bar">
        <button class="tab-btn active" data-tab="analytics">Analytics</button>
        <button class="tab-btn" data-tab="crm">CRM</button>
        <button class="tab-btn" data-tab="tracking">Ad Tracking</button>
        <button class="tab-btn" data-tab="settings">Einstellungen</button>
    </div>

    <div id="tab-analytics" class="tab-content">

    <!-- KPI Cards -->
    <div class="kpi-row" id="kpi-row">
        <div class="kpi-card skeleton" style="height:120px"></div>
        <div class="kpi-card skeleton" style="height:120px"></div>
        <div class="kpi-card skeleton" style="height:120px"></div>
        <div class="kpi-card skeleton" style="height:120px"></div>
    </div>

    <!-- Charts -->
    <div class="charts-row" id="charts-row">
        <div class="chart-card skeleton" style="height:300px"></div>
        <div class="chart-card skeleton" style="height:300px"></div>
    </div>

    <!-- Details -->
    <div class="details-row" id="details-row">
        <div class="detail-card skeleton" style="height:280px"></div>
        <div class="detail-card skeleton" style="height:280px"></div>
        <div class="detail-card skeleton" style="height:280px"></div>
    </div>

    <!-- Sektionen -->
    <div id="sections-section" style="margin-bottom:32px">
        <div class="leads-card skeleton" style="height:280px"></div>
    </div>

    <!-- Recent Leads -->
    <div id="leads-section">
        <div class="leads-card skeleton" style="height:200px"></div>
    </div>

    </div><!-- /tab-analytics -->

    <div id="tab-crm" class="tab-content" style="display:none">
        <div class="crm-top-row">
            <div style="display:flex;align-items:center;gap:16px">
                <div class="section-title" style="margin-bottom:0">CRM Pipeline</div>
                <button class="crm-add-lead-btn" onclick="openAddLeadModal()">+ Lead anlegen</button>
            </div>
            <div class="crm-view-toggle">
                <button class="crm-view-btn active" data-view="kanban"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Kanban</button>
                <button class="crm-view-btn" data-view="table"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg> Tabelle</button>
            </div>
        </div>
        <div id="crm-kpis" class="crm-kpi-row"></div>
        <div id="crm-kanban-view"></div>
        <div id="crm-table-view" style="display:none">
            <div id="crm-pipeline" class="crm-pipeline"></div>
            <div id="crm-table-wrap"></div>
        </div>
    </div>

    <div id="tab-tracking" class="tab-content" style="display:none">
        <div class="tracking-controls">
            <button class="tracking-nav-btn" id="tracking-prev">&larr;</button>
            <span class="tracking-month-label" id="tracking-month-label"></span>
            <button class="tracking-nav-btn" id="tracking-next">&rarr;</button>
            <button class="tracking-refresh-btn" id="tracking-refresh">
                <svg viewBox="0 0 24 24"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                Aktualisieren
            </button>
        </div>
        <div id="tracking-warning"></div>
        <div id="tracking-summary" class="tracking-summary"></div>
        <div class="tracking-table-wrap">
            <table class="tracking-table">
                <thead>
                    <tr>
                        <th>KW</th>
                        <th>Datum</th>
                        <th>Werbekosten</th>
                        <th>Impressionen</th>
                        <th>CTR (all)</th>
                        <th>Link-Klicks</th>
                        <th>Kosten/Klick</th>
                        <th>Durchklickrate</th>
                        <th></th>
                        <th>Formular-Aufrufe</th>
                        <th>Kosten/Formular</th>
                        <th>Formular %</th>
                        <th></th>
                        <th>Leads</th>
                        <th>Kosten/Lead</th>
                        <th>Leads %</th>
                        <th>Gespräche</th>
                        <th>Kosten/Gespräch</th>
                        <th>Abschlüsse</th>
                        <th>Kosten/Abschluss</th>
                        <th>Abschlüsse %</th>
                    </tr>
                </thead>
                <tbody id="tracking-tbody"></tbody>
            </table>
        </div>
    </div>

    <div id="tab-settings" class="tab-content" style="display:none">

        <!-- Passwort ändern -->
        <div class="settings-section">
            <h3 class="settings-title">Passwort ändern</h3>
            <div class="settings-form">
                <div class="pw-wrap"><input type="password" class="crm-input" id="currentPass" placeholder="Aktuelles Passwort"><button type="button" class="pw-toggle" onclick="togglePw(this)"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                <div class="pw-wrap"><input type="password" class="crm-input" id="newPass" placeholder="Neues Passwort (min. 8 Zeichen)"><button type="button" class="pw-toggle" onclick="togglePw(this)"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                <div class="pw-wrap"><input type="password" class="crm-input" id="confirmPass" placeholder="Neues Passwort bestätigen"><button type="button" class="pw-toggle" onclick="togglePw(this)"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                <button class="crm-btn crm-btn-primary" id="changePassBtn" onclick="changePassword()">Passwort ändern</button>
                <div id="settings-pass-msg" class="settings-msg"></div>
            </div>
        </div>

        <!-- Benutzer verwalten -->
        <div class="settings-section">
            <h3 class="settings-title">Benutzer verwalten</h3>
            <div id="users-table-wrap">
                <div class="error-msg">Wird geladen...</div>
            </div>
            <div class="settings-form" style="margin-top:20px">
                <h4 style="font-size:14px;font-weight:600;margin-bottom:12px;color:rgba(255,255,255,0.6)">Benutzer hinzufügen</h4>
                <input type="email" class="crm-input" id="addUserEmail" placeholder="E-Mail">
                <div class="pw-wrap"><input type="password" class="crm-input" id="addUserPass" placeholder="Passwort (min. 8 Zeichen)"><button type="button" class="pw-toggle" onclick="togglePw(this)"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                <button class="crm-btn crm-btn-primary" id="addUserBtn" onclick="addUser()">Hinzufügen</button>
                <div id="settings-user-msg" class="settings-msg"></div>
            </div>
        </div>

    </div>

</div>

<script>
(function() {
    var range = new URLSearchParams(window.location.search).get('range') || '7';

    fetch('/api/dashboard-data.php?range=' + range)
        .then(function(r) { return r.json(); })
        .then(function(data) { renderDashboard(data); })
        .catch(function() { showError(); });

    function showError() {
        var els = ['kpi-row', 'charts-row', 'details-row', 'sections-section', 'leads-section'];
        els.forEach(function(id) {
            document.getElementById(id).innerHTML = '<div class="error-msg">Keine Daten verf\u00fcgbar</div>';
        });
    }

    function fmtChange(val) {
        if (val === null || val === undefined) return { text: '0%', cls: 'neutral' };
        var n = parseFloat(val);
        if (n > 0) return { text: '+' + n.toFixed(1) + '%', cls: 'up' };
        if (n < 0) return { text: n.toFixed(1) + '%', cls: 'down' };
        return { text: '0%', cls: 'neutral' };
    }

    function fmtNum(n) {
        if (n === null || n === undefined) return '0';
        return n.toLocaleString('de-DE');
    }

    function renderDashboard(data) {
        renderKPIs(data.kpis);
        renderChart(data.chart);
        renderFunnel(data.funnel);
        renderDetails(data.top_pages, data.sources, data.scroll_depth);
        renderSections(data.sections);
        renderLeads(data.recent_leads_per_day);
    }

    function renderKPIs(kpis) {
        if (!kpis) { document.getElementById('kpi-row').innerHTML = '<div class="error-msg">Keine KPI-Daten</div>'; return; }
        var items = [
            { key: 'visitors', label: 'Besucher', suffix: '', info: 'Wie viele verschiedene Personen deine Seite besucht haben.' },
            { key: 'pageviews', label: 'Views', suffix: '', info: 'Wie oft Seiten insgesamt geladen wurden. Ein Besucher kann mehrere Views erzeugen.' },
            { key: 'leads', label: 'Leads', suffix: '', info: 'Wie viele Besucher das Kontaktformular komplett ausgef\u00fcllt und abgeschickt haben.' },
            { key: 'engagement', label: 'Engagement', suffix: '', info: 'Score von 0\u2013100. Setzt sich zusammen aus: Verweildauer (max 30), Scroll-Tiefe (max 25), Sektionen gesehen (max 25), Formular-Fortschritt (max 20).' }
        ];
        var html = '';
        items.forEach(function(item) {
            var d = kpis[item.key] || { current: 0, change: 0 };
            var ch = fmtChange(d.change);
            html += '<div class="kpi-card">';
            html += '<div class="kpi-value">' + fmtNum(d.current) + item.suffix + '</div>';
            html += '<div class="kpi-header">';
            html += '<span class="kpi-label" style="margin-bottom:0">' + item.label + '</span>';
            html += '<span class="kpi-info"><span class="kpi-info-icon">i</span><span class="kpi-tooltip">' + item.info + '</span></span>';
            html += '</div>';
            html += '<span class="kpi-change ' + ch.cls + '">';
            html += (ch.cls === 'up' ? '\u2191 ' : ch.cls === 'down' ? '\u2193 ' : '') + ch.text;
            html += '</span>';
            html += '</div>';
        });
        document.getElementById('kpi-row').innerHTML = html;
    }

    function renderChart(chart) {
        var container = document.getElementById('charts-row');
        if (!chart || !chart.labels || !chart.labels.length) {
            container.querySelector('.chart-card:first-child').innerHTML = '<div class="error-msg">Keine Chart-Daten</div>';
            return;
        }

        var labels = chart.labels;
        var visitors = chart.visitors || [];
        var pageviews = chart.pageviews || [];

        var allVals = visitors.concat(pageviews);
        var maxVal = Math.max.apply(null, allVals) || 1;
        maxVal = Math.ceil(maxVal * 1.1);

        var vbW = 800, vbH = 200;
        var padL = 40, padR = 10, padT = 10, padB = 30;
        var chartW = vbW - padL - padR;
        var chartH = vbH - padT - padB;

        function toPoints(arr) {
            var pts = [];
            for (var i = 0; i < arr.length; i++) {
                var x = padL + (arr.length > 1 ? (i / (arr.length - 1)) * chartW : chartW / 2);
                var y = padT + chartH - (arr[i] / maxVal) * chartH;
                pts.push(x.toFixed(1) + ',' + y.toFixed(1));
            }
            return pts;
        }

        var vPts = toPoints(visitors);
        var pPts = toPoints(pageviews);

        var svg = '<svg viewBox="0 0 ' + vbW + ' ' + vbH + '" xmlns="http://www.w3.org/2000/svg">';

        // Grid lines
        for (var g = 0; g <= 4; g++) {
            var gy = padT + (g / 4) * chartH;
            svg += '<line x1="' + padL + '" y1="' + gy + '" x2="' + (vbW - padR) + '" y2="' + gy + '" stroke="rgba(255,255,255,0.04)" stroke-width="1"/>';
        }

        // Y-axis labels
        svg += '<text x="' + (padL - 6) + '" y="' + (padT + 5) + '" text-anchor="end" fill="rgba(255,255,255,0.25)" font-size="10">' + maxVal + '</text>';
        svg += '<text x="' + (padL - 6) + '" y="' + (padT + chartH + 4) + '" text-anchor="end" fill="rgba(255,255,255,0.25)" font-size="10">0</text>';

        // Pageviews line (behind)
        if (pPts.length > 1) {
            svg += '<polyline points="' + pPts.join(' ') + '" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
        }
        pPts.forEach(function(pt) {
            var xy = pt.split(',');
            svg += '<circle cx="' + xy[0] + '" cy="' + xy[1] + '" r="2.5" fill="rgba(255,255,255,0.2)"/>';
        });

        // Visitors line (front)
        if (vPts.length > 1) {
            svg += '<polyline points="' + vPts.join(' ') + '" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
        }
        vPts.forEach(function(pt) {
            var xy = pt.split(',');
            svg += '<circle cx="' + xy[0] + '" cy="' + xy[1] + '" r="3" fill="#fff"/>';
        });

        // X-axis labels
        var step = Math.max(1, Math.floor(labels.length / 7));
        for (var i = 0; i < labels.length; i += step) {
            var lx = padL + (labels.length > 1 ? (i / (labels.length - 1)) * chartW : chartW / 2);
            svg += '<text x="' + lx.toFixed(1) + '" y="' + (vbH - 4) + '" text-anchor="middle" fill="rgba(255,255,255,0.25)" font-size="10">' + labels[i] + '</text>';
        }

        svg += '</svg>';

        // Build card
        var cardHtml = '<h3>Besucher &amp; Seitenaufrufe</h3>';
        cardHtml += '<div class="chart-legend">';
        cardHtml += '<div class="chart-legend-item"><span class="chart-legend-dot" style="background:#fff"></span> Besucher</div>';
        cardHtml += '<div class="chart-legend-item"><span class="chart-legend-dot" style="background:rgba(255,255,255,0.25)"></span> Seitenaufrufe</div>';
        cardHtml += '</div>';
        cardHtml += '<div class="chart-wrapper">' + svg + '</div>';

        // We need to handle the two chart cards separately
        // Rebuild the entire charts row
        var funnelHtml = container.querySelectorAll('.chart-card')[1] ? container.querySelectorAll('.chart-card')[1].innerHTML : '';
        container.innerHTML = '<div class="chart-card">' + cardHtml + '</div><div class="chart-card" id="funnel-card"><h3>Formular-Funnel</h3><div id="funnel-content" class="funnel-row"></div></div>';
    }

    function renderFunnel(funnel) {
        var el = document.getElementById('funnel-content');
        if (!el || !funnel) return;

        var keys = ['Formular sichtbar', 'Gestartet', 'Branche', 'Website', 'Herausforderung', 'Umsatz', 'Kundengewinnung', 'Lead'];
        var maxVal = 0;
        keys.forEach(function(k) { if (funnel[k] > maxVal) maxVal = funnel[k]; });
        if (!maxVal) maxVal = 1;

        var html = '';
        keys.forEach(function(k) {
            var val = funnel[k] || 0;
            var pct = (val / maxVal * 100).toFixed(1);
            var dropLabel = '';
            html += '<div class="funnel-item">';
            html += '<span class="funnel-label">' + k + '</span>';
            html += '<div class="funnel-bar-bg"><div class="funnel-bar" style="width:' + pct + '%">' + val + '</div></div>';
            html += '</div>';
        });
        el.innerHTML = html;

        // Animate bars
        setTimeout(function() {
            var bars = el.querySelectorAll('.funnel-bar');
            bars.forEach(function(bar) { bar.style.width = bar.style.width; });
        }, 50);
    }

    function renderDetails(topPages, sources, scrollDepth) {
        var container = document.getElementById('details-row');
        var html = '';

        // Top Pages
        html += '<div class="detail-card"><h3>Top Seiten</h3>';
        if (topPages && topPages.length) {
            html += '<table class="detail-table"><thead><tr><th>Seite</th><th>Views</th><th style="text-align:right">Avg. Zeit</th></tr></thead><tbody>';
            topPages.forEach(function(p) {
                var timeStr = p.avg_time >= 60 ? Math.floor(p.avg_time / 60) + 'm ' + (p.avg_time % 60) + 's' : p.avg_time + 's';
                html += '<tr><td>' + p.page + '</td><td>' + p.views + '</td><td style="text-align:right">' + timeStr + '</td></tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<div class="error-msg">Keine Daten</div>';
        }
        html += '</div>';

        // Sources
        html += '<div class="detail-card"><h3>Traffic-Quellen</h3>';
        if (sources && sources.length) {
            html += '<table class="detail-table"><thead><tr><th>Quelle</th><th style="text-align:right">Besuche</th></tr></thead><tbody>';
            sources.forEach(function(s) {
                html += '<tr><td>' + s.source + '</td><td style="text-align:right">' + s.visits + '</td></tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<div class="error-msg">Keine Daten</div>';
        }
        html += '</div>';

        // Scroll Depth
        html += '<div class="detail-card"><h3>Scroll-Tiefe</h3>';
        if (scrollDepth) {
            html += '<div class="scroll-bars">';
            ['25', '50', '75', '90', '100'].forEach(function(pct) {
                var val = scrollDepth[pct] || 0;
                html += '<div class="scroll-bar-item">';
                html += '<span class="sb-label">' + pct + '%</span>';
                html += '<div class="sb-track"><div class="sb-fill" style="width:' + val + '%"></div></div>';
                html += '<span class="sb-val">' + val + '%</span>';
                html += '</div>';
            });
            html += '</div>';
        } else {
            html += '<div class="error-msg">Keine Daten</div>';
        }
        html += '</div>';

        container.innerHTML = html;
    }

    function renderSections(sections) {
        var container = document.getElementById('sections-section');
        var html = '<div class="leads-card"><h3>Sektionen</h3>';
        if (sections && sections.length) {
            html += '<table class="detail-table"><thead><tr><th>Sektion</th><th style="text-align:right">Aufrufe</th><th style="text-align:right">\u00d8 Verweildauer</th></tr></thead><tbody>';
            sections.forEach(function(s) {
                var timeStr = s.avg_time >= 60 ? Math.floor(s.avg_time / 60) + 'm ' + (s.avg_time % 60) + 's' : s.avg_time + 's';
                html += '<tr><td>' + s.section + '</td><td style="text-align:right">' + s.views + '</td><td style="text-align:right">' + timeStr + '</td></tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<div class="error-msg">Noch keine Sektions-Daten</div>';
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function renderLeads(leads) {
        var container = document.getElementById('leads-section');
        var html = '<div class="leads-card"><h3>Letzte Leads</h3>';
        if (leads && leads.length) {
            html += '<table class="detail-table"><thead><tr><th>Datum</th><th style="text-align:right">Anzahl</th></tr></thead><tbody>';
            leads.forEach(function(l) {
                html += '<tr><td>' + l.date + '</td><td style="text-align:right">' + l.count + '</td></tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<div class="error-msg">Noch keine Leads</div>';
        }
        html += '</div>';
        container.innerHTML = html;
    }
})();

/* ===== Tab Switching ===== */
(function() {
    var tabBtns = document.querySelectorAll('.tab-btn');
    var crmLoaded = false;
    var settingsLoaded = false;
    var trackingLoaded = false;

    tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = this.getAttribute('data-tab');

            tabBtns.forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(function(el) {
                el.style.display = 'none';
            });
            document.getElementById('tab-' + tab).style.display = 'block';

            if (tab === 'crm' && !crmLoaded) {
                crmLoaded = true;
                loadCRM();
            }
            if (tab === 'tracking' && !trackingLoaded) {
                trackingLoaded = true;
                loadTracking();
            }
            if (tab === 'settings' && !settingsLoaded) {
                settingsLoaded = true;
                loadSettings();
            }
        });
    });
})();

/* ===== Ad Tracking ===== */
(function() {
    var trackingMonth = new Date().toISOString().slice(0, 7);
    var trackingData = null;
    var germanMonths = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

    function updateMonthLabel() {
        var parts = trackingMonth.split('-');
        var label = germanMonths[parseInt(parts[1]) - 1] + ' ' + parts[0];
        document.getElementById('tracking-month-label').textContent = label;
    }

    window.loadTracking = function(forceRefresh) {
        var url = '/api/ad-performance.php?month=' + trackingMonth;
        if (forceRefresh) url += '&refresh=1';

        var tbody = document.getElementById('tracking-tbody');
        tbody.innerHTML = '<tr><td colspan="21" style="text-align:center;padding:60px;color:rgba(255,255,255,0.3)">Lade Daten...</td></tr>';
        document.getElementById('tracking-summary').innerHTML = '';
        document.getElementById('tracking-warning').innerHTML = '';

        var refreshBtn = document.getElementById('tracking-refresh');
        if (forceRefresh) refreshBtn.classList.add('loading');

        updateMonthLabel();

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                refreshBtn.classList.remove('loading');
                if (data.error) {
                    document.getElementById('tracking-warning').innerHTML = '<div class="tracking-error">' + data.error + '</div>';
                    return;
                }
                if (data.warning) {
                    document.getElementById('tracking-warning').innerHTML = '<div class="tracking-warning">' + data.warning + '</div>';
                }
                trackingData = data;
                renderTrackingSummary();
                renderTrackingTable();
            })
            .catch(function(err) {
                refreshBtn.classList.remove('loading');
                document.getElementById('tracking-warning').innerHTML = '<div class="tracking-error">Fehler beim Laden: ' + err.message + '</div>';
            });
    };

    function fmtEur(v) {
        if (v == null || v === 0) return '\u2013';
        return v.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' \u20ac';
    }
    function fmtPct(v) {
        if (v == null) return '\u2013';
        return v.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '%';
    }
    function fmtNum(v) {
        if (v == null || v === 0) return '\u2013';
        return v.toLocaleString('de-DE');
    }
    function fmtInt(v) {
        if (v == null || v === 0) return '\u2013';
        return v.toLocaleString('de-DE', {maximumFractionDigits: 0});
    }

    function renderTrackingSummary() {
        var t = trackingData.monthly_total;
        var a = trackingData.monthly_avg;
        var html = '';
        html += kpiCard('Werbekosten', fmtEur(t.spend), '\u00d8 ' + fmtEur(a.spend) + '/Tag');
        html += kpiCard('Leads', fmtInt(t.leads), '\u00d8 ' + (a.leads || 0).toLocaleString('de-DE', {maximumFractionDigits:1}) + '/Tag');
        html += kpiCard('Kosten/Lead', fmtEur(t.cost_per_lead), t.leads + ' Leads gesamt');
        html += kpiCard('Gespräche', fmtInt(t.gespraeche), '\u00d8 ' + fmtEur(t.cost_per_gespraech) + '/Gespräch');
        html += kpiCard('Abschlüsse', fmtInt(t.abschluesse), fmtPct(t.abschluss_rate) + ' Close-Rate');
        html += kpiCard('Kosten/Abschluss', fmtEur(t.cost_per_abschluss), fmtInt(t.impressions) + ' Impressionen');
        document.getElementById('tracking-summary').innerHTML = html;
    }

    function kpiCard(label, value, sub) {
        return '<div class="tracking-kpi"><div class="tracking-kpi-label">' + label + '</div><div class="tracking-kpi-value">' + value + '</div><div class="tracking-kpi-sub">' + (sub || '') + '</div></div>';
    }

    function renderTrackingTable() {
        var days = trackingData.days;
        var weeklyTotals = trackingData.weekly_totals;
        var html = '';
        var currentKw = null;

        // Monthly sum row at top
        var mt = trackingData.monthly_total;
        html += '<tr class="tracking-row-sum">';
        html += '<td>Summe</td><td></td>';
        html += tdEur(mt.spend) + tdNum(mt.impressions) + '<td></td>' + tdNum(mt.link_clicks) + '<td></td><td></td><td></td>';
        html += tdNum(mt.form_views) + '<td></td><td></td><td></td>';
        html += tdNum(mt.leads) + '<td></td><td></td>';
        html += tdNum(mt.gespraeche) + '<td></td>';
        html += tdNum(mt.abschluesse) + '<td></td><td></td>';
        html += '</tr>';

        // Monthly avg row
        var ma = trackingData.monthly_avg;
        html += '<tr class="tracking-row-avg">';
        html += '<td>\u00d8/Tag</td><td></td>';
        html += tdEur(ma.spend) + tdNum(ma.impressions) + tdPct(ma.ctr) + tdNum(ma.link_clicks) + tdEur(ma.cost_per_link_click) + tdPct(ma.link_ctr) + '<td></td>';
        html += tdNum(ma.form_views) + tdEur(ma.cost_per_form_view) + tdPct(ma.form_view_rate) + '<td></td>';
        html += tdNum(ma.leads) + tdEur(ma.cost_per_lead) + tdPct(ma.lead_rate);
        html += tdNum(ma.gespraeche) + tdEur(ma.cost_per_gespraech);
        html += tdNum(ma.abschluesse) + tdEur(ma.cost_per_abschluss) + tdPct(ma.abschluss_rate);
        html += '</tr>';

        for (var i = 0; i < days.length; i++) {
            var d = days[i];

            // KW change — insert weekly subtotal for previous KW
            if (currentKw !== null && d.kw !== currentKw && weeklyTotals[currentKw]) {
                html += renderWeeklyRows(currentKw, weeklyTotals[currentKw]);
            }
            currentKw = d.kw;

            // Day row
            var cls = '';
            if (d.is_future) cls = 'tracking-row-future';
            else if (!d.has_data && !d.gespraeche && !d.abschluesse) cls = 'tracking-row-empty';

            html += '<tr class="' + cls + '">';
            html += '<td>' + d.kw + '</td>';
            html += '<td>' + formatDate(d.date) + '</td>';
            html += tdEur(d.spend);
            html += tdNum(d.impressions);
            html += tdPct(d.ctr);
            html += tdNum(d.link_clicks);
            html += tdEur(d.cost_per_link_click);
            html += tdPct(d.link_ctr);
            html += '<td></td>'; // spacer
            html += tdNum(d.form_views);
            html += tdEur(d.cost_per_form_view);
            html += tdPct(d.form_view_rate);
            html += '<td></td>'; // spacer
            html += tdNum(d.leads);
            html += tdEurColor(d.cost_per_lead, 100, 200); // CPL thresholds
            html += tdPct(d.lead_rate);
            html += tdNum(d.gespraeche);
            html += tdEur(d.cost_per_gespraech);
            html += tdNum(d.abschluesse);
            html += tdEur(d.cost_per_abschluss);
            html += tdPct(d.abschluss_rate);
            html += '</tr>';
        }

        // Last KW subtotal
        if (currentKw !== null && weeklyTotals[currentKw]) {
            html += renderWeeklyRows(currentKw, weeklyTotals[currentKw]);
        }

        document.getElementById('tracking-tbody').innerHTML = html;
    }

    function renderWeeklyRows(kw, wt) {
        var html = '<tr class="tracking-row-kw">';
        html += '<td>KW ' + kw + '</td><td>Zwischensumme</td>';
        html += tdEur(wt.spend) + tdNum(wt.impressions) + '<td></td>' + tdNum(wt.link_clicks) + '<td></td><td></td><td></td>';
        html += tdNum(wt.form_views) + '<td></td><td></td><td></td>';
        html += tdNum(wt.leads) + '<td></td><td></td>';
        html += tdNum(wt.gespraeche) + '<td></td>';
        html += tdNum(wt.abschluesse) + '<td></td><td></td>';
        html += '</tr>';

        // Weekly average row
        var dc = wt.data_days || 1;
        html += '<tr class="tracking-row-avg">';
        html += '<td></td><td>\u00d8/Tag</td>';
        html += tdEur(dc > 0 ? wt.spend / dc : 0);
        html += tdNum(dc > 0 ? Math.round(wt.impressions / dc) : 0);
        html += tdPct(wt.avg_ctr);
        html += tdNum(dc > 0 ? Math.round(wt.link_clicks / dc) : 0);
        html += tdEur(wt.cost_per_link_click);
        html += tdPct(wt.link_ctr);
        html += '<td></td>';
        html += tdNum(dc > 0 ? Math.round(wt.form_views / dc) : 0);
        html += tdEur(wt.cost_per_form_view);
        html += tdPct(wt.form_view_rate);
        html += '<td></td>';
        html += tdNum(dc > 0 ? +(wt.leads / dc).toFixed(1) : 0);
        html += tdEur(wt.cost_per_lead);
        html += tdPct(wt.lead_rate);
        html += tdNum(dc > 0 ? +(wt.gespraeche / dc).toFixed(1) : 0);
        html += tdEur(wt.cost_per_gespraech);
        html += tdNum(dc > 0 ? +(wt.abschluesse / dc).toFixed(1) : 0);
        html += tdEur(wt.cost_per_abschluss);
        html += tdPct(wt.abschluss_rate);
        html += '</tr>';

        return html;
    }

    function tdEur(v) { return '<td>' + fmtEur(v) + '</td>'; }
    function tdNum(v) { return '<td>' + fmtNum(v) + '</td>'; }
    function tdPct(v) { return '<td>' + fmtPct(v) + '</td>'; }
    function tdInt(v) { return '<td>' + fmtInt(v) + '</td>'; }
    function tdEurColor(v, good, bad) {
        if (v == null || v === 0) return '<td>\u2013</td>';
        var cls = v <= good ? 't-good' : (v <= bad ? 't-ok' : 't-bad');
        return '<td class="' + cls + '">' + fmtEur(v) + '</td>';
    }

    function formatDate(dateStr) {
        var parts = dateStr.split('-');
        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    // Month navigation
    document.getElementById('tracking-prev').addEventListener('click', function() {
        var parts = trackingMonth.split('-');
        var d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 2, 1);
        trackingMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        loadTracking();
    });

    document.getElementById('tracking-next').addEventListener('click', function() {
        var parts = trackingMonth.split('-');
        var d = new Date(parseInt(parts[0]), parseInt(parts[1]), 1);
        var now = new Date();
        if (d > new Date(now.getFullYear(), now.getMonth() + 1, 1)) return; // don't go past current month
        trackingMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        loadTracking();
    });

    document.getElementById('tracking-refresh').addEventListener('click', function() {
        loadTracking(true);
    });

    updateMonthLabel();
})();

/* ===== CRM (Setter-Closer Pipeline) ===== */
(function() {
    var crmData = [];
    var crmKpis = {};
    var activeFilter = null;
    var currentView = 'kanban'; // 'kanban' or 'table'

    var STATUS_MAP = {
        'neu':              { label: 'Neu',              color: '#3b82f6', cls: 'status-neu' },
        'kontaktiert':      { label: 'Kontaktiert',      color: '#8b5cf6', cls: 'status-kontaktiert' },
        'setting_gebucht':  { label: 'Setting gebucht',  color: '#f59e0b', cls: 'status-setting_gebucht' },
        'qualifiziert':     { label: 'Qualifiziert',     color: '#06b6d4', cls: 'status-qualifiziert' },
        'nachgespraech':    { label: 'Nachgespr\u00e4ch',color: '#a855f7', cls: 'status-nachgespraech' },
        'gewonnen':         { label: 'Gewonnen',         color: '#10b981', cls: 'status-gewonnen' },
        'followup':         { label: 'Follow-up',        color: '#f97316', cls: 'status-followup' },
        'verloren':         { label: 'Verloren',         color: '#ef4444', cls: 'status-verloren' }
    };
    var STATUS_KEYS = ['neu', 'kontaktiert', 'setting_gebucht', 'qualifiziert', 'nachgespraech', 'gewonnen', 'followup', 'verloren'];

    // View toggle
    document.querySelectorAll('.crm-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.crm-view-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            currentView = this.getAttribute('data-view');
            if (currentView === 'kanban') {
                document.getElementById('crm-kanban-view').style.display = '';
                document.getElementById('crm-table-view').style.display = 'none';
            } else {
                document.getElementById('crm-kanban-view').style.display = 'none';
                document.getElementById('crm-table-view').style.display = '';
            }
            renderCRM();
        });
    });

    window.loadCRM = function() {
        Promise.all([
            fetch('/api/crm-data.php?action=list').then(function(r) { return r.json(); }),
            fetch('/api/crm-data.php?action=crm_kpis').then(function(r) { return r.json(); })
        ]).then(function(results) {
            crmData = results[0].leads || [];
            crmKpis = results[1] || {};
            renderKpis();
            renderCRM();
        }).catch(function() {
            document.getElementById('crm-kanban-view').innerHTML = '<div class="error-msg">CRM-Daten konnten nicht geladen werden</div>';
        });
    };

    function renderKpis() {
        var items = [
            { key: 'calls_this_week', label: 'Calls diese Woche', fmt: 'num' },
            { key: 'appointments_this_week', label: 'Termine diese Woche', fmt: 'num' },
            { key: 'closed_this_month', label: 'Abschl\u00fcsse Monat', fmt: 'num' },
            { key: 'conversion_rate', label: 'Conversion Rate', fmt: 'pct' },
            { key: 'pipeline_value', label: 'Pipeline-Wert', fmt: 'eur' },
            { key: 'avg_days_to_close', label: '\u00d8 Tage bis Abschluss', fmt: 'days' }
        ];
        var html = '';
        items.forEach(function(item) {
            var val = crmKpis[item.key] || 0;
            var display = '';
            if (item.fmt === 'eur') display = parseFloat(val).toLocaleString('de-DE') + ' \u20ac';
            else if (item.fmt === 'pct') display = val + '%';
            else if (item.fmt === 'days') display = val + 'd';
            else display = val.toString();
            html += '<div class="crm-kpi-card">';
            html += '<div class="crm-kpi-val">' + display + '</div>';
            html += '<div class="crm-kpi-label">' + item.label + '</div>';
            html += '</div>';
        });
        document.getElementById('crm-kpis').innerHTML = html;
    }

    function renderCRM() {
        if (currentView === 'kanban') {
            renderKanban();
        } else {
            renderPipeline();
            renderTable();
        }
    }

    // ── KANBAN ──────────────────────────────────────────────────────

    function renderKanban() {
        var buckets = {};
        STATUS_KEYS.forEach(function(k) { buckets[k] = []; });
        crmData.forEach(function(lead) {
            var key = lead.status;
            if (!buckets[key]) {
                // Legacy status mapping
                if (key === 'termin') key = 'setting_gebucht';
                else if (key === 'angebot') key = 'qualifiziert';
                else key = 'neu';
            }
            if (buckets[key]) buckets[key].push(lead);
        });

        var html = '<div class="kanban-board" id="kanban-board">';
        STATUS_KEYS.forEach(function(stKey) {
            var s = STATUS_MAP[stKey];
            var leads = buckets[stKey];
            var isFollowup = stKey === 'followup';
            html += '<div class="kanban-col' + (isFollowup ? ' followup-col' : '') + '" data-status="' + stKey + '">';
            html += '<div class="kanban-col-header">';
            html += '<span class="kanban-col-title" style="color:' + s.color + '">' + s.label + '</span>';
            html += '<span class="kanban-col-count">' + leads.length + '</span>';
            html += '</div>';
            html += '<div class="kanban-col-body" data-status="' + stKey + '">';
            leads.forEach(function(lead) {
                var dsc = lead.days_since_contact !== null ? lead.days_since_contact : daysBetween(lead.created_at, new Date());
                var warnContact = dsc > 3;
                var warnFollowup = false;
                if (lead.followup_date) {
                    var fDate = new Date(lead.followup_date);
                    var today = new Date();
                    today.setHours(0,0,0,0);
                    if (fDate <= today) warnFollowup = true;
                }
                var cardCls = 'kanban-card';
                if (warnContact) cardCls += ' warn-no-contact';
                if (warnFollowup) cardCls += ' warn-followup';

                var noStats = lead.count_in_stats !== undefined && !lead.count_in_stats;
                if (noStats) cardCls += ' no-stats';

                html += '<div class="' + cardCls + '" draggable="true" data-lead-id="' + lead.id + '">';
                html += '<div class="kanban-card-name">' + esc(lead.name) + (noStats ? ' <span class="no-stats-badge">Privat</span>' : '') + '</div>';
                html += '<div class="kanban-card-branche">' + esc(lead.branche || '\u2014') + (lead.source ? ' \u00b7 ' + esc(lead.source) : '') + '</div>';
                html += '<div class="kanban-card-meta">';
                html += '<span>' + dsc + 'd</span>';
                html += '<span class="followup-pulse"></span>';
                html += '<span class="kanban-card-score">' + (lead.engagement_score || 0) + '</span>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div></div>';
        });
        html += '</div>';

        document.getElementById('crm-kanban-view').innerHTML = html;
        initDragDrop();
        initKanbanClicks();
    }

    function initKanbanClicks() {
        document.querySelectorAll('.kanban-card').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (e.defaultPrevented) return;
                openLeadDetail(this.getAttribute('data-lead-id'));
            });
        });
    }

    function initDragDrop() {
        var draggedCard = null;

        document.querySelectorAll('.kanban-card').forEach(function(card) {
            card.addEventListener('dragstart', function(e) {
                draggedCard = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', this.getAttribute('data-lead-id'));
            });
            card.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                document.querySelectorAll('.kanban-col').forEach(function(col) {
                    col.classList.remove('drag-over');
                });
                draggedCard = null;
            });
        });

        document.querySelectorAll('.kanban-col-body').forEach(function(colBody) {
            colBody.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.closest('.kanban-col').classList.add('drag-over');
            });
            colBody.addEventListener('dragleave', function(e) {
                if (!this.contains(e.relatedTarget)) {
                    this.closest('.kanban-col').classList.remove('drag-over');
                }
            });
            colBody.addEventListener('drop', function(e) {
                e.preventDefault();
                this.closest('.kanban-col').classList.remove('drag-over');
                var leadId = e.dataTransfer.getData('text/plain');
                var newStatus = this.getAttribute('data-status');
                if (!leadId || !newStatus) return;

                // Find lead and check if status actually changed
                var lead = crmData.find(function(l) { return l.id == leadId; });
                if (lead && lead.status !== newStatus) {
                    // Optimistic update
                    lead.status = newStatus;
                    renderKanban();
                    // Persist
                    fetch('/api/crm-data.php?action=update_status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(leadId), status: newStatus })
                    }).then(function(r) { return r.json(); }).then(function(d) {
                        if (!d.success) refreshAll();
                    }).catch(function() { refreshAll(); });
                }
            });
        });
    }

    // ── TABLE (legacy) ──────────────────────────────────────────────

    function renderPipeline() {
        var counts = {};
        STATUS_KEYS.forEach(function(k) { counts[k] = 0; });
        crmData.forEach(function(lead) {
            if (counts[lead.status] !== undefined) counts[lead.status]++;
        });

        var html = '';
        STATUS_KEYS.forEach(function(k) {
            var s = STATUS_MAP[k];
            var isActive = activeFilter === k;
            html += '<div class="crm-pipe-card' + (isActive ? ' active' : '') + '" data-filter="' + k + '">';
            html += '<div class="pipe-count" style="color:' + s.color + '">' + counts[k] + '</div>';
            html += '<div class="pipe-label">' + s.label + '</div>';
            html += '</div>';
        });

        document.getElementById('crm-pipeline').innerHTML = html;
        document.querySelectorAll('#crm-pipeline .crm-pipe-card[data-filter]').forEach(function(card) {
            card.addEventListener('click', function() {
                var f = this.getAttribute('data-filter');
                activeFilter = activeFilter === f ? null : f;
                renderPipeline();
                renderTable();
            });
        });
    }

    function renderTable() {
        var filtered = activeFilter ? crmData.filter(function(l) { return l.status === activeFilter; }) : crmData;

        if (!filtered.length) {
            document.getElementById('crm-table-wrap').innerHTML = '<div class="error-msg">Keine Leads vorhanden</div>';
            return;
        }

        var html = '<table class="crm-table"><thead><tr>';
        html += '<th>Status</th><th>Name</th><th>Branche</th><th>Engagement</th><th>Erstellt</th><th>Letzter Kontakt</th><th>Tage ohne Kontakt</th><th>Aktionen</th>';
        html += '</tr></thead><tbody>';

        filtered.forEach(function(lead) {
            var s = STATUS_MAP[lead.status] || STATUS_MAP['neu'];
            var lastContact = lead.last_contact || null;
            var lastContactStr = lastContact ? formatDate(lastContact) : '\u2014';
            var dsc = lead.days_since_contact !== null ? lead.days_since_contact : daysBetween(lead.created_at, new Date());
            var daysClass = dsc > 3 ? ' text-red' : '';
            var warnFollowup = false;
            if (lead.followup_date) {
                var fDate = new Date(lead.followup_date);
                var today = new Date(); today.setHours(0,0,0,0);
                if (fDate <= today) warnFollowup = true;
            }

            html += '<tr data-lead-id="' + lead.id + '"' + (dsc > 3 ? ' style="border-left:3px solid #ef4444"' : '') + '>';
            html += '<td><span class="status-dot ' + s.cls + '"></span>' + (warnFollowup ? ' <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f97316;animation:fuPulse 1.5s ease-in-out infinite"></span>' : '') + '</td>';
            html += '<td>' + esc(lead.name) + '</td>';
            html += '<td>' + esc(lead.branche || '\u2014') + '</td>';
            html += '<td>' + (lead.engagement_score || 0) + '</td>';
            html += '<td>' + formatDate(lead.created_at) + '</td>';
            html += '<td>' + lastContactStr + '</td>';
            html += '<td class="' + daysClass + '">' + dsc + '</td>';
            html += '<td><button class="crm-btn crm-btn-ghost crm-detail-btn" data-id="' + lead.id + '">\u00d6ffnen</button></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        document.getElementById('crm-table-wrap').innerHTML = html;

        document.querySelectorAll('#crm-table-wrap .crm-table tbody tr').forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.crm-detail-btn')) return;
                var id = this.getAttribute('data-lead-id');
                if (id) openLeadDetail(id);
            });
        });
        document.querySelectorAll('#crm-table-wrap .crm-detail-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                openLeadDetail(this.getAttribute('data-id'));
            });
        });
    }

    // ── LEAD DETAIL MODAL ───────────────────────────────────────────

    function openLeadDetail(id) {
        fetch('/api/crm-data.php?action=detail&id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                renderModal(data);
            });
    }

    function renderModal(data) {
        var existing = document.getElementById('crm-modal-overlay');
        if (existing) existing.remove();

        var lead = data.lead;
        var contacts = data.contacts || [];
        var notes = data.notes || [];

        var s = STATUS_MAP[lead.status] || STATUS_MAP['neu'];
        var createdMin = (lead.created_at || '').replace(' ', 'T').substring(0, 16);

        var html = '<div class="crm-modal-overlay" id="crm-modal-overlay">';
        html += '<div class="crm-modal">';
        html += '<button class="crm-modal-close" id="crm-modal-close">&times;</button>';
        html += '<div class="crm-modal-body">';

        // Left side
        html += '<div class="crm-modal-left">';
        html += '<div class="crm-modal-title">' + esc(lead.name) + '</div>';
        html += '<div class="crm-status-badge" style="background:' + s.color + '20;color:' + s.color + '">' + s.label + '</div>';

        // Status dropdown (custom)
        html += '<div class="jd-dropdown" id="crm-status-dropdown" data-value="' + lead.status + '">';
        html += '<div class="jd-dropdown-trigger"><span><span class="jd-dot" style="display:inline-block;vertical-align:middle;margin-right:8px;background:' + s.color + '"></span>' + s.label + '</span><svg class="jd-dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>';
        html += '<div class="jd-dropdown-menu">';
        STATUS_KEYS.forEach(function(k) {
            var st = STATUS_MAP[k];
            html += '<div class="jd-dropdown-item' + (lead.status === k ? ' selected' : '') + '" data-value="' + k + '"><span class="jd-dot" style="background:' + st.color + '"></span>' + st.label + '</div>';
        });
        html += '</div></div>';

        // Editable info grid
        html += '<div class="crm-info-grid">';
        html += editItem('Name', 'crm-edit-name', lead.name || '', 'text');
        html += editItem('E-Mail', 'crm-edit-email', lead.email || '', 'email');
        html += editItem('Telefon', 'crm-edit-phone', lead.phone || '', 'tel');
        html += editItem('Branche', 'crm-edit-branche', lead.branche || '', 'text');
        html += editItem('Website', 'crm-edit-website', lead.website_url || '', 'text');
        html += editItem('Quelle', 'crm-edit-source', lead.source || '', 'text');
        html += editItemFull('Herausforderung', 'crm-edit-problem', lead.problem || '');
        html += editItemFull('Ziele', 'crm-edit-ziele', lead.ziele || '');
        html += editItem('Investitionspotenzial (\u20ac)', 'crm-invest-input', lead.investitionspotenzial || '', 'text');
        html += '</div>';

        // Closing-Call Termin (only when qualifiziert)
        html += '<div id="crm-closing-section" style="display:' + (lead.status === 'qualifiziert' ? 'block' : 'none') + ';margin-bottom:12px">';
        html += '<label style="font-size:11px;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:.5px;font-weight:600;display:block;margin-bottom:6px">Closing-Call Termin</label>';
        html += '<input type="datetime-local" class="crm-input" id="crm-closing-date" value="' + (lead.closing_date ? lead.closing_date.replace(' ', 'T').substring(0, 16) : '') + '">';
        html += '</div>';

        // Follow-up Termin (only when followup)
        html += '<div id="crm-followup-section" style="display:' + (lead.status === 'followup' ? 'block' : 'none') + ';margin-bottom:12px">';
        html += '<label style="font-size:11px;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:.5px;font-weight:600;display:block;margin-bottom:6px">Follow-up Termin</label>';
        html += '<input type="datetime-local" class="crm-input" id="crm-followup-date" value="' + (lead.followup_date ? lead.followup_date.replace(' ', 'T').substring(0, 16) : '') + '">';
        html += '</div>';

        // Save fields button
        html += '<button class="crm-btn crm-btn-primary" id="crm-save-fields" style="width:100%;margin-bottom:12px">Felder speichern</button>';

        // Revenue (only for gewonnen)
        html += '<div class="crm-revenue-section" id="crm-revenue-section" style="display:' + (lead.status === 'gewonnen' ? 'block' : 'none') + '">';
        html += '<label style="font-size:11px;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:.5px;font-weight:600;display:block;margin-bottom:6px">Umsatz (Projekt)</label>';
        html += '<div style="display:flex;gap:8px">';
        html += '<input type="number" class="crm-input" id="crm-revenue-input" value="' + (lead.revenue || '') + '" placeholder="0" style="flex:1">';
        html += '<button class="crm-btn crm-btn-primary" id="crm-revenue-save">Speichern</button>';
        html += '</div>';
        html += '</div>';

        // Stats toggle in modal
        var isCountedInStats = lead.count_in_stats === undefined || lead.count_in_stats === null || parseInt(lead.count_in_stats) === 1;
        html += '<label class="stats-toggle" style="margin-top:16px">';
        html += '<input type="checkbox" id="crm-count-stats"' + (isCountedInStats ? ' checked' : '') + '>';
        html += '<span class="toggle-switch"></span>';
        html += '<div>';
        html += '<div class="toggle-label">In Ad-Statistiken z\u00e4hlen</div>';
        html += '<div class="toggle-hint">Deaktiviert = Empfehlung/Privat, nicht in KPIs</div>';
        html += '</div>';
        html += '</label>';

        html += '</div>'; // modal-left

        // Right side
        html += '<div class="crm-modal-right">';

        // Contact History
        html += '<div class="crm-section-title">Kontakt-Historie</div>';
        html += '<div class="crm-timeline" id="crm-timeline">';

        // First entry: Lead eingegangen
        html += '<div class="crm-tl-item">';
        html += '<div class="crm-tl-dot first"></div>';
        html += '<div class="crm-tl-date">' + formatDateTime(lead.created_at) + '</div>';
        html += '<span class="crm-tl-type system">Lead eingegangen</span>';
        html += '</div>';

        contacts.forEach(function(c) {
            var typeCls = (c.type || 'sonstiges').toLowerCase().replace(/[^a-z-]/g, '');
            html += '<div class="crm-tl-item">';
            html += '<div class="crm-tl-dot"></div>';
            html += '<div class="crm-tl-actions"><button class="crm-btn-danger crm-delete-contact" data-id="' + c.id + '">&times;</button></div>';
            html += '<div class="crm-tl-date">' + formatDateTime(c.contact_date) + '</div>';
            html += '<span class="crm-tl-type ' + typeCls + '">' + esc(c.type) + '</span>';
            if (c.note) html += '<div class="crm-tl-note">' + esc(c.note) + '</div>';
            html += '</div>';
        });

        html += '</div>';

        // Add contact
        html += '<button class="crm-btn crm-btn-ghost" id="crm-add-contact-btn" style="width:100%;margin-bottom:20px">+ Kontaktpunkt hinzuf\u00fcgen</button>';
        html += '<div class="crm-add-contact-form" id="crm-add-contact-form" style="display:none">';
        html += '<input type="datetime-local" class="crm-input" id="crm-contact-date" min="' + createdMin + '">';
        var contactTypes = [
            {v:'setting-call',l:'Setting-Call',c:'#22c55e'},
            {v:'closing-call',l:'Closing-Call',c:'#3b82f6'},
            {v:'nachgespraech',l:'Nachgespr\u00e4ch',c:'#a855f7'},
            {v:'anruf',l:'Anruf',c:'#f59e0b'},
            {v:'email',l:'E-Mail',c:'#06b6d4'},
            {v:'meeting',l:'Meeting',c:'#ec4899'},
            {v:'sonstiges',l:'Sonstiges',c:'rgba(255,255,255,0.3)'}
        ];
        html += '<div class="jd-dropdown" id="crm-contact-type" data-value="setting-call">';
        html += '<div class="jd-dropdown-trigger"><span><span class="jd-dot" style="display:inline-block;vertical-align:middle;margin-right:8px;background:#22c55e"></span>Setting-Call</span><svg class="jd-dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>';
        html += '<div class="jd-dropdown-menu">';
        contactTypes.forEach(function(ct) {
            html += '<div class="jd-dropdown-item' + (ct.v === 'setting-call' ? ' selected' : '') + '" data-value="' + ct.v + '"><span class="jd-dot" style="background:' + ct.c + '"></span>' + ct.l + '</div>';
        });
        html += '</div></div>';
        html += '<textarea class="crm-textarea" id="crm-contact-note" placeholder="Notiz zum Kontakt..."></textarea>';
        html += '<button class="crm-btn crm-btn-primary" id="crm-contact-save">Speichern</button>';
        html += '</div>';

        // Notes
        html += '<div class="crm-section-title" style="margin-top:20px">Notizen</div>';
        html += '<div id="crm-notes-list">';
        notes.forEach(function(n) {
            html += '<div class="crm-note-item">';
            html += '<div class="crm-note-del"><button class="crm-btn-danger crm-delete-note" data-id="' + n.id + '">&times;</button></div>';
            html += '<div class="crm-note-date">' + formatDateTime(n.created_at) + '</div>';
            html += '<div class="crm-note-text">' + esc(n.note) + '</div>';
            html += '</div>';
        });
        html += '</div>';

        html += '<div style="margin-top:12px">';
        html += '<textarea class="crm-textarea" id="crm-note-input" placeholder="Notiz hinzuf\u00fcgen..."></textarea>';
        html += '<button class="crm-btn crm-btn-primary" id="crm-note-save" style="margin-top:8px">Notiz speichern</button>';
        html += '</div>';

        html += '</div>'; // right
        html += '</div>'; // modal-body
        html += '</div>'; // modal
        html += '</div>'; // overlay

        document.body.insertAdjacentHTML('beforeend', html);

        var leadId = lead.id;

        // Close modal
        document.getElementById('crm-modal-close').addEventListener('click', closeModal);
        document.getElementById('crm-modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.addEventListener('keydown', escHandler);

        // Status change (custom dropdown)
        initDropdown('crm-status-dropdown', function(val) {
            document.getElementById('crm-revenue-section').style.display = val === 'gewonnen' ? 'block' : 'none';
            document.getElementById('crm-closing-section').style.display = val === 'qualifiziert' ? 'block' : 'none';
            document.getElementById('crm-followup-section').style.display = val === 'followup' ? 'block' : 'none';
            updateStatus(leadId, val);
        });

        // Contact type dropdown
        initDropdown('crm-contact-type');

        // Count in stats toggle
        document.getElementById('crm-count-stats').addEventListener('change', function() {
            fetch('/api/crm-data.php?action=update_lead', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: leadId, count_in_stats: this.checked ? 1 : 0 })
            }).then(function() { refreshAfterMutation(leadId); });
        });

        // Save fields (ziele, investitionspotenzial, closing_date, followup_date)
        document.getElementById('crm-save-fields').addEventListener('click', function() {
            var websiteVal = document.getElementById('crm-edit-website').value.trim();
            if (websiteVal) {
                if (!/^https?:\/\//i.test(websiteVal)) websiteVal = 'https://' + websiteVal;
                try {
                    var u = new URL(websiteVal);
                    if (!u.hostname.includes('.')) throw 0;
                } catch(e) {
                    document.getElementById('crm-edit-website').style.borderColor = '#ef4444';
                    document.getElementById('crm-edit-website').focus();
                    return;
                }
                document.getElementById('crm-edit-website').style.borderColor = '';
            }
            var payload = {
                id: leadId,
                name: document.getElementById('crm-edit-name').value.trim(),
                email: document.getElementById('crm-edit-email').value.trim() || null,
                phone: document.getElementById('crm-edit-phone').value.trim() || null,
                branche: document.getElementById('crm-edit-branche').value.trim() || null,
                website_url: websiteVal || null,
                source: document.getElementById('crm-edit-source').value.trim() || null,
                problem: document.getElementById('crm-edit-problem').value.trim() || null,
                ziele: document.getElementById('crm-edit-ziele').value.trim() || null,
                investitionspotenzial: document.getElementById('crm-invest-input').value.trim() || null,
                closing_date: document.getElementById('crm-closing-date').value ? document.getElementById('crm-closing-date').value.replace('T', ' ') + ':00' : null,
                followup_date: document.getElementById('crm-followup-date').value ? document.getElementById('crm-followup-date').value.replace('T', ' ') + ':00' : null
            };
            fetch('/api/crm-data.php?action=update_lead', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success) {
                    var btn = document.getElementById('crm-save-fields');
                    btn.textContent = 'Gespeichert!';
                    setTimeout(function() { btn.textContent = 'Felder speichern'; }, 1500);
                    refreshAll();
                }
            });
        });

        // Revenue
        document.getElementById('crm-revenue-save').addEventListener('click', function() {
            var rev = document.getElementById('crm-revenue-input').value;
            updateRevenue(leadId, rev);
        });

        // Add contact toggle
        document.getElementById('crm-add-contact-btn').addEventListener('click', function() {
            var form = document.getElementById('crm-add-contact-form');
            form.style.display = form.style.display === 'none' ? 'flex' : 'none';
        });

        // Save contact
        document.getElementById('crm-contact-save').addEventListener('click', function() {
            var date = document.getElementById('crm-contact-date').value;
            var type = document.getElementById('crm-contact-type').getAttribute('data-value');
            var note = document.getElementById('crm-contact-note').value;
            if (!date) return;
            addContact(leadId, date, type, note);
        });

        // Save note
        document.getElementById('crm-note-save').addEventListener('click', function() {
            var note = document.getElementById('crm-note-input').value.trim();
            if (!note) return;
            addNote(leadId, note);
        });

        // Delete contacts
        document.querySelectorAll('.crm-delete-contact').forEach(function(btn) {
            btn.addEventListener('click', function() {
                deleteContact(this.getAttribute('data-id'), leadId);
            });
        });

        // Delete notes
        document.querySelectorAll('.crm-delete-note').forEach(function(btn) {
            btn.addEventListener('click', function() {
                deleteNote(this.getAttribute('data-id'), leadId);
            });
        });
    }

    function closeModal() {
        var el = document.getElementById('crm-modal-overlay');
        if (el) el.remove();
        document.removeEventListener('keydown', escHandler);
    }

    function escHandler(e) {
        if (e.key === 'Escape') closeModal();
    }

    // ── API CALLS ───────────────────────────────────────────────────

    function updateStatus(id, status) {
        fetch('/api/crm-data.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status: status })
        }).then(function() { refreshAfterMutation(id); });
    }

    function updateRevenue(id, revenue) {
        fetch('/api/crm-data.php?action=update_revenue', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, revenue: revenue })
        }).then(function() { refreshAfterMutation(id); });
    }

    function addContact(leadId, date, type, note) {
        fetch('/api/crm-data.php?action=add_contact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lead_id: leadId, contact_date: date, type: type, note: note })
        }).then(function() { refreshAfterMutation(leadId); });
    }

    function addNote(leadId, note) {
        fetch('/api/crm-data.php?action=add_note', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lead_id: leadId, note: note })
        }).then(function() { refreshAfterMutation(leadId); });
    }

    function deleteContact(id, leadId) {
        fetch('/api/crm-data.php?action=delete_contact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        }).then(function() { refreshAfterMutation(leadId); });
    }

    function deleteNote(id, leadId) {
        fetch('/api/crm-data.php?action=delete_note', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        }).then(function() { refreshAfterMutation(leadId); });
    }

    function refreshAfterMutation(leadId) {
        refreshAll();
        if (leadId && document.getElementById('crm-modal-overlay')) {
            openLeadDetail(leadId);
        }
    }

    function refreshAll() {
        Promise.all([
            fetch('/api/crm-data.php?action=list').then(function(r) { return r.json(); }),
            fetch('/api/crm-data.php?action=crm_kpis').then(function(r) { return r.json(); })
        ]).then(function(results) {
            crmData = results[0].leads || [];
            crmKpis = results[1] || {};
            renderKpis();
            renderCRM();
        });
    }

    // ── HELPERS ─────────────────────────────────────────────────────

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function formatDate(str) {
        if (!str) return '\u2014';
        var d = new Date(str);
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function formatDateTime(str) {
        if (!str) return '\u2014';
        var d = new Date(str);
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' + d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    }

    function daysBetween(dateStr, now) {
        if (!dateStr) return 0;
        var d = new Date(dateStr);
        var diff = now.getTime() - d.getTime();
        return Math.floor(diff / (1000 * 60 * 60 * 24));
    }

    function infoItem(label, val) {
        return '<div class="crm-info-item"><label>' + label + '</label><div class="info-val">' + val + '</div></div>';
    }

    function infoItemFull(label, val) {
        return '<div class="crm-info-item full-width"><label>' + label + '</label><div class="info-val">' + val + '</div></div>';
    }

    function initDropdown(id, onChange) {
        var dd = document.getElementById(id);
        if (!dd) return;
        var trigger = dd.querySelector('.jd-dropdown-trigger');
        var menu = dd.querySelector('.jd-dropdown-menu');

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            // Close all other dropdowns
            document.querySelectorAll('.jd-dropdown.open').forEach(function(d) { if (d !== dd) d.classList.remove('open'); });
            dd.classList.toggle('open');
        });

        menu.querySelectorAll('.jd-dropdown-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                var val = this.getAttribute('data-value');
                dd.setAttribute('data-value', val);
                // Update trigger text
                trigger.querySelector('span').innerHTML = this.innerHTML;
                // Update selected state
                menu.querySelectorAll('.jd-dropdown-item').forEach(function(i) { i.classList.remove('selected'); });
                this.classList.add('selected');
                dd.classList.remove('open');
                if (onChange) onChange(val);
            });
        });

        // Close on outside click
        document.addEventListener('click', function() { dd.classList.remove('open'); });
    }

    function editItem(label, id, value, type) {
        return '<div class="crm-info-item"><label>' + label + '</label><input type="' + (type || 'text') + '" class="crm-input" id="' + id + '" value="' + esc(value) + '"></div>';
    }

    function editItemFull(label, id, value) {
        return '<div class="crm-info-item full-width"><label>' + label + '</label><textarea class="crm-textarea" id="' + id + '" style="min-height:40px">' + esc(value) + '</textarea></div>';
    }

    // ── Add Lead Modal ─────────────────────────────────────────
    window.openAddLeadModal = function() {
        var existing = document.getElementById('add-lead-overlay');
        if (existing) existing.remove();

        var html = '<div class="add-lead-overlay" id="add-lead-overlay">';
        html += '<div class="add-lead-modal">';
        html += '<h3>Lead anlegen</h3>';

        html += '<div class="form-row-half">';
        html += '<div class="form-row"><label>Name *</label><input type="text" class="crm-input" id="al-name" placeholder="Max Mustermann"></div>';
        html += '<div class="form-row"><label>Branche</label><input type="text" class="crm-input" id="al-branche" placeholder="z.B. Gastronomie"></div>';
        html += '</div>';

        html += '<div class="form-row-half">';
        html += '<div class="form-row"><label>E-Mail</label><input type="email" class="crm-input" id="al-email" placeholder="max@beispiel.de"></div>';
        html += '<div class="form-row"><label>Telefon</label><input type="text" class="crm-input" id="al-phone" placeholder="+49 ..."></div>';
        html += '</div>';

        html += '<div class="form-row"><label>Quelle</label><input type="text" class="crm-input" id="al-source" placeholder="z.B. WhatsApp, Empfehlung, Instagram" value="Manuell"></div>';

        html += '<div class="form-row"><label>Herausforderung / Problem</label><textarea class="crm-textarea" id="al-problem" placeholder="Was braucht der Kunde?"></textarea></div>';

        html += '<div class="form-row"><label>Ziele</label><textarea class="crm-textarea" id="al-ziele" placeholder="Was will der Kunde erreichen?"></textarea></div>';

        html += '<label class="stats-toggle">';
        html += '<input type="checkbox" id="al-count-stats" checked>';
        html += '<span class="toggle-switch"></span>';
        html += '<div>';
        html += '<div class="toggle-label">In Ad-Statistiken z\u00e4hlen</div>';
        html += '<div class="toggle-hint">Deaktiviert = wird nicht in KPIs gez\u00e4hlt (z.B. Empfehlungen)</div>';
        html += '</div>';
        html += '</label>';

        html += '<div class="add-lead-actions">';
        html += '<button class="crm-btn crm-btn-ghost" onclick="closeAddLeadModal()">Abbrechen</button>';
        html += '<button class="crm-btn crm-btn-primary" id="al-save-btn">Lead speichern</button>';
        html += '</div>';

        html += '</div></div>';

        document.body.insertAdjacentHTML('beforeend', html);

        document.getElementById('add-lead-overlay').addEventListener('click', function(e) {
            if (e.target === this) closeAddLeadModal();
        });
        document.addEventListener('keydown', addLeadEscHandler);
        document.getElementById('al-name').focus();

        document.getElementById('al-save-btn').addEventListener('click', function() {
            var name = document.getElementById('al-name').value.trim();
            if (!name) {
                document.getElementById('al-name').style.borderColor = '#ef4444';
                return;
            }
            var payload = {
                action: 'create_lead',
                name: name,
                email: document.getElementById('al-email').value.trim(),
                phone: document.getElementById('al-phone').value.trim(),
                branche: document.getElementById('al-branche').value.trim(),
                source: document.getElementById('al-source').value.trim() || 'Manuell',
                problem: document.getElementById('al-problem').value.trim(),
                ziele: document.getElementById('al-ziele').value.trim(),
                count_in_stats: document.getElementById('al-count-stats').checked ? 1 : 0
            };
            this.disabled = true;
            this.textContent = 'Speichern...';
            fetch('/api/crm-data.php?action=create_lead', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    closeAddLeadModal();
                    loadCRM();
                } else {
                    alert(d.error || 'Fehler beim Speichern');
                    document.getElementById('al-save-btn').disabled = false;
                    document.getElementById('al-save-btn').textContent = 'Lead speichern';
                }
            })
            .catch(function() {
                alert('Netzwerkfehler');
                document.getElementById('al-save-btn').disabled = false;
                document.getElementById('al-save-btn').textContent = 'Lead speichern';
            });
        });
    };

    window.closeAddLeadModal = function() {
        var el = document.getElementById('add-lead-overlay');
        if (el) el.remove();
        document.removeEventListener('keydown', addLeadEscHandler);
    };

    function addLeadEscHandler(e) {
        if (e.key === 'Escape') closeAddLeadModal();
    }
})();

/* ===== Settings ===== */
(function() {
    var currentUserEmail = '<?php echo htmlspecialchars($currentEmail); ?>';

    window.loadSettings = function() {
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'list_users' })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.users) renderUsers(d.users);
            else document.getElementById('users-table-wrap').innerHTML = '<div class="error-msg">Fehler beim Laden der Benutzer.</div>';
        }).catch(function() {
            document.getElementById('users-table-wrap').innerHTML = '<div class="error-msg">Verbindungsfehler.</div>';
        });
    };

    function renderUsers(users) {
        if (!users.length) {
            document.getElementById('users-table-wrap').innerHTML = '<div class="error-msg">Keine Benutzer vorhanden.</div>';
            return;
        }
        var html = '<table class="settings-table"><thead><tr><th>E-Mail</th><th>Erstellt</th><th style="text-align:right">Aktionen</th></tr></thead><tbody>';
        users.forEach(function(u) {
            html += '<tr>';
            html += '<td>' + escSettings(u.email) + '</td>';
            html += '<td>' + escSettings(u.created_at || '\u2014') + '</td>';
            html += '<td style="text-align:right">';
            html += '<button class="settings-pw-btn" onclick="togglePwReset(' + u.id + ')">Passwort</button>';
            if (u.email !== currentUserEmail) {
                html += '<button class="settings-delete-btn" onclick="deleteUser(' + u.id + ')">L\u00f6schen</button>';
            }
            html += '</td>';
            html += '</tr>';
            html += '<tr id="pw-reset-' + u.id + '" style="display:none"><td colspan="3">';
            html += '<div class="pw-reset-row">';
            html += '<div class="pw-wrap" style="flex:1"><input type="password" id="pw-new-' + u.id + '" placeholder="Neues Passwort (min. 8 Zeichen)"><button type="button" class="pw-toggle" onclick="togglePw(this)"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>';
            html += '<button onclick="resetUserPassword(' + u.id + ')">Speichern</button>';
            html += '</div>';
            html += '</td></tr>';
        });
        html += '</tbody></table>';
        document.getElementById('users-table-wrap').innerHTML = html;
    }

    function escSettings(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function showSettingsError(elId, msg) {
        var el = document.getElementById(elId);
        el.className = 'settings-msg error';
        el.textContent = msg;
    }

    function showSettingsSuccess(elId, msg) {
        var el = document.getElementById(elId);
        el.className = 'settings-msg success';
        el.textContent = msg;
    }

    window.changePassword = function() {
        var cur = document.getElementById('currentPass').value;
        var newP = document.getElementById('newPass').value;
        var confirmP = document.getElementById('confirmPass').value;
        if (!cur || !newP || !confirmP) { showSettingsError('settings-pass-msg', 'Bitte alle Felder ausfüllen.'); return; }
        if (newP !== confirmP) { showSettingsError('settings-pass-msg', 'Passwörter stimmen nicht überein.'); return; }
        if (newP.length < 8) { showSettingsError('settings-pass-msg', 'Mindestens 8 Zeichen.'); return; }
        var btn = document.getElementById('changePassBtn');
        btn.disabled = true;
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'change_password', current_password: cur, new_password: newP })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) {
                showSettingsSuccess('settings-pass-msg', 'Passwort geändert.');
                document.getElementById('currentPass').value = '';
                document.getElementById('newPass').value = '';
                document.getElementById('confirmPass').value = '';
            } else {
                showSettingsError('settings-pass-msg', d.error || 'Fehler beim Ändern.');
            }
            btn.disabled = false;
        }).catch(function() {
            showSettingsError('settings-pass-msg', 'Verbindungsfehler.');
            btn.disabled = false;
        });
    };

    window.addUser = function() {
        var email = document.getElementById('addUserEmail').value.trim();
        var pass = document.getElementById('addUserPass').value;
        if (!email || !pass) { showSettingsError('settings-user-msg', 'Bitte alle Felder ausfüllen.'); return; }
        if (pass.length < 8) { showSettingsError('settings-user-msg', 'Passwort muss mindestens 8 Zeichen haben.'); return; }
        var btn = document.getElementById('addUserBtn');
        btn.disabled = true;
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add_user', email: email, password: pass })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) {
                showSettingsSuccess('settings-user-msg', d.message || 'Best\u00e4tigungs-E-Mail wurde gesendet.');
                document.getElementById('addUserEmail').value = '';
                document.getElementById('addUserPass').value = '';
                loadSettings();
            } else {
                showSettingsError('settings-user-msg', d.error || 'Fehler beim Hinzufügen.');
            }
            btn.disabled = false;
        }).catch(function() {
            showSettingsError('settings-user-msg', 'Verbindungsfehler.');
            btn.disabled = false;
        });
    };

    window.togglePw = function(btn) {
        var input = btn.parentElement.querySelector('input');
        if (!input) return;
        var isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.innerHTML = isHidden
            ? '<svg viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
            : '<svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
    };

    window.togglePwReset = function(id) {
        var row = document.getElementById('pw-reset-' + id);
        if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
    };

    window.resetUserPassword = function(id) {
        var input = document.getElementById('pw-new-' + id);
        var pw = input ? input.value : '';
        if (pw.length < 8) { showSettingsError('settings-user-msg', 'Passwort muss mindestens 8 Zeichen lang sein.'); return; }
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reset_user_password', id: id, new_password: pw })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) {
                showSettingsSuccess('settings-user-msg', 'Passwort wurde ge\u00e4ndert.');
                document.getElementById('pw-reset-' + id).style.display = 'none';
                if (input) input.value = '';
            } else {
                showSettingsError('settings-user-msg', d.error || 'Fehler.');
            }
        }).catch(function() { showSettingsError('settings-user-msg', 'Verbindungsfehler.'); });
    };

    window.deleteUser = function(id) {
        if (!confirm('Benutzer wirklich löschen?')) return;
        fetch('/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_user', id: id })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) {
                loadSettings();
            } else {
                showSettingsError('settings-user-msg', d.error || 'Fehler beim Löschen.');
            }
        }).catch(function() {
            showSettingsError('settings-user-msg', 'Verbindungsfehler.');
        });
    };
})();
</script>

</body>
</html>
