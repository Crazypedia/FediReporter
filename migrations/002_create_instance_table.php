<?php

return [
    'up' => function (Migration $m) {
        $m->createTable('plugin_fediverse_instances', [
            'columns' => [
                'id' => ['type' => 'serial', 'primary' => true],
                'domain' => ['type' => 'varchar', 'length' => 255, 'unique' => true],
                'token' => ['type' => 'text', 'null' => false],
                'platform' => ['type' => 'varchar', 'length' => 50],
                'version' => ['type' => 'varchar', 'length' => 50, 'null' => true],
                'enabled' => ['type' => 'boolean', 'default' => true],
                'last_polled' => ['type' => 'datetime', 'null' => true],
                'metadata' => ['type' => 'text', 'null' => true],
                'created' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
                'updated' => ['type' => 'datetime', 'null' => true],
            ],
        ]);
    },

    'down' => function (Migration $m) {
        $m->dropTable('plugin_fediverse_instances');
    }
];
