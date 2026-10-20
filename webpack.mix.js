const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js').postCss('resources/css/app.css', 'public/css', [
    require('tailwindcss'),
    require('autoprefixer'),
]).copy('node_modules/@phosphor-icons/web/src/regular/style.css', 'public/css/phosphor-icons.css')
    .copy('resources/css/fonts.css', 'public/css/fonts.css')
    .copy('resources/fonts/Nunito-Regular.ttf', 'public/fonts/Nunito-Regular.ttf')
    .copy('resources/fonts/Nunito-Medium.ttf', 'public/fonts/Nunito-Medium.ttf')
    .copy('resources/fonts/Nunito-SemiBold.ttf', 'public/fonts/Nunito-SemiBold.ttf')
    .copy('resources/fonts/Nunito-Bold.ttf', 'public/fonts/Nunito-Bold.ttf')
    .copy('node_modules/@phosphor-icons/web/src/regular/Phosphor.woff2', 'public/css/Phosphor.woff2')
    .copy('node_modules/@phosphor-icons/web/src/regular/Phosphor.woff', 'public/css/Phosphor.woff')
    .copy('node_modules/@phosphor-icons/web/src/regular/Phosphor.ttf', 'public/css/Phosphor.ttf')
    .copy('node_modules/@phosphor-icons/web/src/regular/Phosphor.svg', 'public/css/Phosphor.svg')
    .copy('node_modules/sweetalert2/dist/sweetalert2.min.css', 'public/css/sweetalert2.css')
    .version();
