<?php

require_once __DIR__ . '/FediversePlugin.php';

return [
    'id'          => 'fediverse:moderation',
    'version'     => '0.08',
    'name'        => 'Fediverse Moderation Plugin',
    'author'      => 'Crazypedia and ChatGPT',
    'description' => 'Federated abuse report integration for Mastodon, Misskey, and Lemmy.',
    'plugin'      => 'FediversePlugin\\FediversePlugin',
    'url'         => 'https://github.com/Crazypedia/osticket-fediverse-moderation'
];
