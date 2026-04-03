<?php

declare(strict_types=1);

namespace DPRMC\Gofer2FA\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel facade for the container-bound Gofer2FA service.
 *
 * This provides convenient static access to the configured `DPRMC\Gofer2FA\Gofer2FA`
 * instance in Laravel applications while the underlying service remains resolved from
 * the container.
 */
class Gofer2FA extends Facade {
    /**
     * Return the container binding key for the Gofer2FA service.
     */
    protected static function getFacadeAccessor(): string {
        return 'gofer2fa';
    }
}
