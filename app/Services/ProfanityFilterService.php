<?php

namespace App\Services;

use App\Models\Profanity;

class ProfanityFilterService
{
    /**
     * Character substitution map for building leetspeak regex patterns.
     */
    protected array $charSubstitutions = [
        'a' => '[a@4\^]',
        'b' => '[b8]',
        'c' => '[c\(<]',
        'e' => '[e3€£]',
        'i' => '[i1!\|]',
        'l' => '[l1\|]',
        'o' => '[o0]',
        's' => '[s5\$z]',
        't' => '[t7\+]',
        'u' => '[uvµ]',
    ];

    /**
     * Baseline fallback list of profane, vulgar, and abusive terms.
     */
    protected array $fallbackCurseWords = [
        // Multi-word phrases first (English & Tagalog)
        'motherfucker', 'motherfucking', 'son of a bitch', 'bloody hell',
        'putang ina mo', 'putangina mo', 'putang ina', 'putangina', 'putanginamo',
        'tangina mo', 'tanginamo', 'tangina', 'tanginang',
        'pesteng yawa', 'leching yan', 'wala kang hiya', 'walang hiya', 'walanghiya',
        'wala kang kwenta', 'walang kwenta', 'mangmang ka', 'baliw ka', 'lintik ka',
        'pakyu ka', 'pak yu', 'pakyu', 'pakshet', 'pak shet', 'sira ang ulo',
        'sira ulo', 'siraulo',

        // English single words
        'fuck', 'fucker', 'fucking', 'fucked', 'fuckup',
        'shit', 'shitty', 'shithead', 'bullshit', 'horseshit', 'shitface', 'shitbag',
        'bitch', 'bitchy', 'bitches',
        'asshole', 'ass', 'arse', 'arsehole',
        'bastard', 'bastards', 'cunt', 'cunts',
        'dick', 'dickhead', 'dickface', 'cock', 'cocksucker',
        'pussy', 'pussies', 'whore', 'whores', 'whorehouse',
        'slut', 'slutty', 'sluts', 'damn', 'dammit', 'damned',
        'hell', 'crap', 'crappy', 'piss', 'pissed', 'pisser',
        'twat', 'twats', 'wanker', 'wankers', 'wank', 'bollocks',
        'bugger', 'prick', 'pricks', 'skank', 'skanky',
        'douchebag', 'douche', 'jackass', 'jackasses',
        'dipshit', 'dumbass', 'dumbfuck', 'numbnuts',
        'fag', 'faggot', 'retard', 'retarded',
        'scumbag', 'scum', 'pervert', 'perv', 'cuck', 'cucked',

        // Tagalog single words
        'gago', 'gagong', 'gaga', 'tarantado', 'tarantadong',
        'ulol', 'ulolang', 'pucha', 'puchanggala', 'puchangina',
        'kupal', 'kupaling', 'bobo', 'bobong', 'boba',
        'tanga', 'tangang', 'inutil', 'inutilang',
        'lintik', 'lintikan', 'leche', 'letse', 'letseplan',
        'bwisit', 'bwisitin', 'nakakabwisit', 'hayop', 'hayup',
        'hindot', 'hindotan', 'jakol', 'jakulero',
        'kantot', 'kantotin', 'yawa', 'demonyo', 'demonyong',
        'buang', 'buanga', 'gunggong', 'gungong',
        'engot', 'engoting', 'ungas', 'ungasang',
        'baliw', 'baliwang', 'ampota', 'ampotak',
        'salbahe', 'salbaheng', 'hudas', 'hudasang',
        'duwag', 'duwaging', 'sinungaling', 'sinungalingang',
        'burat', 'burating', 'puke', 'puking',
        'pwet', 'pwetan', 'bilat', 'bilating', 'pangit', 'panget',
    ];

    /**
     * Backward-compatible property reference.
     */
    protected array $curseWords = [];

    /**
     * Words that overlap with common, clean words and therefore require strict word boundaries.
     */
    protected array $strictBoundaryWords = [
        'ass', 'hell', 'crap', 'aso', 'arte', 'supot', 'tanga', 'leche', 'cock', 'dam', 'damn', 'scum', 'perv',
    ];

    /**
     * Retrieve the active profane words list from the database/cache, falling back to baseline defaults.
     *
     * @return array<int, string>
     */
    public function getActiveCurseWords(): array
    {
        try {
            if (class_exists(Profanity::class)) {
                $words = Profanity::getActiveWords();
                if (! empty($words)) {
                    return $words;
                }
            }
        } catch (\Throwable $e) {
            // Database not ready or in early migration state
        }

        return ! empty($this->curseWords) ? $this->curseWords : $this->fallbackCurseWords;
    }

    /**
     * Clean and normalize incoming text by stripping profanity, including leetspeak and spaced evasions.
     */
    public function clean(?string $text, bool $trimAndNormalizeWhitespace = true): string
    {
        if (! $text) {
            return '';
        }

        $cleaned = $text;
        $words = $this->getActiveCurseWords();

        foreach ($words as $word) {
            $pattern = $this->buildRegexPattern($word);
            $cleaned = (string) preg_replace($pattern, '', $cleaned);
        }

        if ($trimAndNormalizeWhitespace) {
            // Clean up multi-spaces and trim punctuation artifacts
            $cleaned = (string) preg_replace('/\s+/', ' ', $cleaned);

            return trim($cleaned);
        }

        return $cleaned;
    }

    /**
     * Check if a given string contains any profanity (plain, leetspeak, or spaced).
     */
    public function hasProfanity(?string $text): bool
    {
        if (! $text) {
            return false;
        }

        $words = $this->getActiveCurseWords();

        foreach ($words as $word) {
            $pattern = $this->buildRegexPattern($word);
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build an evasive-resistant regex pattern for a profane word or phrase.
     */
    public function buildRegexPattern(string $phrase): string
    {
        $words = explode(' ', strtolower(trim($phrase)));
        $patternParts = [];

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $charPatterns = [];
            $chars = str_split($word);

            foreach ($chars as $char) {
                $sub = $this->charSubstitutions[$char] ?? preg_quote($char, '/');
                // Match 1 or more occurrences of the character/symbol
                $charPatterns[] = $sub.'+';
            }

            // Allow optional spacing, dots, dashes, underscores, asterisks between letters
            $wordPattern = implode('[\s_\.\-\*]*', $charPatterns);
            $patternParts[] = $wordPattern;
        }

        if (empty($patternParts)) {
            return '//';
        }

        // Join multiple words allowing variable whitespace
        $fullBody = implode('\s+', $patternParts);

        return '/(?<![a-zA-Z0-9])'.$fullBody.'(?![a-zA-Z0-9])/i';
    }
}
