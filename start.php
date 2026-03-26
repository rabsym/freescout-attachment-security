<?php

/**
 * AttachmentSecurity Module Bootstrap
 *
 * Entry point loaded by the nwidart/laravel-modules package when the module is active.
 * Registers the module's route file with the Laravel router.
 *
 * @package Modules\AttachmentSecurity
 * @author  Raimundo Alba
 */

/*
|--------------------------------------------------------------------------
| Register Namespaces and Routes
|--------------------------------------------------------------------------
*/

if (!app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
