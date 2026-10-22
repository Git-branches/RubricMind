<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AuditDetailsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('format');
    }

    public function testShowsKeysAndValuesAsLabels(): void
    {
        $html = audit_details('{"total_score":28,"provider":"demo"}');

        $this->assertStringContainsString('<span class="detail__key">total score:</span> 28', $html);
        $this->assertStringContainsString('<span class="detail__key">provider:</span> demo', $html);
    }

    public function testShowsFromToChangesAsArrows(): void
    {
        $html = audit_details('{"score_changes":{"1":{"from":null,"to":10},"2":{"from":5,"to":6}}}');

        $this->assertStringContainsString('score changes 1:</span> none → 10', $html);
        $this->assertStringContainsString('score changes 2:</span> 5 → 6', $html);
    }

    public function testEscapesUserText(): void
    {
        $html = audit_details('{"reason":"<script>alert(1)</script>"}');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testHandlesEmptyAndInvalidJson(): void
    {
        $this->assertSame('', audit_details(null));
        $this->assertStringContainsString('not json &amp; more', audit_details('not json & more'));
    }
}
