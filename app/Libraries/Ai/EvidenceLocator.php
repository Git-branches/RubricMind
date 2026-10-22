<?php

namespace App\Libraries\Ai;

/**
 * Finds an AI-quoted passage in the original essay (FR-08).
 *
 * Matching ignores letter case, runs of whitespace, and curly vs. straight
 * quotes, because AI output often differs in those ways. Anything else must
 * match exactly; otherwise the quote is treated as unverified.
 */
final class EvidenceLocator
{
    // Shorter quotes match too easily by chance to count as evidence.
    private const MIN_QUOTE_LENGTH = 12;

    /**
     * @return array{start: int, length: int}|null Character offsets in $text, or null when not found.
     */
    public static function locate(string $text, string $quote): ?array
    {
        $quote = trim($quote, " \t\n\r\"'“”‘’…");

        if (mb_strlen($quote) < self::MIN_QUOTE_LENGTH) {
            return null;
        }

        [$normalText, $map] = self::normalize($text);
        [$normalQuote]      = self::normalize($quote);

        $position = mb_strpos($normalText, $normalQuote);

        if ($position === false) {
            return null;
        }

        $start = $map[$position];
        $end   = $map[$position + mb_strlen($normalQuote) - 1] + 1;

        return ['start' => $start, 'length' => $end - $start];
    }

    /**
     * @return array{0: string, 1: list<int>} Normalized text and, for each of its
     *                                        characters, the position in the original.
     */
    private static function normalize(string $text): array
    {
        $out       = '';
        $map       = [];
        $lastSpace = true;

        foreach (mb_str_split($text) as $i => $char) {
            if (preg_match('/\s/u', $char) === 1) {
                if ($lastSpace) {
                    continue;
                }

                $char      = ' ';
                $lastSpace = true;
            } else {
                $char      = strtr(mb_strtolower($char), ['“' => '"', '”' => '"', '‘' => "'", '’' => "'"]);
                $lastSpace = false;
            }

            $out  .= $char;
            $map[] = $i;
        }

        if (str_ends_with($out, ' ')) {
            $out = mb_substr($out, 0, -1);
            array_pop($map);
        }

        return [$out, $map];
    }
}
