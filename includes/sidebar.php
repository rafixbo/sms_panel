<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function navActive($page) {
    global $currentPage;
    return $currentPage === $page ? ' active' : '';
}
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-mark">
            <div class="logo-icon-sm">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <div class="logo-text">
                <strong><?= APP_NAME ?></strong>
                <small>v<?= APP_VERSION ?></small>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-label">Overview</span>
            <a href="/dashboard.php" class="nav-link<?= navActive('dashboard') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                </span> Dashboard
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">API Management</span>
            <a href="/keys.php" class="nav-link<?= navActive('keys') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                </span> API Keys
            </a>
            <a href="/keys.php?action=new" class="nav-link<?= ($currentPage==='keys' && isset($_GET['action']) && $_GET['action']==='new') ? ' active' : '' ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </span> Create New Key
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Gateway & Proxy</span>
            <a href="/gateways.php" class="nav-link<?= navActive('gateways') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </span> Gateways
            </a>
            <a href="/proxies.php" class="nav-link<?= navActive('proxies') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span> Proxies
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">Analytics</span>
            <a href="/logs.php" class="nav-link<?= navActive('logs') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </span> SMS Logs
            </a>
            <a href="/stats.php" class="nav-link<?= navActive('stats') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </span> Statistics
            </a>
            <a href="/points.php" class="nav-link<?= navActive('points') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </span> Points History
            </a>
        </div>
        <div class="nav-section">
            <span class="nav-section-label">System</span>
            <a href="/system_logs.php" class="nav-link<?= navActive('system_logs') ?>">
                <span class="icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span> System Logs
            </a>
        </div>
    </nav>
    <div class="sidebar-footer">
        v<?= APP_VERSION ?> · <?= date('Y') ?>
    </div>
</aside>
