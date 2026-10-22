<?php

namespace Tests\Unit\Ai;

use App\Libraries\Ai\AiResultNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class AiResultNormalizerTest extends CIUnitTestCase
{
    private const ESSAY = 'School should start later. Research shows teenagers need more sleep.';

    private function criteria(): array
    {
        return [
            ['id' => 1, 'title' => 'Thesis', 'max_points' => 10, 'levels' => [
                ['id' => 11, 'label' => 'Excellent', 'points' => 10],
                ['id' => 12, 'label' => 'Developing', 'points' => 5],
            ]],
            ['id' => 2, 'title' => 'Evidence', 'max_points' => 15, 'levels' => []],
        ];
    }

    public function testDropsUnknownAndDuplicateCriteria(): void
    {
        $result = AiResultNormalizer::normalize(['criteria' => [
            ['criterion_id' => 99, 'suggested_points' => 5],
            ['criterion_id' => 2, 'suggested_points' => 9],
            ['criterion_id' => 2, 'suggested_points' => 1],
        ]], $this->criteria(), self::ESSAY);

        $this->assertSame([2], array_keys($result['criteria']));
        $this->assertSame(9, $result['criteria'][2]['points']);
    }

    public function testClampsScoresAndSnapsToNearestLevel(): void
    {
        $result = AiResultNormalizer::normalize(['criteria' => [
            ['criterion_id' => 1, 'suggested_points' => 8],
            ['criterion_id' => 2, 'suggested_points' => 40],
        ]], $this->criteria(), self::ESSAY);

        $this->assertSame(10, $result['criteria'][1]['points']);
        $this->assertSame(11, $result['criteria'][1]['level_id']);
        $this->assertSame(15, $result['criteria'][2]['points']);
        $this->assertNull($result['criteria'][2]['level_id']);
    }

    public function testTieBetweenLevelsChoosesTheLowerLevel(): void
    {
        $result = AiResultNormalizer::normalize(['criteria' => [['criterion_id' => 1, 'suggested_points' => 7]]], $this->criteria(), self::ESSAY);

        // 7 is closer to 5 than 10.
        $this->assertSame(5, $result['criteria'][1]['points']);

        $tie = AiResultNormalizer::normalize(['criteria' => [['criterion_id' => 1, 'suggested_points' => 8]]], [
            ['id' => 1, 'title' => 'T', 'max_points' => 10, 'levels' => [['id' => 1, 'points' => 6], ['id' => 2, 'points' => 10]]],
        ], self::ESSAY);

        $this->assertSame(6, $tie['criteria'][1]['points']);
    }

    public function testMarksQuotesNotFoundInTheEssayAsUnverified(): void
    {
        $result = AiResultNormalizer::normalize(['criteria' => [[
            'criterion_id'     => 2,
            'suggested_points' => 10,
            'evidence'         => [
                ['quote' => 'Research shows teenagers need more sleep.', 'note' => 'Uses research.'],
                ['quote' => 'Studies from Harvard prove this beyond doubt.', 'note' => 'Invented.'],
            ],
        ]]], $this->criteria(), self::ESSAY);

        [$real, $invented] = $result['criteria'][2]['evidence'];
        $this->assertTrue($real['verified']);
        $this->assertSame(27, $real['start']);
        $this->assertFalse($invented['verified']);
        $this->assertNull($invented['start']);
    }

    public function testIgnoresMalformedOutput(): void
    {
        $result = AiResultNormalizer::normalize(['criteria' => 'nonsense', 'feedback' => 5, 'mechanics' => [['pattern' => '']]], $this->criteria(), self::ESSAY);

        $this->assertSame([], $result['criteria']);
        $this->assertSame([], $result['mechanics']);
        $this->assertSame('', $result['feedback']['summary']);
    }
}
