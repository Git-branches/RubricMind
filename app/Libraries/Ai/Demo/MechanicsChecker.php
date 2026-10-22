<?php

namespace App\Libraries\Ai\Demo;

/**
 * Finds recurring grammar, usage, spelling, capitalization, punctuation, and
 * style patterns (FR-10). Each pattern has a category and a weight: real
 * errors count fully toward the mechanics score, style habits only a little.
 *
 * The rules are deliberately cautious: a pattern is only reported when the
 * wording makes the mistake likely, so teachers are not flooded with false alarms.
 */
final class MechanicsChecker
{
    public const WEIGHTS = [
        'Grammar'        => 1.0,
        'Usage'          => 1.0,
        'Spelling'       => 0.8,
        'Punctuation'    => 0.7,
        'Capitalization' => 0.6,
        'Style'          => 0.25,
    ];

    // Frequent student misspellings => correct spelling.
    private const MISSPELLINGS = [
        'alot' => 'a lot', 'definately' => 'definitely', 'definatly' => 'definitely', 'untill' => 'until',
        'recieve' => 'receive', 'recieved' => 'received', 'beleive' => 'believe', 'belive' => 'believe', 'thier' => 'their',
        'wich' => 'which', 'becuase' => 'because', 'becasue' => 'because', 'beacuse' => 'because', 'bacause' => 'because',
        'arguement' => 'argument', 'occured' => 'occurred', 'occurence' => 'occurrence', 'seperate' => 'separate',
        'seperately' => 'separately', 'goverment' => 'government', 'enviroment' => 'environment', 'enviornment' => 'environment',
        'tommorow' => 'tomorrow', 'tomorow' => 'tomorrow', 'tommorrow' => 'tomorrow', 'begining' => 'beginning',
        'beggining' => 'beginning', 'wierd' => 'weird', 'truely' => 'truly', 'accross' => 'across', 'adress' => 'address',
        'basicly' => 'basically', 'buisness' => 'business', 'calender' => 'calendar', 'comming' => 'coming',
        'completly' => 'completely', 'concious' => 'conscious', 'dissapoint' => 'disappoint', 'embarass' => 'embarrass',
        'existance' => 'existence', 'experiance' => 'experience', 'finaly' => 'finally', 'foriegn' => 'foreign',
        'freind' => 'friend', 'freinds' => 'friends', 'happend' => 'happened', 'immediatly' => 'immediately',
        'independant' => 'independent', 'intresting' => 'interesting', 'knowlege' => 'knowledge', 'libary' => 'library',
        'neccessary' => 'necessary', 'necesary' => 'necessary', 'noticable' => 'noticeable', 'occassion' => 'occasion',
        'oppurtunity' => 'opportunity', 'oportunity' => 'opportunity', 'persue' => 'pursue', 'posession' => 'possession',
        'prefered' => 'preferred', 'probaly' => 'probably', 'probly' => 'probably', 'realy' => 'really',
        'reccomend' => 'recommend', 'recomend' => 'recommend', 'relevent' => 'relevant', 'remeber' => 'remember',
        'rember' => 'remember', 'responsability' => 'responsibility', 'sentance' => 'sentence', 'succesful' => 'successful',
        'sucessful' => 'successful', 'suprise' => 'surprise', 'surprize' => 'surprise', 'tendancy' => 'tendency',
        'writting' => 'writing', 'studing' => 'studying', 'teh' => 'the', 'everytime' => 'every time',
        'eachother' => 'each other', 'acheive' => 'achieve', 'acheivement' => 'achievement',
        'beautifull' => 'beautiful', 'carefull' => 'careful', 'helpfull' => 'helpful', 'usefull' => 'useful',
        'stressfull' => 'stressful', 'wonderfull' => 'wonderful', 'familys' => 'families', 'studys' => 'studies',
        'activitys' => 'activities', 'grammer' => 'grammar', 'mispell' => 'misspell', 'percieve' => 'perceive',
        'scedule' => 'schedule', 'shedule' => 'schedule', 'scool' => 'school', 'skool' => 'school', 'tecnology' => 'technology',
        'technolgy' => 'technology', 'educaton' => 'education', 'importent' => 'important', 'diffrent' => 'different',
        'differant' => 'different', 'problen' => 'problem', 'reasearch' => 'research', 'resarch' => 'research',
    ];

    private const MISSING_APOSTROPHE = ['dont' => "don't", 'doesnt' => "doesn't", 'didnt' => "didn't", 'cant' => "can't",
        'isnt' => "isn't", 'arent' => "aren't", 'wasnt' => "wasn't", 'werent' => "weren't", 'shouldnt' => "shouldn't",
        'couldnt' => "couldn't", 'wouldnt' => "wouldn't", 'havent' => "haven't", 'hasnt' => "hasn't", 'im' => "I'm",
        'ive' => "I've", 'youre' => "you're", 'theyre' => "they're", 'thats' => "that's", 'whats' => "what's"];

    private const WORDY = [
        'due to the fact that'        => 'because',
        'in order to'                 => 'to',
        'at this point in time'       => 'now',
        'the reason why is because'   => 'because',
        'the reason is because'       => 'because',
        'each and every'              => 'every',
        'in my personal opinion'      => 'in my opinion (or just state it)',
        'it is important to note that' => '(leave it out)',
        "in today's society"          => 'today',
        'first and foremost'          => 'first',
        'end result'                  => 'result',
        'true fact'                   => 'fact',
        'at the end of the day'       => 'in the end',
    ];

    private const INTRODUCTORY = 'However|Therefore|In conclusion|Finally|First|Second|Third|For example|For instance|On the other hand'
        . '|In addition|Furthermore|Moreover|Additionally|Consequently|As a result|In fact|Overall|Lastly|Meanwhile|Nevertheless'
        . '|Instead|Personally|Ultimately|To conclude|In summary|To sum up|Of course|Unfortunately|Fortunately|In my opinion';

    private EssayProfile $essay;

    /** @var array<string, int> Byte offset of each sentence in the essay. */
    private array $sentenceOffsets = [];

    /**
     * @return list<array{pattern: string, category: string, description: string, occurrences: int,
     *     examples: list<string>, suggestion: string, weight: float, sentence: string|null}> Most important first.
     */
    public static function check(EssayProfile $essay): array
    {
        return (new self($essay))->run();
    }

    private function __construct(EssayProfile $essay)
    {
        $this->essay = $essay;
        $from        = 0;

        foreach ($essay->sentences as $i => $sentence) {
            $offset = strpos($essay->text, $sentence, $from);

            if ($offset !== false) {
                $this->sentenceOffsets[$i] = $offset;
                $from                      = $offset + strlen($sentence);
            }
        }
    }

    private function run(): array
    {
        $found = [];

        foreach ($this->rules() as $rule) {
            $matches = $rule['find']();

            if (count($matches) < ($rule['min'] ?? 1)) {
                continue;
            }

            $texts = array_map(fn ($m) => substr($this->essay->text, $m[0], $m[1]), $matches);

            $found[] = [
                'pattern'     => $rule['pattern'],
                'category'    => $rule['category'],
                'description' => $rule['category'] . ': ' . $rule['description'],
                'occurrences' => count($matches),
                'examples'    => array_map(fn ($m) => $this->snippet($m[0], $m[1]), array_slice($matches, 0, 3)),
                'suggestion'  => is_callable($rule['suggestion']) ? ($rule['suggestion'])($texts) : $rule['suggestion'],
                'weight'      => self::WEIGHTS[$rule['category']],
                'sentence'    => $this->sentenceAt($matches[0][0]),
            ];
        }

        // Real errors first, then style habits; within each, the most frequent (weighted) first.
        usort($found, static fn ($a, $b) => [$a['category'] === 'Style', $b['weight'] * $b['occurrences'], $b['weight']]
            <=> [$b['category'] === 'Style', $a['weight'] * $a['occurrences'], $a['weight']]);

        return $found;
    }

    /**
     * Weighted count of real errors (style habits excluded), per 100 words.
     */
    public static function errorRate(array $found, int $words): float
    {
        $errors = 0.0;

        foreach ($found as $pattern) {
            if ($pattern['category'] !== 'Style') {
                $errors += $pattern['weight'] * $pattern['occurrences'];
            }
        }

        return $words === 0 ? 0.0 : $errors / $words * 100;
    }

    private function rules(): array
    {
        return [
            // --- Grammar ---------------------------------------------------------
            [
                'pattern'     => 'Subject-verb agreement',
                'category'    => 'Grammar',
                'description' => 'The verb does not agree with its subject (singular vs. plural).',
                'suggestion'  => 'Match the verb to the subject: "students are", "they were", "everyone has", "there are many".',
                'find'        => fn () => $this->regex('/\bthere\s+(?:is|was)\s+(?:many|several|few|a few|lots|two|three|four|some\s+\w+s)\b|\b(?:students|they|we|people|teachers|kids|children|parents|classmates|schools|you|many of them|some of them|most of them)\s+(?:is|was|has|does|doesn\'t|wasn\'t|isn\'t)\b|\b(?:he|she|it|everyone|everybody|someone|somebody|nobody|each|everything)\s+(?:are|were|have|don\'t|aren\'t|weren\'t)\b/i'),
            ],
            [
                'pattern'     => 'A / an',
                'category'    => 'Grammar',
                'description' => 'Use "an" before a vowel sound and "a" before a consonant sound.',
                'suggestion'  => 'Write "an hour", "an example", "a university", "a big change".',
                'find'        => fn () => $this->articleErrors(),
            ],
            [
                'pattern'     => 'Double negative',
                'category'    => 'Grammar',
                'description' => 'Two negative words cancel each other out.',
                'suggestion'  => 'Use one negative: "don\'t have any time" or "have no time".',
                'find'        => fn () => $this->regex('/\b(?:don\'t|doesn\'t|didn\'t|can\'t|won\'t|isn\'t|aren\'t|wasn\'t|weren\'t|haven\'t|hasn\'t|ain\'t|never)\s+(?:\w+\s+){0,2}?(?:no(?!\s+(?:matter|longer|one\s+else))|nothing|nobody|nowhere|none)\b/i'),
            ],
            [
                'pattern'     => 'Double comparative',
                'category'    => 'Grammar',
                'description' => '"More" or "most" is used with a word that is already comparative.',
                'suggestion'  => 'Write "better" (not "more better") and "easiest" (not "most easiest").',
                'find'        => fn () => $this->regex('/\b(?:more|most)\s+(?:better|best|worse|worst|easier|easiest|harder|hardest|bigger|biggest|smaller|smallest|faster|happier|healthier|safer|earlier|later|greater)\b/i'),
            ],

            // --- Usage: commonly confused words ----------------------------------
            [
                'pattern'     => 'Its / it\'s',
                'category'    => 'Usage',
                'description' => '"It\'s" means "it is"; "its" shows ownership.',
                'suggestion'  => 'Write "it\'s important" (it is) but "its purpose" (belonging to it).',
                'find'        => fn () => $this->regex('/\bits\s+(?:a|an|not|been|going|very|so|too|important|true|time|hard|easy|clear|the)\b|\bit[\'’]s\s+(?:own|purpose|goal|ability|size|color|colour|name)\b/i'),
            ],
            [
                'pattern'     => 'Your / you\'re',
                'category'    => 'Usage',
                'description' => '"You\'re" means "you are"; "your" shows ownership.',
                'suggestion'  => 'Write "you\'re right" (you are) but "your school" (belonging to you).',
                'find'        => fn () => $this->regex('/\byour\s+(?:going|welcome|right|wrong|not|being|so|very|too|able|supposed|always|never)\b|\byou[\'’]re\s+(?:own|book|essay|school|teacher|teachers|friend|friends|family|parents|life|body|health|grade|grades|homework)\b/i'),
            ],
            [
                'pattern'     => 'Their / there / they\'re',
                'category'    => 'Usage',
                'description' => '"There" is a place or "there is"; "their" shows ownership; "they\'re" means "they are".',
                'suggestion'  => 'Write "there are many", "their parents", and "they\'re tired".',
                'find'        => fn () => $this->regex('/\btheir\s+(?:is|are|was|were)\b|\b(?:there|they[\'’]re)\s+(?:own|house|parents|school|teacher|teachers|friends|family|grades|homework|lives|health|bodies|minds)\b/i'),
            ],
            [
                'pattern'     => 'Then / than',
                'category'    => 'Usage',
                'description' => '"Than" compares; "then" is about time or order.',
                'suggestion'  => 'Write "better than" and "first we ate, then we left".',
                'find'        => fn () => $this->regex('/\b(?:more|less|better|worse|rather|other|higher|lower|bigger|smaller|faster|slower|easier|harder|earlier|later|longer|shorter|fewer|greater)\s+then\b|\b(?:more|less)\s+\w+\s+then\b|\b(?:and|since|until|just)\s+than\b/i'),
            ],
            [
                'pattern'     => 'To / too',
                'category'    => 'Usage',
                'description' => '"Too" means "also" or "more than enough"; "to" goes before a verb or a place.',
                'suggestion'  => 'Write "too tired" and "to sleep".',
                'find'        => fn () => $this->regex('/\b(?:is|are|was|were|it\'s|way|much)\s+to\s+(?:much|many|late|early|hard|easy|big|small|long|short|tired|young|old|expensive|difficult|often)\b|\btoo\s+(?:go|be|do|make|get|have|see|learn|help|start|sleep|study)\b/i'),
            ],
            [
                'pattern'     => 'Affect / effect',
                'category'    => 'Usage',
                'description' => '"Affect" is usually the verb; "effect" is usually the noun.',
                'suggestion'  => 'Write "sleep affects learning" and "a positive effect".',
                'find'        => fn () => $this->regex('/\b(?:an|the|positive|negative|big|huge|good|bad|great|major|side)\s+affects?\b|\b(?:will|can|could|would|may|might|to|does|did|not)\s+effect\b/i'),
            ],
            [
                'pattern'     => 'Lose / loose',
                'category'    => 'Usage',
                'description' => '"Lose" means to stop having something; "loose" means not tight.',
                'suggestion'  => 'Write "lose sleep" and "lose focus".',
                'find'        => fn () => $this->regex('/\bloose\s+(?:weight|points|sleep|focus|interest|time|money|track|their|his|her|my|our)\b/i'),
            ],
            [
                'pattern'     => '"Could of" instead of "could have"',
                'category'    => 'Usage',
                'description' => '"Could of", "should of", and "would of" are mishearings of "could have" and so on.',
                'suggestion'  => 'Write "could have", "should have", "would have".',
                'find'        => fn () => $this->regex('/\b(?:could|would|should|must|might)\s+of\b/i'),
            ],
            [
                'pattern'     => 'Informal language',
                'category'    => 'Usage',
                'description' => 'Texting or casual words do not fit an academic essay.',
                'suggestion'  => 'Use formal words: "going to" instead of "gonna", "because" instead of "cuz", "you" instead of "u".',
                'find'        => fn () => $this->regex('/(?<![\p{L}\p{N}\'’.])(?:gonna|wanna|gotta|kinda|sorta|cuz|coz|lol|omg|idk|tbh|btw|dude|yeah|nope|okay|u|ur)(?![\p{L}\p{N}\'’.])/u'),
            ],

            // --- Spelling ----------------------------------------------------------
            [
                'pattern'     => 'Misspelled words',
                'category'    => 'Spelling',
                'description' => 'Commonly misspelled words.',
                'suggestion'  => fn (array $texts) => 'Correct the spelling: ' . $this->corrections($texts, self::MISSPELLINGS) . '.',
                'find'        => fn () => $this->regex('/\b(?:' . implode('|', array_map('preg_quote', array_keys(self::MISSPELLINGS))) . ')\b/i'),
            ],
            [
                'pattern'     => 'Missing apostrophe',
                'category'    => 'Spelling',
                'description' => 'Contractions need an apostrophe where letters are left out.',
                'suggestion'  => fn (array $texts) => 'Add the apostrophe: ' . $this->corrections($texts, self::MISSING_APOSTROPHE) . '.',
                'find'        => fn () => $this->regex('/\b(?:' . implode('|', array_keys(self::MISSING_APOSTROPHE)) . ')\b/i'),
            ],

            // --- Capitalization ----------------------------------------------------
            [
                'pattern'     => 'Lowercase "i"',
                'category'    => 'Capitalization',
                'description' => 'The pronoun "I" is always a capital letter.',
                'suggestion'  => 'Write "I" as a capital letter, including in "I\'m" and "I\'ve".',
                'find'        => fn () => $this->regex('/(?<![\p{L}\p{N}\'’.])i(?=[\s\'’,.!?;:]|$)(?!\.\w)/u'),
            ],
            [
                'pattern'     => 'Sentence starts with a lowercase letter',
                'category'    => 'Capitalization',
                'description' => 'Each sentence begins with a capital letter.',
                'suggestion'  => 'Capitalize the first word of every sentence.',
                'find'        => fn () => $this->lowercaseStarts(),
            ],
            [
                'pattern'     => 'Days and months in lowercase',
                'category'    => 'Capitalization',
                'description' => 'Names of days and months are capitalized.',
                'suggestion'  => 'Write "Monday" and "September".',
                'find'        => fn () => $this->regex('/\b(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday|january|february|april|june|july|august|september|october|november|december)\b/'),
            ],

            // --- Punctuation -------------------------------------------------------
            [
                'pattern'     => 'Missing comma after an introductory word',
                'category'    => 'Punctuation',
                'description' => 'Introductory words and phrases are usually followed by a comma.',
                'suggestion'  => 'Add a comma: "However, …", "In conclusion, …", "For example, …".',
                'find'        => fn () => $this->regex('/(?:^|(?<=[.!?])\s+)(?:' . self::INTRODUCTORY . ')(?!,)\s+(?!of\b|and\b|to\b|that\b|is\b|was\b)[\p{L}]+/mu'),
            ],
            [
                'pattern'     => 'Comma splice',
                'category'    => 'Punctuation',
                'description' => 'Two complete sentences are joined with only a comma.',
                'suggestion'  => 'Use a period, a semicolon, or a joining word: "Students are tired; they need sleep." or "…tired, so they…".',
                'find'        => fn () => $this->commaSplices(),
            ],
            [
                'pattern'     => 'Run-on sentence',
                'category'    => 'Punctuation',
                'description' => 'A very long sentence with almost no punctuation is hard to follow.',
                'suggestion'  => 'Split it into two or three sentences, or add commas between its parts.',
                'find'        => fn () => $this->sentencesWhere(static fn ($s, $words) => $words > 35 && substr_count($s, ',') <= 1 && preg_match('/[;:—–]/u', $s) !== 1),
            ],
            [
                'pattern'     => 'Missing end punctuation',
                'category'    => 'Punctuation',
                'description' => 'A paragraph ends without a period, question mark, or exclamation mark.',
                'suggestion'  => 'End every sentence with a period, question mark, or exclamation mark.',
                'find'        => fn () => $this->missingEndPunctuation(),
            ],
            [
                'pattern'     => 'Spacing around punctuation',
                'category'    => 'Punctuation',
                'description' => 'A space before a punctuation mark, or no space after one.',
                'suggestion'  => 'Put no space before , . ! ? and one space after them: "tired. Research…".',
                'find'        => fn () => $this->regex('/[ \t]+[,;:!?](?=\s|$)|[ \t]+\.(?=\s|$)|[,;](?=\p{L})|[.!?](?=\p{Lu}\p{Ll}{2,})/u'),
            ],
            [
                'pattern'     => 'Repeated punctuation',
                'category'    => 'Punctuation',
                'description' => 'Marks like "!!" or "??" are too informal for an essay.',
                'suggestion'  => 'Use a single punctuation mark.',
                'find'        => fn () => $this->regex('/[!?]{2,}|,{2,}/'),
            ],

            // --- Style (counted lightly) -----------------------------------------
            [
                'pattern'     => 'Repeated word',
                'category'    => 'Style',
                'description' => 'The same word appears twice in a row.',
                'suggestion'  => 'Remove the extra word.',
                'find'        => fn () => $this->regex('/\b(?!(?:had|that)\b)(\w+)\s+\1\b/i'),
            ],
            [
                'pattern'     => 'Very long sentences',
                'category'    => 'Style',
                'description' => 'Sentences over 40 words can lose the reader.',
                'suggestion'  => 'Split long sentences so each one makes one point.',
                'find'        => fn () => $this->sentencesWhere(static fn ($s, $words) => $words > 40 && ! ($words > 35 && substr_count($s, ',') <= 1)),
            ],
            [
                'pattern'     => 'Wordy phrases',
                'category'    => 'Style',
                'description' => 'Longer phrases that can be said in fewer words.',
                'suggestion'  => fn (array $texts) => 'Say it more directly: ' . $this->corrections($texts, self::WORDY) . '.',
                'find'        => fn () => $this->regex('/\b(?:' . implode('|', array_map(static fn ($p) => preg_quote($p, '/'), array_keys(self::WORDY))) . ')\b/i'),
            ],
            [
                'pattern'     => 'Vague words',
                'category'    => 'Style',
                'description' => 'General words like "things", "good", and "very" make points less precise.',
                'suggestion'  => 'Replace them with specific words: "very tired" → "exhausted", "things" → "assignments" or "activities".',
                'min'         => 4,
                'find'        => fn () => $this->regex('/\b(?:things?|stuff|good|bad|nice|very|really|a lot|lots of|kind of|sort of|basically|literally)\b/i'),
            ],
            [
                'pattern'     => 'Passive voice',
                'category'    => 'Style',
                'description' => 'Sentences where the action is done "by" someone hide who acts.',
                'suggestion'  => 'Put the doer first: "The school changed the schedule" instead of "The schedule was changed by the school".',
                'min'         => 2,
                'find'        => fn () => $this->regex('/\b(?:is|are|was|were|be|been|being)\s+(?:\w+ly\s+)?(?:\w+ed|given|taken|done|made|seen|known|written|shown|told|found|kept|held|brought|built|chosen|taught|understood)\s+by\b/i'),
            ],
            [
                'pattern'     => 'Contractions in formal writing',
                'category'    => 'Style',
                'description' => 'Many teachers prefer full forms in academic essays.',
                'suggestion'  => 'Write "do not" instead of "don\'t" and "it is" instead of "it\'s".',
                'min'         => 3,
                'find'        => fn () => $this->regex('/\b\w+n[\'’]t\b|\b(?:i[\'’]m|it[\'’]s|that[\'’]s|they[\'’]re|we[\'’]re|you[\'’]re|i[\'’]ve|we[\'’]ve|i[\'’]ll|we[\'’]ll|they[\'’]ll|let[\'’]s)\b/i'),
            ],
            [
                'pattern'     => 'Addressing the reader as "you"',
                'category'    => 'Style',
                'description' => 'An argument usually sounds stronger when it talks about people in general.',
                'suggestion'  => 'Write about "students" or "people" instead of "you".',
                'min'         => 4,
                'find'        => fn () => $this->regex('/\byou(?:r|rs|rself)?\b/i'),
            ],
            [
                'pattern'     => 'Too many exclamation marks',
                'category'    => 'Style',
                'description' => 'Exclamation marks make an argument sound emotional rather than reasoned.',
                'suggestion'  => 'Let your evidence create the impact; end most sentences with a period.',
                'min'         => 3,
                'find'        => fn () => $this->regex('/!/'),
            ],
            [
                'pattern'     => 'Repetitive sentence beginnings',
                'category'    => 'Style',
                'description' => 'Many sentences start with the same word.',
                'suggestion'  => 'Vary how sentences begin: start some with a transition, a reason, or a detail.',
                'find'        => fn () => $this->repetitiveOpeners(),
            ],
        ];
    }

    /**
     * The essay sentence that contains a byte offset.
     */
    private function sentenceAt(int $byteOffset): ?string
    {
        foreach ($this->sentenceOffsets as $i => $start) {
            if ($byteOffset >= $start && $byteOffset < $start + strlen($this->essay->sentences[$i])) {
                return $this->essay->sentences[$i];
            }
        }

        return null;
    }

    // --- Finders: each returns a list of [byte offset, byte length] -----------------

    private function regex(string $pattern): array
    {
        if (preg_match_all($pattern, $this->essay->text, $m, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        return array_map(static fn ($match) => [$match[1], strlen($match[0])], $m[0]);
    }

    private function articleErrors(): array
    {
        preg_match_all('/\b(a|an|A|An)\s+([A-Za-z][\w\'-]*)/', $this->essay->text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $found = [];

        foreach ($m as $match) {
            $word = $match[2][0];

            // Skip acronyms ("a USB", "an FBI") and single letters, which depend on how they are read.
            if (strlen($word) < 2 || ctype_upper($word)) {
                continue;
            }

            $vowelSound = self::startsWithVowelSound(strtolower($word));
            $isAn       = strtolower($match[1][0]) === 'an';

            if ($isAn !== $vowelSound) {
                $found[] = [$match[0][1], strlen($match[0][0])];
            }
        }

        return $found;
    }

    public static function startsWithVowelSound(string $word): bool
    {
        if (preg_match('/^(?:hour|honest|honor|honour|heir)/', $word) === 1) {
            return true;
        }

        if (preg_match('/^(?:one|once|uni|use|usu|uti|ure|eu|ewe|ufo|ubi|uk)/', $word) === 1) {
            return false;
        }

        return preg_match('/^[aeiou]/', $word) === 1;
    }

    private function lowercaseStarts(): array
    {
        $found = [];

        // At the start of a paragraph, or after . ! ? unless it follows an abbreviation like "e.g.".
        preg_match_all('/(?:^|(?<=[.!?])\s+)(\p{Ll}[\p{L}\']*)/mu', $this->essay->text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($m as $match) {
            $offset = $match[1][1];
            $before = substr($this->essay->text, max(0, $offset - 8), min(8, $offset));

            if (preg_match('/(?:\b(?:e\.g|i\.e|etc|vs|mr|mrs|ms|dr|st|a\.m|p\.m|no)\.\s*)$/i', $before) === 1) {
                continue;
            }

            // A lowercase "i" is reported by its own rule.
            if ($match[1][0] === 'i') {
                continue;
            }

            $found[] = [$offset, strlen($match[1][0])];
        }

        return $found;
    }

    private function commaSplices(): array
    {
        $found = [];

        foreach ($this->essay->sentences as $i => $sentence) {
            if (! isset($this->sentenceOffsets[$i]) || EssayProfile::startsWithSubordinator($sentence)) {
                continue;
            }

            $pattern = '/,\s+(?:I|you|he|she|it|we|they|this|that|there|these|those)\s+(?:is|are|was|were|am|will|would|can|could|should|has|have|had|do|does|did|need|needs|want|wants)\b/i';

            if (preg_match($pattern, $sentence, $m, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            // The part before the comma must be a clause of its own, not an introductory phrase.
            $before = substr($sentence, 0, $m[0][1]);

            if (EssayProfile::wordCountOf($before) >= 4 && preg_match('/\b(?:is|are|was|were|am|has|have|had|do|does|did|will|can|should|would|need|needs|make|makes|go|goes|get|gets|feel|feels)\b/i', $before) === 1) {
                $found[] = [$this->sentenceOffsets[$i] + $m[0][1], strlen($m[0][0])];
            }
        }

        return $found;
    }

    private function sentencesWhere(callable $test): array
    {
        $found = [];

        foreach ($this->essay->sentences as $i => $sentence) {
            if (isset($this->sentenceOffsets[$i]) && $test($sentence, EssayProfile::wordCountOf($sentence))) {
                $found[] = [$this->sentenceOffsets[$i], strlen($sentence)];
            }
        }

        return $found;
    }

    private function missingEndPunctuation(): array
    {
        $found = [];

        foreach ($this->essay->paragraphs as $p => $sentences) {
            $last = end($sentences);

            // A short title line at the top ("The Case for Later Start Times") is left alone.
            $isTitle = $p === 0 && count($sentences) === 1 && EssayProfile::wordCountOf($last) <= 10;

            if ($isTitle || EssayProfile::wordCountOf($last) < 3 || preg_match('/[.!?…]["”’\')\]]*$/u', $last) === 1) {
                continue;
            }

            $index = array_search($last, $this->essay->sentences, true);

            if ($index !== false && isset($this->sentenceOffsets[$index])) {
                $length  = strlen($last);
                $found[] = [$this->sentenceOffsets[$index] + max(0, $length - 40), min(40, $length)];
            }
        }

        return $found;
    }

    private function repetitiveOpeners(): array
    {
        $opener = $this->essay->commonestOpener();
        $total  = count($this->essay->sentences);

        if ($opener['word'] === null || $total < 5 || $opener['count'] < 4 || $opener['count'] / $total < 0.35) {
            return [];
        }

        return $this->sentencesWhere(static fn ($s) => (EssayProfile::wordsOf($s)[0] ?? '') === $opener['word']);
    }

    /**
     * "recieve → receive, thier → their" for the words actually found.
     */
    private function corrections(array $texts, array $map): string
    {
        $pairs = [];

        foreach ($texts as $text) {
            $key = strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? ''));

            if (isset($map[$key])) {
                $pairs[$key] = "\"{$text}\" → \"{$map[$key]}\"";
            }
        }

        return implode(', ', array_slice(array_values($pairs), 0, 5));
    }

    /**
     * A few whole words around a match (byte offsets).
     */
    private function snippet(int $byteOffset, int $byteLength): string
    {
        $essay = $this->essay->text;

        // Context comes from the same line only, so an example never runs into the next paragraph.
        $lineStart = strrpos(substr($essay, 0, $byteOffset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd   = strpos($essay, "\n", $byteOffset + $byteLength);
        $lineEnd   = $lineEnd === false ? strlen($essay) : $lineEnd;

        $before = preg_split('/\s+/u', trim(substr($essay, $lineStart, $byteOffset - $lineStart)), -1, PREG_SPLIT_NO_EMPTY);
        $after  = preg_split('/\s+/u', trim(substr($essay, $byteOffset + $byteLength, $lineEnd - $byteOffset - $byteLength)), -1, PREG_SPLIT_NO_EMPTY);
        $match  = trim(preg_replace('/\s+/u', ' ', substr($essay, $byteOffset, $byteLength)) ?? '');

        // Long matches (whole sentences) are shortened instead of padded with context.
        if (mb_strlen($match) > 100) {
            return mb_substr($match, 0, 100) . '…';
        }

        // If the match starts or ends inside a word, keep that whole word.
        $prefix = $byteOffset > 0 && preg_match('/\w$/u', substr($essay, 0, $byteOffset)) === 1 ? array_pop($before) : '';
        $suffix = preg_match('/^\w/u', substr($essay, $byteOffset + $byteLength)) === 1 ? array_shift($after) : '';

        $text = trim(implode(' ', array_slice($before, -4)) . ' ' . $prefix . $match . $suffix . ' ' . implode(' ', array_slice($after, 0, 4)));

        return (count($before) > 4 ? '…' : '') . $text . (count($after) > 4 ? '…' : '');
    }
}
