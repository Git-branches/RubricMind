<?php

namespace Tests\Support;

use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;

/**
 * Builds classes, rubrics, assignments, and submissions for feature tests.
 * Use in a FeatureTestCase subclass (needs makeUser()).
 */
trait ClassroomFixtures
{
    protected const ESSAY = 'School should start later because students need more sleep to learn well.';

    protected function makeClass(array $teacher, array $overrides = []): array
    {
        $classes = model(ClassModel::class);
        $id      = $classes->insert($overrides + ['teacher_id' => $teacher['id'], 'name' => 'English 10', 'status' => 'active']);

        return $classes->find($id);
    }

    /**
     * Two criteria: "Thesis" (levels 10 and 5) and "Evidence" (no levels, 0–15).
     */
    protected function makeRubric(array $teacher): array
    {
        $rubrics = model(RubricModel::class);
        $id      = $rubrics->saveWithCriteria(null, $teacher['id'], ['title' => 'Essay Rubric', 'description' => null], [
            [
                'title'       => 'Thesis',
                'description' => null,
                'max_points'  => 10,
                'levels'      => [
                    ['label' => 'Excellent', 'description' => null, 'points' => 10],
                    ['label' => 'Developing', 'description' => null, 'points' => 5],
                ],
            ],
            ['title' => 'Evidence', 'description' => null, 'max_points' => 15, 'levels' => []],
        ]);

        return $rubrics->find($id);
    }

    protected function makeAssignment(array $class, array $rubric, array $overrides = []): array
    {
        $assignments = model(AssignmentModel::class);
        $id          = $assignments->insert($overrides + [
            'class_id'     => $class['id'],
            'rubric_id'    => $rubric['id'],
            'title'        => 'Argumentative Essay',
            'instructions' => 'Write an argumentative essay.',
            'due_at'       => date('Y-m-d H:i:s', strtotime('+7 days')),
            'status'       => 'published',
        ]);

        return $assignments->find($id);
    }

    protected function enroll(array $class, array $student): void
    {
        model(ClassModel::class)->enrollByEmail($class['id'], [$student['email']]);
    }

    /**
     * A teacher, their class with one enrolled student, a rubric, and a published assignment.
     *
     * @return array{0: array, 1: array, 2: array, 3: array, 4: array} teacher, student, class, rubric, assignment
     */
    protected function classroom(array $assignmentOverrides = []): array
    {
        $teacher    = $this->makeUser('teacher');
        $student    = $this->makeUser('student');
        $class      = $this->makeClass($teacher);
        $rubric     = $this->makeRubric($teacher);
        $assignment = $this->makeAssignment($class, $rubric, $assignmentOverrides);
        $this->enroll($class, $student);

        return [$teacher, $student, $class, $rubric, $assignment];
    }

    protected function submitEssay(array $assignment, array $student, string $content = self::ESSAY): array
    {
        $submissions = model(SubmissionModel::class);
        $id          = $submissions->saveEssay(null, $assignment['id'], $student['id'], $content, false);

        return $submissions->find($id);
    }
}
