<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Libraries\Ai\AiException;
use App\Libraries\ClassInsights;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\LessonPlanModel;
use App\Models\RubricModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Classroom Insight Dashboard and lesson-plan drafts (FR-11).
 */
class Insights extends BaseController
{
    private const MAX_PLAN_LENGTH = 20000;

    public function index(): string
    {
        return view('teacher/insights/index', ['classes' => (new ClassInsights())->overview($this->userId())]);
    }

    public function assignment(int $id): string
    {
        $assignment = $this->findAssignmentOr404($id);

        return view('teacher/insights/assignment', [
            'assignment'  => $assignment,
            'class'       => model(ClassModel::class)->find($assignment['class_id']),
            'rubric'      => model(RubricModel::class)->find($assignment['rubric_id']),
            'insights'    => (new ClassInsights())->forAssignment($assignment),
            'lessonPlans' => model(LessonPlanModel::class)->forAssignment($id),
            'aiEnabled'   => service('systemSettings')->aiEnabled(),
        ]);
    }

    public function generatePlan(int $id): RedirectResponse
    {
        $assignment = $this->findAssignmentOr404($id);
        $insights   = (new ClassInsights())->forAssignment($assignment);
        $back       = redirect()->to(site_url("teacher/assignments/{$id}/insights#lesson-plans"));

        if (! service('systemSettings')->aiEnabled()) {
            return $back->with('error', 'AI features are turned off, so lesson plans cannot be generated.');
        }

        if ($insights['gradedCount'] === 0) {
            return $back->with('error', 'Finalize at least one grade before generating a lesson plan.');
        }

        $focus    = ClassInsights::lessonFocus($insights);
        $analyzer = service('essayAnalyzer');

        try {
            $plan = $analyzer->lessonPlan($focus);
        } catch (AiException $e) {
            return $back->with('error', 'The lesson plan could not be generated: ' . $e->getMessage());
        }

        $planId = model(LessonPlanModel::class)->insert([
            'assignment_id' => $id,
            'teacher_id'    => $this->userId(),
            'title'         => mb_substr($plan['title'], 0, 200),
            'content'       => LessonPlanModel::toText($plan),
            'focus'         => json_encode($focus, JSON_UNESCAPED_UNICODE),
            'provider'      => $analyzer->provider(),
        ]);

        audit_log('lesson_plan.generated', 'lesson_plan', $planId, ['assignment_id' => $id]);

        return redirect()->to(site_url("teacher/lesson-plans/{$planId}"))
            ->with('success', 'Draft lesson plan created. Review and edit it before using it in class.');
    }

    public function plan(int $id): string
    {
        $plan = $this->findPlanOr404($id);

        return view('teacher/insights/lesson_plan', [
            'plan'       => $plan,
            'assignment' => model(AssignmentModel::class)->find($plan['assignment_id']),
        ]);
    }

    public function updatePlan(int $id): RedirectResponse|string
    {
        $plan = $this->findPlanOr404($id);
        $data = [
            'title'   => trim((string) $this->request->getPost('title')),
            'content' => trim(str_replace("\r\n", "\n", (string) $this->request->getPost('content'))),
        ];

        $rules = [
            'title'   => ['label' => 'title', 'rules' => 'required|max_length[200]'],
            'content' => ['label' => 'lesson plan', 'rules' => 'required|max_length[' . self::MAX_PLAN_LENGTH . ']'],
        ];

        if (! $this->validateData($data, $rules)) {
            return view('teacher/insights/lesson_plan', [
                'plan'       => array_merge($plan, $data),
                'assignment' => model(AssignmentModel::class)->find($plan['assignment_id']),
                'errors'     => $this->validator->getErrors(),
            ]);
        }

        model(LessonPlanModel::class)->update($id, $data);
        audit_log('lesson_plan.updated', 'lesson_plan', $id);

        return redirect()->to(site_url("teacher/lesson-plans/{$id}"))->with('success', 'Lesson plan saved.');
    }

    public function deletePlan(int $id): RedirectResponse
    {
        $plan = $this->findPlanOr404($id);

        model(LessonPlanModel::class)->delete($id);
        audit_log('lesson_plan.deleted', 'lesson_plan', $id, ['title' => $plan['title']]);

        return redirect()->to(site_url("teacher/assignments/{$plan['assignment_id']}/insights#lesson-plans"))
            ->with('success', 'Lesson plan deleted.');
    }

    private function findAssignmentOr404(int $id): array
    {
        return model(AssignmentModel::class)->findForTeacher($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }

    private function findPlanOr404(int $id): array
    {
        return model(LessonPlanModel::class)->findForTeacher($id, $this->userId()) ?? throw PageNotFoundException::forPageNotFound();
    }
}
