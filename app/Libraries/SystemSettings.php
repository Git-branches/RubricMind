<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use Config\Ai;

/**
 * System-level settings managed by administrators (§3).
 *
 * Values live in the `settings` table; anything not saved yet falls back to
 * the defaults below (and Config\Ai for AI settings). Access it through
 * service('systemSettings').
 */
final class SystemSettings
{
    /**
     * Supported AI providers: key => [short name, label for the Settings page].
     * Each one is created in Config\Services::essayAnalyzer().
     */
    public const AI_PROVIDERS = [
        'demo'   => ['Demo AI', 'Demo AI (simulated, runs on this server)'],
        'gemini' => ['Google Gemini', 'Google Gemini (sends essay text to Google)'],
    ];

    public const MIN_RETENTION_YEARS     = 1;
    public const MAX_RETENTION_YEARS     = 10;
    public const DEFAULT_RETENTION_YEARS = 3;

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function schoolName(): string
    {
        return (string) ($this->value('school_name') ?? '');
    }

    public function aiEnabled(): bool
    {
        return $this->bool('ai_enabled', config(Ai::class)->enabled);
    }

    public function aiProvider(): string
    {
        $provider = $this->value('ai_provider') ?? config(Ai::class)->provider;

        return array_key_exists($provider, self::AI_PROVIDERS) ? $provider : 'demo';
    }

    /**
     * Short name of the current provider, e.g. "Google Gemini".
     */
    public function aiProviderName(): string
    {
        return self::AI_PROVIDERS[$this->aiProvider()][0];
    }

    /**
     * Whether Gemini can be used: an API key is set in .env.
     */
    public static function geminiConfigured(): bool
    {
        return trim(config(Ai::class)->geminiApiKey) !== '';
    }

    /**
     * Whether students see a notice that AI may help review their essays (§5).
     */
    public function aiNoticeEnabled(): bool
    {
        return $this->bool('ai_notice_enabled', true);
    }

    /**
     * Whether students may create their own accounts on the sign-in page (FR-14).
     */
    public function registrationEnabled(): bool
    {
        return $this->bool('registration_enabled', true);
    }

    /**
     * Whether students may upload a PDF or DOCX file instead of typing their essay (FR-05).
     */
    public function uploadsEnabled(): bool
    {
        return $this->bool('uploads_enabled', true);
    }

    /**
     * Years to keep an archived class's essays and grades before they may be deleted (§5).
     */
    public function retentionYears(): int
    {
        $years = (int) ($this->value('retention_years') ?? self::DEFAULT_RETENTION_YEARS);

        return max(self::MIN_RETENTION_YEARS, min(self::MAX_RETENTION_YEARS, $years));
    }

    /**
     * Saves settings and returns what changed, for the audit log.
     *
     * @param array<string, string> $values
     *
     * @return array<string, array{from: string|null, to: string}>
     */
    public function save(array $values, int $userId): array
    {
        $this->load();
        $changes = [];
        $now     = date('Y-m-d H:i:s');

        foreach ($values as $key => $value) {
            $previous = $this->values[$key] ?? null;

            if ($previous === $value) {
                continue;
            }

            $changes[$key] = ['from' => $previous, 'to' => $value];
            $row           = ['value' => $value, 'updated_by' => $userId, 'updated_at' => $now];

            if (array_key_exists($key, $this->values)) {
                $this->db->table('settings')->where('key', $key)->update($row);
            } else {
                $this->db->table('settings')->insert($row + ['key' => $key]);
            }

            $this->values[$key] = $value;
        }

        return $changes;
    }

    private function value(string $key): ?string
    {
        $this->load();

        return $this->values[$key] ?? null;
    }

    private function bool(string $key, bool $default): bool
    {
        $value = $this->value($key);

        return $value === null ? $default : $value === '1';
    }

    private function load(): void
    {
        if ($this->values !== null) {
            return;
        }

        $this->values = [];

        foreach ($this->db->table('settings')->get()->getResultArray() as $row) {
            $this->values[$row['key']] = $row['value'];
        }
    }
}
