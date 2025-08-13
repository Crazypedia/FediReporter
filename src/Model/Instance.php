<?php

namespace FediversePlugin\Model;

use Db;

/**
 * Instance is a DB model wrapper for the plugin_fediverse_instances table.
 */
class Instance
{
    /**
     * Get all enabled instances.
     *
     * @return array
     */
    public static function getEnabled(): array
    {
        $sql = "SELECT * FROM plugin_fediverse_instances WHERE enabled = 1";
        return Db::connection()->query($sql)->fetchAll();
    }

    /**
     * Get a single instance by domain.
     *
     * @param string $domain
     * @return array|null
     */
    public static function getByDomain(string $domain): ?array
    {
        $sql = "SELECT * FROM plugin_fediverse_instances WHERE domain = ?";
        $row = Db::connection()->query($sql, [$domain])->fetch();
        return $row ?: null;
    }

    /**
     * Save or update an instance entry.
     *
     * @param array $data
     * @return void
     */
    public static function save(array $data): void
    {
        $existing = self::getByDomain($data['domain']);

        if ($existing) {
            $sql = "UPDATE plugin_fediverse_instances SET
                token = ?, platform = ?, version = ?, enabled = ?, metadata = ?, updated = NOW()
                WHERE domain = ?";
            Db::connection()->query($sql, [
                $data['token'],
                $data['platform'],
                $data['version'],
                $data['enabled'] ?? 1,
                json_encode($data['metadata'] ?? []),
                $data['domain']
            ]);
        } else {
            $sql = "INSERT INTO plugin_fediverse_instances
                (domain, token, platform, version, enabled, metadata, created)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
            Db::connection()->query($sql, [
                $data['domain'],
                $data['token'],
                $data['platform'],
                $data['version'],
                $data['enabled'] ?? 1,
                json_encode($data['metadata'] ?? [])
            ]);
        }
    }
}
