<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<style>
/* ============================================
   SMS PORTAL — ELEGANT STYLESHEET (v3.0)
   Clean, professional, purple-blue palette
   ============================================ */
:root {
    --bg: #f0f2f5;
    --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --surface: #ffffff;
    --surface2: #f8f9fb;
    --border: #e8ecf1;
    --border2: #d1d9e6;
    --primary: #5e35b1;
    --primary-light: #7c4dff;
    --primary-bg: rgba(94,53,177,0.06);
    --primary-bg2: rgba(94,53,177,0.1);
    --secondary: #2196f3;
    --secondary-bg: rgba(33,150,243,0.08);
    --accent: #00bcd4;
    --accent-bg: rgba(0,188,212,0.08);
    --pink: #e91e63;
    --pink-bg: rgba(233,30,99,0.08);
    --green: #4caf50;
    --green-bg: rgba(76,175,80,0.08);
    --orange: #ff9800;
    --orange-bg: rgba(255,152,0,0.08);
    --red: #f44336;
    --red-bg: rgba(244,67,54,0.08);
    --text: #1a1a2e;
    --text2: #5c6370;
    --text3: #8c95a6;
    --muted: #b0b8c4;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
    --shadow-md: 0 4px 14px rgba(0,0,0,0.08);
    --shadow-lg: 0 8px 30px rgba(0,0,0,0.12);
    --sidebar-w: 260px;
    --topbar-h: 64px;
    --radius: 12px;
    --radius-sm: 8px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; scroll-behavior: smooth; }
body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    min-height: 100vh;
    display: flex;
}

/* ======= SCROLLBAR ======= */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--muted); }

/* ======= SIDEBAR ======= */
.sidebar {
    width: var(--sidebar-w);
    background: linear-gradient(180deg, #1a1040 0%, #2d1b69 40%, #5e35b1 100%);
    min-height: 100vh;
    position: fixed; top: 0; left: 0; z-index: 100;
    display: flex; flex-direction: column;
    transition: transform 0.3s;
    box-shadow: 4px 0 20px rgba(94,53,177,0.15);
}
.sidebar-logo {
    padding: 24px 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.logo-mark {
    display: flex; align-items: center; gap: 12px;
}
.logo-icon-sm {
    width: 38px; height: 38px;
    background: rgba(255,255,255,0.15);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    backdrop-filter: blur(10px);
}
.logo-text { line-height: 1.2; }
.logo-text strong { font-size: 14px; font-weight: 700; color: #fff; }
.logo-text small {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px; color: rgba(255,255,255,0.5);
    display: block; text-transform: uppercase; letter-spacing: 1px;
}
.sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
.nav-section { margin-bottom: 24px; }
.nav-section-label {
    font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.35);
    text-transform: uppercase; letter-spacing: 2px;
    padding: 0 12px; margin-bottom: 8px; display: block;
}
.nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: rgba(255,255,255,0.6);
    font-size: 13px; font-weight: 500;
    transition: all 0.2s;
    margin-bottom: 2px;
}
.nav-link:hover { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.9); }
.nav-link.active {
    background: rgba(255,255,255,0.15);
    color: #fff; font-weight: 600;
}
.nav-link .icon { width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; opacity: 0.7; }
.nav-link.active .icon { opacity: 1; }
.nav-link .badge-count {
    margin-left: auto;
    background: rgba(255,255,255,0.15);
    color: #fff; font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 20px;
}
.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(255,255,255,0.08);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px; color: rgba(255,255,255,0.3);
}

/* ======= MAIN CONTENT ======= */
.main-content {
    margin-left: var(--sidebar-w);
    flex: 1;
    min-height: 100vh;
    display: flex; flex-direction: column;
}
.topbar {
    height: var(--topbar-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center;
    padding: 0 28px;
    gap: 16px;
    position: sticky; top: 0; z-index: 50;
    box-shadow: var(--shadow-sm);
}
.topbar-title { font-size: 14px; color: var(--text2); flex: 1; font-weight: 500; }
.topbar-actions { display: flex; align-items: center; gap: 12px; }
.topbar-time {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px; color: var(--text3);
}
.btn-logout {
    background: var(--red-bg);
    border: 1px solid rgba(244,67,54,0.15);
    color: var(--red);
    font-family: 'Inter', sans-serif;
    font-size: 12px; font-weight: 600;
    padding: 7px 16px; border-radius: 8px;
    cursor: pointer; text-decoration: none;
    transition: all 0.2s;
}
.btn-logout:hover { background: rgba(244,67,54,0.15); border-color: rgba(244,67,54,0.3); }

/* ======= PAGE BODY ======= */
.page-body { padding: 28px; flex: 1; }
.page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 28px;
}
.page-title { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: var(--text); }
.page-sub { font-size: 13px; color: var(--text3); margin-top: 4px; }
.header-actions { display: flex; gap: 10px; align-items: center; }
.badge-live {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px; color: var(--green);
    background: var(--green-bg);
    border: 1px solid rgba(76,175,80,0.2);
    padding: 5px 12px; border-radius: 20px;
    animation: livePulse 2s infinite;
}
@keyframes livePulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

/* ======= STATS GRID ======= */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px; margin-bottom: 24px;
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px;
    display: flex; align-items: flex-start; gap: 16px;
    position: relative; overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: var(--shadow-sm);
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
}
.accent-green::before { background: var(--green); }
.accent-purple::before { background: var(--primary); }
.accent-cyan::before { background: var(--secondary); }
.accent-orange::before { background: var(--orange); }
.stat-icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.accent-green .stat-icon { background: var(--green-bg); color: var(--green); }
.accent-purple .stat-icon { background: var(--primary-bg2); color: var(--primary); }
.accent-cyan .stat-icon { background: var(--secondary-bg); color: var(--secondary); }
.accent-orange .stat-icon { background: var(--orange-bg); color: var(--orange); }
.stat-info { flex: 1; }
.stat-val { font-size: 28px; font-weight: 800; letter-spacing: -1px; line-height: 1; color: var(--text); }
.stat-label { font-size: 12px; color: var(--text3); margin-top: 4px; font-weight: 500; }
.stat-sub { font-size: 11px; color: var(--primary); font-family: 'JetBrains Mono', monospace; margin-top: 4px; }

/* ======= CARDS ======= */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.2s;
}
.card:hover { box-shadow: var(--shadow-md); }
.card-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px;
}
.card-head h3 { font-size: 15px; font-weight: 700; color: var(--text); }
.btn-link {
    font-size: 12px; color: var(--primary);
    text-decoration: none; font-weight: 600;
    transition: opacity 0.2s;
}
.btn-link:hover { opacity: 0.7; }
.two-col { display: grid; grid-template-columns: 1fr 380px; gap: 24px; }
.card-wide { margin-bottom: 0; }

/* ======= TABLE ======= */
.table-wrap { overflow-x: auto; }
.data-table {
    width: 100%; border-collapse: collapse;
}
.data-table th {
    font-size: 11px; font-weight: 600; color: var(--text3);
    text-transform: uppercase; letter-spacing: 0.8px;
    padding: 10px 12px; text-align: left;
    border-bottom: 2px solid var(--border);
    background: var(--surface2);
}
.data-table td {
    padding: 12px 12px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--surface2); }
.empty-row { text-align: center; color: var(--text3); padding: 32px; }

/* ======= BADGES ======= */
.badge {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px; font-weight: 600;
    padding: 3px 8px; border-radius: 4px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.badge-active, .badge-success { background: var(--green-bg); color: var(--green); border: 1px solid rgba(76,175,80,0.2); }
.badge-suspended, .badge-failed { background: var(--red-bg); color: var(--red); border: 1px solid rgba(244,67,54,0.2); }
.badge-expired, .badge-inactive { background: var(--surface2); color: var(--text3); border: 1px solid var(--border); }
.badge-pending { background: var(--orange-bg); color: var(--orange); border: 1px solid rgba(255,152,0,0.2); }

/* ======= KEY ITEMS ======= */
.key-item {
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
}
.key-item:last-child { border-bottom: none; }
.key-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.key-name { font-size: 13px; font-weight: 600; color: var(--text); }
.key-owner { font-size: 11px; margin-bottom: 8px; }
.pts-bar-wrap { display: flex; align-items: center; gap: 8px; }
.pts-bar { flex: 1; height: 4px; background: var(--border); border-radius: 2px; }
.pts-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); border-radius: 2px; }
.pts-label { font-size: 11px; color: var(--text3); font-family: 'JetBrains Mono', monospace; min-width: 50px; text-align: right; }

/* ======= API EXAMPLE ======= */
.api-example { background: var(--surface2); border-radius: 10px; overflow: hidden; border: 1px solid var(--border); }
.api-url {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}
.method-get {
    background: var(--green-bg); color: var(--green);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 4px;
    border: 1px solid rgba(76,175,80,0.2);
    flex-shrink: 0;
}
.method-post {
    background: var(--primary-bg2); color: var(--primary);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 4px;
    border: 1px solid rgba(94,53,177,0.2);
    flex-shrink: 0;
}
.api-url code {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px; color: var(--text2);
    word-break: break-all;
}
.api-url code .hl { color: var(--primary); font-weight: 600; }
.api-resp pre {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px; color: var(--text2);
    padding: 16px; line-height: 1.7;
    overflow-x: auto;
}

/* ======= FORMS ======= */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block; font-size: 11px; font-weight: 600;
    color: var(--text2); text-transform: uppercase;
    letter-spacing: 1px; margin-bottom: 8px;
}
.form-control {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 10px 14px;
    color: var(--text);
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    outline: none;
    transition: all 0.2s;
}
.form-control:hover { border-color: var(--border2); }
.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-bg);
}
select.form-control {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%235e35b1' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 36px;
}
select.form-control option {
    background: #fff;
    color: var(--text);
    padding: 10px 14px;
}
.form-control.mono { font-family: 'JetBrains Mono', monospace; font-size: 12px; }

/* ======= BUTTONS ======= */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 9px 20px; border-radius: var(--radius-sm);
    font-family: 'Inter', sans-serif; font-size: 13px; font-weight: 600;
    cursor: pointer; border: none; transition: all 0.2s;
    text-decoration: none; white-space: nowrap;
}
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: #fff;
    box-shadow: 0 2px 8px rgba(94,53,177,0.25);
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(94,53,177,0.35);
    filter: brightness(1.05);
}
.btn-primary:active { transform: translateY(0); }
.btn-danger {
    background: var(--red-bg);
    color: var(--red);
    border: 1px solid rgba(244,67,54,0.2);
}
.btn-danger:hover {
    background: rgba(244,67,54,0.15);
    border-color: rgba(244,67,54,0.4);
}
.btn-secondary {
    background: var(--surface);
    color: var(--text2);
    border: 1px solid var(--border);
}
.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-bg);
}
.btn-sm {
    padding: 5px 12px; font-size: 12px; border-radius: 6px;
}

/* ======= CHECKBOX ======= */
.checkbox-label {
    display: flex; align-items: center; gap: 10px;
    cursor: pointer; font-size: 13px; font-weight: 500; color: var(--text);
}
.checkbox-label input[type="checkbox"] {
    width: 18px; height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}

/* ======= ALERTS ======= */
.alert { padding: 14px 18px; border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 20px; font-weight: 500; }
.alert-success { background: var(--green-bg); border: 1px solid rgba(76,175,80,0.2); color: var(--green); }
.alert-danger { background: var(--red-bg); border: 1px solid rgba(244,67,54,0.2); color: var(--red); }
.alert-warning { background: var(--orange-bg); border: 1px solid rgba(255,152,0,0.2); color: var(--orange); }

/* ======= UTILS ======= */
.mono { font-family: 'JetBrains Mono', monospace; font-size: 12px; }
.muted { color: var(--text3); }
.copy-btn {
    background: var(--surface2); border: 1px solid var(--border); color: var(--text2);
    font-family: 'JetBrains Mono', monospace; font-size: 10px;
    padding: 3px 8px; border-radius: 4px; cursor: pointer;
    transition: all 0.15s;
}
.copy-btn:hover { background: var(--primary-bg); color: var(--primary); border-color: rgba(94,53,177,0.3); }
.empty-state { text-align: center; padding: 40px; color: var(--text3); font-size: 13px; }
.empty-state a { color: var(--primary); }
.key-display {
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 8px 12px;
    font-family: 'JetBrains Mono', monospace; font-size: 12px;
    color: var(--primary); letter-spacing: 0.5px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; word-break: break-all;
}
.page-footer {
    padding: 16px 28px;
    border-top: 1px solid var(--border);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px; color: var(--text3);
    display: flex; justify-content: space-between;
    background: var(--surface);
}

/* ======= TOAST NOTIFICATION ======= */
.toast-container {
    position: fixed; top: 20px; right: 20px; z-index: 10000;
    display: flex; flex-direction: column; gap: 8px;
}
.toast {
    min-width: 300px; max-width: 450px;
    padding: 14px 20px;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 13px; font-weight: 500;
    box-shadow: var(--shadow-lg);
    animation: toastIn 0.3s ease both;
    display: flex; align-items: center; gap: 10px;
}
.toast-success { background: #fff; border: 1px solid rgba(76,175,80,0.3); color: var(--green); }
.toast-error { background: #fff; border: 1px solid rgba(244,67,54,0.3); color: var(--red); }
.toast-info { background: #fff; border: 1px solid rgba(94,53,177,0.3); color: var(--primary); }
.toast-close {
    margin-left: auto; background: none; border: none;
    color: inherit; cursor: pointer; font-size: 16px; opacity: 0.4;
}
.toast-close:hover { opacity: 1; }
@keyframes toastIn { from { opacity:0; transform:translateX(30px); } to { opacity:1; transform:translateX(0); } }
@keyframes toastOut { from { opacity:1; transform:translateX(0); } to { opacity:0; transform:translateX(30px); } }

/* ======= MODAL ======= */
.modal-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.4);
    display: flex; align-items: center; justify-content: center;
    animation: fadeIn 0.2s ease;
    backdrop-filter: blur(4px);
}
.modal-content {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    width: 100%; max-width: 640px;
    max-height: 90vh; overflow-y: auto;
    box-shadow: var(--shadow-lg);
    animation: fadeUp 0.3s ease both;
}
.modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 24px 28px 16px;
    border-bottom: 1px solid var(--border);
}
.modal-header h3 { font-size: 18px; font-weight: 700; color: var(--primary); }
.modal-close {
    background: var(--surface2); border: 1px solid var(--border); color: var(--text2);
    font-size: 18px; width: 32px; height: 32px;
    border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.modal-close:hover { background: var(--red-bg); color: var(--red); border-color: rgba(244,67,54,0.3); }
.modal-body { padding: 24px 28px; }

/* ======= CODE AREA (for gateways/proxies) ======= */
.code-area {
    background: #1a1a2e;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 14px;
    color: #e8e8f2;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    line-height: 1.6;
    width: 100%;
    min-height: 100px;
    resize: vertical;
    outline: none;
    tab-size: 2;
}
.code-area:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-bg); }
.hint-box {
    background: var(--primary-bg);
    border: 1px solid rgba(94,53,177,0.12);
    border-radius: var(--radius-sm);
    padding: 14px 16px;
    margin-top: 8px;
    font-size: 12px;
    line-height: 1.7;
    color: var(--text2);
}
.hint-box code {
    background: var(--surface);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: var(--primary);
    border: 1px solid var(--border);
}
.hint-box .hint-title {
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 4px;
    font-size: 12px;
}
.tab-group { display: flex; gap: 0; margin-bottom: 16px; }
.tab-btn {
    padding: 8px 18px;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text3);
    font-family: 'Inter', sans-serif;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.tab-btn:first-child { border-radius: 8px 0 0 8px; }
.tab-btn:last-child { border-radius: 0 8px 8px 0; }
.tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.tab-content { display: none; }
.tab-content.active { display: block; }
.gw-test-result {
    background: #1a1a2e;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 16px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    line-height: 1.7;
    color: #e8e8f2;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

/* ======= RESPONSIVE ======= */
@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2,1fr); }
    .two-col { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .main-content { margin-left: 0; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .form-grid { grid-template-columns: 1fr; }
    .page-body { padding: 16px; }
    .modal-content { margin: 16px; max-width: calc(100% - 32px); }
}

/* Animations */
.fade-in { animation: fadeIn 0.4s ease both; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

/* Search bar */
.search-bar {
    display: flex; align-items: center; gap: 10px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 8px 14px;
}
.search-bar input {
    background: none; border: none; outline: none;
    color: var(--text); font-family: 'Inter', sans-serif;
    font-size: 13px; width: 200px;
}
.search-bar input::placeholder { color: var(--muted); }
</style>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ======= TOAST NOTIFICATION SYSTEM =======
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icons = { success: '\u2713', error: '\u2717', info: '\u2139' };
    toast.innerHTML = `<span>${icons[type] || '\u2139'}</span><span>${message}</span><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'toastOut 0.3s ease both';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        showToast('Copied to clipboard!', 'success');
        setTimeout(() => btn.textContent = 'Copy', 1500);
    });
}

// Live clock
setInterval(() => {
    const el = document.getElementById('clock');
    if (el) el.textContent = new Date().toLocaleTimeString('en-BD', {hour12:false});
}, 1000);
</script>
