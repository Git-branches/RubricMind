<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Defense Demo Mode: features that only exist to present RubricMind offline,
 * for example at an academic defense. Everything here is OFF by default.
 *
 * Turn a feature on in .env (for example `demo.passwordResetLinks = true`).
 * Even when turned on, demo features never work in production or for
 * requests from another computer (see App\Libraries\DefenseDemo).
 */
class Demo extends BaseConfig
{
    /**
     * After "Forgot your password?", show the generated reset link on screen
     * ("Demo Email Ready") instead of only emailing it.
     */
    public bool $passwordResetLinks = false;
}
