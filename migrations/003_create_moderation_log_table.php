<?php

return [
    'up' => function (Migration $m) {
        $m->createTable('plugin_fediverse_moderation_log', [
            'columns' => [
                'id' => ['type' => 'serial', 'primary' => true],
                'ticket_id' => ['type' => 'integer', 'null' => false],
                'domain' => ['type' => 'varchar', 'length' => 255, 'null' => false],
                'report_id' => ['type' => 'varchar', 'length' => 255, 'null' => false],
                'action' => ['type' => 'varchar', 'length' => 100, 'null' => false],
                'status' => ['type' => 'varchar', 'length' => 50, 'null' => false],
                'message' => ['type' => 'text', 'null' => true],
                'created' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP']
            ]
        ]);
    },

    'down' => function (Migration $m) {
        $m->dropTable('plugin_fediverse_moderation_log');
    }
];
