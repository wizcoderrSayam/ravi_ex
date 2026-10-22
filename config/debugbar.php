<?php

// Only the keys that differ from the debugbar defaults; the package fills in the rest.
return [

    // Boots the debugbar even with APP_DEBUG off, so the CMS's Debug mode (Settings → General
    // → App) can turn it on per request (AppServiceProvider). Never in production or tests.
    'force_allow_enable' => env('DEBUGBAR_FORCE_ALLOW_ENABLE', ! in_array(env('APP_ENV', 'production'), ['production', 'testing'], true)),

];
