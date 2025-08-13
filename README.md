# osTicket Fediverse Moderation Plugin

This plugin allows an osTicket installation to integrate with the moderation APIs of federated social media servers such as **Mastodon** and **Misskey/Sharkey**. It provides an interface for importing abuse reports, syncing moderation comments, and performing moderation actions from within osTicket.

---

## 🔧 Features

- ✅ Import abuse reports from Mastodon/Sharkey via webhook or polling
- ✅ Create osTicket tickets from abuse reports
- ✅ Sync moderation comments (push/pull)
- ✅ Perform remote actions (suspend/block/limit/etc.)
- ✅ Agent-controlled moderation options via UI
- ✅ Audit logging of all moderation actions
- ✅ Extensible modular API client for multiple platforms

---

## 📁 Plugin Structure

```
osticket-fediverse-moderation/
├── migrations/                  # DB schema setup files
│   └── 003_create_moderation_log_table.php
├── src/                         # Main PHP logic and classes
│   ├── MastodonAPI.php
│   ├── MisskeyAPI.php
│   ├── InstanceManager.php
│   ├── ModerationSync.php
│   ├── Logger.php
│   ├── ReportIngestor.php
│   └── FediversePlugin.php
├── web/
│   └── report_webhook.php       # Webhook endpoint for incoming reports
├── config.php                   # Plugin configuration and field registration
└── README.md                    # This file
```

---

## 🚀 Installation

1. Place the `osticket-fediverse-moderation/` folder in your osTicket plugin directory.
2. Run the plugin migrations via the admin panel or CLI.
3. Activate the plugin under **Admin → Plugins**.
4. Ensure permissions are set to allow ticket creation and note posting.
5. Configure the webhook endpoint on your federated server or polling script.

---

## 🌐 Webhook Setup Guide

### Your Webhook URL:

```
https://<your-osticket-domain>/include/plugins/osticket-fediverse-moderation/web/report_webhook.php
```

---

### 🦣 Mastodon Admins

Mastodon does **not** natively support report webhooks. Use one of these options:

**Option 1:** Pull via Mastodon Admin API
```bash
curl -H "Authorization: Bearer <admin_token>" https://<your-instance>/api/v1/admin/reports
```
Then forward these to the webhook using a script.

**Option 2:** Build a relay that polls and pushes reports to the plugin webhook.

---

### 🦈 Sharkey / Misskey Admins

Sharkey supports `abuse.report` activities that can be federated or relayed.

1. Configure outbound hooks to POST to the plugin's webhook URL.
2. Include header: `X-Fediverse-Domain: <your-server>`

If using Misskey directly, consider modifying or extending the federation logic to POST abuse reports to the webhook.

---

## 🛠️ Agent Moderation UI

When viewing a fediverse report ticket, agents will see moderation options like:

- [ ] Suspend account
- [ ] Block domain
- [ ] Limit account visibility
- [ ] Mark account/server media as sensitive

These options are attached via a dynamic form and respected on ticket closure.

---

## 📝 Audit Logging

All moderation actions (success/failure) are logged to:

```
plugin_fediverse_moderation_log
```

Log includes:
- Ticket ID
- Remote domain
- Report ID
- Action name
- Status and message
- Timestamp

---

## 🧪 Testing

You can test the ingestion by sending a mock report:

```bash
curl -X POST https://<your-osticket>/include/plugins/osticket-fediverse-moderation/web/report_webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Fediverse-Domain: example.org" \
  -d @sample_report.json
```

---

## 🧯 Troubleshooting

- **Webhook not working?**
  - Check web server logs
  - Ensure POST requests are reaching the endpoint
  - Validate JSON structure of incoming report

- **Ticket not created?**
  - May be duplicate report (report_key is unique)
  - Check plugin logs for error messages

- **Moderation actions not applying?**
  - Ensure correct platform (Mastodon vs Misskey)
  - Ensure tokens and API access are valid
  - Verify ticket fields are set before closure

---

## 🔮 Future Plans

- Admin panel for instance management
- UI for audit log inspection
- Multi-platform support expansion
- Federation health scoring

---

## 🤝 Contributing

This plugin is modular and documented in-code to assist with community contributions. PRs welcome!

