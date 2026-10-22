<?php

use Peer\Ecommerce\Http\Middleware\EnsureAdmin;

// Only the keys that differ from the CMS defaults; the CMS fills in the rest.
return [

    // One login for both panels: guests go to /admin/login, non-admins are turned away.
    // The site: the CMS serves `/` and every unmatched URL; routes/web.php still wins.
    'routes' => [
        'panel' => ['middleware' => ['web', EnsureAdmin::class]],
        'site' => ['enabled' => true],
    ],

    'auth' => ['logout_route' => 'admin.logout'],

    // Hot orange for the panel's buttons, links and highlights, the store's screens included.
    'branding' => ['accent' => '#FF5A1F'],

];
