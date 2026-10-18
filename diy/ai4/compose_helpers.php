<?php
// compose_helpers.php — Pure matching functions (no state, no I/O)
declare(strict_types=1);

class ComposeHelpers {
    /** Default stopwords for word-overlap scoring. */
    public const STOPWORDS = [
        'a','an','the','is','are','was','were','be','been','being',
        'of','in','on','at','to','from','by','with','for','as',
        'and','or','but','not','no','yes','so','than','then',
        'it','its','this','that','these','those',
        'i','me','my','you','your','he','she','we','they','them',
        'do','does','did','have','has','had','will','would','can','could',
        'what','how','where','who','when','why','which',
    ];

    /**
     * Character-level similarity via Levenshtein.
     */
    public static function charSimilarity(string $a, string $b): float {
        if ($a === '' && $b === '') return 1.0;
        if ($a === '' || $b === '') return 0.0;
        $dist = levenshtein($a, $b);
        $maxLen = max(strlen($a), strlen($b));
        return $maxLen > 0 ? 1.0 - ($dist / $maxLen) : 0.0;
    }

    /**
     * Word-overlap similarity (Jaccard on non-stopword tokens).
     */
    public static function wordOverlap(string $a, string $b): float {
        $tokensA = self::tokens($a);
        $tokensB = self::tokens($b);
        if (empty($tokensA) || empty($tokensB)) return 0.0;

        $setA = array_flip($tokensA);
        $setB = array_flip($tokensB);

        $shared = 0;
        foreach ($setA as $w => $_) {
            if (isset($setB[$w])) $shared++;
        }

        $union = count($setA) + count($setB) - $shared;
        return $union > 0 ? $shared / $union : 0.0;
    }

    /**
     * Combined score for fact ranking.
     */
    public static function combinedScore(string $query, string $fact, float $wordWeight = 0.7): float {
        $word = self::wordOverlap($query, $fact);
        $char = self::charSimilarity($query, $fact);
        return $wordWeight * $word + (1.0 - $wordWeight) * $char;
    }

    /**
     * Length penalty: shorter facts get a slight boost.
     */
    public static function lengthBonus(string $text, float $scale = 80.0): float {
        $len = strlen($text);
        return 1.0 / (1.0 + $len / $scale);
    }

    /**
     * Score for a fact that starts with a given content string.
     * NOW: requires a word boundary after the content, so "sun"
     * does NOT match "sunlight...", but DOES match "sun is a star...".
     */
    public static function startsWithScore(string $content, string $fact): float {
        $content = strtolower(trim($content));
        $factLower = strtolower($fact);
        if ($content === '') return 0.0;
        // Must start with content followed by a non-alphanumeric boundary
        if (!preg_match('/^' . preg_quote($content, '/') . '(?![a-z0-9])/', $factLower)) {
            return 0.0;
        }
        return 0.85 * self::lengthBonus($factLower);
    }

    /**
     * Tokenize and strip stopwords.
     */
    public static function tokens(string $text): array {
        $text = strtolower($text);
        $parts = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = [];
        foreach ($parts as $w) {
            if (!in_array($w, self::STOPWORDS, true)) $out[] = $w;
        }
        return array_values(array_unique($out));
    }

    /**
     * Split "X and Y" using a learned predicate to decide prefix expansion.
     */
    public static function splitCompound(string $input, callable $isKnown): array {
        if (strpos($input, ' and ') === false) return [$input];
        $parts = array_map('trim', explode(' and ', $input));
        if (count($parts) < 2) return [$input];

        $first = $parts[0];
        $words = explode(' ', $first);
        $expanded = [$first];

        for ($i = 1; $i < count($parts); $i++) {
            $second = $parts[$i];
            $found = false;
            for ($n = 1; $n < count($words); $n++) {
                $prefix = implode(' ', array_slice($words, 0, $n));
                $candidate = $prefix . ' ' . $second;
                if ($isKnown($candidate)) {
                    $expanded[] = $candidate;
                    $found = true;
                    break;
                }
            }
            if (!$found) $expanded[] = $second;
        }
        return $expanded;
    }

    /**
     * Split text on . ! ? into sentences.
     */
    public static function splitSentences(string $text): array {
        $parts = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_map(fn($p) => trim(preg_replace('/\s+/', ' ', $p)), $parts);
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }

    /**
     * Split input into header (intent phrase) + data (content).
     */
    public static function splitHeader(
        string $input,
        array $intentPhrases,
        array $intentTable
    ): array {
        $q = strtolower(trim($input));
        foreach ($intentPhrases as $phrase) {
            if (strpos($q, $phrase) === 0) {
                $rest = trim(substr($q, strlen($phrase)));
                $rest = preg_replace('/^(the|a|an)\s+/', '', $rest);
                return [
                    'intent' => $intentTable[$phrase],
                    'header' => $phrase,
                    'data'   => $rest,
                ];
            }
        }
        return ['intent' => null, 'header' => null, 'data' => $q];
    }

    /**
     * Split input into regions based on intent-phrase boundaries.
     * Each region = (intent, data, region_bytes).
     * Data is cleaned of fillers, stopwords, and trailing punctuation.
     */
    public static function splitByIntentRegions(
        string $input,
        array $intentPhrases, // sorted longest-first
        array $intentTable
    ): array {
        $q = strtolower(trim($input));
        $q = preg_replace('/\s+/', ' ', $q);
        if ($q === '') return [];

        $len = strlen($q);

        // ---- Find all intent occurrences ----
        $occurrences = [];
        foreach ($intentPhrases as $phrase) {
            $offset = 0;
            while (($pos = strpos($q, $phrase, $offset)) !== false) {
                $occurrences[] = [
                    'pos' => $pos,
                    'len' => strlen($phrase),
                    'phrase' => $phrase,
                    'intent' => $intentTable[$phrase],
                ];
                $offset = $pos + 1;
            }
        }

        // No intents → single chunk with no intent
        if (empty($occurrences)) {
            return [[
                'intent' => null,
                'intent_text' => null,
                'data' => $q,
                'region' => [0, $len],
            ]];
        }

        // ---- Sort by position, drop overlapping ----
        usort($occurrences, fn($a, $b) => $a['pos'] <=> $b['pos']);
        $clean = [];
        foreach ($occurrences as $occ) {
            $overlaps = false;
            foreach ($clean as $c) {
                if ($occ['pos'] < $c['pos'] + $c['len']
                    && $c['pos'] < $occ['pos'] + $occ['len']) {
                    $overlaps = true;
                    break;
                }
            }
            if (!$overlaps) $clean[] = $occ;
        }

        // ---- Build regions ----
        $regions = [];
        $n = count($clean);
        for ($i = 0; $i < $n; $i++) {
            $cur = $clean[$i];
            $next = $clean[$i + 1] ?? null;

            $start = $cur['pos'];
            $end = $next ? $next['pos'] : $len;

            // Data = text between end-of-intent and next intent start
            $data = substr($q, $start + $cur['len'], $end - $start - $cur['len']);

            // If first intent and there's leading text, prepend it
            if ($i === 0 && $start > 0) {
                $lead = trim(substr($q, 0, $start));
                $data = $lead . ' ' . $data;
            }

            $data = self::cleanData($data);

            $regions[] = [
                'intent' => $cur['intent'],
                'intent_text' => $cur['phrase'],
                'data' => $data,
                'region' => [$start, $end],
            ];
        }

        return $regions;
    }

    /**
     * Clean data: strip fillers, stopwords, punctuation.
     */
    public static function cleanData(string $data): string {
        $data = strtolower(trim($data));
        $data = trim($data, " .,;!?-\t\n\r\0\x0B");

        // Remove filler phrases
        $fillers = [
            'also tell me about', 'also tell me', 'tell me about', 'tell me',
            'can you tell me', 'can you', 'could you',
            'please', 'also', 'and also', 'as well as', 'as well',
            'now', 'then', 'additionally', 'moreover', 'furthermore',
            'i want to know', 'i want to', 'i would like to know', 'i would like to',
            'what do you know about', 'describe', 'explain',
        ];
        foreach ($fillers as $f) {
            $data = preg_replace('/\b' . preg_quote($f, '/') . '\b/', ' ', $data);
        }

        // Strip stopwords
        $data = preg_replace('/\b(is|are|was|were|the|a|an|of|did|do|does|in|on|at|to|from|by)\b/', ' ', $data);

        // Collapse whitespace
        $data = trim(preg_replace('/\s+/', ' ', $data));
        $data = trim($data, " .,;!?-\t\n\r\0\x0B");

        return $data;
    }
}
