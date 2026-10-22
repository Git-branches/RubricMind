<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Libraries\RubricForm;
use App\Models\RubricModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class Rubrics extends BaseController
{
    // Starting levels for a new criterion, so teachers don't begin from a blank page.
    private const DEFAULT_LEVELS = [
        ['label' => 'Excellent', 'description' => null, 'points' => ''],
        ['label' => 'Proficient', 'description' => null, 'points' => ''],
        ['label' => 'Developing', 'description' => null, 'points' => ''],
        ['label' => 'Beginning', 'description' => null, 'points' => ''],
    ];

    private RubricModel $rubrics;

    public function __construct()
    {
        $this->rubrics = model(RubricModel::class);
    }

    public function index(): string
    {
        return view('teacher/rubrics/index', ['rubrics' => $this->rubrics->forTeacher($this->userId())]);
    }

    public function new(): string
    {
        return view('teacher/rubrics/form', [
            'rubric'   => null,
            'criteria' => [['title' => '', 'description' => null, 'max_points' => '', 'levels' => self::DEFAULT_LEVELS]],
        ]);
    }

    public function create(): RedirectResponse|string
    {
        $form = RubricForm::parse($this->request->getPost());

        if ($form['errors'] !== []) {
            return view('teacher/rubrics/form', [
                'rubric'   => null,
                'old'      => $form['rubric'],
                'criteria' => $form['criteria'],
                'errors'   => $form['errors'],
            ]);
        }

        $id = $this->rubrics->saveWithCriteria(null, $this->userId(), $form['rubric'], $form['criteria']);
        audit_log('rubric.created', 'rubric', $id, ['title' => $form['rubric']['title']]);

        return redirect()->to(site_url("teacher/rubrics/{$id}"))->with('success', 'Rubric created.');
    }

    public function show(int $id): string
    {
        $rubric   = $this->findOr404($id);
        $criteria = $this->rubrics->criteriaWithLevels($id);

        return view('teacher/rubrics/show', [
            'rubric'     => $rubric,
            'criteria'   => $criteria,
            'isUsed'     => $this->rubrics->isUsedByAssignments($id),
        ]);
    }

    public function edit(int $id): RedirectResponse|string
    {
        $rubric = $this->findOr404($id);

        if ($rubric['locked_at'] !== null) {
            return $this->lockedRedirect($id);
        }

        return view('teacher/rubrics/form', [
            'rubric'   => $rubric,
            'criteria' => $this->rubrics->criteriaWithLevels($id),
        ]);
    }

    public function update(int $id): RedirectResponse|string
    {
        $rubric = $this->findOr404($id);

        if ($rubric['locked_at'] !== null) {
            return $this->lockedRedirect($id);
        }

        $form = RubricForm::parse($this->request->getPost());

        if ($form['errors'] !== []) {
            return view('teacher/rubrics/form', [
                'rubric'   => $rubric,
                'old'      => $form['rubric'],
                'criteria' => $form['criteria'],
                'errors'   => $form['errors'],
            ]);
        }

        $this->rubrics->saveWithCriteria($id, $this->userId(), $form['rubric'], $form['criteria']);
        audit_log('rubric.updated', 'rubric', $id);

        return redirect()->to(site_url("teacher/rubrics/{$id}"))->with('success', 'Rubric saved.');
    }

    public function duplicate(int $id): RedirectResponse
    {
        $rubric = $this->findOr404($id);
        $newId  = $this->rubrics->duplicate($rubric, $this->userId());

        audit_log('rubric.duplicated', 'rubric', $newId, ['copied_from_id' => $id]);

        return redirect()->to(site_url("teacher/rubrics/{$newId}/edit"))
            ->with('success', 'Rubric duplicated. You are now editing the copy.');
    }

    public function delete(int $id): RedirectResponse
    {
        $rubric = $this->findOr404($id);

        if ($rubric['locked_at'] !== null || $this->rubrics->isUsedByAssignments($id)) {
            return redirect()->to(site_url("teacher/rubrics/{$id}"))
                ->with('error', 'This rubric is used by an assignment, so it cannot be deleted.');
        }

        $this->rubrics->delete($id);
        audit_log('rubric.deleted', 'rubric', $id, ['title' => $rubric['title']]);

        return redirect()->to(site_url('teacher/rubrics'))->with('success', "Rubric \"{$rubric['title']}\" deleted.");
    }

    private function findOr404(int $id): array
    {
        return $this->rubrics->findForTeacher($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function lockedRedirect(int $id): RedirectResponse
    {
        return redirect()->to(site_url("teacher/rubrics/{$id}"))
            ->with('warning', 'This rubric has been used for grading, so it is locked. Duplicate it to make changes.');
    }
}
