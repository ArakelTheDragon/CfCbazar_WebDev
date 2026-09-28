<?php

declare(strict_types=1);

/**
 * Lightweight JSON memory store for PHP-only hosting.
 *
 * Design goals:
 * - Preserve compatibility with the current KnowledgeSkill/FactSkill format.
 * - Read older topic formats without destroying them automatically.
 * - Store extensible fact records with stable IDs and metadata.
 * - Keep memory.json as a lightweight topic journal/index.
 * - Use atomic writes and file locking where supported.
 * - Avoid embeddings until an embedding provider is actually implemented.
 
 The system currently has no embedding-generation mechanism, so adding a mandatory embedding field would create unnecessary complexity. When you implement embeddings later, the storage structure can be extended without requiring another major redesign.

The current version should be tested with your existing KnowledgeSkill before we make further changes to the other files.
 */
class MemoryStore
{
    private string $indexPath;
    private string $baseDir;
    private int $maxFactsPerTopic;
    private int $maxTotalFacts;

    public function __construct(
        string $path,
        int $maxFactsPerTopic = 100,
        int $maxTotalFacts = 5000
    ) {
        $this->indexPath = $path;
        $this->baseDir = rtrim(dirname($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'topics';
        $this->maxFactsPerTopic = max(1, $maxFactsPerTopic);
        $this->maxTotalFacts = max($this->maxFactsPerTopic, $maxTotalFacts);

        $this->ensureDirectory(dirname($this->indexPath));
        $this->ensureDirectory($this->baseDir);
    }

    public function getTopicFacts(string $topic): array
    {
        $file = $this->getTopicPath($topic);

        if (!is_file($file)) {
            return [];
        }

        $data = $this->readJsonFile($file);
        return $this->normalizeTopicData($data);
    }

    public function setTopicFacts(string $topic, array $facts): void
    {
        $normalized = $this->normalizeFacts($facts);
        $normalized = $this->pruneTopicFacts($normalized);

        $this->writeJsonFile($this->getTopicPath($topic), $normalized);
        $this->updateTopicIndex($topic, $normalized);
        $this->pruneGlobal();
    }

    public function mergeTopicFacts(string $topic, array $newFacts): array
    {
        $existing = $this->getTopicFacts($topic);
        $incoming = $this->normalizeFacts($newFacts);
        $merged = $this->mergeWithoutExactDuplicates($existing, $incoming);
        $merged = $this->pruneTopicFacts($merged);

        $this->writeJsonFile($this->getTopicPath($topic), $merged);
        $this->updateTopicIndex($topic, $merged);
        $this->pruneGlobal();

        return $merged;
    }

    /**
     * Returns a topic-independent collection of facts for future retrieval logic.
     */
    public function getAllFacts(): array
    {
        $all = [];

        foreach ($this->topicFiles() as $file) {
            $topic = pathinfo($file, PATHINFO_FILENAME);
            foreach ($this->normalizeTopicData($this->readJsonFile($file)) as $fact) {
                $fact['_topic'] = $topic;
                $all[] = $fact;
            }
        }

        return $all;
    }

    private function normalizeTopicData(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        // Current format: a flat array of fact records.
        if (array_is_list($data)) {
            return $this->normalizeFacts($data);
        }

        // Supported older formats: {facts: [...]}, {statements: [...]},
        // or a topic-keyed object such as {php: {facts: [...]}}.
        foreach (['facts', 'statements', 'items', 'entries'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $this->normalizeFacts($data[$key]);
            }
        }

        $collected = [];
        foreach ($data as $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $collected = array_merge($collected, $value);
                } elseif (isset($value['facts']) && is_array($value['facts'])) {
                    $collected = array_merge($collected, $value['facts']);
                } elseif (isset($value['statements']) && is_array($value['statements'])) {
                    $collected = array_merge($collected, $value['statements']);
                }
            }
        }

        return $this->normalizeFacts($collected);
    }

    private function normalizeFacts(array $facts): array
    {
        $normalized = [];
        $now = gmdate('c');

        foreach ($facts as $fact) {
            if (is_string($fact)) {
                $fact = ['value' => trim($fact)];
            }

            if (!is_array($fact)) {
                continue;
            }

            $content = trim((string) ($fact['content'] ?? $fact['value'] ?? $fact['text'] ?? ''));
            if ($content === '') {
                continue;
            }

            $metadata = is_array($fact['metadata'] ?? null) ? $fact['metadata'] : [];
            $createdAt = (string) ($metadata['created_at'] ?? $fact['created_at'] ?? $fact['added_at'] ?? $fact['timestamp'] ?? $now);
            $updatedAt = (string) ($metadata['updated_at'] ?? $fact['updated_at'] ?? $createdAt);

            $record = $fact;
            $record['id'] = (string) ($fact['id'] ?? $this->createFactId());
            $record['type'] = (string) ($fact['type'] ?? $metadata['type'] ?? 'statement');
            $record['value'] = (string) ($fact['value'] ?? $content);
            $record['content'] = $content;
            $record['confidence'] = is_numeric($fact['confidence'] ?? null)
                ? max(0.0, min(1.0, (float) $fact['confidence']))
                : (is_numeric($metadata['confidence'] ?? null) ? max(0.0, min(1.0, (float) $metadata['confidence'])) : 0.80);
            $record['source'] = (string) ($fact['source'] ?? $metadata['source'] ?? 'unknown');
            $record['created_at'] = $createdAt;
            $record['updated_at'] = $updatedAt;
            $record['access_count'] = max(0, (int) ($fact['access_count'] ?? $metadata['access_count'] ?? 0));
            $record['decay_rate'] = max(0.0, (float) ($fact['decay_rate'] ?? $metadata['decay_rate'] ?? 0.05));

            // Embeddings are optional. Do not fabricate vectors.
            if (isset($fact['embedding']) && is_array($fact['embedding'])) {
                $record['embedding'] = array_values(array_filter($fact['embedding'], 'is_numeric'));
            }

            $normalized[] = $record;
        }

        return $normalized;
    }

    private function mergeWithoutExactDuplicates(array $existing, array $incoming): array
    {
        $merged = $existing;
        $known = [];

        foreach ($existing as $fact) {
            $known[$this->dedupKey($fact)] = true;
        }

        foreach ($incoming as $fact) {
            $key = $this->dedupKey($fact);
            if (isset($known[$key])) {
                continue;
            }

            $merged[] = $fact;
            $known[$key] = true;
        }

        return $merged;
    }

    private function dedupKey(array $fact): string
    {
        return strtolower(trim((string) ($fact['content'] ?? $fact['value'] ?? '')));
    }

    private function pruneTopicFacts(array $facts): array
    {
        if (count($facts) <= $this->maxFactsPerTopic) {
            return array_values($facts);
        }

        usort($facts, fn (array $a, array $b): int => strcmp(
            (string) ($a['updated_at'] ?? $a['created_at'] ?? ''),
            (string) ($b['updated_at'] ?? $b['created_at'] ?? '')
        ));

        return array_values(array_slice($facts, -$this->maxFactsPerTopic));
    }

    private function pruneGlobal(): void
    {
        $entries = [];
        $total = 0;

        foreach ($this->topicFiles() as $file) {
            $facts = $this->normalizeTopicData($this->readJsonFile($file));
            $total += count($facts);

            foreach ($facts as $index => $fact) {
                $entries[] = [
                    'file' => $file,
                    'index' => $index,
                    'date' => (string) ($fact['updated_at'] ?? $fact['created_at'] ?? ''),
                ];
            }
        }

        if ($total <= $this->maxTotalFacts) {
            return;
        }

        usort($entries, fn (array $a, array $b): int => strcmp($a['date'], $b['date']));
        $removeCount = $total - $this->maxTotalFacts;
        $removeByFile = [];

        foreach (array_slice($entries, 0, $removeCount) as $entry) {
            $removeByFile[$entry['file']][] = $entry['index'];
        }

        foreach ($removeByFile as $file => $indexes) {
            $facts = $this->normalizeTopicData($this->readJsonFile($file));
            foreach (array_reverse($indexes) as $index) {
                unset($facts[$index]);
            }
            $facts = array_values($facts);

            if ($facts === []) {
                @unlink($file);
            } else {
                $this->writeJsonFile($file, $facts);
            }
        }
    }

    private function updateTopicIndex(string $topic, array $facts): void
    {
        $index = $this->readJsonFile($this->indexPath);
        if (!is_array($index) || array_is_list($index)) {
            $index = [];
        }

        $key = $this->safeTopicName($topic);
        $index[$key] = [
            'topic' => $topic,
            'file' => 'topics/' . $key . '.json',
            'fact_count' => count($facts),
            'last_updated' => gmdate('c'),
        ];

        $this->writeJsonFile($this->indexPath, $index);
    }

    private function topicFiles(): array
    {
        $files = glob($this->baseDir . DIRECTORY_SEPARATOR . '*.json');
        return is_array($files) ? $files : [];
    }

    private function getTopicPath(string $topic): string
    {
        return $this->baseDir . DIRECTORY_SEPARATOR . $this->safeTopicName($topic) . '.json';
    }

    private function safeTopicName(string $topic): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($topic));
        $safe = trim((string) $safe, '_');
        return $safe !== '' ? $safe : 'general_topic';
    }

    private function createFactId(): string
    {
        try {
            return 'fact_' . bin2hex(random_bytes(6));
        } catch (Throwable) {
            return 'fact_' . uniqid('', true);
        }
    }

    private function readJsonFile(string $file): mixed
    {
        $json = @file_get_contents($file);
        if ($json === false || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : [];
    }

    private function writeJsonFile(string $file, array $data): void
    {
        $directory = dirname($file);
        $this->ensureDirectory($directory);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = $file . '.tmp.' . bin2hex(random_bytes(4));

        $handle = @fopen($temporary, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create temporary memory file: ' . $temporary);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock memory file: ' . $temporary);
            }

            if (fwrite($handle, $json) === false || !fflush($handle)) {
                throw new RuntimeException('Unable to write memory file: ' . $file);
            }

            flock($handle, LOCK_UN);
            fclose($handle);

            if (!@rename($temporary, $file)) {
                @unlink($temporary);
                throw new RuntimeException('Unable to replace memory file: ' . $file);
            }
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
            @unlink($temporary);
            throw $exception;
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create memory directory: ' . $directory);
        }
    }
}
