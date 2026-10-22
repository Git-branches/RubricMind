<?php

namespace App\Libraries\Ai;

use App\Libraries\Ai\Demo\EssayProfile;
use App\Libraries\Ai\Demo\MechanicsChecker;

/**
 * The built-in, rule-based essay reviewer. It never calls an external service.
 *
 * It reads the essay the way a writing teacher does (see Demo\EssayProfile):
 * thesis and introduction, evidence and whether it is explained, counter-
 * arguments, conclusion, transitions and paragraphing, sentence variety and
 * vocabulary, plus about 30 grammar, usage, spelling, and punctuation checks
 * (Demo\MechanicsChecker). Each rubric criterion is matched to the kind of
 * writing skill it describes and scored from those measurements, with a
 * rationale that says what was found and what the next level needs.
 *
 * The rules are explainable and consistent, but they do not understand meaning
 * the way a language model does, so the interface labels results
 * "Demo AI (simulated)" and the teacher reviews every suggestion.
 */
final class DemoAnalyzer implements EssayAnalyzer
{
    // Rubric criterion kinds, checked in this order against the title, then the description.
    private const KINDS = [
        'counterargument' => ['counter', 'rebuttal', 'opposing', 'opposition', 'refut', 'other side', 'alternative view'],
        'introduction'    => ['introduction', 'intro', 'hook', 'opening'],
        'conclusion'      => ['conclusion', 'closing', 'concluding', 'ending'],
        'thesis'          => ['thesis', 'claim', 'position', 'stance', 'main idea', 'controlling idea', 'argument', 'purpose', 'focus'],
        'evidence'        => ['evidence', 'support', 'source', 'example', 'research', 'detail', 'citation', 'facts'],
        'analysis'        => ['analysis', 'reasoning', 'explanation', 'elaboration', 'development', 'critical', 'insight', 'depth', 'ideas', 'content', 'logic'],
        'organization'    => ['organization', 'organisation', 'structure', 'transition', 'flow', 'coherence', 'cohesion', 'paragraph', 'order', 'sequence'],
        'word_choice'     => ['word choice', 'vocabulary', 'diction', 'voice', 'style', 'tone', 'language use', 'fluency', 'sentence variety'],
        'mechanics'       => ['grammar', 'mechanic', 'spelling', 'punctuation', 'convention', 'language', 'usage', 'capitalization', 'sentence'],
    ];

    // Skills that need length to develop; very short essays score lower on them.
    private const LENGTH_DEPENDENT = ['evidence', 'analysis', 'organization', 'introduction', 'conclusion', 'counterargument', 'general'];

    // Quality at or above STRONG is described as a strength; below it, as a next step.
    private const STRONG = 0.75;

    private const MAX_MECHANICS = 8;

    public function provider(): string
    {
        return 'demo';
    }

    public function model(): string
    {
        return 'rubricmind-demo-2';
    }

    public function promptVersion(): string
    {
        return 'demo-2';
    }

    public function isSimulated(): bool
    {
        return true;
    }

    public function analyze(array $request): array
    {
        $essay = new EssayProfile($request['essay']);

        if ($essay->sentences === []) {
            throw new AiException('The essay is empty, so there is nothing to analyze.');
        }

        $mechanics   = MechanicsChecker::check($essay);
        $assessments = [];
        $criteria    = [];

        foreach ($request['criteria'] as $criterion) {
            $kind       = self::kindOf($criterion);
            $assessment = $this->assess($kind, $essay, $mechanics);

            if ($essay->wordCount < 150 && in_array($kind, self::LENGTH_DEPENDENT, true)) {
                $assessment['quality'] *= $essay->wordCount < 75 ? 0.7 : 0.85;
            }

            $assessment['quality'] = max(0.15, min(1.0, $assessment['quality']));
            [$points, $level]      = self::pointsFor($criterion, $assessment['quality']);

            $assessments[$criterion['id']] = $assessment + ['title' => $criterion['title']];

            $criteria[] = [
                'criterion_id'     => $criterion['id'],
                'suggested_points' => $points,
                'rationale'        => ($level !== null ? "Suggested level: {$level}. " : '') . $assessment['rationale'],
                'evidence'         => array_slice($assessment['evidence'], 0, 3),
            ];
        }

        return [
            'criteria'  => $criteria,
            'feedback'  => $this->feedback($essay, $assessments, $mechanics),
            'mechanics' => array_map(static fn (array $p) => [
                'pattern'     => $p['pattern'],
                'description' => $p['description'],
                'occurrences' => $p['occurrences'],
                'examples'    => $p['examples'],
                'suggestion'  => $p['suggestion'],
            ], array_slice($mechanics, 0, self::MAX_MECHANICS)),
        ];
    }

    // --- Scoring each kind of criterion ------------------------------------------

    /**
     * @return array{quality: float, rationale: string, evidence: list<array{quote: string, note: string}>, strength: string, next: string}
     */
    private function assess(string $kind, EssayProfile $essay, array $mechanics): array
    {
        return match ($kind) {
            'thesis'          => $this->thesis($essay),
            'introduction'    => $this->introduction($essay),
            'conclusion'      => $this->conclusion($essay),
            'counterargument' => $this->counterargument($essay),
            'evidence'        => $this->evidence($essay),
            'analysis'        => $this->analysis($essay),
            'organization'    => $this->organization($essay),
            'word_choice'     => $this->wordChoice($essay),
            'mechanics'       => $this->mechanics($essay, $mechanics),
            default           => $this->general($essay),
        };
    }

    private function thesis(EssayProfile $essay): array
    {
        $t = $essay->thesis();

        if ($t['sentence'] === null || $t['score'] < 3) {
            return [
                'quality'   => 0.3,
                'rationale' => 'No clear thesis was found: no sentence states a position with words such as "should", "must", or "I believe". A strong thesis takes a side and gives the main reason.',
                'evidence'  => [['quote' => $essay->sentences[0], 'note' => 'Opening sentence: it does not yet state a clear position.']],
                'strength'  => '',
                'next'      => 'Write one sentence in your introduction that takes a side and gives your main reason, for example: "Schools should start later because students learn better when rested."',
            ];
        }

        $words   = EssayProfile::wordCountOf($t['sentence']);
        $quality = 0.4 + ($t['in_intro'] ? 0.2 : 0) + ($t['has_reason'] ? 0.2 : 0) + ($words >= 10 && $words <= 40 ? 0.05 : 0)
            + ($t['specific'] ? 0.15 : 0) - ($t['announces'] ? 0.15 : 0);

        $found   = [];
        $found[] = $t['in_intro'] ? 'the position is stated in the introduction' : 'a position is stated, but only later in the essay';
        $found[] = $t['has_reason'] ? 'it includes a reason ("because…")' : 'it does not give a reason yet';
        $found[] = $t['specific'] ? 'it is specific (it previews the reasons or names a concrete change)' : 'it is still general';

        if ($t['announces']) {
            $found[] = 'it announces the topic ("this essay will…") instead of arguing';
        }

        return [
            'quality'   => $quality,
            'rationale' => 'Thesis found: ' . implode('; ', $found) . '.' . ($quality >= self::STRONG ? '' : ' A top-level thesis is one arguable sentence in the introduction with a clear reason.'),
            'evidence'  => [['quote' => $t['sentence'], 'note' => 'Thesis: states the writer\'s position' . ($t['has_reason'] ? ' and the main reason.' : '.')]],
            'strength'  => 'your position is clear' . ($t['has_reason'] ? ' and backed by a reason' : '') . ': "' . self::shorten($t['sentence'], 120) . '"',
            'next'      => match (true) {
                ! $t['in_intro']   => 'move your position into the introduction so the reader knows your argument from the start.',
                ! $t['has_reason'] => 'add your main reason to your thesis with "because…" so it tells the reader why.',
                default            => 'make your thesis more specific: preview your two or three main reasons ("…because it improves health, attendance, and learning").',
            },
        ];
    }

    private function introduction(EssayProfile $essay): array
    {
        $i       = $essay->introduction();
        $quality = 0.3 + ($i['hook'] ? 0.25 : 0) + ($i['sentences'] >= 2 ? 0.15 : 0) + ($i['thesis_inside'] ? 0.3 : 0);

        if ($essay->paragraphCount() < 2) {
            $quality *= 0.8;
        }

        $found = [
            $i['hook'] ? 'it opens with a hook (a question, fact, or vivid detail)' : 'it opens without a hook',
            "{$i['sentences']} sentence(s) of introduction",
            $i['thesis_inside'] ? 'the thesis is in the introduction' : 'the thesis is not clearly in the introduction',
        ];

        return [
            'quality'   => $quality,
            'rationale' => 'Introduction: ' . implode('; ', $found) . '.' . ($essay->paragraphCount() < 2 ? ' The essay is one paragraph, so the introduction is not separate.' : ''),
            'evidence'  => [['quote' => $essay->paragraphs[0][0], 'note' => $i['hook'] ? 'Opening hook.' : 'Opening sentence.']],
            'strength'  => 'your introduction ' . ($i['hook'] ? 'grabs attention and ' : '') . 'leads into your thesis.',
            'next'      => $i['hook']
                ? 'end your introduction with your thesis so the reader knows exactly what you will argue.'
                : 'open with a hook, such as a surprising fact or a question, before you state your thesis.',
        ];
    }

    private function conclusion(EssayProfile $essay): array
    {
        $c       = $essay->conclusion();
        $quality = 0.25 + ($c['separate'] ? 0.2 : 0) + ($c['cue'] !== null ? 0.15 : 0) + ($c['restates'] ? 0.25 : 0) + ($c['closing_thought'] ? 0.15 : 0);

        $found = [
            $c['separate'] ? 'it has its own paragraph' : 'there is no separate concluding paragraph',
            $c['cue'] !== null ? "it signals the ending (\"{$c['cue']}\")" : 'it does not signal the ending',
            $c['restates'] ? 'it returns to the thesis' : 'it does not clearly return to the thesis',
            $c['closing_thought'] ? 'it ends with a call to action or final thought' : 'it has no final thought or call to action',
        ];

        return [
            'quality'   => $quality,
            'rationale' => 'Conclusion: ' . implode('; ', $found) . '.',
            'evidence'  => [['quote' => $c['sentences'][0], 'note' => 'Start of the conclusion.']],
            'strength'  => 'your conclusion wraps up your argument and leaves the reader with a final thought.',
            'next'      => match (true) {
                ! $c['separate'] => 'give your conclusion its own paragraph that restates your thesis in new words.',
                ! $c['restates'] => 'restate your thesis in new words in your conclusion to remind the reader of your argument.',
                default          => 'end with a call to action or say why the issue matters beyond the essay.',
            },
        ];
    }

    private function counterargument(EssayProfile $essay): array
    {
        $c = $essay->counterargument();

        if ($c['sentence'] === null) {
            return [
                'quality'   => 0.3,
                'rationale' => 'No counterargument was found: the essay does not mention what people on the other side think.',
                'evidence'  => [],
                'strength'  => '',
                'next'      => 'add an opposing view ("Some people argue that…") and then answer it ("However, …").',
            ];
        }

        return [
            'quality'   => $c['rebutted'] ? 0.95 : 0.6,
            'rationale' => $c['rebutted']
                ? 'Counterargument found and answered: the essay presents an opposing view and responds to it.'
                : 'An opposing view is mentioned but not answered. A strong counterargument is followed by a rebuttal.',
            'evidence'  => [['quote' => $c['sentence'], 'note' => $c['rebutted'] ? 'Opposing view, followed by a rebuttal.' : 'Opposing view without a rebuttal.']],
            'strength'  => 'you take the other side seriously and answer it, which makes your argument more convincing.',
            'next'      => 'after the opposing view, explain why your position is still stronger ("However, this ignores…").',
        ];
    }

    private function evidence(EssayProfile $essay): array
    {
        $evidence  = $essay->evidence();
        $count     = count($evidence);
        $types     = array_values(array_unique(array_merge([], ...array_column($evidence, 'types'))));
        $explained = count(array_filter($evidence, static fn ($e) => $e['explained']));

        $bodies      = $essay->bodyParagraphs();
        $unsupported = count(array_filter($bodies, static function (array $sentences) {
            foreach ($sentences as $s) {
                foreach (EssayProfile::EVIDENCE_TYPES as $regex) {
                    if (preg_match($regex, $s) === 1) {
                        return false;
                    }
                }
            }

            return true;
        }));

        $quality = 0.25 + min($count, 4) / 4 * 0.35 + min(count($types), 3) / 3 * 0.2 + ($count > 0 ? $explained / $count * 0.2 : 0);

        if ($count === 0) {
            return [
                'quality'   => 0.25,
                'rationale' => 'No supporting evidence was found: no facts, statistics, research, examples, or quotations.',
                'evidence'  => [],
                'strength'  => '',
                'next'      => 'support each main point with a fact, statistic, or example (for example, "A 2014 study found…"), then explain what it shows.',
            ];
        }

        $typeNames = array_map(static fn ($t) => ['statistic' => 'statistics', 'research' => 'research', 'example' => 'examples',
            'quotation' => 'quotations', 'anecdote' => 'personal experience'][$t], $types);

        $rationale = "Found {$count} sentence(s) with evidence (" . implode(', ', $typeNames) . "); {$explained} of {$count} followed by an explanation of what it shows."
            . ($unsupported > 0 && count($bodies) > 1 ? " {$unsupported} body paragraph(s) have no evidence." : '')
            . ($quality >= self::STRONG ? '' : ' Top-level support uses several kinds of evidence and explains each one.');

        $picked = array_slice($evidence, 0, 2);

        return [
            'quality'   => $quality,
            'rationale' => $rationale,
            'evidence'  => array_map(static fn ($e) => [
                'quote' => $e['sentence'],
                'note'  => 'Evidence (' . implode(', ', $e['types']) . ')' . ($e['explained'] ? ', explained.' : ', not yet explained.'),
            ], $picked),
            'strength'  => 'you support your ideas with ' . implode(' and ', array_slice($typeNames, 0, 2)) . '.',
            'next'      => match (true) {
                $explained < $count => 'after each piece of evidence, add a sentence that explains it: "This shows that…".',
                count($types) < 2   => 'use more than one kind of evidence, such as a statistic and a real example.',
                default             => 'add evidence to every body paragraph, not only some of them.',
            },
        ];
    }

    private function analysis(EssayProfile $essay): array
    {
        $explanations = $essay->explanations();
        $ratio        = count($explanations) / max(1, count($essay->sentences));
        $avgBody      = $essay->averageBodyParagraphWords();
        $evidence     = $essay->evidence();
        $explained    = $evidence === [] ? 0 : count(array_filter($evidence, static fn ($e) => $e['explained'])) / count($evidence);

        $quality = 0.3 + min($ratio / 0.25, 1) * 0.35 + min($avgBody / 80, 1) * 0.2 + $explained * 0.15;

        return [
            'quality'   => $quality,
            'rationale' => sprintf('%d sentence(s) explain why or how points matter (%d%% of sentences); body paragraphs average %d words.',
                count($explanations), (int) round($ratio * 100), (int) round($avgBody))
                . ($quality >= self::STRONG ? ' The reasoning is developed.' : ' Deeper analysis explains the "so what" after each point.'),
            'evidence'  => array_map(static fn ($s) => ['quote' => $s, 'note' => 'Explains or analyzes a point.'], array_slice($explanations, 0, 2)),
            'strength'  => 'you explain why your points matter instead of only listing them.',
            'next'      => $avgBody < 60
                ? 'develop each body paragraph further: give a point, support it, and explain it in two or three more sentences.'
                : 'after each point, explain its effect with phrases like "this means…" or "as a result…".',
        ];
    }

    private function organization(EssayProfile $essay): array
    {
        $paragraphs  = $essay->paragraphCount();
        $transitions = $essay->transitions();
        $topic       = $essay->topicSentences();
        $uneven      = $essay->paragraphUnevenness();
        $conclusion  = $essay->conclusion();

        $quality = 0.15
            + match (true) { $paragraphs >= 4 => 0.25, $paragraphs === 3 => 0.2, $paragraphs === 2 => 0.1, default => 0.0 }
            + min(count($transitions), 4) / 4 * 0.25
            + ($topic['total'] > 0 ? $topic['clear'] / $topic['total'] * 0.2 : 0)
            + ($paragraphs > 1 && $uneven < 0.6 ? 0.1 : 0)
            + ($conclusion['separate'] || $conclusion['cue'] !== null ? 0.05 : 0);

        $kinds = array_keys($transitions);
        $words = array_slice(array_merge(...array_values($transitions ?: [[]])), 0, 4);

        $rationale = "{$paragraphs} paragraph(s); transitions of " . count($kinds) . ' kind(s)'
            . ($words !== [] ? ' (' . implode(', ', array_map(static fn ($w) => "\"{$w}\"", $words)) . ')' : '')
            . ($topic['total'] > 0 ? "; {$topic['clear']} of {$topic['total']} body paragraph(s) open with a clear topic sentence" : '')
            . ($paragraphs > 1 && $uneven >= 0.6 ? '; paragraph lengths are very uneven' : '') . '.';

        $withTransitions = array_values(array_filter($essay->sentences, static function ($s) {
            foreach (EssayProfile::TRANSITIONS as $list) {
                foreach ($list as $word) {
                    if (EssayProfile::startsWith($s, $word)) {
                        return true;
                    }
                }
            }

            return false;
        }));

        return [
            'quality'   => $quality,
            'rationale' => $rationale,
            'evidence'  => array_map(static fn ($s) => ['quote' => $s, 'note' => 'Transition that connects ideas.'], array_slice($withTransitions, 0, 2)),
            'strength'  => 'your ideas are organized into paragraphs and connected with transitions.',
            'next'      => match (true) {
                $paragraphs < 3                                   => 'organize your essay into an introduction, body paragraphs, and a conclusion.',
                count($kinds) < 3                                 => 'use different kinds of transitions: to add ("furthermore"), contrast ("however"), and show results ("as a result").',
                $topic['total'] > 0 && $topic['clear'] < $topic['total'] => 'start each body paragraph with a topic sentence that states its main point.',
                default                                           => 'balance your paragraphs so each main point gets similar development.',
            },
        ];
    }

    private function wordChoice(EssayProfile $essay): array
    {
        $diversity = $essay->lexicalDiversity();
        $long      = $essay->longWordShare();
        $variety   = $essay->sentenceVariety();
        $vague     = $essay->countWords(EssayProfile::VAGUE_WORDS);
        $informal  = $essay->countWords(EssayProfile::INFORMAL_WORDS);
        $vagueRate = array_sum($vague) / max(1, $essay->wordCount) * 100;

        $quality = 0.4
            + self::scale($diversity, 0.55, 0.8) * 0.25
            + self::scale($long, 0.12, 0.25) * 0.15
            + self::scale($variety, 0.15, 0.45) * 0.15
            - min($vagueRate * 0.04, 0.2)
            - min(array_sum($informal) * 0.05, 0.2);

        $rationale = sprintf('Vocabulary range %d%% (different words per 50); %d%% long words; sentence length varies %s; reading level about grade %s.',
            (int) round($diversity * 100), (int) round($long * 100), $variety >= 0.4 ? 'well' : 'little', $essay->gradeLevel())
            . ($vague !== [] ? ' Vague words: ' . implode(', ', array_map(static fn ($w, $n) => "\"{$w}\" ×{$n}", array_keys(array_slice($vague, 0, 3)), array_slice($vague, 0, 3))) . '.' : '')
            . ($informal !== [] ? ' Informal words: ' . implode(', ', array_keys(array_slice($informal, 0, 3))) . '.' : '');

        $target = array_merge(array_keys($informal), array_keys($vague));
        $quotes = [];

        foreach ($essay->sentences as $s) {
            if ($target !== [] && EssayProfile::hasAny($s, $target)) {
                $quotes[] = ['quote' => $s, 'note' => 'Word choice could be more precise or formal here.'];
                break;
            }
        }

        return [
            'quality'   => $quality,
            'rationale' => $rationale,
            'evidence'  => $quotes,
            'strength'  => 'your word choice is precise and your sentences vary in length, which keeps the essay readable.',
            'next'      => match (true) {
                $informal !== []  => 'replace informal words such as "' . array_key_first($informal) . '" with formal ones.',
                $vagueRate > 1.5  => 'replace vague words such as "' . array_key_first($vague) . '" with specific ones ("exhausted" instead of "very tired").',
                $variety < 0.3    => 'mix short and long sentences so your writing does not sound repetitive.',
                default           => 'use more precise, topic-specific vocabulary to sound more authoritative.',
            },
        ];
    }

    private function mechanics(EssayProfile $essay, array $mechanics): array
    {
        $rate   = MechanicsChecker::errorRate($mechanics, $essay->wordCount);
        $errors = array_values(array_filter($mechanics, static fn ($p) => $p['category'] !== 'Style'));
        $style  = count($mechanics) - count($errors);

        $quality = match (true) {
            $rate === 0.0 => 0.95,
            $rate < 0.5   => 0.9,
            $rate < 1.0   => 0.82,
            $rate < 2.0   => 0.7,
            $rate < 3.5   => 0.58,
            $rate < 5.0   => 0.45,
            default       => 0.32,
        } - min($style * 0.02, 0.08);

        $byCategory = [];

        foreach ($errors as $p) {
            $byCategory[$p['category']] = ($byCategory[$p['category']] ?? 0) + $p['occurrences'];
        }

        $rationale = $errors === []
            ? "No grammar, spelling, or punctuation errors were found in {$essay->wordCount} words."
            : 'About ' . implode(', ', array_map(static fn ($c, $n) => "{$n} " . strtolower($c), array_keys($byCategory), $byCategory))
                . " issue(s) in {$essay->wordCount} words (" . round($rate, 1) . ' per 100 words, weighted). Most frequent: ' . strtolower($errors[0]['pattern']) . '.';

        $quote = $errors[0]['sentence'] ?? null;

        return [
            'quality'   => $quality,
            'rationale' => $rationale . ($style > 0 ? " {$style} style habit(s) are listed in the mechanics summary as suggestions." : ''),
            'evidence'  => $quote === null ? [] : [['quote' => $quote, 'note' => 'Contains a possible ' . strtolower($errors[0]['category']) . ' issue: ' . strtolower($errors[0]['pattern']) . '.']],
            'strength'  => $errors === []
                ? 'your writing is free of grammar, spelling, and punctuation errors, so your ideas come through clearly.'
                : 'your grammar, spelling, and punctuation are mostly correct, so your ideas come through clearly.',
            'next'      => $errors === []
                ? 'reread your essay aloud to catch small slips.'
                : 'proofread for ' . strtolower($errors[0]['pattern']) . ': ' . lcfirst($errors[0]['suggestion']),
        ];
    }

    private function general(EssayProfile $essay): array
    {
        $evidence = count($essay->evidence());
        $thesis   = $essay->thesis();
        $quality  = 0.35 + min($essay->wordCount / 400, 1) * 0.3 + min($evidence, 3) / 3 * 0.15
            + min($essay->paragraphCount(), 4) / 4 * 0.1 + ($thesis['score'] >= 3 ? 0.1 : 0);

        return [
            'quality'   => $quality,
            'rationale' => "Judged from the essay's overall development: {$essay->wordCount} words in {$essay->paragraphCount()} paragraph(s), {$evidence} sentence(s) with evidence"
                . ($thesis['score'] >= 3 ? ', and a clear position.' : ', and no clear position yet.'),
            'evidence'  => $thesis['sentence'] !== null ? [['quote' => $thesis['sentence'], 'note' => 'Main point of the essay.']] : [],
            'strength'  => 'your essay is well developed.',
            'next'      => 'develop this part of your essay with more detail and explanation.',
        ];
    }

    // --- Feedback -----------------------------------------------------------------

    private function feedback(EssayProfile $essay, array $assessments, array $mechanics): array
    {
        $byQuality = $assessments;
        uasort($byQuality, static fn ($a, $b) => $b['quality'] <=> $a['quality']);

        $strengths    = [];
        $improvements = [];

        foreach ($byQuality as $a) {
            if ($a['quality'] >= self::STRONG && $a['strength'] !== '') {
                $strengths[] = self::shorten("{$a['title']}: " . $a['strength'], 300);
            }
        }

        foreach (array_reverse($byQuality, true) as $a) {
            if ($a['quality'] < self::STRONG) {
                $improvements[] = self::shorten("{$a['title']}: " . ucfirst($a['next']), 300);
            }
        }

        // Writing habits worth praising or fixing even when no criterion covers them.
        if ($essay->sentenceVariety() >= 0.45 && count($strengths) < 4) {
            $strengths[] = 'Your sentences vary in length, which keeps your writing lively.';
        }

        $topError = array_values(array_filter($mechanics, static fn ($p) => $p['category'] !== 'Style'))[0] ?? null;

        if ($topError !== null && count($improvements) < 4) {
            $improvements[] = self::shorten("Proofreading: watch for {$topError['pattern']} ({$topError['occurrences']}×). " . $topError['suggestion'], 300);
        }

        // Strong essays still get a concrete step toward the top level, not only praise.
        foreach (array_reverse($byQuality, true) as $a) {
            if (count($improvements) >= 2) {
                break;
            }

            if ($a['quality'] >= self::STRONG && $a['quality'] < 0.92) {
                $improvements[] = self::shorten("To reach the top level in {$a['title']}: " . $a['next'], 300);
            }
        }

        if ($strengths === []) {
            $strengths[] = 'You responded to the prompt and organized your ideas into sentences a reader can follow.';
        }

        // Every area is already at the top: suggest a stretch goal instead.
        if ($improvements === []) {
            $types          = array_merge([], ...array_column($essay->evidence(), 'types'));
            $improvements[] = match (true) {
                ! in_array('quotation', $types, true) => 'Challenge: add a short quotation from an expert and explain it, to make your strongest point even more convincing.',
                $essay->sentenceVariety() < 0.4       => 'Challenge: vary your sentence lengths more; a short sentence after a long one adds emphasis.',
                default                               => 'Challenge: in your conclusion, explain the wider impact of your argument beyond your own school.',
            };
        }

        $overall = array_sum(array_column($assessments, 'quality')) / max(1, count($assessments));
        $summary = match (true) {
            $overall >= 0.85 => 'This is a strong, well-developed essay.',
            $overall >= self::STRONG => 'This is a solid essay with a clear argument.',
            $overall >= 0.55 => 'This essay has a good foundation. A few focused revisions will make your argument more convincing.',
            default          => 'You have a starting point. Focus on the next steps below to build a clearer, better-supported argument.',
        };

        $best  = array_key_first($byQuality);
        $worst = array_key_last($byQuality);

        if ($best !== null && $worst !== null && $best !== $worst && $assessments[$worst]['quality'] < 0.85) {
            $summary .= " Your strongest area is {$assessments[$best]['title']}; the biggest gain will come from {$assessments[$worst]['title']}.";
        } elseif ($assessments !== [] && $assessments[$worst]['quality'] >= 0.85) {
            $summary .= ' Every part of the rubric is handled well.';
        }

        $summary .= " ({$essay->wordCount} words in {$essay->paragraphCount()} paragraph(s); reading level about grade {$essay->gradeLevel()}.)";

        if ($essay->wordCount < 150) {
            $summary .= ' The essay is quite short, so develop each idea in more detail.';
        }

        return [
            'summary'      => $summary,
            'strengths'    => array_slice($strengths, 0, 5),
            'improvements' => array_slice($improvements, 0, 5),
        ];
    }

    // --- Lesson plans ---------------------------------------------------------------

    public function lessonPlan(array $focus): array
    {
        $weakest = $focus['criteria'][0] ?? null;

        if ($weakest === null) {
            throw new AiException('There are no finalized scores to build a lesson plan from yet.');
        }

        $second  = $focus['criteria'][1] ?? null;
        $pattern = $focus['mechanics'][0]['pattern'] ?? null;
        $kind    = self::kindOf(['title' => $weakest['title'], 'description' => $weakest['description']]);

        $modelling = match ($kind) {
            'thesis'          => ['Show two thesis statements side by side: one vague ("School times are important") and one arguable ("Our school should start at 8:30 so students get enough sleep").',
                'Ask students what makes the second one stronger: a clear position, a reason, and something someone could disagree with.'],
            'introduction'    => ['Read three opening sentences: a question, a surprising fact, and a plain statement. Vote on which makes readers want to continue.',
                'Show how a good introduction moves from the hook to background to the thesis.'],
            'conclusion'      => ['Compare a conclusion that only repeats the thesis word for word with one that restates it in new words and ends with a call to action.',
                'List three ways to end strongly: a call to action, a look to the future, or a link back to the hook.'],
            'counterargument' => ['Model the "they say / I say" move: "Some people argue that… However, …".',
                'Have pairs list the strongest objection to their own thesis and write one sentence answering it.'],
            'evidence'        => ['Model the "claim → evidence → explanation" chain on the board with one example from the assignment topic.',
                'Point out that evidence is only half the job: the explanation says why the evidence proves the claim.'],
            'analysis'        => ['Take one piece of evidence and ask "So what?" three times, writing each answer as a sentence.',
                'Show how those answers turn a list of facts into an argument.'],
            'organization'    => ['Show a short essay with its paragraphs shuffled. As a class, put them back in order and name the clue words that helped.',
                'Build a class word bank of transitions for adding, contrasting, and concluding ideas.'],
            'word_choice'     => ['Display a paragraph full of vague words ("very good", "a lot of things"). Rewrite it together with precise words.',
                'Start a class list of strong replacements for the most overused words.'],
            'mechanics'       => ['Display three anonymous sentences with common errors from the class (rewritten so no student can be identified).',
                'Correct them together, naming the rule each time.'],
            default           => ["Model what a strong response to \"{$weakest['title']}\" looks like using a short anonymous example.",
                'Highlight the features that make it strong.'],
        };

        $sections = [
            ['heading' => 'Warm-up', 'minutes' => 5, 'steps' => [
                "Ask: \"What makes writing convincing?\" Collect quick answers on the board.",
                "Share that the class's average for {$weakest['title']} was {$weakest['average_percent']}%, so today focuses on that skill.",
            ]],
            ['heading' => 'Mini-lesson', 'minutes' => 12, 'steps' => $modelling],
            ['heading' => 'Guided practice', 'minutes' => 10, 'steps' => [
                "In pairs, students revise one paragraph from their own essay to improve {$weakest['title']}.",
                'Partners give one specific compliment and one suggestion using the rubric wording.',
            ]],
        ];

        if ($pattern !== null) {
            $sections[] = ['heading' => 'Mechanics focus', 'minutes' => 8, 'steps' => [
                "Quick review of the class's most common writing pattern: {$pattern}.",
                'Students find and fix one example of it in their own essay.',
            ]];
        }

        $sections[] = ['heading' => 'Independent practice', 'minutes' => 7, 'steps' => [
            'Each student rewrites their weakest paragraph on their own.',
            $second !== null ? "Early finishers check their paragraph against \"{$second['title']}\" as well." : 'Early finishers read their revision to a partner.',
        ]];

        return [
            'title'            => "Mini-lesson: improving {$weakest['title']}",
            'objective'        => "Students will be able to strengthen the \"{$weakest['title']}\" part of their essays and explain what they changed using the rubric.",
            'duration_minutes' => array_sum(array_column($sections, 'minutes')),
            'sections'         => $sections,
            'materials'        => ['The assignment rubric', "Students' graded essays", 'Board or projector'],
            'assessment'       => "Exit ticket: students write one improved sentence for {$weakest['title']} and name the rubric level they think it reaches.",
        ];
    }

    // --- Helpers ----------------------------------------------------------------------

    public static function kindOf(array $criterion): string
    {
        foreach ([$criterion['title'], $criterion['description'] ?? ''] as $text) {
            $text = mb_strtolower((string) $text);

            foreach (self::KINDS as $kind => $words) {
                foreach ($words as $word) {
                    if (str_contains($text, $word)) {
                        return $kind;
                    }
                }
            }
        }

        return 'general';
    }

    /**
     * Points for a 0–1 quality, moved to the nearest performance level when the
     * criterion has levels (the lower one on a tie), with that level's label.
     *
     * @return array{0: int, 1: string|null}
     */
    private static function pointsFor(array $criterion, float $quality): array
    {
        $points = (int) round($quality * $criterion['max_points']);

        if ($criterion['levels'] === []) {
            return [$points, null];
        }

        $best = null;

        foreach ($criterion['levels'] as $level) {
            $distance = abs($level['points'] - $quality * $criterion['max_points']);

            if ($best === null || $distance < $best[0] || ($distance === $best[0] && $level['points'] < $best[1]['points'])) {
                $best = [$distance, $level];
            }
        }

        return [(int) $best[1]['points'], $best[1]['label']];
    }

    private static function scale(float $value, float $low, float $high): float
    {
        return max(0.0, min(1.0, ($value - $low) / ($high - $low)));
    }

    private static function shorten(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);

        return rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: $max - 1), ' ,;:') . '…';
    }
}
