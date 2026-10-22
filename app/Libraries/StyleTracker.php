<?php

namespace App\Libraries;

/**
 * Style Growth Tracker (FR-09): compares an essay's writing style with the
 * same student's earlier graded essays.
 *
 * The measurements are simple and explainable, computed locally without AI.
 * A change is an uncertain signal for the teacher to look at, never proof of
 * AI use, plagiarism, or misconduct, and it never affects the grade.
 */
final class StyleTracker
{
    // Sufficient history: at least this many earlier finalized essays of this length.
    public const MIN_HISTORY       = 2;
    public const MIN_HISTORY_WORDS = 150;

    // A notable change needs at least this many metrics to differ.
    private const MIN_CHANGED_METRICS = 2;

    private const TRANSITIONS = ['however', 'therefore', 'furthermore', 'moreover', 'in addition', 'for example',
        'for instance', 'consequently', 'as a result', 'on the other hand', 'in conclusion', 'nevertheless', 'although'];

    /**
     * Each metric: label, explanation, and how big a change must be (relative
     * to the student's usual value, and in absolute units) to count as notable.
     */
    public const METRICS = [
        'avg_sentence_length' => [
            'label'        => 'Average sentence length',
            'unit'         => 'words',
            'explanation'  => 'How many words a typical sentence has.',
            'min_relative' => 0.35,
            'min_absolute' => 4.0,
        ],
        'avg_word_length' => [
            'label'        => 'Average word length',
            'unit'         => 'letters',
            'explanation'  => 'Longer words often mean more formal or technical vocabulary.',
            'min_relative' => 0.15,
            'min_absolute' => 0.6,
        ],
        'vocabulary_richness' => [
            'label'        => 'Vocabulary variety',
            'unit'         => 'index',
            'explanation'  => 'Different words used, adjusted for essay length.',
            'min_relative' => 0.25,
            'min_absolute' => 1.5,
        ],
        'long_word_ratio' => [
            'label'        => 'Long words',
            'unit'         => '% of words',
            'explanation'  => 'Share of words with 7 or more letters.',
            'min_relative' => 0.40,
            'min_absolute' => 6.0,
        ],
        'comma_rate' => [
            'label'        => 'Comma use',
            'unit'         => 'per 100 words',
            'explanation'  => 'Commas usually rise with longer, more complex sentences.',
            'min_relative' => 0.50,
            'min_absolute' => 2.0,
        ],
        'transition_rate' => [
            'label'        => 'Transition words',
            'unit'         => 'per 100 words',
            'explanation'  => 'Words like "however" and "therefore".',
            'min_relative' => 0.60,
            'min_absolute' => 1.0,
        ],
    ];

    /**
     * @return array<string, float> Metric key => value.
     */
    public static function measure(string $text): array
    {
        $words     = preg_split('/[^\p{L}\p{N}\']+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = max(1, count($words));
        $sentences = max(1, count(preg_split('/[.!?]+(?=\s|$)/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $letters   = array_map('mb_strlen', $words);
        $lower     = ' ' . mb_strtolower(preg_replace('/\s+/u', ' ', $text)) . ' ';

        $transitions = 0;

        foreach (self::TRANSITIONS as $transition) {
            $transitions += preg_match_all('/(?<!\p{L})' . preg_quote($transition, '/') . '(?!\p{L})/u', $lower);
        }

        return [
            'avg_sentence_length' => round($wordCount / $sentences, 2),
            'avg_word_length'     => round(array_sum($letters) / $wordCount, 2),
            'vocabulary_richness' => round(count(array_unique($words)) / sqrt($wordCount), 2),
            'long_word_ratio'     => round(count(array_filter($letters, static fn (int $n) => $n >= 7)) / $wordCount * 100, 2),
            'comma_rate'          => round(substr_count($text, ',') / $wordCount * 100, 2),
            'transition_rate'     => round($transitions / $wordCount * 100, 2),
        ];
    }

    /**
     * @param list<array{content: string, word_count: int}> $history Earlier finalized essays by the same student.
     *
     * @return array{
     *     status: 'insufficient_history'|'consistent'|'notable_change',
     *     history_count: int,
     *     metrics: list<array{key: string, label: string, unit: string, explanation: string,
     *         current: float, usual: float, change: float, notable: bool, direction: string}>
     * }
     */
    public static function compare(string $current, array $history): array
    {
        $eligible = array_values(array_filter($history, static fn (array $e) => $e['word_count'] >= self::MIN_HISTORY_WORDS));

        if (count($eligible) < self::MIN_HISTORY) {
            return ['status' => 'insufficient_history', 'history_count' => count($eligible), 'metrics' => []];
        }

        $now      = self::measure($current);
        $previous = array_map(static fn (array $e) => self::measure($e['content']), $eligible);
        $metrics  = [];

        foreach (self::METRICS as $key => $meta) {
            $usual    = array_sum(array_column($previous, $key)) / count($previous);
            $relative = $usual > 0 ? ($now[$key] - $usual) / $usual : 0.0;
            $notable  = abs($relative) >= $meta['min_relative'] && abs($now[$key] - $usual) >= $meta['min_absolute'];

            $metrics[] = [
                'key'         => $key,
                'label'       => $meta['label'],
                'unit'        => $meta['unit'],
                'explanation' => $meta['explanation'],
                'current'     => $now[$key],
                'usual'       => round($usual, 2),
                'change'      => round($relative * 100),
                'notable'     => $notable,
                'direction'   => $now[$key] >= $usual ? 'higher' : 'lower',
            ];
        }

        $changed = count(array_filter($metrics, static fn (array $m) => $m['notable']));

        return [
            'status'        => $changed >= self::MIN_CHANGED_METRICS ? 'notable_change' : 'consistent',
            'history_count' => count($eligible),
            'metrics'       => $metrics,
        ];
    }
}
