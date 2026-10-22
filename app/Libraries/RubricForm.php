<?php

namespace App\Libraries;

/**
 * Reads and validates the nested rubric editor form (FR-04).
 *
 * Expected POST shape:
 *   title, description,
 *   criteria[<key>][title|description|max_points],
 *   criteria[<key>][levels][<key>][label|description|points]
 *
 * Keys are arbitrary (the editor generates them); order is preserved.
 */
final class RubricForm
{
    public const MAX_CRITERIA          = 20;
    public const MAX_LEVELS            = 10;
    public const MAX_CRITERION_POINTS  = 1000;

    /**
     * @return array{
     *     rubric: array{title: string, description: string|null},
     *     criteria: list<array{title: string, description: string|null, max_points: int|string, levels: list<array{label: string, description: string|null, points: int|string}>}>,
     *     errors: list<string>
     * }
     */
    public static function parse(array $post): array
    {
        $errors = [];

        $rubric = [
            'title'       => self::text($post['title'] ?? ''),
            'description' => self::nullableText($post['description'] ?? ''),
        ];

        if ($rubric['title'] === '') {
            $errors[] = 'The rubric title is required.';
        } elseif (mb_strlen($rubric['title']) > 150) {
            $errors[] = 'The rubric title must be 150 characters or fewer.';
        }

        if ($rubric['description'] !== null && mb_strlen($rubric['description']) > 2000) {
            $errors[] = 'The rubric description must be 2000 characters or fewer.';
        }

        $criteria = [];
        $rawCriteria = is_array($post['criteria'] ?? null) ? array_values($post['criteria']) : [];

        if ($rawCriteria === []) {
            $errors[] = 'Add at least one criterion.';
        } elseif (count($rawCriteria) > self::MAX_CRITERIA) {
            $errors[] = 'A rubric can have at most ' . self::MAX_CRITERIA . ' criteria.';
        }

        foreach (array_slice($rawCriteria, 0, self::MAX_CRITERIA) as $i => $raw) {
            $raw  = is_array($raw) ? $raw : [];
            $name = 'Criterion ' . ($i + 1);

            $criterion = [
                'title'       => self::text($raw['title'] ?? ''),
                'description' => self::nullableText($raw['description'] ?? ''),
                'max_points'  => self::text($raw['max_points'] ?? ''),
                'levels'      => [],
            ];

            if ($criterion['title'] === '') {
                $errors[] = "{$name}: the title is required.";
            } else {
                $name .= " ({$criterion['title']})";

                if (mb_strlen($criterion['title']) > 150) {
                    $errors[] = "{$name}: the title must be 150 characters or fewer.";
                }
            }

            if ($criterion['description'] !== null && mb_strlen($criterion['description']) > 1000) {
                $errors[] = "{$name}: the description must be 1000 characters or fewer.";
            }

            $maxPoints = self::wholeNumber($criterion['max_points']);

            if ($maxPoints === null || $maxPoints < 1 || $maxPoints > self::MAX_CRITERION_POINTS) {
                $errors[] = "{$name}: maximum points must be a whole number from 1 to " . self::MAX_CRITERION_POINTS . '.';
            } else {
                $criterion['max_points'] = $maxPoints;
            }

            $rawLevels = is_array($raw['levels'] ?? null) ? array_values($raw['levels']) : [];

            if (count($rawLevels) > self::MAX_LEVELS) {
                $errors[] = "{$name}: a criterion can have at most " . self::MAX_LEVELS . ' performance levels.';
            }

            $seenPoints = [];

            foreach (array_slice($rawLevels, 0, self::MAX_LEVELS) as $j => $rawLevel) {
                $rawLevel  = is_array($rawLevel) ? $rawLevel : [];
                $levelName = "{$name}, level " . ($j + 1);

                $level = [
                    'label'       => self::text($rawLevel['label'] ?? ''),
                    'description' => self::nullableText($rawLevel['description'] ?? ''),
                    'points'      => self::text($rawLevel['points'] ?? ''),
                ];

                if ($level['label'] === '') {
                    $errors[] = "{$levelName}: the label is required.";
                } elseif (mb_strlen($level['label']) > 100) {
                    $errors[] = "{$levelName}: the label must be 100 characters or fewer.";
                }

                if ($level['description'] !== null && mb_strlen($level['description']) > 1000) {
                    $errors[] = "{$levelName}: the description must be 1000 characters or fewer.";
                }

                $points = self::wholeNumber($level['points']);

                if ($points === null) {
                    $errors[] = "{$levelName}: points must be a whole number.";
                } elseif (is_int($criterion['max_points']) && $points > $criterion['max_points']) {
                    $errors[] = "{$levelName}: {$points} points is more than the criterion maximum of {$criterion['max_points']}.";
                } elseif (in_array($points, $seenPoints, true)) {
                    $errors[] = "{$levelName}: another level already gives {$points} points. Each level needs a different point value.";
                } else {
                    $level['points'] = $points;
                    $seenPoints[]    = $points;
                }

                $criterion['levels'][] = $level;
            }

            $criteria[] = $criterion;
        }

        return ['rubric' => $rubric, 'criteria' => $criteria, 'errors' => $errors];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === '' ? null : $text;
    }

    private static function wholeNumber(string $value): ?int
    {
        return preg_match('/^\d{1,6}$/', $value) === 1 ? (int) $value : null;
    }
}
