<?php
// ============================================================================
// CfCbazar - Local AI Plugin Loader Module
// File: /ai2/plugins/PluginLoader.php
// ============================================================================

if (!class_exists('PluginLoader')) {
    class PluginLoader {

        /**
         * Dynamically scans and loads all plugins and skills into the LocalAIEngine.
         * Supports subfolder plugins, standalone PHP files, dependency injection, and error catching.
         *
         * @param mixed $ai     LocalAIEngine instance
         * @param array $config Optional configuration parameters (e.g. OpenRouter API keys, memory paths)
         * @return array        List of successfully registered plugin class names
         */
        public static function loadPlugins($ai, array $config = []): array {
            $pluginDir = __DIR__;
            $loadedPlugins = [];

            if (!is_dir($pluginDir)) {
                return $loadedPlugins;
            }

            $entries = scandir($pluginDir);
            if ($entries === false) {
                return $loadedPlugins;
            }

            foreach ($entries as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $fullPath = $pluginDir . '/' . $item;

                /* ---------------------------------------------------------
                   1. Subdirectory Plugins (e.g., /plugins/Search/plugin.php)
                --------------------------------------------------------- */
                if (is_dir($fullPath)) {
                    $pluginFile = $fullPath . '/plugin.php';

                    if (file_exists($pluginFile)) {
                        try {
                            require_once $pluginFile;

                            // Support flexible naming conventions (FolderPlugin, Folder, or capitalized)
                            $candidateClasses = [
                                $item . "Plugin",
                                ucfirst($item) . "Plugin",
                                $item,
                                ucfirst($item)
                            ];

                            foreach ($candidateClasses as $pluginClass) {
                                if (class_exists($pluginClass)) {
                                    $plugin = self::instantiatePlugin($pluginClass, $config);

                                    if ($plugin && method_exists($plugin, 'register')) {
                                        $plugin->register($ai);
                                        $loadedPlugins[] = $pluginClass;
                                    }
                                    break;
                                }
                            }
                        } catch (Throwable $e) {
                            error_log("PluginLoader Folder Error [{$item}]: " . $e->getMessage());
                        }
                    }
                }
                /* ---------------------------------------------------------
                   2. Direct File Plugins (e.g., /plugins/CustomPlugin.php)
                --------------------------------------------------------- */
                elseif (is_file($fullPath) && pathinfo($fullPath, PATHINFO_EXTENSION) === 'php') {
                    if (basename($fullPath) === 'PluginLoader.php') {
                        continue;
                    }

                    try {
                        require_once $fullPath;
                        $className = pathinfo($fullPath, PATHINFO_FILENAME);

                        if (class_exists($className)) {
                            $plugin = self::instantiatePlugin($className, $config);

                            if ($plugin && method_exists($plugin, 'register')) {
                                $plugin->register($ai);
                                $loadedPlugins[] = $className;
                            }
                        }
                    } catch (Throwable $e) {
                        error_log("PluginLoader File Error [{$item}]: " . $e->getMessage());
                    }
                }
            }

            return $loadedPlugins;
        }

        /**
         * Instantiates a plugin class, injecting optional $config if required by the constructor.
         */
        private static function instantiatePlugin(string $className, array $config = []): ?object {
            try {
                $reflector = new ReflectionClass($className);

                if (!$reflector->isInstantiable()) {
                    return null;
                }

                $constructor = $reflector->getConstructor();
                if ($constructor && $constructor->getNumberOfParameters() > 0) {
                    return $reflector->newInstance($config);
                }

                return new $className();
            } catch (Throwable $e) {
                error_log("PluginLoader Instantiation Error [{$className}]: " . $e->getMessage());
                return null;
            }
        }
    }
}
