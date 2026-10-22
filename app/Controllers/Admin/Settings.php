<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RetentionService;
use App\Libraries\SystemSettings;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Ai;
use RuntimeException;

/**
 * System-level settings (§3) and data retention (§5).
 */
class Settings extends BaseController
{
    public function index(): string
    {
        return $this->page();
    }

    public function update(): RedirectResponse|string
    {
        $data = [
            'school_name'       => trim((string) $this->request->getPost('school_name')),
            'ai_enabled'        => $this->request->getPost('ai_enabled') === '1' ? '1' : '0',
            'ai_provider'       => (string) $this->request->getPost('ai_provider'),
            'ai_notice_enabled' => $this->request->getPost('ai_notice_enabled') === '1' ? '1' : '0',
            'uploads_enabled'   => $this->request->getPost('uploads_enabled') === '1' ? '1' : '0',
            'registration_enabled' => $this->request->getPost('registration_enabled') === '1' ? '1' : '0',
            'retention_years'   => trim((string) $this->request->getPost('retention_years')),
        ];

        $rules = [
            'school_name'     => ['label' => 'school name', 'rules' => 'permit_empty|max_length[150]'],
            'ai_provider'     => ['label' => 'AI provider', 'rules' => 'required|in_list[' . implode(',', array_keys(SystemSettings::AI_PROVIDERS)) . ']'],
            'retention_years' => ['label' => 'retention period', 'rules' => 'required|is_natural_no_zero|greater_than_equal_to['
                . SystemSettings::MIN_RETENTION_YEARS . ']|less_than_equal_to[' . SystemSettings::MAX_RETENTION_YEARS . ']'],
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->page($data, $this->validator->getErrors());
        }

        if ($data['ai_provider'] === 'gemini' && ! SystemSettings::geminiConfigured()) {
            return $this->page($data, ['ai_provider' => 'Add your Gemini API key to the .env file (ai.geminiApiKey) before choosing Google Gemini.']);
        }

        $changes = service('systemSettings')->save($data, $this->userId());

        if ($changes !== []) {
            audit_log('settings.updated', 'settings', null, $changes);
        }

        return redirect()->to(site_url('admin/settings'))
            ->with('success', $changes === [] ? 'No changes to save.' : 'Settings saved.');
    }

    public function purgeClass(int $id): RedirectResponse
    {
        $back = redirect()->to(site_url('admin/settings#retention'));

        try {
            $deleted = $this->retention()->purge($id);
        } catch (RuntimeException $e) {
            return $back->with('error', $e->getMessage());
        }

        audit_log('retention.class_deleted', 'class', $id, $deleted);

        return $back->with('success', "Deleted \"{$deleted['name']}\": {$deleted['assignments']} assignment(s) and {$deleted['submissions']} essay(s). Student accounts were kept.");
    }

    /**
     * @param array<string, string>    $old
     * @param array<array-key, string> $errors
     */
    private function page(array $old = [], array $errors = []): string
    {
        $settings = service('systemSettings');

        return view('admin/settings/index', [
            'values' => $old + [
                'school_name'       => $settings->schoolName(),
                'ai_enabled'        => $settings->aiEnabled() ? '1' : '0',
                'ai_provider'       => $settings->aiProvider(),
                'ai_notice_enabled' => $settings->aiNoticeEnabled() ? '1' : '0',
                'uploads_enabled'   => $settings->uploadsEnabled() ? '1' : '0',
                'registration_enabled' => $settings->registrationEnabled() ? '1' : '0',
                'retention_years'   => (string) $settings->retentionYears(),
            ],
            'mailer'         => service('mailer'),
            'retentionYears' => $settings->retentionYears(),
            'geminiReady'    => SystemSettings::geminiConfigured(),
            'geminiModel'    => config(Ai::class)->geminiModel,
            'archived'       => $this->retention()->archivedClasses(),
            'errors'         => $errors,
        ]);
    }

    private function retention(): RetentionService
    {
        return new RetentionService(db_connect(), service('systemSettings')->retentionYears());
    }
}
