const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('resources/js/theme.js', 'utf8');

function boot({ stored = null, dark = false, blocked = false } = {}) {
    const events = {};
    const root = { dataset: {}, style: {} };
    const attributes = {};
    const button = { setAttribute: (key, value) => { attributes[key] = value; } };
    const storage = new Map(stored === null ? [] : [['vertexpl-theme', stored]]);
    const system = { matches: dark, addEventListener: (_, fn) => { events.system = fn; } };
    vm.runInNewContext(source, {
        document: {
            documentElement: root,
            querySelectorAll: () => [button],
            addEventListener: (name, fn) => { events[name] = fn; },
        },
        window: { matchMedia: () => system, addEventListener: (name, fn) => { events[name] = fn; } },
        localStorage: {
            getItem: (key) => { if (blocked) throw Error('blocked'); return storage.get(key) ?? null; },
            setItem: (key, value) => { if (blocked) throw Error('blocked'); storage.set(key, value); },
        },
    });
    return { root, system, storage, events, attributes, toggle: () => events.click({ target: { closest: () => button } }) };
}

test('system preference is applied before DOMContentLoaded', () => {
    assert.equal(boot({ dark: true }).root.dataset.theme, 'dark');
    assert.equal(boot().root.dataset.theme, 'light');
});
test('saved preference overrides the system and survives reload', () => {
    const app = boot({ dark: true, stored: 'light' });
    assert.equal(app.root.dataset.theme, 'light');
    app.toggle();
    assert.equal(app.storage.get('vertexpl-theme'), 'dark');
    assert.equal(boot({ stored: app.storage.get('vertexpl-theme') }).root.dataset.theme, 'dark');
});
test('invalid preference falls back to system', () => {
    assert.equal(boot({ stored: 'invalid', dark: true }).root.dataset.theme, 'dark');
});
test('blocked storage does not break theme selection', () => {
    const app = boot({ blocked: true });
    app.toggle();
    assert.equal(app.root.dataset.theme, 'dark');
});
test('system changes apply only before a manual selection', () => {
    const app = boot();
    app.system.matches = true;
    app.events.system();
    assert.equal(app.root.dataset.theme, 'dark');
    app.toggle();
    app.events.system();
    assert.equal(app.root.dataset.theme, 'light');
});
test('other tabs update preference and clearing storage restores system preference', () => {
    const app = boot();
    app.events.storage({ key: 'vertexpl-theme', newValue: 'dark' });
    assert.equal(app.root.dataset.theme, 'dark');
    app.events.storage({ key: null, newValue: null });
    assert.equal(app.root.dataset.theme, 'light');
});
test('toggle accessibility and native color scheme follow the theme', () => {
    const app = boot();
    app.toggle();
    assert.equal(app.attributes['aria-pressed'], 'true');
    assert.equal(app.root.style.colorScheme, 'dark');
    assert.match(app.attributes['aria-label'], /Light Mode/);
});

test('dark body, muted text, alerts and primary buttons have readable contrast', () => {
    const { dark } = require('../../resources/theme/palette');
    const luminance = (rgb) => rgb.split(' ').map(Number).map(c => {
        c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    }).reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
    for (const [text, background] of [
        ['slate-900', 'white'], ['slate-500', 'white'], ['indigo-600', 'indigo-50'],
        ['red-700', 'red-50'], ['amber-800', 'amber-50'], ['emerald-700', 'emerald-50'],
        ['white', 'indigo-600'],
    ]) {
        const a = luminance(dark[`--theme-text-${text}`]);
        const b = luminance(dark[`--theme-background-${background}`]);
        assert.ok((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) >= 4.5, `${text} on ${background}`);
    }
});
