<?php
/**
 * Admin page for managing Fediverse instances.
 * Provides OAuth-based instance authorization.
 */

require_once INCLUDE_DIR . 'class.plugin.php';
require_once dirname(__DIR__) . '/plugin.php';

use FediversePlugin\OAuthHandler;
use FediversePlugin\Model\Instance;
use FediversePlugin\API\ServerProber;
use FediversePlugin\API\APIException;
use FediversePlugin\DebugHelper;

// Check admin permissions
if (!$thisstaff || !$thisstaff->isAdmin()) {
    Http::response(403, 'Access Denied');
    exit;
}

$errors = [];
$messages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_instance':
                handleAddInstance($errors, $messages);
                break;

            case 'delete_instance':
                handleDeleteInstance($errors, $messages);
                break;

            case 'toggle_instance':
                handleToggleInstance($errors, $messages);
                break;
        }
    }
}

// Handle OAuth callback
if (isset($_GET['action']) && $_GET['action'] === 'oauth_callback') {
    handleOAuthCallback($errors, $messages);
}

// Get all configured instances
$instances = Instance::getEnabled();
$allInstances = getAllInstances();

/**
 * Handle adding a new instance via OAuth.
 */
function handleAddInstance(array &$errors, array &$messages): void
{
    $domain = trim($_POST['domain'] ?? '');

    if (empty($domain)) {
        $errors[] = 'Domain is required';
        return;
    }

    // Remove protocol if provided
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = trim($domain, '/');

    try {
        // Detect platform
        DebugHelper::logInfo('InstanceAdmin', 'Detecting platform for domain', ['domain' => $domain]);
        $platformInfo = ServerProber::probe($domain);
        $platform = $platformInfo['platform'];

        // Start OAuth flow
        $oauthData = OAuthHandler::registerApp($domain, $platform);

        // Store OAuth data in session for callback
        $_SESSION['fediverse_oauth'] = [
            'domain' => $domain,
            'platform' => $platform,
            'client_id' => $oauthData['client_id'],
            'client_secret' => $oauthData['client_secret'],
            'session' => $oauthData['session'] ?? null
        ];

        // Redirect to authorization URL
        header('Location: ' . $oauthData['auth_url']);
        exit;

    } catch (APIException $e) {
        DebugHelper::logError('InstanceAdmin', 'Failed to add instance', [
            'domain' => $domain,
            'error' => $e->getMessage()
        ]);
        $errors[] = 'Failed to add instance: ' . $e->getMessage();
    } catch (Exception $e) {
        DebugHelper::logError('InstanceAdmin', 'Unexpected error adding instance', [
            'domain' => $domain,
            'error' => $e->getMessage()
        ]);
        $errors[] = 'Unexpected error: ' . $e->getMessage();
    }
}

/**
 * Handle OAuth callback and save instance.
 */
function handleOAuthCallback(array &$errors, array &$messages): void
{
    if (!isset($_SESSION['fediverse_oauth'])) {
        $errors[] = 'OAuth session expired. Please try again.';
        return;
    }

    $oauthData = $_SESSION['fediverse_oauth'];
    $code = $_GET['code'] ?? null;

    if (!$code && $oauthData['platform'] === 'misskey') {
        // Misskey uses session-based auth
        $code = $oauthData['session'];
    }

    if (!$code) {
        $errors[] = 'Authorization failed: No code received';
        unset($_SESSION['fediverse_oauth']);
        return;
    }

    try {
        // Exchange code for token
        $token = OAuthHandler::exchangeToken(
            $oauthData['domain'],
            $oauthData['platform'],
            $code,
            $oauthData['client_id'],
            $oauthData['client_secret']
        );

        // Verify admin permissions
        $accountInfo = OAuthHandler::verifyAdminToken(
            $oauthData['domain'],
            $oauthData['platform'],
            $token
        );

        // Get platform version
        $platformInfo = ServerProber::probe($oauthData['domain']);

        // Save instance
        Instance::save([
            'domain' => $oauthData['domain'],
            'token' => $token,
            'platform' => $oauthData['platform'],
            'version' => $platformInfo['version'] ?? 'unknown',
            'enabled' => 1,
            'metadata' => [
                'authorized_by' => $accountInfo['username'],
                'authorized_role' => $accountInfo['role'],
                'authorized_at' => date('Y-m-d H:i:s')
            ]
        ]);

        DebugHelper::logSuccess('InstanceAdmin', 'Instance added successfully', [
            'domain' => $oauthData['domain'],
            'platform' => $oauthData['platform'],
            'authorized_by' => $accountInfo['username']
        ]);

        $messages[] = sprintf(
            'Instance %s added successfully! Authorized by: %s (%s)',
            $oauthData['domain'],
            $accountInfo['username'],
            $accountInfo['role']
        );

        unset($_SESSION['fediverse_oauth']);

    } catch (APIException $e) {
        DebugHelper::logError('InstanceAdmin', 'OAuth callback failed', [
            'domain' => $oauthData['domain'],
            'error' => $e->getMessage()
        ]);
        $errors[] = 'Authorization failed: ' . $e->getMessage();
        unset($_SESSION['fediverse_oauth']);
    }
}

/**
 * Handle deleting an instance.
 */
function handleDeleteInstance(array &$errors, array &$messages): void
{
    $instanceId = (int)($_POST['instance_id'] ?? 0);

    if ($instanceId <= 0) {
        $errors[] = 'Invalid instance ID';
        return;
    }

    try {
        $sql = "DELETE FROM plugin_fediverse_instances WHERE id = ?";
        Db::connection()->query($sql, [$instanceId]);

        $messages[] = 'Instance deleted successfully';
        DebugHelper::logInfo('InstanceAdmin', 'Instance deleted', ['id' => $instanceId]);
    } catch (Exception $e) {
        $errors[] = 'Failed to delete instance: ' . $e->getMessage();
    }
}

/**
 * Handle enabling/disabling an instance.
 */
function handleToggleInstance(array &$errors, array &$messages): void
{
    $instanceId = (int)($_POST['instance_id'] ?? 0);
    $enabled = (int)($_POST['enabled'] ?? 0);

    if ($instanceId <= 0) {
        $errors[] = 'Invalid instance ID';
        return;
    }

    try {
        $sql = "UPDATE plugin_fediverse_instances SET enabled = ? WHERE id = ?";
        Db::connection()->query($sql, [$enabled, $instanceId]);

        $status = $enabled ? 'enabled' : 'disabled';
        $messages[] = "Instance {$status} successfully";
        DebugHelper::logInfo('InstanceAdmin', "Instance {$status}", ['id' => $instanceId]);
    } catch (Exception $e) {
        $errors[] = 'Failed to update instance: ' . $e->getMessage();
    }
}

/**
 * Get all instances from database.
 */
function getAllInstances(): array
{
    $sql = "SELECT * FROM plugin_fediverse_instances ORDER BY created DESC";
    return Db::connection()->query($sql)->fetchAll();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Fediverse Instances - OSTicket</title>
    <style>
        .fediverse-admin {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .message {
            padding: 10px 15px;
            margin: 10px 0;
            border-radius: 4px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .instance-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        .instance-form h3 {
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .instances-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .instances-table th,
        .instances-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .instances-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .instances-table tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background: #28a745;
            color: white;
        }
        .badge-secondary {
            background: #6c757d;
            color: white;
        }
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        .help-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: 5px;
        }
        .oauth-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .oauth-info h4 {
            margin-top: 0;
            color: #004085;
        }
        .oauth-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .oauth-info code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="fediverse-admin">
        <h1>🌐 Fediverse Instance Management</h1>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $message): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="oauth-info">
            <h4>🔐 OAuth Authentication</h4>
            <p>This plugin uses OAuth to securely connect to Fediverse instances. When you add an instance:</p>
            <ul>
                <li>You'll be redirected to the instance to authorize the app</li>
                <li>You must log in with an <strong>admin</strong> or <strong>moderator</strong> account</li>
                <li>The plugin requests <code>admin:read</code> and <code>admin:write</code> scopes</li>
                <li>Your access token is securely stored and used to manage reports</li>
            </ul>
            <p><strong>Supported platforms:</strong> Mastodon, Misskey/Sharkey</p>
        </div>

        <div class="instance-form">
            <h3>➕ Add New Instance</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_instance">

                <div class="form-group">
                    <label for="domain">Instance Domain:</label>
                    <input type="text" id="domain" name="domain" placeholder="mastodon.social" required>
                    <div class="help-text">
                        Enter the domain without https:// (e.g., mastodon.social, misskey.io)
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">🔗 Connect via OAuth</button>
            </form>
        </div>

        <h2>📋 Configured Instances</h2>

        <?php if (empty($allInstances)): ?>
            <p>No instances configured yet. Add one above to get started!</p>
        <?php else: ?>
            <table class="instances-table">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Platform</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Authorized By</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allInstances as $instance): ?>
                        <?php
                        $metadata = json_decode($instance['metadata'] ?? '{}', true);
                        $authorizedBy = $metadata['authorized_by'] ?? 'Unknown';
                        $authorizedRole = $metadata['authorized_role'] ?? '';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($instance['domain']); ?></strong></td>
                            <td><span class="badge badge-info"><?php echo htmlspecialchars($instance['platform']); ?></span></td>
                            <td><?php echo htmlspecialchars($instance['version'] ?? 'unknown'); ?></td>
                            <td>
                                <?php if ($instance['enabled']): ?>
                                    <span class="badge badge-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($authorizedBy); ?>
                                <?php if ($authorizedRole): ?>
                                    <span class="badge badge-secondary"><?php echo htmlspecialchars($authorizedRole); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($instance['created'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_instance">
                                    <input type="hidden" name="instance_id" value="<?php echo $instance['id']; ?>">
                                    <input type="hidden" name="enabled" value="<?php echo $instance['enabled'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn <?php echo $instance['enabled'] ? 'btn-secondary' : 'btn-success'; ?>">
                                        <?php echo $instance['enabled'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this instance?');">
                                    <input type="hidden" name="action" value="delete_instance">
                                    <input type="hidden" name="instance_id" value="<?php echo $instance['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
