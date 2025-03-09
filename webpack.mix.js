let mix = require('laravel-mix');

// Copy FontAwesome CSS and Webfonts
mix.copy('node_modules/@fortawesome/fontawesome-free/css/all.min.css', 'public/css/fontawesome.css')
   .copy('node_modules/@fortawesome/fontawesome-free/webfonts', 'public/webfonts')
   .copy('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'public/js/bootstrap.bundle.min.js')
   .copy('node_modules/jquery/dist/jquery.min.js', 'public/js/jquery.min.js')
   .copy('node_modules/jquery.easing/jquery.easing.min.js', 'public/js/jquery.easing.min.js');

mix.js('resources/js/app.js', 'public/js')
   .js('resources/js/calendar.js', 'public/js')
   .js('resources/js/sb-admin-2.js', 'public/js/sb-admin-2.js')
   .js('resources/js/sb-admin-2.min.js', 'public/js/sb-admin-2.min.js')
   .css('resources/css/app.css', 'public/css')
   .css('resources/css/sb-admin-2.css', 'public/css/sb-admin-2.css')
   .css('resources/css/sb-admin-2.min.css', 'public/css/sb-admin-2.min.css')
   .copyDirectory('resources/scss', 'public/scss')
   .copyDirectory('resources/img', 'public/img')
   .version();

// Enable versioning (cache busting in production)
if (mix.inProduction()) {
   mix.version();
}