<?php

declare(strict_types=1);

if (!class_exists('LocalEmbedder', false)) {

/**
 * Pure-PHP local text embedder (feature hashing + n-grams).
 * No APIs, no extensions, upload-only.
 *
 * Produces fixed-dimension L2-normalized float vectors for cosine search.
 */
class LocalEmbedder
{
    public const DIMENSIONS = 1536;

    private static function lower(string $s): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
    }

    private static function len(string $s): int
    {
        return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    }

    private static function sub(string $s, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($s, $start, $length) : substr($s, $start, $length);
    }

    /** @var array<string, true> */
    private static array $stopWords = [
        'a' => true, 'an' => true, 'the' => true, 'and' => true, 'or' => true,
        'but' => true, 'in' => true, 'on' => true, 'at' => true, 'to' => true,
        'for' => true, 'of' => true, 'with' => true, 'by' => true, 'from' => true,
        'is' => true, 'are' => true, 'was' => true, 'were' => true, 'be' => true,
        'been' => true, 'being' => true, 'have' => true, 'has' => true, 'had' => true,
        'do' => true, 'does' => true, 'did' => true, 'will' => true, 'would' => true,
        'could' => true, 'should' => true, 'may' => true, 'might' => true, 'must' => true,
        'shall' => true, 'can' => true, 'need' => true, 'dare' => true, 'ought' => true,
        'used' => true, 'it' => true, 'its' => true, 'this' => true, 'that' => true,
        'these' => true, 'those' => true, 'i' => true, 'you' => true, 'he' => true,
        'she' => true, 'we' => true, 'they' => true, 'me' => true, 'him' => true,
        'her' => true, 'us' => true, 'them' => true, 'my' => true, 'your' => true,
        'his' => true, 'their' => true, 'what' => true, 'which' => true, 'who' => true,
        'whom' => true, 'whose' => true, 'where' => true, 'when' => true, 'why' => true,
        'how' => true, 'all' => true, 'each' => true, 'every' => true, 'both' => true,
        'few' => true, 'more' => true, 'most' => true, 'other' => true, 'some' => true,
        'such' => true, 'no' => true, 'nor' => true, 'not' => true, 'only' => true,
        'own' => true, 'same' => true, 'so' => true, 'than' => true, 'too' => true,
        'very' => true, 'just' => true, 'about' => true, 'into' => true, 'over' => true,
        'after' => true, 'before' => true, 'between' => true, 'under' => true,
        'again' => true, 'further' => true, 'then' => true, 'once' => true,
        'here' => true, 'there' => true, 'any' => true, 'if' => true, 'as' => true,
        'up' => true, 'down' => true, 'out' => true, 'off' => true, 'please' => true,
        'tell' => true, 'give' => true, 'show' => true, 'know' => true,
    ];

    /**
     * Embed text into a fixed-length L2-normalized float vector.
     *
     * @return list<float>
     */
    public static function embed(string $text, int $dimensions = self::DIMENSIONS): array
    {
        $dimensions = max(32, min(2048, $dimensions));
        $vector = array_fill(0, $dimensions, 0.0);

        $tokens = self::tokenize($text);
        if ($tokens === []) {
            return $vector;
        }

        // Unigrams
        foreach ($tokens as $token) {
            self::addFeature($vector, $dimensions, $token, 1.0);
        }

        // Bigrams
        $count = count($tokens);
        for ($i = 0; $i < $count - 1; $i++) {
            self::addFeature($vector, $dimensions, $tokens[$i] . '_' . $tokens[$i + 1], 0.75);
        }

        // Character trigrams (helps misspellings / short words)
        $compact = preg_replace('/\s+/u', '', self::lower($text)) ?? '';
        $len = self::len($compact);
        if ($len >= 3) {
            for ($i = 0; $i <= $len - 3; $i++) {
                $tri = self::sub($compact, $i, 3);
                self::addFeature($vector, $dimensions, 'c3:' . $tri, 0.35);
            }
        }

        return self::l2Normalize($vector);
    }

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public static function embedBatch(array $texts, int $dimensions = self::DIMENSIONS): array
    {
        $out = [];
        foreach ($texts as $text) {
            $out[] = self::embed((string) $text, $dimensions);
        }
        return $out;
    }

    public static function dimensions(): int
    {
        return self::DIMENSIONS;
    }

    /**
     * @return list<string>
     */
    private static function tokenize(string $text): array
    {
        $text = self::lower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if (self::len($part) < 2) {
                continue;
            }
            if (isset(self::$stopWords[$part])) {
                continue;
            }
            $tokens[] = $part;
        }

        return $tokens;
    }

    /**
     * @param list<float> $vector
     */
    private static function addFeature(array &$vector, int $dimensions, string $feature, float $weight): void
    {
        // Signed feature hashing (hashing trick)
        $hash = self::stableHash($feature);
        $index = $hash % $dimensions;
        $sign = (($hash >> 16) & 1) === 0 ? 1.0 : -1.0;
        $vector[$index] += $sign * $weight;
    }

    private static function stableHash(string $value): int
    {
        // crc32 is fast and available everywhere; mask to unsigned 31-bit
        $h = crc32($value);
        if ($h < 0) {
            $h = $h & 0x7fffffff;
        }
        return $h;
    }

    /**
     * @param list<float> $vector
     * @return list<float>
     */
    private static function l2Normalize(array $vector): array
    {
        $sum = 0.0;
        foreach ($vector as $v) {
            $sum += $v * $v;
        }
        if ($sum <= 0.0) {
            return $vector;
        }
        $norm = sqrt($sum);
        foreach ($vector as $i => $v) {
            $vector[$i] = $v / $norm;
        }
        return $vector;
    }
}

} // class_exists LocalEmbedder
