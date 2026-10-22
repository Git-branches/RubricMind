<?php

namespace Tests\Unit;

use App\Libraries\StyleTracker;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class StyleTrackerTest extends CIUnitTestCase
{
    /**
     * Short, simple sentences: the student's usual style.
     */
    public static function simpleEssay(int $sentences = 40): string
    {
        $pool = ['I like my school.', 'We play games at lunch.', 'My friends are kind to me.', 'The class is fun.', 'I read books at home.'];

        return implode(' ', array_map(static fn (int $i) => $pool[$i % count($pool)], range(0, $sentences - 1)));
    }

    /**
     * Long sentences with long, formal words: a very different style.
     */
    public static function formalEssay(int $sentences = 12): string
    {
        $pool = [
            'Contemporary educational institutions, notwithstanding considerable administrative constraints, increasingly acknowledge that adolescents require substantially extended nocturnal rest, consequently necessitating comprehensive reconsideration of conventional scheduling paradigms.',
            'Furthermore, longitudinal investigations demonstrate, with remarkable consistency, that delayed commencement times correlate significantly with improved concentration, enhanced academic performance, and diminished absenteeism.',
        ];

        return implode(' ', array_map(static fn (int $i) => $pool[$i % count($pool)], range(0, $sentences - 1)));
    }

    private static function history(string ...$essays): array
    {
        return array_map(static fn (string $e) => ['content' => $e, 'word_count' => count(preg_split('/\s+/', $e))], $essays);
    }

    public function testNoHistoryIsInsufficient(): void
    {
        $result = StyleTracker::compare(self::simpleEssay(), []);

        $this->assertSame('insufficient_history', $result['status']);
        $this->assertSame([], $result['metrics']);
    }

    public function testOneEarlierEssayIsInsufficient(): void
    {
        $result = StyleTracker::compare(self::simpleEssay(), self::history(self::simpleEssay()));

        $this->assertSame('insufficient_history', $result['status']);
        $this->assertSame(1, $result['history_count']);
    }

    public function testShortEarlierEssaysDoNotCount(): void
    {
        $result = StyleTracker::compare(self::simpleEssay(), self::history(self::simpleEssay(5), self::simpleEssay(5), self::simpleEssay()));

        $this->assertSame('insufficient_history', $result['status']);
        $this->assertSame(1, $result['history_count']);
    }

    public function testSimilarWritingIsConsistent(): void
    {
        $result = StyleTracker::compare(self::simpleEssay(42), self::history(self::simpleEssay(40), self::simpleEssay(45)));

        $this->assertSame('consistent', $result['status']);
        $this->assertCount(count(StyleTracker::METRICS), $result['metrics']);
    }

    public function testVeryDifferentWritingIsANotableChange(): void
    {
        $result  = StyleTracker::compare(self::formalEssay(), self::history(self::simpleEssay(40), self::simpleEssay(45)));
        $changed = array_column(array_filter($result['metrics'], static fn (array $m) => $m['notable']), 'key');

        $this->assertSame('notable_change', $result['status']);
        $this->assertContains('avg_sentence_length', $changed);
        $this->assertContains('avg_word_length', $changed);
    }

    public function testMeasuresBasicMetrics(): void
    {
        $metrics = StyleTracker::measure('One two three four. Five six, seven eight.');

        $this->assertSame(4.0, $metrics['avg_sentence_length']);
        $this->assertSame(12.5, $metrics['comma_rate']);
    }
}
