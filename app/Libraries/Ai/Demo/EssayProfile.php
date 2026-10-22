<?php

namespace App\Libraries\Ai\Demo;

/**
 * Measures an essay the way a writing teacher reads it, using transparent,
 * explainable rules: structure (introduction, thesis, body, conclusion),
 * argument (evidence and the explanation that follows it, counterarguments),
 * coherence (transitions, topic sentences, paragraph balance), and style
 * (sentence variety, vocabulary, readability, vague or informal words).
 *
 * Every sentence kept here is an exact substring of the essay, so it can be
 * used as a verifiable evidence quote.
 */
final class EssayProfile
{
    public const POSITION_CUES = ['should', 'must', 'ought to', 'need to', 'needs to', 'has to', 'have to', 'believe', 'argue',
        'it is clear that', 'in my opinion', 'i think', 'i feel', 'it is important', 'is essential', 'is necessary', 'is better',
        'would benefit', 'would be better', 'deserve', 'deserves', 'it is time', 'we need'];

    private const REASON_CUES = ['because', 'since', 'so that', 'due to', 'in order to', 'which will', 'which would', 'as a result', 'by '];

    private const ANNOUNCEMENT_CUES = ['this essay will', 'in this essay i', 'i will write', 'i am going to write', 'i will talk about',
        'i am going to talk', 'this essay is about', 'i will be discussing'];

    public const EVIDENCE_TYPES = [
        // Real statistics, not just any number ("8:30" or "Grade 10" are not evidence).
        'statistic' => '/\b\d[\d,.]*\s*(?:%|percent|per cent)|\b(?:\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(?:out of|in)\s+(?:\d+|ten|every)\b|\b\d[\d,.]*\s+(?:million|thousand|billion|hours?|minutes?|days?|years?|students|people|teens|teenagers|schools|children|adults)\b|\b(?:half of|majority of|minority of|twice as|times more|nearly all|most students)\b/iu',
        'research'  => '/\b(?:research|researchers?|stud(?:y|ies)|surveys?|according to|experts?|scientists?|reports?|data|universit(?:y|ies)|journal|published|found that|evidence|doctors?)\b/iu',
        'example'   => '/\b(?:for example|for instance|such as|to illustrate|in particular|one example|e\.g\.)/iu',
        'quotation' => '/["“][^"”]{12,}["”]/u',
        'anecdote'  => '/\b(?:i remember|when i was|in my experience|my (?:friend|teacher|brother|sister|mother|father|mom|dad|cousin|classmate|family)|personally|last (?:year|week|month))\b/iu',
    ];

    private const EXPLANATION = '/\b(?:this shows|this means|this proves|this suggests|this demonstrates|this explains|which means|which shows|that is why|this is why|as a result|therefore|because of this|this is important because|in other words|thus|clearly|consequently|so that|this leads to|which leads to|this makes|outweighs?|because|shows that|proves that)\b/iu';

    private const COUNTER = '/\b(?:some people|some may|some might|some argue|some believe|some say|some think|critics|opponents|others (?:believe|argue|say|think|may)|it is true that|admittedly|although some|while some|those who disagree|people who disagree|one might argue|it can be argued|the other side|on the other hand)\b/iu';

    private const REBUTTAL = '/\b(?:however|but|yet|nevertheless|still|even so|despite this|this is not|that is not|although|even though|in reality|the truth is)\b/iu';

    private const CONCLUSION_CUES = ['in conclusion', 'to conclude', 'in summary', 'to sum up', 'overall', 'ultimately', 'for these reasons',
        'all in all', 'in the end', 'to summarize'];

    private const HOOK = '/\?|\b(?:imagine|have you ever|did you know|picture this|every (?:day|morning|year))\b|["“]|\d/iu';

    public const TRANSITIONS = [
        'adding'     => ['furthermore', 'moreover', 'in addition', 'additionally', 'also', 'besides', 'another', 'not only'],
        'contrast'   => ['however', 'on the other hand', 'although', 'even though', 'nevertheless', 'yet', 'but', 'in contrast', 'whereas', 'despite', 'instead', 'still'],
        'cause'      => ['therefore', 'as a result', 'consequently', 'because', 'since', 'thus', 'due to', 'hence', 'for this reason'],
        'example'    => ['for example', 'for instance', 'such as', 'in particular', 'specifically', 'to illustrate'],
        'sequence'   => ['first', 'firstly', 'second', 'secondly', 'third', 'next', 'then', 'finally', 'lastly', 'to begin', 'after that'],
        'concluding' => ['in conclusion', 'to conclude', 'in summary', 'to sum up', 'overall', 'ultimately', 'for these reasons', 'all in all'],
    ];

    // Words that count anywhere; other single words count only at the start of a sentence.
    private const STRONG_TRANSITIONS = ['however', 'therefore', 'moreover', 'furthermore', 'consequently', 'nevertheless', 'thus',
        'hence', 'additionally'];

    public const VAGUE_WORDS = ['thing', 'things', 'stuff', 'good', 'bad', 'nice', 'very', 'really', 'a lot', 'lots of', 'kind of',
        'sort of', 'big', 'great', 'basically', 'literally', 'pretty much'];

    public const INFORMAL_WORDS = ['gonna', 'wanna', 'gotta', 'kinda', 'sorta', 'u', 'ur', 'cuz', 'coz', 'lol', 'omg', 'idk', 'tbh',
        'btw', 'awesome', 'cool', 'dude', 'yeah', 'nope', 'ok', 'okay'];

    private const STOPWORDS = ['the', 'a', 'an', 'and', 'or', 'but', 'if', 'of', 'to', 'in', 'on', 'at', 'for', 'with', 'by', 'from',
        'as', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'it', 'its', 'this', 'that', 'these', 'those', 'they', 'them',
        'their', 'we', 'our', 'us', 'you', 'your', 'i', 'me', 'my', 'he', 'she', 'his', 'her', 'not', 'no', 'so', 'do', 'does',
        'did', 'have', 'has', 'had', 'will', 'would', 'can', 'could', 'should', 'may', 'might', 'must', 'also', 'more', 'most',
        'than', 'then', 'there', 'here', 'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'some', 'many', 'much',
        'very', 'just', 'into', 'about', 'because', 'too', 'only', 'other', 'such', 'one', 'get', 'make', 'need', 'think'];

    private const SUBORDINATORS = ['because', 'although', 'though', 'if', 'when', 'whenever', 'since', 'while', 'after', 'before',
        'unless', 'until', 'as', 'once', 'whether', 'even'];

    public readonly string $text;

    /** @var list<list<string>> Sentences of each paragraph. */
    public readonly array $paragraphs;

    /** @var list<string> */
    public readonly array $sentences;

    /** @var list<string> Lowercase words. */
    public readonly array $words;

    public readonly int $wordCount;

    private ?array $thesisCache = null;

    public function __construct(string $essay)
    {
        $this->text = trim($essay);

        $blocks = preg_split('/\n\s*\n/u', $this->text) ?: [];

        // Many students separate paragraphs with a single line break.
        if (count($blocks) === 1 && str_contains($this->text, "\n")) {
            $blocks = preg_split('/\n/u', $this->text) ?: [];
        }

        $paragraphs = [];

        foreach ($blocks as $block) {
            $sentences = self::splitSentences($block);

            if ($sentences !== []) {
                $paragraphs[] = $sentences;
            }
        }

        $this->paragraphs = $paragraphs;
        $this->sentences  = $paragraphs === [] ? [] : array_merge(...$paragraphs);
        $this->words      = self::wordsOf($this->text);
        $this->wordCount  = count($this->words);
    }

    // Words that end with a period without ending the sentence ("8:30 a.m. because…").
    private const ABBREVIATIONS = ['e.g', 'i.e', 'etc', 'vs', 'mr', 'mrs', 'ms', 'dr', 'st', 'a.m', 'p.m', 'no', 'approx', 'jr', 'sr',
        'prof', 'inc', 'u.s', 'ft', 'fig', 'vol', 'ed', 'al'];

    /**
     * Sentences as exact substrings of the text. A sentence ends at . ! or ?
     * followed by a space, except after abbreviations; "7.5 hours" stays whole.
     * A lowercase next word still starts a new sentence (students often forget
     * the capital), unless the period belongs to an abbreviation.
     *
     * @return list<string>
     */
    public static function splitSentences(string $paragraph): array
    {
        $lines = preg_split('/\n/u', trim($paragraph)) ?: [];
        $out   = [];

        foreach ($lines as $line) {
            $line  = trim($line);
            $start = 0;

            preg_match_all('/[.!?]+["”’\')]*\s+(?=\S)/u', $line, $ends, PREG_OFFSET_CAPTURE);

            foreach ($ends[0] as [$end, $offset]) {
                $before = substr($line, $start, $offset - $start);
                preg_match('/([\p{L}.]+)$/u', $before, $last);
                $word = mb_strtolower(rtrim($last[1] ?? '', '.'));

                if (str_contains($end, '.')) {
                    // "a.m." or "e.g." or a single initial ("J. Smith") does not end a sentence.
                    if (in_array($word, self::ABBREVIATIONS, true) || mb_strlen($word) === 1 && ctype_upper($last[1] ?? '')) {
                        continue;
                    }
                }

                $out[] = trim(substr($line, $start, $offset + strlen(rtrim($end)) - $start));
                $start = $offset + strlen($end);
            }

            $out[] = trim(substr($line, $start));
        }

        return array_values(array_filter($out, static fn ($s) => preg_match('/[\p{L}\p{N}]/u', $s) === 1 && mb_strlen($s) > 2));
    }

    /**
     * @return list<string>
     */
    public static function wordsOf(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’][\p{L}]+)*/u', mb_strtolower($text), $m);

        return $m[0];
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return preg_match('/(?<![\p{L}\p{N}])' . preg_quote(mb_strtolower($needle), '/') . '(?![\p{L}\p{N}])/u', mb_strtolower($haystack)) === 1;
    }

    public static function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (self::contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function wordCountOf(string $text): int
    {
        return count(self::wordsOf($text));
    }

    // --- Structure -----------------------------------------------------------

    public function paragraphCount(): int
    {
        return count($this->paragraphs);
    }

    /**
     * Body paragraphs: everything between the introduction and the conclusion
     * (with fewer than three paragraphs, everything after the first).
     *
     * @return list<list<string>>
     */
    public function bodyParagraphs(): array
    {
        $count = $this->paragraphCount();

        return match (true) {
            $count >= 3 => array_slice($this->paragraphs, 1, $count - 2),
            $count === 2 => [$this->paragraphs[1]],
            default      => $this->paragraphs,
        };
    }

    /**
     * The most thesis-like sentence, preferring the introduction.
     *
     * @return array{sentence: string|null, score: int, in_intro: bool, has_reason: bool, announces: bool, specific: bool}
     */
    public function thesis(): array
    {
        if ($this->thesisCache !== null) {
            return $this->thesisCache;
        }

        $intro = $this->paragraphCount() > 1 ? $this->paragraphs[0] : array_slice($this->sentences, 0, 3);
        $best  = ['sentence' => null, 'score' => 0, 'in_intro' => false, 'has_reason' => false, 'announces' => false, 'specific' => false];

        foreach ($this->sentences as $sentence) {
            $inIntro   = in_array($sentence, $intro, true);
            $hasReason = self::hasAny($sentence, self::REASON_CUES);
            $announces = self::hasAny($sentence, self::ANNOUNCEMENT_CUES);
            $words     = self::wordCountOf($sentence);

            $score = (self::hasAny($sentence, self::POSITION_CUES) ? 3 : 0)
                + ($hasReason ? 2 : 0)
                + ($words >= 10 && $words <= 40 ? 1 : 0)
                + ($inIntro ? 2 : 0)
                - ($announces ? 2 : 0)
                - (str_ends_with($sentence, '?') ? 3 : 0);

            // A later sentence must be clearly stronger to replace one from the introduction.
            if ($score > $best['score'] + ($best['in_intro'] && ! $inIntro ? 1 : 0)) {
                // Specific: previews several reasons ("health, attendance, and learning") or names a concrete change.
                $specific = preg_match('/\w+,\s+\w+(?:\s+\w+)?,?\s+and\s+\w+|\d/u', $sentence) === 1;

                $best = ['sentence' => $sentence, 'score' => $score, 'in_intro' => $inIntro, 'has_reason' => $hasReason,
                    'announces' => $announces, 'specific' => $specific];
            }
        }

        // Without a position word it is not a thesis, however good the rest is.
        if ($best['sentence'] !== null && ! self::hasAny($best['sentence'], self::POSITION_CUES)) {
            $best['score'] = min($best['score'], 2);
        }

        return $this->thesisCache = $best;
    }

    /**
     * Content words of the thesis, to check that the essay stays on it.
     *
     * @return list<string>
     */
    public function thesisKeywords(): array
    {
        $thesis = $this->thesis()['sentence'];

        return $thesis === null ? [] : self::contentWords($thesis);
    }

    /**
     * @return list<string>
     */
    public static function contentWords(string $text): array
    {
        return array_values(array_unique(array_filter(
            self::wordsOf($text),
            static fn (string $w) => mb_strlen($w) > 3 && ! in_array($w, self::STOPWORDS, true),
        )));
    }

    /**
     * @return array{hook: bool, sentences: int, thesis_inside: bool}
     */
    public function introduction(): array
    {
        $intro = $this->paragraphCount() > 1 ? $this->paragraphs[0] : array_slice($this->sentences, 0, 2);

        return [
            'hook'          => $intro !== [] && preg_match(self::HOOK, $intro[0]) === 1,
            'sentences'     => count($intro),
            'thesis_inside' => $this->thesis()['in_intro'] && $this->thesis()['score'] >= 3,
        ];
    }

    /**
     * @return array{separate: bool, cue: string|null, restates: bool, closing_thought: bool, sentences: list<string>}
     */
    public function conclusion(): array
    {
        $separate  = $this->paragraphCount() >= 3;
        $sentences = $separate ? $this->paragraphs[count($this->paragraphs) - 1] : array_slice($this->sentences, -2);
        $text      = implode(' ', $sentences);

        $cue = null;

        foreach (self::CONCLUSION_CUES as $candidate) {
            if (self::contains($text, $candidate)) {
                $cue = $candidate;
                break;
            }
        }

        $keywords = $this->thesisKeywords();
        $shared   = count(array_intersect($keywords, self::contentWords($text)));

        return [
            'separate'        => $separate,
            'cue'             => $cue,
            'restates'        => $keywords !== [] && $shared >= min(2, count($keywords)),
            'closing_thought' => preg_match('/\b(?:should|must|let us|let\'s|we need|imagine|together|if we|it is time|future|everyone)\b/iu', $text) === 1,
            'sentences'       => $sentences,
        ];
    }

    // --- Argument ------------------------------------------------------------

    /**
     * Sentences that use evidence, with the kinds of evidence each one uses.
     *
     * @return list<array{sentence: string, types: list<string>, explained: bool}>
     */
    public function evidence(): array
    {
        $found = [];

        foreach ($this->paragraphs as $sentences) {
            foreach ($sentences as $i => $sentence) {
                $types = [];

                foreach (self::EVIDENCE_TYPES as $type => $regex) {
                    if (preg_match($regex, $sentence) === 1) {
                        $types[] = $type;
                    }
                }

                if ($types === []) {
                    continue;
                }

                // Explained: this sentence or one of the next two in the paragraph says why the evidence matters.
                $explained = false;

                foreach (array_slice($sentences, $i, 3) as $candidate) {
                    $explained = $explained || preg_match(self::EXPLANATION, $candidate) === 1;
                }

                $found[] = ['sentence' => $sentence, 'types' => $types, 'explained' => $explained];
            }
        }

        return $found;
    }

    /**
     * Sentences that explain or analyze (why or how something matters).
     *
     * @return list<string>
     */
    public function explanations(): array
    {
        return array_values(array_filter($this->sentences, static fn ($s) => preg_match(self::EXPLANATION, $s) === 1));
    }

    /**
     * @return array{sentence: string|null, rebutted: bool}
     */
    public function counterargument(): array
    {
        foreach ($this->paragraphs as $sentences) {
            foreach ($sentences as $i => $sentence) {
                if (preg_match(self::COUNTER, $sentence, $m, PREG_OFFSET_CAPTURE) !== 1) {
                    continue;
                }

                $after    = substr($sentence, $m[0][1] + strlen($m[0][0]));
                $rebutted = preg_match(self::REBUTTAL, $after) === 1 || preg_match(self::REBUTTAL, $sentences[$i + 1] ?? '') === 1;

                return ['sentence' => $sentence, 'rebutted' => $rebutted];
            }
        }

        return ['sentence' => null, 'rebutted' => false];
    }

    // --- Coherence -----------------------------------------------------------

    /**
     * Transitions used, grouped by what they do.
     *
     * @return array<string, list<string>>
     */
    public function transitions(): array
    {
        $used = [];

        foreach (self::TRANSITIONS as $category => $words) {
            foreach ($words as $word) {
                $anywhere = str_contains($word, ' ') || in_array($word, self::STRONG_TRANSITIONS, true);

                foreach ($this->sentences as $sentence) {
                    if ($anywhere ? self::contains($sentence, $word) : self::startsWith($sentence, $word)) {
                        $used[$category][] = $word;
                        break;
                    }
                }
            }
        }

        return array_map('array_values', array_map('array_unique', $used));
    }

    public static function startsWith(string $sentence, string $word): bool
    {
        $lower = mb_strtolower(ltrim($sentence, " \t\"“‘("));

        return str_starts_with($lower, $word . ' ') || str_starts_with($lower, $word . ',');
    }

    /**
     * Body paragraphs whose first sentence states a point rather than jumping into an example.
     *
     * @return array{total: int, clear: int, first_sentences: list<string>}
     */
    public function topicSentences(): array
    {
        $keywords = $this->thesisKeywords();
        $clear    = 0;
        $firsts   = [];

        foreach ($this->bodyParagraphs() as $sentences) {
            $first    = $sentences[0];
            $firsts[] = $first;
            $words    = self::wordCountOf($first);
            $startsWithEvidence = self::startsWith($first, 'for example') || self::startsWith($first, 'for instance');

            $linked = self::hasAny($first, array_merge(...array_values(self::TRANSITIONS)))
                || ($keywords !== [] && array_intersect($keywords, self::contentWords($first)) !== []);

            if ($words >= 6 && ! $startsWithEvidence && ! str_ends_with($first, '?') && $linked) {
                $clear++;
            }
        }

        return ['total' => count($firsts), 'clear' => $clear, 'first_sentences' => $firsts];
    }

    /**
     * How uneven paragraph lengths are: 0 is perfectly even (coefficient of variation).
     */
    public function paragraphUnevenness(): float
    {
        $lengths = array_map(static fn ($p) => self::wordCountOf(implode(' ', $p)), $this->paragraphs);

        return self::variation($lengths);
    }

    public function averageBodyParagraphWords(): float
    {
        $body = $this->bodyParagraphs();

        return $body === [] ? 0.0 : array_sum(array_map(static fn ($p) => self::wordCountOf(implode(' ', $p)), $body)) / count($body);
    }

    // --- Style ---------------------------------------------------------------

    public function averageSentenceLength(): float
    {
        return $this->sentences === [] ? 0.0 : $this->wordCount / count($this->sentences);
    }

    /**
     * How much sentence lengths vary (coefficient of variation): around 0.4 or more reads naturally.
     */
    public function sentenceVariety(): float
    {
        return self::variation(array_map([self::class, 'wordCountOf'], $this->sentences));
    }

    /**
     * The word most sentences start with, and how many start with it.
     *
     * @return array{word: string|null, count: int}
     */
    public function commonestOpener(): array
    {
        $openers = array_map(static fn ($s) => self::wordsOf($s)[0] ?? '', $this->sentences);
        $counts  = array_count_values(array_filter($openers));
        arsort($counts);

        return ['word' => array_key_first($counts), 'count' => (int) (reset($counts) ?: 0)];
    }

    /**
     * Vocabulary range: the share of different words in each 50-word window (MATTR).
     */
    public function lexicalDiversity(): float
    {
        $n = $this->wordCount;

        if ($n === 0) {
            return 0.0;
        }

        if ($n <= 50) {
            return count(array_unique($this->words)) / $n;
        }

        $total = 0.0;

        for ($i = 0; $i + 50 <= $n; $i++) {
            $total += count(array_unique(array_slice($this->words, $i, 50))) / 50;
        }

        return $total / ($n - 49);
    }

    /**
     * Share of words with seven or more letters.
     */
    public function longWordShare(): float
    {
        return $this->wordCount === 0 ? 0.0
            : count(array_filter($this->words, static fn ($w) => mb_strlen($w) >= 7)) / $this->wordCount;
    }

    /**
     * Flesch–Kincaid grade level (an estimate of the school grade the text reads at).
     */
    public function gradeLevel(): float
    {
        if ($this->wordCount === 0 || $this->sentences === []) {
            return 0.0;
        }

        $syllables = array_sum(array_map([self::class, 'syllables'], $this->words));

        return max(0.0, round(0.39 * ($this->wordCount / count($this->sentences)) + 11.8 * ($syllables / $this->wordCount) - 15.59, 1));
    }

    public static function syllables(string $word): int
    {
        $word = preg_replace('/[^a-z]/', '', strtolower($word)) ?? '';

        if (strlen($word) <= 3) {
            return 1;
        }

        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word) ?? $word;
        $word = preg_replace('/^y/', '', $word) ?? $word;

        return max(1, preg_match_all('/[aeiouy]{1,2}/', $word));
    }

    /**
     * @return array<string, int> Each word from the list that appears, with its count.
     */
    public function countWords(array $list): array
    {
        $found = [];
        $lower = mb_strtolower($this->text);

        foreach ($list as $word) {
            $count = preg_match_all('/(?<![\p{L}\p{N}\'’])' . preg_quote($word, '/') . '(?![\p{L}\p{N}\'’])/u', $lower);

            if ($count > 0) {
                $found[$word] = $count;
            }
        }

        arsort($found);

        return $found;
    }

    public static function startsWithSubordinator(string $sentence): bool
    {
        return in_array(self::wordsOf($sentence)[0] ?? '', self::SUBORDINATORS, true);
    }

    /**
     * @param list<int|float> $values
     */
    private static function variation(array $values): float
    {
        $n = count($values);

        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;

        if ($mean == 0) {
            return 0.0;
        }

        $variance = array_sum(array_map(static fn ($v) => ($v - $mean) ** 2, $values)) / $n;

        return sqrt($variance) / $mean;
    }
}
