# Masto/Misskey Reports Importer (osTicket plugin)

Imports user reports from **Mastodon**, **Misskey**, and **Sharkey** via **webhooks** into osTicket as tickets,
and syncs **staff notes** back to the originating server as moderation notes.

## Install

1. Copy the `masto_reports_plugin` folder to your osTicket `include/plugins/` directory.
2. In osTicket Admin → **Manage → Plugins**, click **Add New Plugin** and enable **Masto/Misskey Reports Importer**.
3. Open plugin **Settings** and set:
   - **Webhook Secret (shared)** – this must match what your server sends.
   - (Optional) Help Topic / Department / Priority
   - **Synthetic Email Domain** – used for reporter emails when absent.
   - **Mastodon Admin Access Token** – used to post moderation notes back to Mastodon.
   - **Misskey/Sharkey Admin Token** – used to post moderation notes back to Misskey/Sharkey.

## Webhook endpoint

Use this URL (adjust to your path):

```
https://<your-domain>/include/plugins/masto_reports_plugin/webhook.php
```

**Headers required:**

- `Authorization: Bearer <WEBHOOK_SECRET>` **or** `X-Webhook-Token: <WEBHOOK_SECRET>`
- `X-Origin-Instance: https://your.instance.tld`

**Body:** JSON payload of the report. The plugin auto-detects platform by shape.

### Supported payload shapes (examples)

**Mastodon-like:**
```json
{
  "id": "12345",
  "created_at": "2025-08-10T12:34:56Z",
  "category": "spam",
  "comment": "Abusive behavior",
  "account": { "id": "900", "acct": "reporter@example.org", "username": "reporter" },
  "target_account": { "id": "901", "acct": "abuser@example.org", "username": "abuser" },
  "status_ids": ["111","112"]
}
```

**Misskey/Sharkey-like:**
```json
{
  "id": "abc123",
  "createdAt": "2025-08-10T12:34:56Z",
  "comment": "Harassment",
  "reporter": { "id": "u1", "username": "reporter" },
  "targetUser": { "id": "u2", "username": "abuser" }
}
```

## How it works

- On webhook call, the plugin:
  1. Verifies secret
  2. Auto-detects platform (Mastodon or Misskey/Sharkey) by payload keys
  3. Normalizes and creates a ticket
  4. Stores metadata on the ticket (platform, instance, target account id)
  5. Deduplicates by `(platform, instance, report_id)` in table `ost_masto_reports_imports`

- When a staff **note/reply** is added to that ticket, the plugin:
  - Posts a **moderation note** to the origin server with text: `[Agent Name]: {comment}`
  - Truncates to safe length (480 chars Mastodon, 950 Misskey/Sharkey)

## Security
- Webhook secret must match the configured value.
- You can rotate the secret anytime in plugin settings.
- Consider limiting access to the endpoint at the network level if feasible.

## Uninstall
- Disable the plugin in Admin → Manage → Plugins.
- Remove the `include/plugins/masto_reports_plugin` directory.
- Optional: drop the table `ost_masto_reports_imports`.

## License
MIT
