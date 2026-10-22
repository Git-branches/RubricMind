<?php

namespace App\Libraries;

/**
 * When a student may submit or edit an essay (FR-05).
 *
 * - New submissions are accepted while the assignment is published and the
 *   class is active. After the due date they are accepted but marked late.
 * - An existing submission can be edited until the due date passes or the
 *   teacher starts grading it, whichever comes first.
 */
final class SubmissionRules
{
    public static function isPastDue(array $assignment, ?string $now = null): bool
    {
        return ($now ?? date('Y-m-d H:i:s')) > $assignment['due_at'];
    }

    public static function acceptsSubmissions(array $assignment, array $class): bool
    {
        return $assignment['status'] === 'published' && $class['status'] === 'active';
    }

    public static function canEdit(array $submission, array $assignment, array $class, ?string $now = null): bool
    {
        return self::acceptsSubmissions($assignment, $class)
            && ! self::isPastDue($assignment, $now)
            && $submission['locked_at'] === null
            && $submission['status'] === 'submitted';
    }

    /**
     * The student-facing label for a submission. Grading progress is hidden
     * until the teacher releases results (FR-12).
     */
    public static function studentStatusLabel(?string $submissionStatus): string
    {
        return match ($submissionStatus) {
            null       => 'Not submitted',
            'released' => 'Graded',
            default    => 'Submitted',
        };
    }
}
