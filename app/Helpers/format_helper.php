<?php

if (! function_exists('format_datetime')) {
    /**
     * "Oct 21, 2026, 3:30 PM" for display. Returns an empty string for null.
     */
    function format_datetime(?string $datetime): string
    {
        return $datetime === null ? '' : date('M j, Y, g:i A', strtotime($datetime));
    }
}

if (! function_exists('format_bytes')) {
    /**
     * "24 KB", "1.3 MB" for file sizes.
     */
    function format_bytes(?int $bytes): string
    {
        return match (true) {
            $bytes === null        => '',
            $bytes < 1024          => $bytes . ' B',
            $bytes < 1024 * 1024   => round($bytes / 1024) . ' KB',
            default                => round($bytes / 1024 / 1024, 1) . ' MB',
        };
    }
}

if (! function_exists('audit_details')) {
    /**
     * Audit log details as short, escaped "key: value" labels instead of raw JSON.
     * A {"from": a, "to": b} pair reads "a → b"; any other nested value stays compact JSON.
     */
    function audit_details(?string $json): string
    {
        if ($json === null || $json === '') {
            return '';
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            return '<span class="detail">' . esc($json) . '</span>';
        }

        $value = static function ($v) use (&$value): string {
            if (is_array($v) && array_key_exists('from', $v) && array_key_exists('to', $v) && count($v) === 2) {
                return $value($v['from']) . ' → ' . $value($v['to']);
            }

            return match (true) {
                $v === null  => 'none',
                is_bool($v)  => $v ? 'yes' : 'no',
                is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                default      => (string) $v,
            };
        };

        $html = '';

        foreach ($data as $key => $v) {
            // Lists of changes (such as score_changes) show one label per entry.
            if (is_array($v) && $v !== [] && array_is_list($v) === false && is_array(reset($v))) {
                foreach ($v as $subKey => $subValue) {
                    $html .= '<span class="detail"><span class="detail__key">' . esc(str_replace('_', ' ', $key . ' ' . $subKey)) . ':</span> ' . esc($value($subValue)) . '</span>';
                }

                continue;
            }

            $html .= '<span class="detail"><span class="detail__key">' . esc(str_replace('_', ' ', (string) $key)) . ':</span> ' . esc($value($v)) . '</span>';
        }

        return $html;
    }
}

if (! function_exists('highlight_essay')) {
    /**
     * Escaped essay text with verified AI evidence wrapped in <mark> (FR-08).
     * Each mark shows its criterion number, so highlights do not rely on color alone.
     * Unverified or overlapping quotes are not highlighted.
     *
     * @param list<array{id: int, criterion_id: int, start: int|null, length: int|null, verified: bool}> $highlights
     * @param array<int, int>                                                                          $numbers    Criterion ID => position in the rubric (1-based).
     * @param bool                                                                                     $withIds    Add id="evidence-N" anchors. Use false for a second copy on the same page.
     */
    function highlight_essay(string $text, array $highlights, array $numbers, bool $withIds = true): string
    {
        $spans = array_values(array_filter($highlights, static fn (array $h) => $h['verified'] && $h['start'] !== null));
        usort($spans, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $html     = '';
        $position = 0;

        foreach ($spans as $span) {
            if ($span['start'] < $position) {
                continue;
            }

            $number = $numbers[$span['criterion_id']] ?? 0;
            $html  .= esc(mb_substr($text, $position, $span['start'] - $position));
            $html  .= '<mark class="evidence evidence--' . ($number % 6) . '"' . ($withIds ? ' id="evidence-' . $span['id'] . '"' : '') . '>'
                . '<span class="visually-hidden">Evidence for criterion ' . $number . ': </span>'
                . esc(mb_substr($text, $span['start'], $span['length']))
                . '<sup class="evidence__tag" aria-hidden="true">' . $number . '</sup></mark>';
            $position = $span['start'] + $span['length'];
        }

        return $html . esc(mb_substr($text, $position));
    }
}

if (! function_exists('assignment_status_label')) {
    function assignment_status_label(string $status): string
    {
        return match ($status) {
            'draft'     => 'Draft',
            'published' => 'Open',
            'closed'    => 'Closed',
            default     => ucfirst($status),
        };
    }
}

if (! function_exists('submission_status_label')) {
    /**
     * Teacher-facing label for the grading workflow (FR-06).
     */
    function submission_status_label(?string $status): string
    {
        return match ($status) {
            null               => 'Not submitted',
            'submitted'        => 'Submitted',
            'ai_evaluated'     => 'AI suggestions ready',
            'teacher_reviewed' => 'Reviewed',
            'finalized'        => 'Finalized',
            'released'         => 'Released',
            default            => ucfirst($status),
        };
    }
}
