<?php

/**
 * PSR-4 Autoloader for FediversePlugin namespace and main plugin class
 *
 * Automatically loads classes from the src/ directory based on namespace.
 * Example: FediversePlugin\API\MastodonAPI -> src/API/MastodonAPI.php
 * Also handles the main FediversePlugin class in the root directory.
 */
spl_autoload_register(function ($class) {
    // Handle the main plugin class (no namespace)
    if ($class === 'FediversePlugin') {
        $file = __DIR__ . '/FediversePlugin.php';
        if (file_exists($file)) {
            require_once $file;
        }
        return;
    }

    // Handle classes in the FediversePlugin namespace
    $prefix = 'FediversePlugin\\';
    $base_dir = __DIR__ . '/src/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Not our namespace, let other autoloaders handle it
        return;
    }

    // Get the relative class name (remove namespace prefix)
    $relative_class = substr($class, $len);

    // Convert namespace separators to directory separators and add .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
    }
});

return [
    'id'          => 'fediverse:moderation',
    'version'     => '0.08',
    'name'        => 'Fediverse Moderation Plugin',
    'author'      => 'Crazypedia and ChatGPT',
    'description' => 'Federated abuse report integration for Mastodon and Misskey. (Lemmy support coming soon)',
    'plugin'      => 'FediversePlugin',
    'url'         => 'https://github.com/Crazypedia/osticket-fediverse-moderation'
];
