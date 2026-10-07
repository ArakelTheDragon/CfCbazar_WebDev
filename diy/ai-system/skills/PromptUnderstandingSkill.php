<?php

declare(strict_types=1);

require_once __DIR__ . '/FactSkill.php';
require_once __DIR__ . '/../core/SkillData.php';
require_once __DIR__ . '/../core/MemoryStore.php';

/**
 * Analyse an English-language user prompt (including longer / multi-clause text).
 *
 * Produces routing/context info + prompt facts + query embedding.
 * Does not retrieve memory, call OpenRouter for chat, or generate final response.
 */
class PromptUnderstandingSkill
{
    private static function len(string $s): int
    {
        return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    }

    private static function sub(string $s, int $start, ?int $len = null): string
    {
        if (function_exists('mb_substr')) {
            return $len === null ? mb_substr($s, $start) : mb_substr($s, $start, $len);
        }
        return $len === null ? substr($s, $start) : substr($s, $start, $len);
    }

    private static function rpos(string $hay, string $needle): int|false
    {
        return function_exists('mb_strrpos') ? mb_strrpos($hay, $needle) : strrpos($hay, $needle);
    }

    /**
     * Preferred entry: fill SkillData in place (Phase 2).
     */
    public function process(SkillData $data, ?MemoryStore $memory = null): SkillData
    {
        $data->setSkill('prompt_understanding');
        $clean = $this->normalizePrompt($data->prompt());
        $data->set('prompt', $clean);

        $core = $this->extractCoreQuery($clean);
        $intent = $this->detectIntent($clean);
        $secondary = $this->detectSecondaryIntents($clean, $intent);
        $topic = $this->extractTopic($clean);
        if ($intent === 'generate_code') {
            $pl = strtolower($clean);
            if (preg_match('/\bphp\b/', $pl)) {
                $topic = 'php_page';
            } elseif (preg_match('/\bhtml\b|\bweb\s*page\b|\bwebpage\b/', $pl)) {
                $topic = 'html_page';
            }
        }
        $subtopics = $this->extractSubtopics($clean, $topic);
        $entities = $this->extractEntities($clean);
        $questionType = $this->detectQuestionType($clean);
        $constraints = $this->extractConstraints($clean);
        $keyPhrases = $this->extractKeyPhrases($clean);
        $isMultiPart = $this->isMultiPart($clean);

        $embedText = $core !== '' ? $core : $clean;
        if ($keyPhrases !== []) {
            $embedText .= ' ' . implode(' ', array_slice($keyPhrases, 0, 6));
        }

        $promptFacts = FactSkill::extractFactsFromPrompt($clean);
        $queryEmbedding = FactSkill::embed($embedText);

        // Memory peek: refine topic / seed memory_hits (read-only)
        $memoryHits = [];
        if ($memory !== null && $queryEmbedding !== []) {
            if ($intent !== 'generate_code') {
                $topic = $memory->resolveTopic($topic);
            }

            $hits = $memory->searchByVector($queryEmbedding, 0.32, 6, null);
            foreach ($hits as $hit) {
                if (!is_array($hit)) {
                    continue;
                }
                $memoryHits[] = SkillData::normalizeFact($hit);

                // Bias topic toward strong memory match (not for code generation)
                if ($intent !== 'generate_code'
                    && isset($hit['_topic'], $hit['_score'])
                    && (float)$hit['_score'] >= 0.48
                ) {
                    $memTopic = $memory->resolveTopic((string)$hit['_topic']);
                    if ($memTopic !== '' && $memTopic !== 'general_topic') {
                        $topic = $memTopic;
                    }
                }
            }

            // If topic still weak, try text search on key phrases
            if ($memoryHits === [] && $keyPhrases !== []) {
                $hits = $memory->searchByText(
                    implode(' ', array_slice($keyPhrases, 0, 5)),
                    0.30,
                    4,
                    null
                );
                foreach ($hits as $hit) {
                    if (is_array($hit)) {
                        $memoryHits[] = SkillData::normalizeFact($hit);
                    }
                }
            }
        }

        $data->set('core_query', $core);
        $data->set('intent', $intent);
        $data->set('secondary_intents', $secondary);
        $data->set('topic', $topic);
        $data->set('subtopics', $subtopics);
        $data->set('entities', $entities);
        $data->set('question_type', $questionType);
        $data->set('constraints', $constraints);
        $data->set('key_phrases', $keyPhrases);
        $data->set('is_multi_part', $isMultiPart);
        $data->set('query_embedding', $queryEmbedding);
        $data->set('memory_hits', $memoryHits);
        $data->mergeFacts($promptFacts);

        return $data;
    }

    /**
     * Legacy array API (still used by older callers / tests).
     *
     * @return array<string, mixed>
     */
    public function analyze(string $prompt): array
    {
        $data = SkillData::create($prompt, $prompt);
        $data = $this->process($data);

        return [
            'raw_prompt'         => $data->prompt(),
            'core_query'         => (string)$data->get('core_query', ''),
            'intent'             => (string)$data->get('intent', 'ask_information'),
            'secondary_intents'  => is_array($data->get('secondary_intents')) ? $data->get('secondary_intents') : [],
            'topic'              => $data->topic(),
            'subtopics'          => is_array($data->get('subtopics')) ? $data->get('subtopics') : [],
            'entities'           => is_array($data->get('entities')) ? $data->get('entities') : [],
            'question_type'      => (string)$data->get('question_type', 'statement'),
            'constraints'        => is_array($data->get('constraints')) ? $data->get('constraints') : [],
            'key_phrases'        => is_array($data->get('key_phrases')) ? $data->get('key_phrases') : [],
            'is_multi_part'      => (bool)$data->get('is_multi_part', false),
            'prompt_facts'       => is_array($data->get('facts')) ? $data->get('facts') : [],
            'query_embedding'    => is_array($data->get('query_embedding')) ? $data->get('query_embedding') : [],
        ];
    }

    private function normalizePrompt(string $prompt): string
    {
        $prompt = trim($prompt);
        $prompt = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $prompt) ?? $prompt;
        // Collapse whitespace but keep single spaces (multi-sentence stays one block)
        $prompt = preg_replace('/[ \t]+/u', ' ', $prompt) ?? $prompt;
        $prompt = preg_replace('/\s*\n\s*/u', ' ', $prompt) ?? $prompt;

        return trim($prompt);
    }

    /**
     * Pull the main ask from long prompts (after "about/regarding", first question, etc.).
     */
    private function extractCoreQuery(string $prompt): string
    {
        // Prefer explicit focus markers
        if (preg_match(
            '/\b(?:about|regarding|concerning|on the topic of|related to)\s+(.+)$/i',
            $prompt,
            $m
        )) {
            $core = trim($m[1], " \t?.!,;:");
            if (self::len($core) >= 8) {
                return $core;
            }
        }

        // Split into sentences; prefer the first interrogative, else the longest content sentence
        $parts = preg_split('/(?<=[.!?])\s+/u', $prompt, -1, PREG_SPLIT_NO_EMPTY) ?: [$prompt];
        $questions = [];
        $statements = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(?:what|who|when|where|why|how|which|can|could|should|is|are|do|does)\b/i', $part)
                || str_ends_with($part, '?')) {
                $questions[] = $part;
            } else {
                $statements[] = $part;
            }
        }

        if ($questions !== []) {
            // First question is usually the main ask; if multiple, join short ones
            if (count($questions) === 1) {
                return $questions[0];
            }
            $joined = implode(' ', array_slice($questions, 0, 2));
            return self::len($joined) <= 280 ? $joined : $questions[0];
        }

        // Long statement: take first ~200 chars as core focus
        if (self::len($prompt) > 220) {
            $cut = self::sub($prompt, 0, 200);
            $sp = self::rpos($cut, ' ');
            return trim($sp !== false ? self::sub($cut, 0, $sp) : $cut);
        }

        return $prompt;
    }

    private function detectIntent(string $prompt): string
    {
        $p = strtolower($prompt);

        $patterns = [
            // Code / page generation (must be before generic "what is")
            'make me a simple html'     => 'generate_code',
            'make me a simple php'      => 'generate_code',
            'make me an html'           => 'generate_code',
            'make me an advanced'       => 'generate_code',
            'make me a complex'         => 'generate_code',
            'make me a php'             => 'generate_code',
            'make me a simple page'     => 'generate_code',
            'make me a webpage'         => 'generate_code',
            'make me a web page'        => 'generate_code',
            'create a simple html'      => 'generate_code',
            'create a simple php'       => 'generate_code',
            'create an html'            => 'generate_code',
            'create a php'              => 'generate_code',
            'write me a simple html'    => 'generate_code',
            'write me a simple php'     => 'generate_code',
            'write a simple html'       => 'generate_code',
            'write a simple php'        => 'generate_code',
            'generate a simple html'    => 'generate_code',
            'generate a simple php'     => 'generate_code',
            'build me a simple'         => 'generate_code',
            'give me a simple html'     => 'generate_code',
            'give me a simple php'      => 'generate_code',
            'step by step'              => 'explain_process',
            'walk me through'           => 'explain_process',
            'explain how'               => 'explain_process',
            'how do i'                  => 'explain_process',
            'how can i'                 => 'explain_process',
            'how does'                  => 'explain_process',
            'how is'                    => 'explain_process',
            'how to'                    => 'explain_process',
            'why does'                  => 'explain_reason',
            'why is'                    => 'explain_reason',
            'why are'                   => 'explain_reason',
            'why do'                    => 'explain_reason',
            'what is the difference'    => 'compare_things',
            'what are the differences'  => 'compare_things',
            'what is different'         => 'compare_things',
            'difference between'        => 'compare_things',
            'differences between'       => 'compare_things',
            'compare'                   => 'compare_things',
            ' vs '                      => 'compare_things',
            'versus'                    => 'compare_things',
            'pros and cons'             => 'compare_things',
            'advantages and disadvantages' => 'compare_things',
            'what is'                   => 'define_concept',
            'what are'                  => 'define_concept',
            'what does'                 => 'define_concept',
            'define '                   => 'define_concept',
            'meaning of'                => 'define_concept',
            'list '                     => 'list_information',
            'give me a list'            => 'list_information',
            'enumerate'                 => 'list_information',
            'summarize'                 => 'ask_information',
            'summary of'                => 'ask_information',
            'show me'                   => 'ask_information',
            'tell me'                   => 'ask_information',
            'help me'                   => 'explain_process',
            'troubleshoot'              => 'explain_process',
            'fix '                     => 'explain_process',
            'debug'                     => 'explain_process',
            'install '                  => 'explain_process',
            'configure '                => 'explain_process',
            'connect '                  => 'explain_process',
        ];

        foreach ($patterns as $pattern => $intent) {
            if ($this->containsPhrase($p, $pattern)) {
                return $intent;
            }
        }

        // "make/create/write ... php/html page" without exact phrase above
        if (preg_match('/\b(?:make|create|write|generate|build)\b.*\b(?:php|html|css|js|javascript)\b.*\b(?:page|file|script|document)?/i', $p)
            || preg_match('/\b(?:php|html)\s+page\b/i', $p) && preg_match('/\b(?:make|create|write|generate|build|simple)\b/i', $p)) {
            return 'generate_code';
        }

        if ($this->containsWord($p, 'how')) {
            return 'explain_process';
        }
        if ($this->containsWord($p, 'why')) {
            return 'explain_reason';
        }
        if ($this->containsWord($p, 'who') || $this->containsWord($p, 'what')) {
            return 'ask_information';
        }

        return 'ask_information';
    }

    /**
     * @return list<string>
     */
    private function detectSecondaryIntents(string $prompt, string $primary): array
    {
        $p = strtolower($prompt);
        $found = [];
        $map = [
            'example'   => 'list_information',
            'examples'  => 'list_information',
            'compare'   => 'compare_things',
            'difference'=> 'compare_things',
            'why'       => 'explain_reason',
            'how'       => 'explain_process',
            'list'      => 'list_information',
            'steps'     => 'explain_process',
        ];
        foreach ($map as $word => $intent) {
            if ($intent === $primary) {
                continue;
            }
            if ($this->containsWord($p, $word)) {
                $found[$intent] = true;
            }
        }
        return array_keys($found);
    }

    private function extractTopic(string $prompt): string
    {
        $p = strtolower($prompt);

        $topics = [
            'simple php page' => 'php_page',
            'php page' => 'php_page',
            'simple html page' => 'html_page',
            'html page' => 'html_page',
            'web page' => 'html_page',
            'webpage' => 'html_page',
            'prompt understanding' => 'prompt_understanding',
            'response understanding' => 'response_understanding',
            'knowledge skill' => 'knowledge_skill',
            'fact skill' => 'fact_skill',
            'memory store' => 'ai_memory_system',
            'ai memory' => 'ai_memory_system',
            'artificial intelligence' => 'artificial_intelligence',
            'vector embedding' => 'vector_embeddings',
            'vector embeddings' => 'vector_embeddings',
            'php programming' => 'php_programming',
            'sky stream puck' => 'sky_stream_puck',
            'skystream' => 'sky_stream_puck',
            'php' => 'php_programming',
            'json' => 'json_data',
            'router' => 'ai_router_system',
            'memory' => 'ai_memory_system',
            'build trust' => 'building_trust',
            'building trust' => 'building_trust',
            'openrouter' => 'openrouter',
            'conversation memory' => 'conversation_memory',
        ];

        // Structural patterns before short keyword hits (so "json" does not steal compare prompts)
        if (preg_match('/"([^"]{2,120})"/', $prompt, $match)) {
            return $this->slugTopic($match[1]);
        }

        if (preg_match('/\b([a-zA-Z0-9_-]+\.(?:php|json|txt|js|css|html))\b/i', $prompt, $match)) {
            return $this->slugTopic(pathinfo($match[1], PATHINFO_FILENAME));
        }

        if (preg_match(
            '/\b(?:about|regarding|concerning|on the topic of)\s+([^.?!,]{3,80})/i',
            $prompt,
            $m
        )) {
            $focus = $this->stripQuestionWrappers(trim($m[1]));
            if ($focus !== '') {
                return $this->slugTopic($this->topicWordsFromText($focus, 5));
            }
        }

        if (preg_match(
            '/\b(?:difference between|differences between|compare)\s+(.+?)\s+(?:and|vs\.?|versus)\s+(.+?)(?:[.?,!]|$)/i',
            $prompt,
            $m
        )) {
            $left = $this->topicWordsFromText(trim($m[1]), 3);
            $rightRaw = preg_replace('/\bwith\s+examples?\b.*$/i', '', trim($m[2])) ?? trim($m[2]);
            $right = $this->topicWordsFromText(trim($rightRaw), 3);
            if ($left !== '' && $right !== '') {
                return $this->slugTopic($left . '_vs_' . $right);
            }
            if ($left !== '') {
                return $this->slugTopic($left);
            }
        }

        // Longer keyword phrases first
        uksort($topics, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($topics as $keyword => $topicName) {
            if ($this->containsPhrase($p, $keyword)) {
                return $topicName;
            }
        }

        $subject = $this->stripQuestionWrappers($prompt);
        $candidate = $this->topicWordsFromText($subject, 5);

        return $candidate !== '' ? $this->slugTopic($candidate) : 'general_topic';
    }

    /**
     * @return list<string>
     */
    private function extractSubtopics(string $prompt, string $mainTopic): array
    {
        $sub = [];
        // Split on ; and "and also" / "as well as" for secondary subjects
        $chunks = preg_split(
            '/\s*(?:;|\band also\b|\bas well as\b|\bplus\b|\bthen\b)\s+/i',
            $prompt
        ) ?: [];
        foreach ($chunks as $i => $chunk) {
            if ($i === 0) {
                continue;
            }
            $words = $this->topicWordsFromText($this->stripQuestionWrappers($chunk), 4);
            if ($words === '') {
                continue;
            }
            $slug = $this->slugTopic($words);
            if ($slug !== $mainTopic && $slug !== 'general_topic') {
                $sub[] = $slug;
            }
        }
        return array_values(array_unique($sub));
    }

    private function stripQuestionWrappers(string $text): string
    {
        $subject = trim($text);
        $subject = preg_replace(
            '/^(?:what|who|when|where|why|how|which)\b(?:\s+(?:is|are|does|do|did|can|could|would|should|was|were|am|the|a|an))?\s+/i',
            '',
            $subject
        ) ?? $subject;
        $subject = preg_replace(
            '/^(?:i|you|we|they|me|my|your|our)\s+/i',
            '',
            trim((string)$subject)
        ) ?? $subject;
        $subject = preg_replace(
            '/^(?:do|does|did|can|could|would|should|please|to)\s+(?:i|you|we|they)?\s*/i',
            '',
            trim((string)$subject)
        ) ?? $subject;
        $subject = preg_replace('/^to\s+/i', '', trim((string)$subject)) ?? $subject;

        return trim((string)$subject, " \t\n\r\0\x0B?.!,;:");
    }

    private function topicWordsFromText(string $text, int $maxWords): string
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = [
            'i','me','my','you','your','we','our','they','them','the','a','an','to','of','in',
            'on','for','and','or','do','does','did','how','what','why','when','where','who',
            'is','are','was','were','be','been','being','with','from','that','this','it',
            'its','as','at','by','if','so','than','then','too','very','can','could','would',
            'should','will','just','also','about','into','over','after','before','between',
            'please','help','need','want','tell','give','show','some','any','more','most',
            'using','use','get','make','simple','simply','english','detail','details',
        ];
        $words = array_values(array_filter(
            $words,
            static function (string $w) use ($stop): bool {
                $lw = strtolower(trim($w, '.,!?;:()[]"\''));
                return $lw !== '' && strlen($lw) >= 2 && !in_array($lw, $stop, true);
            }
        ));
        $words = array_slice($words, 0, max(1, $maxWords));
        $clean = [];
        foreach ($words as $w) {
            $clean[] = trim($w, '.,!?;:()[]"\'');
        }
        return trim(implode(' ', $clean));
    }

    private function extractEntities(string $prompt): array
    {
        $entities = [];

        if (preg_match_all('/"([^"]+)"/', $prompt, $quoted)) {
            foreach ($quoted[1] as $q) {
                $q = trim($q);
                if ($q !== '') {
                    $entities[] = $q;
                }
            }
        }

        // Capitalized multi-word names (Sky Stream Puck)
        if (preg_match_all('/\b([A-Z][a-zA-Z0-9]+(?:\s+[A-Z][a-zA-Z0-9]+){1,4})\b/', $prompt, $caps)) {
            foreach ($caps[1] as $name) {
                if (!preg_match('/^(?:What|How|Why|When|Where|Who|The|This|That|Please|Can|Could|Should)\b/', $name)) {
                    $entities[] = $name;
                }
            }
        }

        if (preg_match_all('/\b[a-zA-Z0-9_-]+\.(?:php|json|txt|js|css|html)\b/i', $prompt, $files)) {
            foreach ($files[0] as $file) {
                $entities[] = $file;
            }
        }

        foreach ([
            'php', 'ai', 'json', 'array', 'router', 'memory', 'openrouter',
            'knowledge skill', 'fact skill', 'prompt understanding skill',
            'response understanding skill', 'vector embeddings', 'embeddings',
            'wifi', 'wi-fi', 'sky stream puck',
        ] as $entity) {
            if ($this->containsPhrase(strtolower($prompt), $entity)) {
                $entities[] = $entity;
            }
        }

        return array_values(array_unique($entities));
    }

    /**
     * Soft constraints: simple, step by step, short, beginners, etc.
     *
     * @return list<string>
     */
    private function extractConstraints(string $prompt): array
    {
        $p = strtolower($prompt);
        $out = [];
        $map = [
            'simple' => 'simple',
            'advanced' => 'advanced',
            'complex' => 'complex',
            'detailed' => 'detailed',
            'professional' => 'professional',
            'beginner' => 'beginner',
            'step by step' => 'step_by_step',
            'steps' => 'step_by_step',
            'brief' => 'brief',
            'short' => 'brief',
            'detailed' => 'detailed',
            'in detail' => 'detailed',
            'example' => 'with_examples',
            'examples' => 'with_examples',
            'without jargon' => 'simple',
            'eli5' => 'simple',
        ];
        foreach ($map as $needle => $label) {
            if ($this->containsPhrase($p, $needle) || $this->containsWord($p, $needle)) {
                $out[$label] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Content words / short phrases useful for retrieval.
     *
     * @return list<string>
     */
    private function extractKeyPhrases(string $prompt): array
    {
        $text = $this->stripQuestionWrappers($prompt);
        $words = preg_split('/\s+/u', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = [
            'the','and','for','with','that','this','from','into','about','please',
            'what','how','why','when','where','who','which','does','did','can','could',
            'would','should','will','are','is','was','were','have','has','had','been',
            'a','an','to','of','in','on','at','by','or','as','it','its','my','your',
            'me','you','we','they','them','do','just','also','very','more','most',
            'some','any','than','then','so','if','but','not','no','yes',
        ];
        $phrases = [];
        $content = [];
        foreach ($words as $w) {
            $w = trim($w, '.,!?;:()[]"\'');
            if (strlen($w) < 3 || in_array($w, $stop, true)) {
                continue;
            }
            $content[] = $w;
            $phrases[$w] = true;
        }
        // Bigrams from content words
        for ($i = 0; $i < count($content) - 1; $i++) {
            $phrases[$content[$i] . ' ' . $content[$i + 1]] = true;
        }
        return array_slice(array_keys($phrases), 0, 12);
    }

    private function isMultiPart(string $prompt): bool
    {
        $qCount = substr_count($prompt, '?');
        if ($qCount >= 2) {
            return true;
        }
        if (preg_match('/\b(?:also|and also|as well as|secondly|furthermore|another question)\b/i', $prompt)) {
            return true;
        }
        $sentences = preg_split('/(?<=[.!?])\s+/u', $prompt) ?: [];
        return count($sentences) >= 3;
    }

    private function detectQuestionType(string $prompt): string
    {
        $p = strtolower(ltrim($prompt));

        foreach (['who', 'what', 'when', 'where', 'why', 'how'] as $type) {
            if ($this->containsWordAtStart($p, $type) || preg_match('/\b' . $type . '\b/i', $p) === 1) {
                // Prefer the first interrogative word appearing in the prompt
                if (preg_match('/\b(who|what|when|where|why|how)\b/i', $p, $m)) {
                    return strtolower($m[1]);
                }
                return $type;
            }
        }

        return str_contains($prompt, '?') ? 'what' : 'statement';
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        $phrase = trim(strtolower($phrase));
        if ($phrase === '') {
            return false;
        }

        if (preg_match('/^[a-z0-9]+(?:[ _-][a-z0-9]+)*$/i', $phrase)) {
            return preg_match('/\b' . preg_quote($phrase, '/') . '\b/i', $text) === 1;
        }

        return str_contains($text, $phrase);
    }

    private function containsWord(string $text, string $word): bool
    {
        return preg_match('/\b' . preg_quote(strtolower($word), '/') . '\b/i', $text) === 1;
    }

    private function containsWordAtStart(string $text, string $word): bool
    {
        return preg_match('/^' . preg_quote(strtolower($word), '/') . '\b/i', $text) === 1;
    }

    private function slugTopic(string $value): string
    {
        $value = strtolower(trim($value));
        // Drop leading articles so "a sky stream puck" → sky_stream_puck
        $value = preg_replace('/^(?:a|an|the)\s+/i', '', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value) ?? '';
        $value = trim($value, '_');
        // Drop leading article tokens after slugify
        $value = preg_replace('/^(?:a|an|the)_+/i', '', $value) ?? $value;

        return $value !== '' ? $value : 'general_topic';
    }
}
