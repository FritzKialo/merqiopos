(function() {
    const THEME_KEY = 'sme_theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        const icon = document.getElementById('theme-toggle-icon');
        if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
    }

    // Apply saved theme immediately (before paint to avoid flash)
    const saved = localStorage.getItem(THEME_KEY);
    const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    applyTheme(saved || preferred);

    window.toggleTheme = function() {
        const current = document.documentElement.getAttribute('data-theme');
        applyTheme(current === 'dark' ? 'light' : 'dark');
    };

    // Sync across tabs
    window.addEventListener('storage', function(e) {
        if (e.key === THEME_KEY) applyTheme(e.newValue);
    });
})();

// PWA install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferredPrompt = e;
    const banner = document.getElementById('pwa-install-banner');
    if (banner) banner.style.display = 'flex';
});

window.installPwa = function() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function() { deferredPrompt = null; });
    }
    const banner = document.getElementById('pwa-install-banner');
    if (banner) banner.style.display = 'none';
};
