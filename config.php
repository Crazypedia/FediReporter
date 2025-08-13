<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class FediverseModerationPluginConfig extends PluginConfig
{
    function getOptions()
    {
        return [];
    }

    function pre_save($config, &$errors)
    {
        // Register custom form and fields for moderation actions
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
}
