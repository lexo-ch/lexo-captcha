const mix = require('laravel-mix');

mix
  .setPublicPath('dist')
  .browserSync({
    proxy: "http://test.test",
    files: [
      'dist/**/**',
      '**/*.php',
    ],
  });

mix
  .js('resources/scripts/admin-lexocaptcha.js', 'js')
  .js('resources/scripts/lexo-captcha.js', 'js')
  .sass('resources/styles/admin-lexocaptcha.scss', 'css')
  .options({
    processCssUrls: false,
  });

mix
  .version()
  .sourceMaps();
