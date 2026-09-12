<?php

class PluginLoader {

    public static function loadPlugins(LocalAIEngine $ai) {

        $pluginDir = __DIR__;

        foreach (scandir($pluginDir) as $folder) {

            if ($folder === '.' || $folder === '..') continue;

            $pluginPath = $pluginDir . '/' . $folder . '/plugin.php';

            if (file_exists($pluginPath)) {
                require_once $pluginPath;

                if (class_exists($folder . "Plugin")) {
                    $pluginClass = $folder . "Plugin";
                    $plugin = new $pluginClass();
                    $plugin->register($ai);
                }
            }
        }
    }
}

