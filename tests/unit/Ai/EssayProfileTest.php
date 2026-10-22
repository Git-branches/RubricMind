<?php

namespace Tests\Unit\Ai;

use App\Libraries\Ai\Demo\EssayProfile;
use App\Libraries\Ai\Demo\MechanicsChecker;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The measurements behind the Demo AI.
 *
 * @internal
 */
final class EssayProfileTest extends CIUnitTestCase
{
    public function testSplitsSentencesWithoutBreakingAbbreviationsOrNumbers(): void
    {
        $this->assertSame(
            ['School starts at 7:30 a.m. every day.', 'Teens need 8.5 hours of sleep, e.g. on weekdays.', 'they are tired.', 'Is that fair?'],
            EssayProfile::splitSentences('School starts at 7:30 a.m. every day. Teens need 8.5 hours of sleep, e.g. on weekdays. they are tired. Is that fair?'),
        );
    }

    public function testSentencesAreExactSubstringsOfTheEssay(): void
    {
        $essay   = "First line here.  Second “quoted” one!\nThird line without end\n\nNew paragraph. Another one?";
        $profile = new EssayProfile($essay);

        foreach ($profile->sentences as $sentence) {
            $this->assertStringContainsString($sentence, $essay);
        }

        // Blank lines separate paragraphs; a single line break inside one does not.
        $this->assertSame(2, $profile->paragraphCount());
    }

    public function testFindsThesisInTheIntroduction(): void
    {
        $profile = new EssayProfile("Mornings are hard. Schools should start later because students need sleep.\n\nResearch shows teens sleep too little.");
        $thesis  = $profile->thesis();

        $this->assertSame('Schools should start later because students need sleep.', $thesis['sentence']);
        $this->assertTrue($thesis['in_intro']);
        $this->assertTrue($thesis['has_reason']);
    }

    public function testTimesAreNotStatistics(): void
    {
        $profile = new EssayProfile('Class starts at 8:30 in Grade 10. Yet 73 percent of students sleep less than 8 hours.');
        $evidence = $profile->evidence();

        $this->assertCount(1, $evidence);
        $this->assertContains('statistic', $evidence[0]['types']);
    }

    public function testArticleRuleKnowsVowelSounds(): void
    {
        $this->assertTrue(MechanicsChecker::startsWithVowelSound('hour'));
        $this->assertTrue(MechanicsChecker::startsWithVowelSound('example'));
        $this->assertFalse(MechanicsChecker::startsWithVowelSound('university'));
        $this->assertFalse(MechanicsChecker::startsWithVowelSound('one'));
        $this->assertFalse(MechanicsChecker::startsWithVowelSound('big'));
    }

    public function testGradeLevelIsHigherForComplexWriting(): void
    {
        $simple  = new EssayProfile('I like school. It is fun. We play a lot. My friends are nice.');
        $complex = new EssayProfile('Adolescents experience significant physiological changes that fundamentally alter their circadian rhythms, consequently making early instruction considerably less effective.');

        $this->assertGreaterThan($simple->gradeLevel() + 5, $complex->gradeLevel());
    }
}
