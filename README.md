# osTicket Fediverse Moderation Plugin

This plugin adds integration between your osTicket installation and federated social platforms like **Mastodon** and **Misskey/Sharkey**. _(Lemmy support is under development and coming soon!)_

---

## ✅ Features

- 📩 Import abuse reports via webhook or polling
- 🧾 Create tickets from reports, including post/account info
- 🔄 Sync moderation comments between osTicket and fediverse
- 🔒 Perform remote moderation actions (suspend, block, etc.)
- 📋 UI for agents to select moderation actions on ticket closure
- 🗂️ Logs all actions for auditing and trust

---

## 📁 Plugin Structure

```
osticket-fediverse-moderation/
├── migrations/                  # DB schema creation scripts
├── src/                         # Core plugin logic
│   ├── API/                     # Platform-specific APIs
│   ├── InstanceManager.php
│   ├── ServerProber.php
│   ├── TicketMapper.php
│   ├── ReportIngestor.php
│   ├── ModerationSync.php
│   ├── Logger.php
│   └── PollingHandler.php
├── web/
│   └── report_webhook.php       # Endpoint to receive abuse reports
├── config.php                   # Plugin configuration and field setup
├── test/                        # Sample reports for testing
│   ├── sample_report.json
│   └── sample_lemmy_report.json
└── README.md
```

---

## ⚙️ Installation Instructions

### Quick Install (Recommended)

1. **Copy plugin files** to your osTicket installation:
   ```bash
   cp -r osticket-fediverse-moderation /path/to/osticket/include/plugins/
   ```

2. **Activate the plugin** in osTicket Admin Panel:
   - Navigate to: **Admin → Manage → Plugins**
   - Find "Fediverse Moderation Plugin"
   - Click **Enable/Activate**
   - Database tables will be created automatically

3. **Verify installation**:
   - Check that 3 new tables exist:
     - `plugin_fediverse_reports`
     - `plugin_fediverse_instances`
     - `plugin_fediverse_moderation_log`

### Manual Database Installation (Optional)

If automatic installation fails, run the SQL script manually:
```bash
mysql -u username -p osticket_db < install.sql
```

### What Gets Installed

The plugin creates 3 database tables:

- **plugin_fediverse_reports** - Stores incoming abuse reports
- **plugin_fediverse_instances** - Configured fediverse instances (domains, tokens, platform type)
- **plugin_fediverse_moderation_log** - Audit trail of all moderation actions

### Configuration

After installation, abuse reports will be processed via:
- `report_webhook.php` for servers that push reports
- `PollingHandler` (via cron) for polling-based ingestion

---

## 🌐 Webhook Setup

### Webhook URL

```
https://<your-domain>/include/plugins/osticket-fediverse-moderation/web/report_webhook.php
```

### Mastodon

Mastodon does **not** have native push support for reports.
- You can write a script to **poll `/api/v1/admin/reports`** and POST to the webhook.
- Example CLI:
```bash
curl -H "Authorization: Bearer <token>" https://instance/api/v1/admin/reports
```

### Misskey / Sharkey

- May federate `abuse.report` objects
- Can use `abuse.report` activities to push reports
- Ensure header: `X-Fediverse-Domain: yourdomain.com`

### Lemmy

- Poll `/api/v3/modlog` regularly
- Reports come from `ReportView` objects
- Actions supported: `resolve_report`, `ban_user`, `mod/add_note`

---

## 🧯 Troubleshooting

| Problem | Solution |
|--------|----------|
| Ticket not created | Check for duplicates (same `report_key`) |
| API errors | Check instance token, domain, and API compatibility |
| Polling not working | Ensure `PollingHandler::pollAllInstances()` is invoked on schedule |
| Webhook not triggered | Validate payload format and headers |

---

## 📑 Git Versioning

This plugin is designed to support Git versioning:

- Includes proper folder structure
- Migration history tracked via `migrations/`
- Comments preserved across files
- `README.md` and `.gitignore` recommended

---

## 🧪 Testing Locally

Test ingestion using:

```bash
curl -X POST https://<your-domain>/include/plugins/osticket-fediverse-moderation/web/report_webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Fediverse-Domain: example.org" \
  -d @test/sample_report.json
```

---

## 🔐 Audit Logging

All moderation actions are logged to `plugin_fediverse_moderation_log`, including:
- Action taken
- Result (success/fail)
- Linked ticket ID
- Domain and report ID
- Timestamp

---

## 📌 Future Roadmap

- Admin panel for managing instances
- Bulk moderation actions
- Support for more platforms (e.g., Kbin, Pleroma)
- Viewable audit log in osTicket UI
