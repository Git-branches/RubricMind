<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Default AI assistance settings (Phase 4). Administrators change them on the
 * Settings page; these values apply until a setting has been saved there.
 */
class Ai extends BaseConfig
{
    /**
     * Which EssayAnalyzer to use. Supported: "demo" (simulated, runs locally,
     * sends nothing outside this server). Real providers can be added in
     * Config\Services::essayAnalyzer() and App\Libraries\SystemSettings::AI_PROVIDERS.
     */
    public string $provider = 'demo';

    /**
     * Set to false to hide AI features entirely; manual grading still works.
     */
    public bool $enabled = true;

    /**
     * Google Gemini (Google AI Studio). Keep the key in .env as
     * `ai.geminiApiKey = ...`; never commit it.
     */
    public string $geminiApiKey = '';

    /**
     * Any Gemini model that supports generateContent, e.g. the fast, low-cost
     * "gemini-3.5-flash-lite" or the stronger "gemini-3.8-flash".
     * Override with `ai.geminiModel`.
     */
    public string $geminiModel = 'gemini-3.5-flash-lite';

    /**
     * How long to wait for an AI answer before giving up (seconds).
     */
    public int $timeoutSeconds = 90;
}
