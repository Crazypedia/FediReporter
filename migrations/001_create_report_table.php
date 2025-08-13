<?php

return [
    'up' => function (Migration $m) {
        $m->createTable('plugin_fediverse_reports', [
            'columns' => [
                'id' => ['type' => 'serial', 'primary' => true],
                'report_key' => ['type' => 'varchar', 'length' => 255, 'unique' => true],
                'ticket_id' => ['type' => 'integer', 'null' => false],
                'raw_data' => ['type' => 'text', 'null' => true],
                'created' => ['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP'],
            ],
        ]);
    },

    'down' => function (Migration $m) {
        $m->dropTable('plugin_fediverse_reports');
    }
];
