<?php

declare(strict_types=1);

/**
 * Conservative pure-PHP spelling helper.
 * Only fixes likely single-edit typos against a lexicon.
 * Never "corrects" unknown valid words into shorter near-matches.
 */
if (!class_exists('SpellCorrector', false)) {

class SpellCorrector
{
    /** @var array<string, true> */
    private static array $lexicon = [
        // function / question words
        'the' => true, 'and' => true, 'for' => true, 'with' => true, 'that' => true,
        'this' => true, 'what' => true, 'how' => true, 'why' => true, 'who' => true,
        'when' => true, 'where' => true, 'which' => true, 'from' => true, 'about' => true,
        'into' => true, 'over' => true, 'after' => true, 'before' => true, 'between' => true,
        'please' => true, 'explain' => true, 'define' => true, 'describe' => true,
        'compare' => true, 'list' => true, 'show' => true, 'tell' => true, 'give' => true,
        'make' => true, 'create' => true, 'help' => true, 'need' => true, 'want' => true,
        'know' => true, 'think' => true, 'work' => true, 'works' => true, 'working' => true,
        'build' => true, 'building' => true, 'built' => true, 'trust' => true, 'trusted' => true,
        'true' => true, 'false' => true, 'simple' => true, 'example' => true, 'examples' => true,
        'information' => true, 'question' => true, 'answer' => true, 'response' => true,
        'system' => true, 'memory' => true, 'topic' => true, 'topics' => true,
        'fact' => true, 'facts' => true, 'knowledge' => true, 'prompt' => true,
        'router' => true, 'skill' => true, 'skills' => true,
        // people / common verbs & nouns
        'people' => true, 'person' => true, 'team' => true, 'user' => true, 'users' => true,
        'good' => true, 'better' => true, 'best' => true, 'more' => true, 'most' => true,
        'like' => true, 'just' => true, 'also' => true, 'only' => true, 'very' => true,
        'have' => true, 'has' => true, 'had' => true, 'does' => true, 'did' => true,
        'can' => true, 'could' => true, 'would' => true, 'should' => true, 'will' => true,
        'get' => true, 'got' => true, 'use' => true, 'using' => true, 'used' => true,
        'find' => true, 'learn' => true, 'write' => true, 'read' => true, 'run' => true,
        'start' => true, 'stop' => true, 'open' => true, 'close' => true, 'save' => true,
        'load' => true, 'store' => true, 'search' => true, 'query' => true, 'result' => true,
        'results' => true, 'data' => true, 'value' => true, 'values' => true, 'text' => true,
        'word' => true, 'words' => true, 'sentence' => true, 'language' => true,
        'english' => true, 'meaning' => true, 'means' => true, 'important' => true,
        'different' => true, 'same' => true, 'other' => true, 'another' => true,
        'first' => true, 'second' => true, 'next' => true, 'last' => true, 'new' => true,
        'old' => true, 'long' => true, 'short' => true, 'high' => true, 'low' => true,
        'small' => true, 'large' => true, 'big' => true, 'local' => true, 'remote' => true,
        'public' => true, 'private' => true, 'secure' => true, 'security' => true,
        'problem' => true, 'issue' => true, 'error' => true, 'errors' => true,
        'fix' => true, 'fixed' => true, 'change' => true, 'update' => true,
        'improve' => true, 'improved' => true, 'improvement' => true,
        // tech
        'php' => true, 'json' => true, 'html' => true, 'css' => true, 'javascript' => true,
        'python' => true, 'sql' => true, 'api' => true, 'http' => true, 'server' => true,
        'client' => true, 'database' => true, 'array' => true, 'object' => true,
        'class' => true, 'function' => true, 'method' => true, 'variable' => true,
        'string' => true, 'integer' => true, 'boolean' => true, 'null' => true,
        'exception' => true, 'file' => true, 'files' => true, 'folder' => true,
        'directory' => true, 'path' => true, 'config' => true, 'configuration' => true,
        'openrouter' => true, 'embedding' => true, 'embeddings' => true,
        'vector' => true, 'vectors' => true, 'cosine' => true, 'similarity' => true,
        'artificial' => true, 'intelligence' => true, 'quantum' => true, 'computing' => true,
        'programming' => true, 'software' => true, 'code' => true, 'coding' => true,
        'developer' => true, 'development' => true, 'request' => true, 'model' => true,
        'models' => true, 'chat' => true, 'completion' => true, 'understanding' => true,
        'analysis' => true, 'analyze' => true, 'extract' => true, 'retrieve' => true,
        'index' => true, 'underwater' => true, 'volcano' => true, 'volcanoes' => true,
        'morning' => true, 'hello' => true, 'thanks' => true, 'thank' => true,
        'cfcbazar' => true, 'ai' => true, 'reason' => true, 'reasoning' => true,
        'conversation' => true, 'session' => true, 'context' => true, 'history' => true,
        'relationship' => true, 'relationships' => true, 'friend' => true, 'friends' => true,
        'family' => true, 'business' => true, 'customer' => true, 'customers' => true,
        'honest' => true, 'honesty' => true, 'respect' => true, 'reliable' => true,
        'reliability' => true, 'consistent' => true, 'consistency' => true,
        'communication' => true, 'communicate' => true, 'listen' => true, 'listening' => true,
        'food' => true, 'foods' => true, 'form' => true, 'forms' => true, 'from' => true, 'water' => true, 'house' => true, 'home' => true,
        'time' => true, 'year' => true, 'day' => true, 'week' => true, 'month' => true,
        'man' => true, 'woman' => true, 'child' => true, 'children' => true,
        'world' => true, 'life' => true, 'hand' => true, 'part' => true, 'place' => true,
        'case' => true, 'point' => true, 'group' => true, 'company' => true, 'number' => true,
        'fact' => true, 'idea' => true, 'body' => true, 'book' => true, 'end' => true,
        'job' => true, 'money' => true, 'story' => true, 'room' => true, 'area' => true,
        'name' => true, 'power' => true, 'game' => true, 'line' => true, 'city' => true,
        'side' => true, 'head' => true, 'level' => true, 'order' => true, 'car' => true,
        'law' => true, 'door' => true, 'face' => true, 'education' => true, 'policy' => true,
        'health' => true, 'music' => true, 'art' => true, 'history' => true, 'science' => true,
        'school' => true, 'student' => true, 'teacher' => true, 'family' => true,
        'friend' => true, 'friends' => true, 'love' => true, 'hate' => true,
        'eat' => true, 'eating' => true, 'drink' => true, 'sleep' => true, 'walk' => true,
        'run' => true, 'play' => true, 'buy' => true, 'sell' => true, 'pay' => true,
        'call' => true, 'try' => true, 'ask' => true, 'seem' => true, 'feel' => true,
        'leave' => true, 'put' => true, 'mean' => true, 'keep' => true, 'let' => true,
        'begin' => true, 'seem' => true, 'help' => true, 'talk' => true, 'turn' => true,
        'start' => true, 'show' => true, 'hear' => true, 'play' => true, 'move' => true,
        'live' => true, 'believe' => true, 'hold' => true, 'bring' => true, 'happen' => true,
        'must' => true, 'still' => true, 'many' => true, 'much' => true, 'some' => true,
        'own' => true, 'other' => true, 'into' => true, 'than' => true, 'then' => true,
        'look' => true, 'only' => true, 'come' => true, 'made' => true, 'after' => true,
        'back' => true, 'little' => true, 'where' => true, 'much' => true, 'before' => true,
        'great' => true, 'same' => true, 'through' => true, 'being' => true, 'under' => true,
        'never' => true, 'something' => true, 'always' => true, 'between' => true,
        'another' => true, 'because' => true, 'while' => true, 'during' => true,
        'without' => true, 'again' => true, 'place' => true, 'around' => true,
        'however' => true, 'until' => true, 'against' => true, 'among' => true,
        'though' => true, 'whether' => true, 'together' => true, 'enough' => true,
        'really' => true, 'almost' => true, 'later' => true, 'early' => true,
        'young' => true, 'important' => true, 'public' => true, 'human' => true,
        'both' => true, 'each' => true, 'few' => true, 'such' => true, 'why' => true,
        'meal' => true, 'meals' => true, 'fruit' => true, 'fruits' => true,
        'vegetable' => true, 'vegetables' => true, 'meat' => true, 'bread' => true,
        'rice' => true, 'fish' => true, 'milk' => true, 'coffee' => true, 'tea' => true,
        'cook' => true, 'cooking' => true, 'recipe' => true, 'recipes' => true,
        'restaurant' => true, 'kitchen' => true, 'dinner' => true, 'lunch' => true,
        'breakfast' => true, 'hungry' => true, 'taste' => true, 'sweet' => true,
        'salt' => true, 'sugar' => true, 'oil' => true, 'egg' => true, 'eggs' => true,

    ];

    /**
     * Correct only clear single-edit typos. Unknown words are left unchanged.
     *
     * @param list<string> $extraWords optional extra lexicon entries (must already be clean)
     */
    /**
     * Correct only clear single-edit typos. One pass; each distinct word form
     * is decided once (like a normal spell-checker). Known lexicon words are
     * never changed.
     *
     * @param list<string> $extraWords optional extra lexicon entries
     */
    public static function correct(string $prompt, array $extraWords = []): string
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return $prompt;
        }

        $lex = self::$lexicon;
        foreach ($extraWords as $word) {
            foreach (preg_split('/[_\s-]+/', strtolower((string)$word)) ?: [] as $part) {
                $part = preg_replace('/[^a-z0-9]+/i', '', $part) ?? $part;
                // Long topic tokens can join the lexicon; short ones only if already known
                if ($part === '') {
                    continue;
                }
                if (strlen($part) >= 6 || isset(self::$lexicon[$part])) {
                    $lex[$part] = true;
                }
            }
        }

        // Cache: each distinct lowercase token is corrected at most once
        $cache = [];

        return preg_replace_callback(
            '/[A-Za-z][A-Za-z0-9\']*/',
            static function (array $m) use ($lex, &$cache): string {
                $token = $m[0];
                $lower = strtolower($token);
                $len = strlen($lower);

                if (array_key_exists($lower, $cache)) {
                    return self::applyCase($token, $cache[$lower]);
                }

                // Keep short tokens and known words — never "fix" them
                if ($len < 4 || isset($lex[$lower])) {
                    $cache[$lower] = $lower;
                    return $token;
                }

                $best = null;
                $ties = 0;

                foreach ($lex as $word => $_) {
                    $word = (string)$word;
                    $wlen = strlen($word);
                    if (abs($wlen - $len) > 1) {
                        continue;
                    }
                    $dist = levenshtein($lower, $word);
                    if ($dist === 0) {
                        $cache[$lower] = $lower;
                        return $token;
                    }
                    if ($dist !== 1) {
                        continue;
                    }
                    // Skip first-letter-only swaps on short/medium words (food→good)
                    if ($len <= 6 && $wlen === $len
                        && $lower[0] !== $word[0]
                        && substr($lower, 1) === substr($word, 1)) {
                        continue;
                    }
                    // Skip last-letter-only swaps that yield a different common word of same length
                    if ($len <= 5 && $wlen === $len
                        && substr($lower, 0, -1) === substr($word, 0, -1)
                        && $lower[$len - 1] !== $word[$wlen - 1]) {
                        // allow only if original looks like a clear typo pattern — be conservative
                        continue;
                    }

                    if ($best === null) {
                        $best = $word;
                        $ties = 1;
                    } elseif ($best !== $word) {
                        $ties++;
                    }
                }

                if ($best === null || $ties > 1) {
                    $cache[$lower] = $lower;
                    return $token;
                }

                // Do not shorten short content words into tiny function words
                if (strlen($best) < $len && $len <= 5 && strlen($best) <= 3) {
                    $cache[$lower] = $lower;
                    return $token;
                }

                $cache[$lower] = $best;
                return self::applyCase($token, $best);
            },
            $prompt
        ) ?? $prompt;
    }

    private static function applyCase(string $original, string $replacement): string
    {
        if ($original === strtoupper($original) && strlen($original) > 1) {
            return strtoupper($replacement);
        }
        if (isset($original[0]) && $original[0] === strtoupper($original[0])
            && $original !== strtolower($original)) {
            return ucfirst($replacement);
        }
        return $replacement;
    }
}

} // class_exists
