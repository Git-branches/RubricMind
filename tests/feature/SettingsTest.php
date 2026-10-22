<?php

namespace Tests\Feature;

use App\Models\AiRunModel;
use App\Models\ClassModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use App\Models\UserModel;
use Config\Services;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Admin settings (§3), the student AI notice, and data retention (§5).
 *
 * @internal
 */
final class SettingsTest extends FeatureTestCase
{
    use ClassroomFixtures;

    private function saveSettings(array $admin, array $overrides = [])
    {
        Services::resetSingle('systemSettings');

        return $this->signedInAs($admin)->post('admin/settings', $overrides + [
            'school_name'       => '',
            'ai_enabled'        => '1',
            'ai_provider'       => 'demo',
            'ai_notice_enabled' => '1',
            'retention_years'   => '3',
        ]);
    }

    /**
     * A class archived $yearsAgo years ago with one graded essay and AI results.
     */
    private function archivedClassWithWork(float $yearsAgo): array
    {
        [$teacher, $student, $class, $rubric, $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);
        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai");

        model(ClassModel::class)->update($class['id'], [
            'status'      => 'archived',
            'archived_at' => date('Y-m-d H:i:s', (int) strtotime('-' . (int) round($yearsAgo * 365) . ' days')),
        ]);

        return [$teacher, $student, $class, $rubric, $submission];
    }

    // Settings page

    public function testAdminSeesDefaultSettings(): void
    {
        $page = $this->signedInAs($this->makeUser('admin'))->get('admin/settings');

        $page->assertOK();
        $page->assertSee('Turn on AI suggestions');
        $this->assertStringContainsString('name="retention_years" value="3"', $page->response()->getBody());
    }

    public function testOnlyAdminsCanOpenSettings(): void
    {
        $this->signedInAs($this->makeUser('teacher'))->get('admin/settings')->assertStatus(403);
    }

    public function testSavingSettingsIsStoredAndAudited(): void
    {
        $admin = $this->makeUser('admin');

        $this->saveSettings($admin, ['school_name' => 'Rizal National High School', 'retention_years' => '5'])->assertSessionHas('success', 'Settings saved.');

        $this->seeInDatabase('settings', ['key' => 'school_name', 'value' => 'Rizal National High School', 'updated_by' => $admin['id']]);
        $this->seeInDatabase('settings', ['key' => 'retention_years', 'value' => '5']);
        $this->seeInDatabase('audit_logs', ['action' => 'settings.updated', 'user_id' => $admin['id']]);

        Services::resetSingle('systemSettings');
        Services::resetSingle('auth');
        $this->withSession([])->get('login')->assertSee('Rizal National High School');
    }

    public function testInvalidSettingsAreRejected(): void
    {
        $admin = $this->makeUser('admin');

        $this->saveSettings($admin, ['retention_years' => '0'])->assertSee('Please fix the following');
        $this->saveSettings($admin, ['retention_years' => '11'])->assertSee('Please fix the following');
        $this->saveSettings($admin, ['ai_provider' => 'unknown-ai'])->assertSee('Please fix the following');

        $this->dontSeeInDatabase('settings', ['key' => 'retention_years']);
    }

    // Gemini provider

    public function testGeminiCannotBeChosenWithoutAnApiKey(): void
    {
        config(\Config\Ai::class)->geminiApiKey = '';

        $this->saveSettings($this->makeUser('admin'), ['ai_provider' => 'gemini'])
            ->assertSee('Add your Gemini API key to the .env file');

        $this->dontSeeInDatabase('settings', ['key' => 'ai_provider', 'value' => 'gemini']);
    }

    public function testGeminiCanBeChosenWhenTheKeyIsSet(): void
    {
        config(\Config\Ai::class)->geminiApiKey = 'test-key';
        $admin = $this->makeUser('admin');

        $this->saveSettings($admin, ['ai_provider' => 'gemini'])->assertSessionHas('success');

        $this->seeInDatabase('settings', ['key' => 'ai_provider', 'value' => 'gemini']);
        Services::resetSingle('systemSettings');
        Services::resetSingle('essayAnalyzer');
        $this->assertSame('gemini', service('essayAnalyzer')->provider());
    }

    public function testStudentNoticeNamesGeminiWhenItIsUsed(): void
    {
        config(\Config\Ai::class)->geminiApiKey = 'test-key';
        [, $student, , , $assignment] = $this->classroom();
        $this->saveSettings($this->makeUser('admin'), ['ai_provider' => 'gemini']);

        Services::resetSingle('systemSettings');
        Services::resetSingle('essayAnalyzer');
        $this->signedInAs($student)->get("student/assignments/{$assignment['id']}")
            ->assertSee('Your essay text is sent to Google Gemini, an outside AI service, for analysis.');
    }

    // AI on/off and the student notice

    public function testTurningAiOffHidesAndBlocksAiFeatures(): void
    {
        $this->saveSettings($this->makeUser('admin'), ['ai_enabled' => '0']);
        [$teacher, $student, , , $assignment] = $this->classroom();
        $submission = $this->submitEssay($assignment, $student);

        Services::resetSingle('systemSettings');
        $page = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}");
        $page->assertDontSee('Get AI suggestions');

        Services::resetSingle('systemSettings');
        $this->signedInAs($teacher)->post("teacher/submissions/{$submission['id']}/ai")->assertSessionHas('error');
        $this->assertNull(model(AiRunModel::class)->latestForSubmission($submission['id']));
    }

    public function testStudentSeesAiNoticeOnlyWhenEnabled(): void
    {
        $admin = $this->makeUser('admin');
        [, $student, , , $assignment] = $this->classroom();
        $url = "student/assignments/{$assignment['id']}";

        $this->saveSettings($admin);
        Services::resetSingle('systemSettings');
        $page = $this->signedInAs($student)->get($url);
        $page->assertSee('About AI in grading');
        $page->assertSee('Your teacher always reviews the work and decides your final grade.');

        $this->saveSettings($admin, ['ai_notice_enabled' => '0']);
        Services::resetSingle('systemSettings');
        $this->signedInAs($student)->get($url)->assertDontSee('About AI in grading');

        $this->saveSettings($admin, ['ai_enabled' => '0']);
        Services::resetSingle('systemSettings');
        $this->signedInAs($student)->get($url)->assertDontSee('About AI in grading');
    }

    // Data retention

    public function testArchivingRecordsTheDateAndRestoringClearsIt(): void
    {
        $teacher = $this->makeUser('teacher');
        $class   = $this->makeClass($teacher);

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/archive");
        $this->assertNotNull(model(ClassModel::class)->find($class['id'])['archived_at']);

        $this->signedInAs($teacher)->post("teacher/classes/{$class['id']}/archive");
        $this->assertNull(model(ClassModel::class)->find($class['id'])['archived_at']);
    }

    public function testOldArchivedClassCanBePermanentlyDeleted(): void
    {
        $admin = $this->makeUser('admin');
        [, $student, $class, $rubric, $submission] = $this->archivedClassWithWork(4);

        $page = $this->signedInAs($admin)->get('admin/settings');
        $page->assertSee('Delete records');

        $this->signedInAs($admin)->post("admin/retention/classes/{$class['id']}/delete")->assertSessionHas('success');

        $this->dontSeeInDatabase('classes', ['id' => $class['id']]);
        $this->dontSeeInDatabase('submissions', ['id' => $submission['id']]);
        $this->dontSeeInDatabase('submission_revisions', ['submission_id' => $submission['id']]);
        $this->dontSeeInDatabase('evaluations', ['submission_id' => $submission['id']]);
        $this->dontSeeInDatabase('ai_runs', ['submission_id' => $submission['id']]);
        $this->dontSeeInDatabase('class_students', ['class_id' => $class['id']]);

        // Kept: the student's account, the teacher's rubric, and an audit record without essay text.
        $this->assertNotNull(model(UserModel::class)->find($student['id']));
        $this->assertNotNull(model(RubricModel::class)->find($rubric['id']));
        $this->seeInDatabase('audit_logs', ['action' => 'retention.class_deleted', 'entity_id' => $class['id']]);
    }

    public function testRecentlyArchivedClassCannotBeDeleted(): void
    {
        $admin = $this->makeUser('admin');
        [, , $class, , $submission] = $this->archivedClassWithWork(1);

        $this->signedInAs($admin)->get('admin/settings')->assertSee('Kept until');

        $this->signedInAs($admin)->post("admin/retention/classes/{$class['id']}/delete")
            ->assertSessionHas('error', 'This class is not archived long enough to be deleted.');

        $this->seeInDatabase('submissions', ['id' => $submission['id']]);
    }

    public function testActiveClassCannotBeDeleted(): void
    {
        $admin = $this->makeUser('admin');
        [, $student, $class, , $assignment] = $this->classroom();
        $this->submitEssay($assignment, $student);

        $this->signedInAs($admin)->post("admin/retention/classes/{$class['id']}/delete")->assertSessionHas('error');

        $this->seeInDatabase('classes', ['id' => $class['id']]);
    }

    public function testShorterRetentionPeriodMakesClassEligible(): void
    {
        $admin = $this->makeUser('admin');
        [, , $class] = $this->archivedClassWithWork(1.5);

        $this->saveSettings($admin, ['retention_years' => '1']);

        Services::resetSingle('systemSettings');
        $this->signedInAs($admin)->post("admin/retention/classes/{$class['id']}/delete")->assertSessionHas('success');
        $this->dontSeeInDatabase('classes', ['id' => $class['id']]);
        $this->assertSame(0, model(SubmissionModel::class)->countAllResults());
    }

    public function testTeacherCannotDeleteRetainedRecords(): void
    {
        [$teacher, , $class] = $this->archivedClassWithWork(4);

        $this->signedInAs($teacher)->post("admin/retention/classes/{$class['id']}/delete")->assertStatus(403);
        $this->seeInDatabase('classes', ['id' => $class['id']]);
    }
}
