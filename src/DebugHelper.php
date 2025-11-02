<?php

namespace FediversePlugin;

/**
 * DebugHelper provides centralized logging and debugging capabilities
 * for the Fediverse Moderation Plugin.
 */
class DebugHelper
{
    /**
     * Enable/disable debug mode via environment variable or constant
     *
     * Set FEDIVERSE_DEBUG=1 in environment or define('FEDIVERSE_DEBUG', true)
     */
    private static function isDebugMode(): bool
    {
        // Check environment variable
        if (getenv('FEDIVERSE_DEBUG')) {
            return true;
        }

        // Check PHP constant
        if (defined('FEDIVERSE_DEBUG') && FEDIVERSE_DEBUG) {
            return true;
        }

        return false;
    }

    /**
     * Log an error message with context
     *
     * @param string $component The component/class name
     * @param string $message The error message
     * @param array $context Additional context data
     */
    public static function logError(string $component, string $message, array $context = []): void
    {
        $logMessage = "[FediversePlugin:{$component}] ERROR: {$message}";

        if (!empty($context)) {
            $logMessage .= " | Context: " . json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Log a warning message with context
     *
     * @param string $component The component/class name
     * @param string $message The warning message
     * @param array $context Additional context data
     */
    public static function logWarning(string $component, string $message, array $context = []): void
    {
        $logMessage = "[FediversePlugin:{$component}] WARNING: {$message}";

        if (!empty($context)) {
            $logMessage .= " | Context: " . json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Log a debug message (only if debug mode is enabled)
     *
     * @param string $component The component/class name
     * @param string $message The debug message
     * @param array $context Additional context data
     */
    public static function logDebug(string $component, string $message, array $context = []): void
    {
        if (!self::isDebugMode()) {
            return;
        }

        $logMessage = "[FediversePlugin:{$component}] DEBUG: {$message}";

        if (!empty($context)) {
            $logMessage .= " | Context: " . json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Log an info message (only if debug mode is enabled)
     *
     * @param string $component The component/class name
     * @param string $message The info message
     * @param array $context Additional context data
     */
    public static function logInfo(string $component, string $message, array $context = []): void
    {
        if (!self::isDebugMode()) {
            return;
        }

        $logMessage = "[FediversePlugin:{$component}] INFO: {$message}";

        if (!empty($context)) {
            $logMessage .= " | Context: " . json_encode($context);
        }

        error_log($logMessage);
    }

    /**
     * Log a successful operation (only if debug mode is enabled)
     *
     * @param string $component The component/class name
     * @param string $message The success message
     * @param array $context Additional context data
     */
    public static function logSuccess(string $component, string $message, array $context = []): void
    {
        if (!self::isDebugMode()) {
            return;
        }

        $logMessage = "[FediversePlugin:{$component}] SUCCESS: {$message}";

        if (!empty($context)) {
            $logMessage .= " | Context: " . json_encode($context);
        }

        error_log($logMessage);
    }
}
