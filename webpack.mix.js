const mix = require('laravel-mix');

mix
	.setPublicPath('dist');

mix
	.js('resources/scripts/front.js', 'js')
  .options({
    processCssUrls: false,
  });

mix
  .version()
  .sourceMaps();
