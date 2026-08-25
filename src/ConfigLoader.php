<?php
declare(strict_types=1);

namespace BuildStatus;

use RuntimeException;

final class ConfigLoader
{
    public static function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Configuration file not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Unable to read configuration file: {$path}");
        }

        try {
            $config = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid JSON in {$path}: {$e->getMessage()}", 0, $e);
        }

        if (!is_array($config)) {
            throw new RuntimeException("Configuration root must be a JSON object.");
        }

        if (empty($config['timezone']) || !is_string($config['timezone'])) {
            throw new RuntimeException("Configuration must define a timezone.");
        }

        if (empty($config['groups']) || !is_array($config['groups'])) {
            throw new RuntimeException("Configuration must define at least one group.");
        }

        return $config;
    }
}
