<?php

namespace Tests\Unit\Ai;

use App\Libraries\Ai\AiException;
use App\Libraries\Ai\AiResultNormalizer;
use App\Libraries\Ai\DemoAnalyzer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class DemoAnalyzerTest extends CIUnitTestCase
{
    private const ESSAY = "School should start later because students is always tired in the morning.\n\n"
        . "Research shows that teenagers need more sleep. For example, a survey found that most students sleep less than seven hours.\n\n"
        . "However students also have activities after school. In conclusion schools should try a later start.";

    private function criteria(): array
    {
        return [
            ['id' => 1, 'title' => 'Thesis statement', 'description' => 'Clear position.', 'max_points' => 10, 'levels' => [
                ['id' => 11, 'label' => 'Excellent', 'description' => null, 'points' => 10],
                ['id' => 12, 'label' => 'Developing', 'description' => null, 'points' => 6],
            ]],
            ['id' => 2, 'title' => 'Evidence and support', 'description' => null, 'max_points' => 10, 'levels' => []],
            ['id' => 3, 'title' => 'Grammar and mechanics', 'description' => null, 'max_points' => 5, 'levels' => []],
        ];
    }

    public function testSuggestsAScoreForEveryCriterion(): void
    {
        $raw = (new DemoAnalyzer())->analyze(['essay' => self::ESSAY, 'criteria' => $this->criteria()]);

        $this->assertSame([1, 2, 3], array_column($raw['criteria'], 'criterion_id'));

        foreach ($raw['criteria'] as $suggestion) {
            $this->assertNotSame('', $suggestion['rationale']);
        }
    }

    public function testEvidenceQuotesAreExactTextFromTheEssay(): void
    {
        $raw    = (new DemoAnalyzer())->analyze(['essay' => self::ESSAY, 'criteria' => $this->criteria()]);
        $result = AiResultNormalizer::normalize($raw, $this->criteria(), self::ESSAY);

        $quotes = array_merge(...array_map(static fn ($c) => $c['evidence'], array_values($result['criteria'])));
        $this->assertNotEmpty($quotes);

        foreach ($quotes as $quote) {
            $this->assertTrue($quote['verified'], "Quote not found in essay: {$quote['quote']}");
        }
    }

    public function testFindsRecurringMechanicsPatterns(): void
    {
        $raw      = (new DemoAnalyzer())->analyze(['essay' => self::ESSAY, 'criteria' => $this->criteria()]);
        $patterns = array_column($raw['mechanics'], 'pattern');

        $this->assertContains('Subject-verb agreement', $patterns);
        $this->assertContains('Missing comma after an introductory word', $patterns);
    }

    public function testFeedbackHasStrengthsAndNextSteps(): void
    {
        $raw = (new DemoAnalyzer())->analyze(['essay' => self::ESSAY, 'criteria' => $this->criteria()]);

        $this->assertNotSame('', $raw['feedback']['summary']);
        $this->assertNotEmpty($raw['feedback']['strengths']);
        $this->assertNotEmpty($raw['feedback']['improvements']);
    }

    public function testEmptyEssayFails(): void
    {
        $this->expectException(AiException::class);

        (new DemoAnalyzer())->analyze(['essay' => '   ', 'criteria' => $this->criteria()]);
    }

    public function testIsLabeledAsSimulated(): void
    {
        $this->assertTrue((new DemoAnalyzer())->isSimulated());
    }

    private const STRONG_ESSAY = "Every morning, thousands of teenagers drag themselves to class before sunrise. Our school should start at 8:30 a.m. because later classes would improve students' health, attendance, and learning.\n\n"
        . "First, teenagers need more sleep. According to the American Academy of Pediatrics, teens need eight to ten hours each night, yet a survey found that 73 percent of students sleep less than eight hours. This shows that the schedule works against their sleep cycles.\n\n"
        . "Furthermore, districts with later start times report clear benefits. For example, a study in Minnesota found that attendance rose after classes began later. This suggests that a small change can make a measurable difference.\n\n"
        . "Some people argue that a later start would interfere with sports. However, the districts in the study adjusted practice times with little disruption.\n\n"
        . "In conclusion, starting school at 8:30 a.m. would make students healthier and better prepared to learn. Our school board should take this step.";

    private function total(string $essay, array $criteria): int
    {
        $raw = (new DemoAnalyzer())->analyze(['essay' => $essay, 'criteria' => $criteria]);

        return array_sum(array_column(AiResultNormalizer::normalize($raw, $criteria, $essay)['criteria'], 'points'));
    }

    public function testStrongEssayScoresHigherThanWeakEssay(): void
    {
        $this->assertGreaterThan($this->total(self::ESSAY, $this->criteria()) + 5, $this->total(self::STRONG_ESSAY, $this->criteria()));
    }

    public function testMatchesCriterionKindsFromTheirTitles(): void
    {
        $this->assertSame('counterargument', DemoAnalyzer::kindOf(['title' => 'Counterargument and rebuttal', 'description' => null]));
        $this->assertSame('conclusion', DemoAnalyzer::kindOf(['title' => 'Conclusion', 'description' => null]));
        $this->assertSame('word_choice', DemoAnalyzer::kindOf(['title' => 'Word choice and voice', 'description' => null]));
        $this->assertSame('analysis', DemoAnalyzer::kindOf(['title' => 'Ideas and content', 'description' => null]));
        $this->assertSame('mechanics', DemoAnalyzer::kindOf(['title' => 'Conventions', 'description' => null]));
        $this->assertSame('general', DemoAnalyzer::kindOf(['title' => 'Effort', 'description' => null]));
    }

    public function testRecognizesAnsweredCounterargument(): void
    {
        $criteria = [['id' => 1, 'title' => 'Counterargument', 'description' => null, 'max_points' => 5, 'levels' => []]];

        $strong = (new DemoAnalyzer())->analyze(['essay' => self::STRONG_ESSAY, 'criteria' => $criteria])['criteria'][0];
        $weak   = (new DemoAnalyzer())->analyze(['essay' => self::ESSAY, 'criteria' => $criteria])['criteria'][0];

        $this->assertStringContainsString('found and answered', $strong['rationale']);
        $this->assertSame('Some people argue that a later start would interfere with sports.', $strong['evidence'][0]['quote']);
        $this->assertLessThan($strong['suggested_points'], $weak['suggested_points']);
    }

    public function testFindsCommonlyConfusedWordsAndMisspellings(): void
    {
        $essay = "Its a fact that students are more tired then adults. Their is a reason for this. I recieve alot of homework, "
            . "and a hour of sleep is lost. We could of fixed this.";

        $raw      = (new DemoAnalyzer())->analyze(['essay' => $essay, 'criteria' => $this->criteria()]);
        $patterns = array_column($raw['mechanics'], null, 'pattern');

        foreach (['Its / it\'s', 'Then / than', 'Their / there / they\'re', 'A / an', '"Could of" instead of "could have"', 'Misspelled words'] as $expected) {
            $this->assertArrayHasKey($expected, $patterns);
        }

        $this->assertStringContainsString('"recieve" → "receive"', $patterns['Misspelled words']['suggestion']);
        $this->assertStringStartsWith('Usage: ', $patterns['Then / than']['description']);
    }

    public function testCorrectEssayHasNoErrorPatterns(): void
    {
        $raw    = (new DemoAnalyzer())->analyze(['essay' => self::STRONG_ESSAY, 'criteria' => $this->criteria()]);
        $errors = array_filter($raw['mechanics'], static fn ($p) => ! str_starts_with($p['description'], 'Style: '));

        $this->assertSame([], array_column($errors, 'pattern'));
    }

    public function testFeedbackFitsTheStoredLengths(): void
    {
        foreach ([self::ESSAY, self::STRONG_ESSAY] as $essay) {
            $feedback = (new DemoAnalyzer())->analyze(['essay' => $essay, 'criteria' => $this->criteria()])['feedback'];

            $this->assertLessThanOrEqual(1000, mb_strlen($feedback['summary']));

            foreach (array_merge($feedback['strengths'], $feedback['improvements']) as $item) {
                $this->assertLessThanOrEqual(300, mb_strlen($item));
            }
        }
    }
}
