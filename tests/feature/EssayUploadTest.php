<?php

namespace Tests\Feature;

use App\Libraries\RetentionService;
use App\Libraries\SubmissionFiles;
use App\Models\ClassModel;
use App\Models\SubmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\ClassroomFixtures;
use Tests\Support\FeatureTestCase;

/**
 * Submitting an essay from an uploaded PDF or DOCX file (FR-05).
 *
 * PHP only accepts real HTTP uploads, so these tests start after the upload:
 * a pending file is placed in storage and the session, as the upload step does.
 * Reading the text from files is covered by Tests\Unit\DocumentTextTest.
 *
 * @internal
 */
final class EssayUploadTest extends FeatureTestCase
{
    use ClassroomFixtures;

    /** @var list<string> */
    private array $stored = [];

    protected function tearDown(): void
    {
        foreach ($this->stored as $name) {
            SubmissionFiles::delete($name);
        }

        parent::tearDown();
    }

    /**
     * A file the student just uploaded and has not submitted yet.
     *
     * @return array{name: string, path: string, size: int}
     */
    private function pendingUpload(string $name = 'My Essay.docx'): array
    {
        $stored = 'pending-' . bin2hex(random_bytes(8)) . '.docx';
        @mkdir(SubmissionFiles::directory(), 0777, true);
        file_put_contents(SubmissionFiles::path($stored), 'original file contents');
        $this->stored[] = $stored;

        return ['name' => $name, 'path' => $stored, 'size' => 22];
    }

    private function submitWithPending(array $student, array $assignment, array $pending, bool $attach = true)
    {
        // withSession() replaces the whole session, so add the upload to the signed-in one.
        $this->signedInAs($student);

        return $this->withSession($this->session + ['pending_uploads' => [$assignment['id'] => $pending]])
            ->post("student/assignments/{$assignment['id']}/submission", ['content' => self::ESSAY] + ($attach ? ['attach_file' => '1'] : []));
    }

    public function testStudentPageOffersUploadWhenEnabled(): void
    {
        [, $student, , , $assignment] = $this->classroom();

        $this->signedInAs($student)->get("student/assignments/{$assignment['id']}")->assertSee('Have your essay in a file?');

        service('systemSettings')->save(['uploads_enabled' => '0'], $student['id']);
        $this->signedInAs($student)->get("student/assignments/{$assignment['id']}")->assertDontSee('Have your essay in a file?');
    }

    public function testUploadIsRefusedWhenTurnedOff(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        service('systemSettings')->save(['uploads_enabled' => '0'], $student['id']);

        $this->signedInAs($student)->post("student/assignments/{$assignment['id']}/upload")
            ->assertSessionHas('error', 'File uploads are turned off. Type or paste your essay instead.');
    }

    public function testUploadWithoutFileShowsError(): void
    {
        [, $student, , , $assignment] = $this->classroom();

        $this->signedInAs($student)->post("student/assignments/{$assignment['id']}/upload")
            ->assertSee('Choose a PDF or Word (.docx) file to upload.');
    }

    public function testSubmittingAttachesThePendingFile(): void
    {
        [$teacher, $student, , , $assignment] = $this->classroom();
        $pending                              = $this->pendingUpload();

        $this->submitWithPending($student, $assignment, $pending)->assertSessionHas('success');

        $submission     = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);
        $this->stored[] = $submission['file_path'];

        $this->assertSame('My Essay.docx', $submission['file_name']);
        $this->assertStringStartsWith('essay-', $submission['file_path']);
        $this->assertFileExists(SubmissionFiles::path($submission['file_path']));
        $this->assertFileDoesNotExist(SubmissionFiles::path($pending['path']));
        $this->assertArrayNotHasKey($assignment['id'], $_SESSION['pending_uploads']);

        // The teacher sees and can download the original file; the student can too.
        $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}")->assertSee('My Essay.docx');
        // (A download streams the file when sent, so the test response has no body to check.)
        $download = $this->signedInAs($teacher)->get("teacher/submissions/{$submission['id']}/file")->response();
        $this->assertSame(200, $download->getStatusCode());
        $download->buildHeaders(); // Normally done while sending.
        $this->assertStringContainsString('attachment', $download->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('My Essay.docx', $download->getHeaderLine('Content-Disposition'));

        $own = $this->signedInAs($student)->get("student/assignments/{$assignment['id']}/file")->response();
        $this->assertSame(200, $own->getStatusCode());
    }

    private function assertNotFound(callable $request): void
    {
        try {
            $request();
            $this->fail('Expected "page not found".');
        } catch (PageNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testUntickingAttachDiscardsTheFile(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        $pending                      = $this->pendingUpload();

        $this->submitWithPending($student, $assignment, $pending, false)->assertSessionHas('success');

        $submission = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);
        $this->assertNull($submission['file_name']);
        $this->assertFileDoesNotExist(SubmissionFiles::path($pending['path']));
    }

    public function testNewUploadReplacesTheOldFile(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        $this->submitWithPending($student, $assignment, $this->pendingUpload('first.docx'));
        $first = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);

        $this->submitWithPending($student, $assignment, $this->pendingUpload('second.docx'))
            ->assertSessionHas('success', 'Your submission has been updated.');
        $second         = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);
        $this->stored[] = $second['file_path'];

        $this->assertSame('second.docx', $second['file_name']);
        $this->assertFileDoesNotExist(SubmissionFiles::path($first['file_path']));
    }

    public function testOthersCannotDownloadTheFile(): void
    {
        [, $student, , , $assignment] = $this->classroom();
        $this->submitWithPending($student, $assignment, $this->pendingUpload());
        $submission     = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);
        $this->stored[] = $submission['file_path'];

        $otherTeacher = $this->makeUser('teacher');
        $classmate    = $this->makeUser('student');
        model(ClassModel::class)->enrollByEmail($assignment['class_id'], [$classmate['email']]);

        $this->assertNotFound(fn () => $this->signedInAs($otherTeacher)->get("teacher/submissions/{$submission['id']}/file"));
        // The classmate's own download link finds no file of theirs.
        $this->assertNotFound(fn () => $this->signedInAs($classmate)->get("student/assignments/{$assignment['id']}/file"));
    }

    public function testRetentionDeleteRemovesUploadedFiles(): void
    {
        [, $student, $class, , $assignment] = $this->classroom();
        $this->submitWithPending($student, $assignment, $this->pendingUpload());
        $submission = model(SubmissionModel::class)->findForStudent($assignment['id'], $student['id']);

        model(ClassModel::class)->update($class['id'], ['status' => 'archived', 'archived_at' => date('Y-m-d H:i:s', strtotime('-5 years'))]);
        (new RetentionService(db_connect(), 3))->purge($class['id']);

        $this->assertFileDoesNotExist(SubmissionFiles::path($submission['file_path']));
    }
}
