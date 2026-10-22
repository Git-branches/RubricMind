<?php

namespace App\Libraries\Ai;

/**
 * Cleans an EssayAnalyzer result before it is stored:
 *
 * - drops suggestions for criteria that are not in the rubric;
 * - keeps scores inside the criterion range and snaps them to a performance level;
 * - trims text to sensible lengths;
 * - locates every evidence quote in the essay and marks unmatched ones unverified.
 *
 * Never trust provider output directly: a real AI can return anything.
 */
final class AiResultNormalizer
{
    private const MAX_EVIDENCE_PER_CRITERION = 3;
    private const MAX_FEEDBACK_ITEMS         = 5;
    private const MAX_MECHANICS_PATTERNS     = 8;
    private const MAX_EXAMPLES               = 3;

    /**
     * @param list<array<string, mixed>> $criteria Rubric criteria with levels.
     *
     * @return array{
     *     criteria: array<int, array{points: int, level_id: int|null, rationale: string|null,
     *         evidence: list<array{quote: string, note: string|null, start: int|null, length: int|null, verified: bool}>}>,
     *     feedback: array{summary: string, strengths: list<string>, improvements: list<string>},
     *     mechanics: list<array{pattern: string, description: string|null, occurrences: int, examples: list<string>, suggestion: string|null}>
     * }
     */
    public static function normalize(array $raw, array $criteria, string $essay): array
    {
        $byId = [];

        foreach ($criteria as $criterion) {
            $byId[(int) $criterion['id']] = $criterion;
        }

        $result = ['criteria' => [], 'feedback' => self::feedback($raw['feedback'] ?? []), 'mechanics' => []];

        foreach (self::listOf($raw['criteria'] ?? []) as $item) {
            $id = (int) ($item['criterion_id'] ?? 0);

            if (! isset($byId[$id]) || isset($result['criteria'][$id])) {
                continue;
            }

            [$points, $levelId] = self::snap($byId[$id], (int) ($item['suggested_points'] ?? 0));

            $evidence = [];

            foreach (array_slice(self::listOf($item['evidence'] ?? []), 0, self::MAX_EVIDENCE_PER_CRITERION) as $piece) {
                $quote = self::text($piece['quote'] ?? '', 500);

                if ($quote === null) {
                    continue;
                }

                $location   = EvidenceLocator::locate($essay, $quote);
                $evidence[] = [
                    'quote'    => $quote,
                    'note'     => self::text($piece['note'] ?? '', 300),
                    'start'    => $location['start'] ?? null,
                    'length'   => $location['length'] ?? null,
                    'verified' => $location !== null,
                ];
            }

            $result['criteria'][$id] = [
                'points'    => $points,
                'level_id'  => $levelId,
                'rationale' => self::text($item['rationale'] ?? '', 1000),
                'evidence'  => $evidence,
            ];
        }

        foreach (array_slice(self::listOf($raw['mechanics'] ?? []), 0, self::MAX_MECHANICS_PATTERNS) as $item) {
            $pattern = self::text($item['pattern'] ?? '', 150);

            if ($pattern === null) {
                continue;
            }

            $examples = array_values(array_filter(array_map(
                static fn ($e) => self::text($e, 200),
                array_slice(self::listOf($item['examples'] ?? []), 0, self::MAX_EXAMPLES),
            )));

            $result['mechanics'][] = [
                'pattern'     => $pattern,
                'description' => self::text($item['description'] ?? '', 500),
                'occurrences' => max(1, min(999, (int) ($item['occurrences'] ?? 1))),
                'examples'    => $examples,
                'suggestion'  => self::text($item['suggestion'] ?? '', 500),
            ];
        }

        return $result;
    }

    /**
     * Keeps a score inside 0..max and, when the criterion has levels, moves it
     * to the nearest level (the lower one on a tie).
     *
     * @return array{0: int, 1: int|null} Points and level ID.
     */
    private static function snap(array $criterion, int $points): array
    {
        $points = max(0, min((int) $criterion['max_points'], $points));

        if ($criterion['levels'] === []) {
            return [$points, null];
        }

        $best = null;

        foreach ($criterion['levels'] as $level) {
            $distance = abs($level['points'] - $points);

            if ($best === null || $distance < $best[0] || ($distance === $best[0] && $level['points'] < $best[1]['points'])) {
                $best = [$distance, $level];
            }
        }

        return [$best[1]['points'], (int) $best[1]['id']];
    }

    private static function feedback(mixed $raw): array
    {
        $raw   = is_array($raw) ? $raw : [];
        $items = static fn ($list) => array_values(array_filter(array_map(
            static fn ($s) => self::text($s, 300),
            array_slice(self::listOf($list), 0, self::MAX_FEEDBACK_ITEMS),
        )));

        return [
            'summary'      => self::text($raw['summary'] ?? '', 1000) ?? '',
            'strengths'    => $items($raw['strengths'] ?? []),
            'improvements' => $items($raw['improvements'] ?? []),
        ];
    }

    private static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
