<?php

namespace App\Services;

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
     * Comprehensive list of profane, vulgar, and abusive terms.
     */
    protected array $curseWords = [
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
     * Words that overlap with common, clean words and therefore require strict word boundaries.
     */
    protected array $strictBoundaryWords = [
        'ass', 'hell', 'crap', 'aso', 'arte', 'supot', 'tanga', 'leche', 'cock', 'dam', 'damn', 'scum', 'perv',
    ];

    /**
     * Clean and normalize incoming text by stripping profanity, including leetspeak and spaced evasions.
     */
    public function clean(?string $text): string
    {
        if (! $text) {
            return '';
        }

        $cleaned = $text;

        foreach ($this->curseWords as $word) {
            $pattern = $this->buildRegexPattern($word);
            $cleaned = (string) preg_replace($pattern, '', $cleaned);
        }

        // Clean up multi-spaces and trim punctuation artifacts
        $cleaned = (string) preg_replace('/\s+/', ' ', $cleaned);

        return trim($cleaned);
    }

    /**
     * Check if a given string contains any profanity (plain, leetspeak, or spaced).
     */
    public function hasProfanity(?string $text): bool
    {
        if (! $text) {
            return false;
        }

        foreach ($this->curseWords as $word) {
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
    protected function buildRegexPattern(string $phrase): string
    {
        $words = explode(' ', strtolower($phrase));
        $patternParts = [];

        foreach ($words as $word) {
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

        // Join multiple words allowing variable whitespace
        $fullBody = implode('\s+', $patternParts);

        // Determine if boundary is required
        if (in_array(strtolower($phrase), $this->strictBoundaryWords, true)) {
            return '/(?<![a-zA-Z0-9])'.$fullBody.'(?![a-zA-Z0-9])/i';
        }

        return '/(?<![a-zA-Z0-9])?'.$fullBody.'(?![a-zA-Z0-9])?/i';
    }
}
