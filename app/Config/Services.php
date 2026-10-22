<?php

namespace Config;

use App\Libraries\Ai\DemoAnalyzer;
use App\Libraries\Ai\EssayAnalyzer;
use App\Libraries\Ai\GeminiAnalyzer;
use App\Libraries\Auth;
use App\Libraries\Mailer;
use App\Libraries\SystemSettings;
use App\Models\UserModel;
use CodeIgniter\Config\BaseService;
use InvalidArgumentException;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    public static function auth(bool $getShared = true): Auth
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new Auth(service('session'), model(UserModel::class));
    }

    public static function systemSettings(bool $getShared = true): SystemSettings
    {
        if ($getShared) {
            return static::getSharedInstance('systemSettings');
        }

        return new SystemSettings(db_connect());
    }

    public static function mailer(bool $getShared = true): Mailer
    {
        if ($getShared) {
            return static::getSharedInstance('mailer');
        }

        return new Mailer(db_connect(), config(Email::class));
    }

    /**
     * The AI provider used for essay suggestions, chosen on the admin Settings page.
     */
    public static function essayAnalyzer(bool $getShared = true): EssayAnalyzer
    {
        if ($getShared) {
            return static::getSharedInstance('essayAnalyzer');
        }

        $provider = service('systemSettings')->aiProvider();
        $config   = config(Ai::class);

        return match ($provider) {
            'demo'   => new DemoAnalyzer(),
            'gemini' => new GeminiAnalyzer(static::curlrequest([], null, null, false), $config->geminiApiKey, $config->geminiModel, $config->timeoutSeconds),
            default  => throw new InvalidArgumentException("Unknown AI provider \"{$provider}\"."),
        };
    }
}
