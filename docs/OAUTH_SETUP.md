# OAuth Authentication Setup Guide

## Overview

The Fediverse Moderation Plugin uses **OAuth 2.0** to securely authenticate with Fediverse instances. This ensures that:
- Only authorized admins/moderators can connect instances
- Access tokens are obtained securely
- Permissions are properly verified
- No manual token handling required

## Supported Platforms

### Mastodon
- **OAuth 2.0** standard flow
- **Required Scopes:** `admin:read`, `admin:write`
- **Required Role:** Admin or Moderator

### Misskey/Sharkey
- **MiAuth** authentication (Misskey's OAuth variant)
- **Required Permissions:** `read:admin:abuse-user-reports`, `write:admin:abuse-user-reports`
- **Required Role:** Admin or Moderator

## Adding an Instance (Admin)

### Step 1: Access Instance Management

1. Log into OSTicket as an administrator
2. Navigate to: **Admin Panel → Manage → Plugins**
3. Find "Fediverse Moderation Plugin"
4. Click **"Manage Instances"** button

### Step 2: Initiate OAuth Flow

1. On the Instance Management page, click **"➕ Add New Instance"**
2. Enter the instance domain (without https://)
   - Example: `mastodon.social`
   - Example: `misskey.io`
3. Click **"🔗 Connect via OAuth"**

### Step 3: Authorize on Fediverse Instance

You will be redirected to the Fediverse instance. You must:

**For Mastodon:**
1. Log in with your **admin** or **moderator** account
2. Review the requested permissions:
   - `admin:read` - Read admin data
   - `admin:write` - Perform admin actions
3. Click **"Authorize"**

**For Misskey:**
1. Log in with your **admin** or **moderator** account
2. Review the requested permissions:
   - Read abuse reports
   - Write to abuse reports
3. Click **"Accept"**

### Step 4: Return to OSTicket

After authorization:
- You'll be redirected back to OSTicket
- The plugin verifies your admin/moderator role
- The instance is saved with your access token
- You'll see a success message

## OAuth Flow Diagram

```
┌─────────────┐
│  OSTicket   │
│   Admin     │
└──────┬──────┘
       │ 1. Clicks "Add Instance"
       │ 2. Enters domain
       ▼
┌─────────────────────┐
│  OAuth              │
│  Handler            │
│ (Plugin)            │
└──────┬──────────────┘
       │ 3. Registers app with instance
       │ 4. Redirects to auth URL
       ▼
┌─────────────────────┐
│  Fediverse          │
│  Instance           │
│ (mastodon.social)   │
└──────┬──────────────┘
       │ 5. User logs in
       │ 6. Reviews permissions
       │ 7. Authorizes app
       ▼
┌─────────────────────┐
│  OAuth              │
│  Callback           │
└──────┬──────────────┘
       │ 8. Exchanges code for token
       │ 9. Verifies admin role
       │ 10. Saves instance
       ▼
┌─────────────────────┐
│  Instance           │
│  Configured!        │
└─────────────────────┘
```

## Permission Verification

The plugin automatically verifies that the authenticating user has admin or moderator permissions:

### Mastodon
Checks the `role` field in account verification:
```json
{
  "id": "12345",
  "username": "admin",
  "role": {
    "name": "admin"  // Must be "admin" or "moderator"
  }
}
```

### Misskey
Checks boolean flags:
```json
{
  "id": "abc123",
  "username": "admin",
  "isAdmin": true,      // Or isModerator: true
  "isModerator": false
}
```

**If verification fails**, you'll see an error message explaining that an admin/moderator account is required.

## Security Features

### 1. **Secure Token Storage**
- Access tokens are stored in the database
- Never exposed in URLs or logs
- Only used for API requests

### 2. **Admin-Only Access**
- Only OSTicket admins can add instances
- Only Fediverse admins/moderators can authorize

### 3. **Scoped Permissions**
- Mastodon: Only requests admin scopes
- Misskey: Only requests abuse report permissions
- No access to DMs, follows, or other data

### 4. **OAuth Standards**
- Uses industry-standard OAuth 2.0
- HTTPS required (enforced by Fediverse instances)
- Authorization codes expire quickly
- No password handling by plugin

## Managing Instances

### View Configured Instances

The instance management page shows:
- Domain name
- Platform type (Mastodon/Misskey)
- Platform version
- Current status (Enabled/Disabled)
- Who authorized it (username + role)
- When it was added

### Enable/Disable Instance

Click the **Enable/Disable** button to temporarily stop processing reports from an instance without deleting it.

### Delete Instance

Click the **Delete** button to permanently remove an instance. This will:
- Delete the instance record
- Remove the stored access token
- Stop processing reports from that instance

**⚠️ Warning:** Reports already received will not be deleted, but no new reports will be accepted.

## Troubleshooting

### "Authorization failed: No code received"

**Cause:** The OAuth callback didn't receive an authorization code

**Solutions:**
1. Ensure your OSTicket URL is correct and accessible
2. Check that the callback URL isn't being blocked by firewall
3. Verify the instance is reachable from your server
4. Try the process again

### "Account must have admin or moderator role"

**Cause:** The account used to authorize doesn't have sufficient permissions

**Solutions:**
1. Log out of the Fediverse instance
2. Log in with an admin or moderator account
3. Try authorization again

### "Failed to register app"

**Cause:** The instance couldn't be contacted or refused the app registration

**Solutions:**
1. Verify the domain is correct
2. Check that the instance is online
3. Ensure the instance allows app registrations
4. Check your server's outbound HTTPS connectivity

### OAuth Session Expired

**Cause:** You took too long to complete the authorization

**Solutions:**
1. Start the process again
2. Complete authorization promptly after redirecting

## Technical Details

### Callback URL Format

```
https://your-osticket.com/scp/plugins.php?id=fediverse:moderation&action=oauth_callback
```

This URL must be accessible from the internet for OAuth to work.

### Required Permissions

**Mastodon API:**
- `GET /api/v1/accounts/verify_credentials` - Verify user
- `GET /api/v1/admin/reports` - Fetch reports
- `POST /api/v1/admin/reports/:id/resolve` - Close reports
- `POST /api/v1/admin/accounts/:id/action` - Moderation actions

**Misskey API:**
- `POST /api/i` - Verify user
- `POST /api/admin/abuse/notes/list` - Fetch reports
- Various admin endpoints for moderation

### Token Refresh

**Mastodon:** Tokens do not expire by default
**Misskey:** Tokens do not expire

If a token becomes invalid, you'll need to re-authorize the instance.

## Best Practices

### 1. **Use Dedicated Accounts**
Consider creating a dedicated admin/moderator account for OSTicket integration rather than using personal accounts.

### 2. **Monitor Access**
Regularly review authorized instances in the management page and remove any that are no longer needed.

### 3. **Test First**
Add instances one at a time and verify they work before adding more.

### 4. **Enable Debug Mode**
When setting up OAuth for the first time, enable debug mode to see detailed logging:
```bash
export FEDIVERSE_DEBUG=1
```

### 5. **Secure Your OSTicket**
Ensure your OSTicket installation uses HTTPS and is properly secured, as it will store access tokens.

## FAQ

**Q: Can I manually add an instance without OAuth?**
A: Not recommended. OAuth ensures proper permission verification and secure token handling.

**Q: What happens if I delete an instance?**
A: The access token is removed and no new reports will be processed. Existing tickets remain.

**Q: Can I have multiple instances?**
A: Yes! Add as many instances as you need.

**Q: Do I need a separate account on each instance?**
A: Yes, you must authorize each instance separately with an admin/moderator account on that instance.

**Q: What if my instance uses a non-standard port?**
A: Include the port in the domain: `instance.com:3000`

**Q: Is this compatible with single-user Mastodon instances?**
A: Yes, but you must be the admin of that instance.

## Support

If you encounter issues:
1. Check the OSTicket error log
2. Enable debug mode (`FEDIVERSE_DEBUG=1`)
3. Review the troubleshooting section
4. Check instance management page for error messages
5. Consult the deployment readiness document

## Security Considerations

**✅ Secure:**
- OAuth 2.0 industry standard
- No password storage
- Admin-only access
- Scoped permissions

**⚠️ Consider:**
- Tokens stored in plaintext (encryption recommended for Phase 2)
- No webhook signature validation yet (Phase 2)
- Manual token revocation required if compromised

For production deployments, consider implementing the Phase 2 security enhancements outlined in `DEPLOYMENT_READINESS.md`.
