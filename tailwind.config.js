const defaultTheme = require('tailwindcss/defaultTheme');
const plugin = require('tailwindcss/plugin');
const palette = require('./resources/theme/palette');

module.exports = {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            backgroundColor: palette.utilities.background,
            gradientColorStops: palette.utilities.background,
            textColor: palette.utilities.text,
            borderColor: { DEFAULT: palette.utilities.border.gray[200], ...palette.utilities.border },
            divideColor: palette.utilities.border,
            ringColor: palette.utilities.border,
            ringOffsetColor: palette.utilities.background,
            fontFamily: {
                sans: ['Nunito', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [require('@tailwindcss/forms'), plugin(({ addBase }) => {
        addBase({
            ':root': { ...palette.light, 'color-scheme': 'light' },
            ':root[data-theme="dark"]': { ...palette.dark, 'color-scheme': 'dark' },
        });
    })],
};
