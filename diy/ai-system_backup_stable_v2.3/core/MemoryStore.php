<?php

class MemoryStore
{
    private string $baseDir;

    // CONFIG: pruning limits (still active)
    private int $maxFactsPerTopic = 100;
    private int $maxTotalFacts    = 5000;

    public function __construct(string $path)
    {
        // $path is still passed as memory/memory.json, but we’ll treat its dir as base
        $this->baseDir = dirname($path) . '/topics';

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    private function getTopicPath(string $topic): string
    {
        // sanitize topic for filesystem
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $topic);
        return $this->baseDir . '/' . $safe . '.json';
    }

    public function getTopicFacts(string $topic): array
    {
        $file = $this->getTopicPath($topic);

        if (!file_exists($file)) {
            return [];
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    public function setTopicFacts(string $topic, array $facts): void
    {
        $facts = $this->pruneTopicFacts($facts);
        $file  = $this->getTopicPath($topic);

        file_put_contents($file, json_encode($facts, JSON_PRETTY_PRINT));

        $this->pruneGlobal();
    }

    public function mergeTopicFacts(string $topic, array $newFacts): array
    {
        $existing = $this->getTopicFacts($topic);
        $merged   = array_merge($existing, $newFacts);

        $merged = $this->pruneTopicFacts($merged);

        $file = $this->getTopicPath($topic);
        file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT));

        $this->pruneGlobal();

        return $merged;
    }

    private function pruneTopicFacts(array $facts): array
    {
        $count = count($facts);
        if ($count <= $this->maxFactsPerTopic) {
            return $facts;
        }

        usort($facts, function ($a, $b) {
            $ta = $a['created_at'] ?? '';
            $tb = $b['created_at'] ?? '';
            return strcmp($ta, $tb);
        });

        return array_slice($facts, $count - $this->maxFactsPerTopic);
    }

    private function pruneGlobal(): void
    {
        // collect all facts across topic files
        $all = [];
        $total = 0;

        foreach (glob($this->baseDir . '/*.json') as $file) {
            $topic = basename($file, '.json');
            $json  = file_get_contents($file);
            $facts = json_decode($json, true) ?: [];

            foreach ($facts as $idx => $fact) {
                $all[] = [
                    'topic'      => $topic,
                    'index'      => $idx,
                    'created_at' => $fact['created_at'] ?? '',
                ];
                $total++;
            }
        }

        if ($total <= $this->maxTotalFacts) {
            return;
        }

        usort($all, function ($a, $b) {
            return strcmp($a['created_at'], $b['created_at']);
        });

        $toRemove = $total - $this->maxTotalFacts;

        for ($i = 0; $i < $toRemove; $i++) {
            $entry = $all[$i];
            $topicFile = $this->getTopicPath($entry['topic']);

            if (!file_exists($topicFile)) {
                continue;
            }

            $json  = file_get_contents($topicFile);
            $facts = json_decode($json, true) ?: [];

            // remove oldest fact (we don’t rely on index here, just shift)
            array_shift($facts);

            if (empty($facts)) {
                unlink($topicFile);
            } else {
                file_put_contents($topicFile, json_encode($facts, JSON_PRETTY_PRINT));
            }
        }
    }
}