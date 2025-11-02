# Ticket Creation from Fediverse Reports

## Overview

The `createTicketFromReport()` method in `ReportIngestor.php` automatically creates osTicket tickets from incoming Fediverse abuse reports.

## Process Flow

1. **Report received** via webhook (`web/report_webhook.php`)
2. **Validation** - Check required fields exist
3. **Deduplication** - Check if report already processed
4. **Storage** - Save raw report to `plugin_fediverse_reports` table
5. **Ticket Creation** - Create formatted osTicket ticket
6. **Linking** - Update database with ticket_id
7. **Logging** - Record action in moderation log

## Ticket Format

### Subject
```
Fediverse Abuse Report: username@instance.org
```

### Body Structure
```
=== FEDIVERSE ABUSE REPORT ===

Report ID: abc123
Source Instance: mastodon.social
Reported At: 2025-08-12T14:30:00Z
Category: violation

--- Reported Account ---
Username: badactor@instance.org
Display Name: Bad Actor Name
Profile URL: https://instance.org/@badactor

--- Report Reason ---
This account is repeatedly posting harmful misinformation.

--- Reported Posts (2) ---

Post #1 (ID: post001)
Posted: 2025-08-12T13:00:00Z
Content:
This is clearly untrue information designed to harm.

Post #2 (ID: post002)
Posted: 2025-08-12T13:15:00Z
Content:
Another post with misleading content.

--- Next Steps ---
1. Review the reported content and account
2. Determine appropriate moderation action
3. Close ticket to apply selected actions to remote server
```

## User Assignment

Tickets are created under a generic reporter user:
- **Email:** `fediverse-reports@{domain}`
- **Name:** `Fediverse Reporter ({domain})`

This keeps all reports from the same instance grouped under one user account.

## Database Updates

After ticket creation:

1. `plugin_fediverse_reports.ticket_id` is updated with the new ticket ID
2. `plugin_fediverse_moderation_log` records the ticket creation event

## Error Handling

The method handles errors gracefully:
- Returns `null` if user creation fails
- Returns `null` if ticket creation fails
- Logs all errors to PHP error log
- Database remains consistent (report stored even if ticket fails)

## Content Processing

- HTML tags are stripped from post content for cleaner display
- HTML entities are decoded (e.g., `&amp;` → `&`)
- Whitespace is normalized
- Multiple reported posts are numbered for clarity

## Example Usage

```php
// In webhook handler
$reportData = json_decode($input, true);
$domain = $_SERVER['HTTP_X_FEDIVERSE_DOMAIN'] ?? 'unknown';

$ticket = ReportIngestor::process($reportData, $domain);

if ($ticket) {
    echo "Ticket #{$ticket->getId()} created successfully\n";
} else {
    echo "Failed to create ticket (see error log)\n";
}
```

## Testing

Use the sample report file to test:

```bash
curl -X POST http://localhost/osticket/include/plugins/fediverse-moderation/web/report_webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Fediverse-Domain: mastodon.social" \
  -d @test/sample_report.json
```

## Notes

- Post content HTML is stripped for security and readability
- Multiple posts are included in a single ticket
- Reporter identity preserved from original report
- Ticket source is set to 'API' for tracking
- Instance domain stored in IP field for reference
