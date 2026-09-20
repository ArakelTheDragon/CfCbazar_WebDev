<?php

class MemoryStore
{
    private string $path;
    private array $data = [];

    public function __construct(string $path)
    {
        $this->path = $path;

        if (file_exists($path)) {
            $json = file_get_contents($path);
            $this->data = json_decode($json, true) ?: [];
        } else {
            $this->data = [];
        }
    }

    public function getTopicFacts(string $topic): array
    {
        return $this->data['topics'][$topic] ?? [];
    }

    public function setTopicFacts(string $topic, array $facts): void
    {
        if (!isset($this->data['topics'])) {
            $this->data['topics'] = [];
        }

        $this->data['topics'][$topic] = $facts;
        $this->save();
    }

    public function mergeTopicFacts(string $topic, array $newFacts): array
    {
        $existing = $this->getTopicFacts($topic);
        $merged = array_merge($existing, $newFacts);
        $this->setTopicFacts($topic, $merged);
        return $merged;
    }

    private function save(): void
    {
        if (!is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0777, true);
        }

        file_put_contents($this->path, json_encode($this->data, JSON_PRETTY_PRINT));
    }
}