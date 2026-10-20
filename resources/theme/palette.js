const colors = require('tailwindcss/colors');

// Keep Light values identical to Tailwind; each role has its own Dark palette.
const families = ['slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];
const neutral = new Set(families.slice(0, 5));
const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
const rgb = (hex) => hex.replace('#', '').match(/../g).map((part) => parseInt(part, 16)).join(' ');
const light = {};
const dark = {};
const utilities = {};
light['--theme-danger-surface'] = 'rgb(255 247 247)';
dark['--theme-danger-surface'] = 'rgb(69 10 10)';

for (const role of ['background', 'text', 'border']) {
    utilities[role] = { white: `rgb(var(--theme-${role}-white) / <alpha-value>)` };
    light[`--theme-${role}-white`] = '255 255 255';
    dark[`--theme-${role}-white`] = role === 'background' ? '30 41 59' : '255 255 255';
    for (const family of families) {
        utilities[role][family] = {};
        for (const shade of shades) {
            const hex = colors[family][shade];
            if (!hex) continue;
            let darkShade = shade;
            if (role === 'text') {
                darkShade = neutral.has(family)
                    ? ({ 500: 400, 600: 300, 700: 200, 800: 100, 900: 100, 950: 50 }[shade] || shade)
                    : ({ 500: 400, 600: 300, 700: 300, 800: 200, 900: 100, 950: 100 }[shade] || shade);
            } else if (role === 'background') {
                darkShade = ({ 50: 900, 100: 900, 200: 800, 300: 700 }[shade] || shade);
            } else {
                darkShade = ({ 50: 800, 100: 700, 200: 700, 300: 600 }[shade] || shade);
            }
            const name = `--theme-${role}-${family}-${shade}`;
            light[name] = rgb(hex);
            dark[name] = rgb(colors[family][darkShade] || hex);
            utilities[role][family][shade] = `rgb(var(${name}) / <alpha-value>)`;
        }
    }
}

module.exports = { light, dark, utilities, families, rgb };
