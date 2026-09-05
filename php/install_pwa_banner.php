<?php
// Reusable PWA Installation Banner & Service Worker Registrar
?>
<!-- PWA Install Banner & Head Tags -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#16a34a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Canteen">
<link rel="apple-touch-icon" href="uploads/Burger.jpg">

<div id="pwaInstallBanner" style="
    display: none;
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: calc(100% - 32px);
    max-width: 480px;
    background: #0f172a;
    color: white;
    padding: 14px 18px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.35);
    z-index: 99999;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    animation: pwaSlideUp 0.4s ease;
    border: 1px solid rgba(255,255,255,0.1);
">
    <div style="display:flex; align-items:center; gap:12px;">
        <img src="uploads/Burger.jpg" alt="App Icon" style="width:42px; height:42px; border-radius:10px; object-fit:cover; border:2px solid #16a34a;">
        <div>
            <div style="font-weight:700; font-size:14px; color:#ffffff;">Install Canteen App</div>
            <div style="font-size:12px; color:#94a3b8;">Faster ordering & live tracking</div>
        </div>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
        <button id="pwaInstallBtn" type="button" style="
            background: #16a34a;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(22,163,74,0.3);
            white-space: nowrap;
        ">Install</button>
        <button id="pwaCloseBtn" type="button" aria-label="Close" style="
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 18px;
            cursor: pointer;
            padding: 4px 8px;
        ">&times;</button>
    </div>
</div>

<!-- iOS Specific Guide Modal -->
<div id="pwaIosModal" style="
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    z-index: 99999;
    justify-content: center;
    align-items: flex-end;
    padding: 20px;
">
    <div style="
        background: white;
        border-radius: 20px;
        padding: 24px;
        width: 100%;
        max-width: 400px;
        text-align: center;
        margin-bottom: 20px;
    ">
        <h3 style="font-size:18px; margin-bottom:10px; color:#0f172a;">Install on iPhone / iPad</h3>
        <p style="font-size:13px; color:#64748b; line-height:1.5; margin-bottom:16px;">
            1. Tap the <strong>Share</strong> button <span style="font-size:18px;">⎋</span> at the bottom of Safari.<br>
            2. Scroll down and tap <strong>Add to Home Screen ➕</strong>.<br>
            3. Tap <strong>Add</strong> at the top right.
        </p>
        <button onclick="document.getElementById('pwaIosModal').style.display='none'" style="
            width: 100%;
            padding: 12px;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
        ">Got It</button>
    </div>
</div>

<style>
@keyframes pwaSlideUp {
    from { transform: translate(-50%, 60px); opacity: 0; }
    to   { transform: translate(-50%, 0); opacity: 1; }
}
</style>

<script>
(function() {
    // 1. Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js').catch(() => {});
        });
    }

    // 2. Check if already installed in standalone mode
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (isStandalone) return; // Already running as installed app

    // 3. Android / Chrome / Edge installation prompt handler
    let deferredPrompt = null;
    const banner = document.getElementById('pwaInstallBanner');
    const installBtn = document.getElementById('pwaInstallBtn');
    const closeBtn = document.getElementById('pwaCloseBtn');

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        // Check if user dismissed recently (24h)
        const dismissedAt = localStorage.getItem('pwa_dismissed_at');
        if (dismissedAt && (Date.now() - parseInt(dismissedAt)) < 86400000) {
            return;
        }

        if (banner) {
            banner.style.display = 'flex';
        }
    });

    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    console.log('User accepted PWA installation');
                }
                deferredPrompt = null;
                if (banner) banner.style.display = 'none';
            } else {
                // iOS Detection fallback
                const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
                if (isIos) {
                    const iosModal = document.getElementById('pwaIosModal');
                    if (iosModal) iosModal.style.display = 'flex';
                }
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            if (banner) banner.style.display = 'none';
            localStorage.setItem('pwa_dismissed_at', Date.now().toString());
        });
    }
})();
</script>
