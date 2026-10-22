<?php

namespace App\Libraries\Ai;

/**
 * An AI provider that reads one essay against a rubric (FR-07, FR-08, FR-10).
 *
 * Implementations receive only the essay text and the rubric, never student
 * names, IDs, emails, or class details (privacy requirement).
 *
 * analyze() returns this shape; AiResultNormalizer cleans and verifies it:
 *
 *   [
 *     'criteria' => [[
 *       'criterion_id'     => int,
 *       'suggested_points' => int,
 *       'rationale'        => string,
 *       'evidence'         => [['quote' => string (exact text from the essay), 'note' => string]],
 *     ]],
 *     'feedback' => ['summary' => string, 'strengths' => list<string>, 'improvements' => list<string>],
 *     'mechanics' => [[
 *       'pattern' => string, 'description' => string, 'occurrences' => int,
 *       'examples' => list<string>, 'suggestion' => string,
 *     ]],
 *   ]
 */
interface EssayAnalyzer
{
    /**
     * Short provider key stored with each run, e.g. "demo" or "claude".
     */
    public function provider(): string;

    public function model(): string;

    /**
     * Version of the instructions/heuristics, stored so results can be explained later.
     */
    public function promptVersion(): string;

    /**
     * Whether results come from a simulation rather than a real AI model.
     */
    public function isSimulated(): bool;

    /**
     * @param array{
     *     essay: string,
     *     criteria: list<array{id: int, title: string, description: string|null, max_points: int,
     *         levels: list<array{id: int, label: string, description: string|null, points: int}>}>
     * } $request
     *
     * @throws AiException When the provider cannot produce a result.
     */
    public function analyze(array $request): array;

    /**
     * Drafts a lesson plan for the class's learning gaps (FR-11). The input is
     * aggregated results only (see ClassInsights::lessonFocus()), never student data.
     *
     * @return array{title: string, objective: string, duration_minutes: int,
     *     sections: list<array{heading: string, minutes: int, steps: list<string>}>,
     *     materials: list<string>, assessment: string}
     *
     * @throws AiException
     */
    public function lessonPlan(array $focus): array;
}
