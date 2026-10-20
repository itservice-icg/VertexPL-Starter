// This file is also included synchronously in <head>, before styles load.
(() => {
    const root = document.documentElement;
    const key = 'vertexpl-theme';
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    let preference;
    try { preference = localStorage.getItem(key); } catch (_) { /* Storage can be blocked. */ }
    if (!['light', 'dark'].includes(preference)) preference = null;

    const apply = (theme) => {
        root.dataset.theme = theme;
        root.style.colorScheme = theme;
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const isDark = theme === 'dark';
            button.setAttribute('aria-pressed', String(isDark));
            button.title = isDark ? 'เปลี่ยนเป็น Light Mode' : 'เปลี่ยนเป็น Dark Mode';
            button.setAttribute('aria-label', button.title);
        });
    };
    apply(preference || (system.matches ? 'dark' : 'light'));

    document.addEventListener('DOMContentLoaded', () => apply(root.dataset.theme));
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-theme-toggle]')) return;
        preference = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem(key, preference); } catch (_) { /* Keep the in-memory choice. */ }
        apply(preference);
    });
    system.addEventListener('change', () => {
        if (!preference) apply(system.matches ? 'dark' : 'light');
    });
    window.addEventListener('storage', (event) => {
        if (event.key !== key && event.key !== null) return;
        preference = ['light', 'dark'].includes(event.newValue) ? event.newValue : null;
        apply(preference || (system.matches ? 'dark' : 'light'));
    });
})();
