<?php
if (!defined('INCLUDE_DIR')) die('No direct access.');

require_once INCLUDE_DIR.'class.plugin.php';
require_once INCLUDE_DIR.'class.forms.php';

class MastoReportsConfig extends PluginConfig {
    function getOptions() {
        return [
            'sec1' => new SectionBreakField(['label' => 'Webhook Settings']),
            'webhook_secret' => new PasswordField([
                'label' => 'Webhook Secret (shared)',
                'hint' => 'Incoming requests must include this secret (Authorization: Bearer <secret> or X-Webhook-Token header).',
                'required' => true,
            ]),
            'synthetic_email_domain' => new TextboxField([
                'label' => 'Synthetic Email Domain',
                'hint' => 'Used to fabricate requester emails when absent',
                'default' => 'reports.local',
            ]),
            'sec2' => new SectionBreakField(['label' => 'Outgoing API Credentials']),
            'mastodon_access_token' => new PasswordField([
                'label' => 'Mastodon Admin Access Token',
                'hint' => 'Required to send moderation notes to Mastodon instances.'
            ]),
            'misskey_access_token' => new PasswordField([
                'label' => 'Misskey/Sharkey Admin Token',
                'hint' => 'Required to send moderation notes to Misskey/Sharkey instances.'
            ]),
            'sec3' => new SectionBreakField(['label' => 'Ticket Mapping (Optional)']),
            'default_help_topic_id' => new ChoiceField([
                'label' => 'Help Topic',
                'choices' => self::getHelpTopics(),
                'default' => '',
            ]),
            'default_department_id' => new ChoiceField([
                'label' => 'Department',
                'choices' => self::getDepartments(),
                'default' => '',
            ]),
            'default_priority_id' => new ChoiceField([
                'label' => 'Priority',
                'choices' => self::getPriorities(),
                'default' => '',
            ]),
        ];
    }

    private static function getHelpTopics() {
        $choices = ['' => '— Default —'];
        foreach (Topic::objects() as $t) $choices[$t->getId()] = $t->getFullName();
        return $choices;
    }
    private static function getDepartments() {
        $choices = ['' => '— Default —'];
        foreach (Dept::getDepartments() as $d) $choices[$d->getId()] = $d->getFullName();
        return $choices;
    }
    private static function getPriorities() {
        $choices = ['' => '— Default —'];
        foreach (Priority::all() as $p) $choices[$p->getId()] = $p->getDesc();
        return $choices;
    }
}
