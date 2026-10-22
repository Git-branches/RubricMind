<?php

namespace Tests\Unit\Ai;

use App\Libraries\Ai\EvidenceLocator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class EvidenceLocatorTest extends CIUnitTestCase
{
    private const ESSAY = "Many students arrive tired.\n\nResearch shows  that teenagers' sleep shifts later. “Later starts” help.";

    public function testFindsExactQuote(): void
    {
        $found = EvidenceLocator::locate(self::ESSAY, 'Many students arrive tired.');

        $this->assertSame(['start' => 0, 'length' => 27], $found);
    }

    public function testIgnoresCaseWhitespaceAndQuoteStyle(): void
    {
        $quote = 'research shows that TEENAGERS\' sleep shifts later. "Later starts" help';
        $found = EvidenceLocator::locate(self::ESSAY, $quote);

        $this->assertNotNull($found);
        $this->assertSame('Research shows  that teenagers\' sleep shifts later. “Later starts” help', mb_substr(self::ESSAY, $found['start'], $found['length']));
    }

    public function testReturnsNullForTextNotInTheEssay(): void
    {
        $this->assertNull(EvidenceLocator::locate(self::ESSAY, 'Schools that start later report higher grades.'));
    }

    public function testRejectsQuotesTooShortToBeMeaningful(): void
    {
        $this->assertNull(EvidenceLocator::locate(self::ESSAY, 'tired'));
    }

    public function testWorksWithMultibyteText(): void
    {
        $essay = 'Ang mga estudyante ay pagod. Ñandú café résumé is here.';
        $found = EvidenceLocator::locate($essay, 'ñandú café résumé');

        $this->assertSame('Ñandú café résumé', mb_substr($essay, $found['start'], $found['length']));
    }
}
