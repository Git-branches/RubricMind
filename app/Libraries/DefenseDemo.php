<?php

namespace App\Libraries;

use Config\Demo;

/**
 * Decides whether a Defense Demo Mode feature may be used (see Config\Demo).
 *
 * A demo feature works only when all three are true:
 * - it is turned on in .env;
 * - the app is not running in production;
 * - the request comes from this computer (localhost), so nobody on the
 *   network can use it even if it was left on.
 */
final class DefenseDemo
{
    private const LOCAL_ADDRESSES = ['127.0.0.1', '::1'];

    public static function resetLinksEnabled(string $ipAddress, string $environment = ENVIRONMENT): bool
    {
        return config(Demo::class)->passwordResetLinks
            && $environment !== 'production'
            && in_array($ipAddress, self::LOCAL_ADDRESSES, true);
    }
}
