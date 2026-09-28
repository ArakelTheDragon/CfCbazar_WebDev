<?php

class SkillManager
{
    private array $cache = [];

    public static function load(string $skillName)
    {
        $path = __DIR__ . '/../skills/' . $skillName . '.php';

        if (file_exists($path)) {
            require_once $path;

            if (class_exists($skillName)) {
                return new $skillName();
            }
        }

        return null;
    }

    public function get(string $skillName)
    {
        if (isset($this->cache[$skillName])) {
            return $this->cache[$skillName];
        }

        $instance = self::load($skillName);

        if ($instance === null) {
            throw new Exception("Skill not found: " . $skillName);
        }

        $this->cache[$skillName] = $instance;

        return $instance;
    }

    public static function exists(string $skillName): bool
    {
        $path = __DIR__ . '/../skills/' . $skillName . '.php';
        return file_exists($path);
    }
}