<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class FediverseModerationPluginConfig extends PluginConfig
{
    function getOptions()
    {
        $pluginUrl = admin_url('plugins.php?id=fediverse:moderation');

        return [
            'manage_instances_link' => new FreeTextField([
                'label' => '🌐 Manage Instances',
                'hint' => 'Configure Fediverse instances using OAuth authentication. <br><br><a href="' . __DIR__ . '/admin/instances.php" class="btn btn-primary" style="background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">➕ Manage Instances</a>',
                'configuration' => ['html' => true],
            ]),
            'debug_mode' => new BooleanField([
                'label' => '🐛 Debug Mode',
                'hint' => 'Enable verbose debug logging for troubleshooting. Can also be enabled via FEDIVERSE_DEBUG=1 environment variable.',
                'default' => false,
            ]),
            'webhook_info' => new FreeTextField([
                'label' => '🔗 Webhook URL',
                'hint' => 'Configure your Fediverse instance to send abuse reports to this URL:<br><br><code style="background:#f8f9fa;padding:10px;display:block;border-radius:4px;font-family:monospace;">' . htmlspecialchars(rtrim(self::getOsTicketUrl(), '/') . '/include/plugins/fediverse-moderation/web/report_webhook.php') . '</code><br><br>Set header: <code>X-Fediverse-Domain: your.instance.domain</code>',
                'configuration' => ['html' => true],
            ]),
        ];
    }

    function pre_save($config, &$errors)
    {
        // Set debug mode constant if enabled
        if (isset($config['debug_mode']) && $config['debug_mode']) {
            if (!defined('FEDIVERSE_DEBUG')) {
                define('FEDIVERSE_DEBUG', true);
            }
        }

        // Register custom form and fields for moderation actions on tickets
        $form = DynamicForm::objects()->filter(['type' => 'T', 'title' => 'Fediverse Moderation'])->first();

        if (!$form) {
            $form = DynamicForm::create(['type' => 'T', 'title' => 'Fediverse Moderation']);
        }

        $fields = [
            'fediverse_suspend_account' => [
                'label' => 'Suspend account',
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'hint' => 'Suspend the reported account on the remote server.'
            ],
            'fediverse_block_domain' => [
                'label' => 'Block domain',
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'hint' => 'Block the domain the reported account belongs to.'
            ],
            'fediverse_limit_account' => [
                'label' => 'Limit account',
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'hint' => 'Limit the reported account (e.g., shadowban or reduce visibility).'
            ],
            'fediverse_flag_account_media_sensitive' => [
                'label' => 'Flag account media as sensitive',
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'hint' => 'Automatically mark all media from this account as sensitive.'
            ],
            'fediverse_flag_server_media_sensitive' => [
                'label' => 'Flag server media as sensitive',
                'type' => 'boolean',
                'required' => false,
                'default' => false,
                'hint' => 'Automatically mark all media from this server as sensitive.'
            ]
        ];

        foreach ($fields as $name => $props) {
            if (!$form->fields->filter(['name' => $name])->exists()) {
                $form->addField(array_merge(['name' => $name], $props));
            }
        }

        $form->save();
        return parent::pre_save($config, $errors);
    }

    /**
     * Get OSTicket base URL
     */
    private static function getOsTicketUrl(): string
    {
        if (defined('ROOT_URL')) {
            return ROOT_URL;
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}";
    }
}
