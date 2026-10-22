<?php

use App\Providers\AppServiceProvider;
use Peer\CMS\CMSServiceProvider;
use Peer\Ecommerce\EcommerceServiceProvider;

/*
 * The CMS and ecommerce packages are loaded from their git submodules in packages/
 * (see composer.json's autoload), not installed by Composer, so package discovery
 * doesn't register them.
 */
return [
    CMSServiceProvider::class,
    EcommerceServiceProvider::class,
    AppServiceProvider::class,
];
