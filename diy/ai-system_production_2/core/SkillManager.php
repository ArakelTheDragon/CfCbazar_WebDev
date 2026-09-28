<?php

declare(strict_types=1);

if (!class_exists('SkillManager', false)) {

    class SkillManager
    {
        private array $cache = [];

        /**
         * Load a skill class from the /skills directory.
         *
         * The skill name must be a simple PHP class/file name.
         */
        public static function load(string $skillName): ?object
        {
            if (!self::isValidSkillName($skillName)) {
                return null;
            }

            $path = __DIR__ . '/../skills/' . $skillName . '.php';

            if (!is_file($path)) {
                return null;
            }

            require_once $path;

            if (!class_exists($skillName, false)) {
                return null;
            }

            $instance = new $skillName();

            return is_object($instance) ? $instance : null;
        }

        /**
         * Return a cached skill instance, loading it when necessary.
         *
         * @throws RuntimeException
         */
        public function get(string $skillName): object
        {
            if (!self::isValidSkillName($skillName)) {
                throw new RuntimeException(
                    'Invalid skill name: ' . $skillName
                );
            }

            if (isset($this->cache[$skillName])) {
                return $this->cache[$skillName];
            }

            $instance = self::load($skillName);

            if ($instance === null) {
                throw new RuntimeException(
                    'Skill not found or could not be loaded: ' . $skillName
                );
            }

            $this->cache[$skillName] = $instance;

            return $instance;
        }

        /**
         * Check whether a valid skill file and class exist.
         */
        public static function exists(string $skillName): bool
        {
            if (!self::isValidSkillName($skillName)) {
                return false;
            }

            $path = __DIR__ . '/../skills/' . $skillName . '.php';

            if (!is_file($path)) {
                return false;
            }

            if (!class_exists($skillName, false)) {
                require_once $path;
            }

            return class_exists($skillName, false);
        }

        /**
         * Clear all cached skill instances.
         *
         * Useful during development/testing.
         */
        public function clearCache(): void
        {
            $this->cache = [];
        }

        /**
         * Validate that a skill name is a simple class/file name.
         */
        private static function isValidSkillName(string $skillName): bool
        {
            return preg_match(
                '/^[A-Za-z_][A-Za-z0-9_]*$/',
                $skillName
            ) === 1;
        }
    }
}
