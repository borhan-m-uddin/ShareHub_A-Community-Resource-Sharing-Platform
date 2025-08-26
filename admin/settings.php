<?php
require_once __DIR__ . '/../bootstrap.php';
require_admin();

// Defaults
$defaults = [
    'site_name' => 'ShareHub',
    'default_theme' => 'light', // light|dark
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => '',
    'smtp_from_name' => '',
    'smtp_secure' => '', // tls|ssl|empty
];

$settings = array_merge($defaults, get_settings_all());
$error = null;
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        // Validate and normalize inputs
        $site_name = trim((string)($_POST['site_name'] ?? $settings['site_name']));
        $default_theme = ($_POST['default_theme'] ?? $settings['default_theme']) === 'dark' ? 'dark' : 'light';
        $smtp_host = trim((string)($_POST['smtp_host'] ?? ''));
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        if ($smtp_port <= 0) $smtp_port = 587;
        $smtp_user = trim((string)($_POST['smtp_user'] ?? ''));
        $smtp_pass = (string)($_POST['smtp_pass'] ?? '');
        // If password field left blank, keep existing password
        if ($smtp_pass === '') { $smtp_pass = (string)get_setting('smtp_pass', ''); }
        $smtp_from = trim((string)($_POST['smtp_from'] ?? ''));
        $smtp_from_name = trim((string)($_POST['smtp_from_name'] ?? ''));
        $smtp_secure = $_POST['smtp_secure'] ?? '';
        if (!in_array($smtp_secure, ['', 'tls', 'ssl'], true)) { $smtp_secure = ''; }

        $toSave = compact('site_name','default_theme','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name','smtp_secure');
        if (save_settings($toSave, true)) {
            $saved = true;
            $settings = array_merge($settings, $toSave);
            flash_set('success', 'Settings saved successfully.');
            header('Location: ' . site_href('admin/settings.php'));
            exit;
        } else {
            $error = 'Failed to save settings. Please check file permissions for /storage.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
<?php render_header(); ?>
<div class="wrapper">
    <div class="card">
        <div class="card-body">
            <h2>⚙️ Platform Settings</h2>
            <p class="muted">Configure site name, default theme, and SMTP for emails.</p>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($msg = flash_get('success')): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
            <form method="post" action="<?php echo site_href('admin/settings.php'); ?>" class="form-grid">
                <?php echo csrf_field(); ?>
                <div class="form-row">
                    <label for="site_name">Site Name</label>
                    <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                </div>
                <div class="form-row">
                    <label for="default_theme">Default Theme</label>
                    <select id="default_theme" name="default_theme">
                        <option value="light" <?php echo $settings['default_theme']==='light'?'selected':''; ?>>Light</option>
                        <option value="dark" <?php echo $settings['default_theme']==='dark'?'selected':''; ?>>Dark</option>
                    </select>
                </div>

                <fieldset class="form-fieldset" style="margin-top:16px;">
                    <legend>SMTP (optional)</legend>
                    <div class="grid grid-2">
                        <div class="form-row">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars((string)$settings['smtp_host']); ?>">
                        </div>
                        <div class="form-row">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" value="<?php echo (int)$settings['smtp_port']; ?>">
                        </div>
                        <div class="form-row">
                            <label for="smtp_user">SMTP Username</label>
                            <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars((string)$settings['smtp_user']); ?>">
                        </div>
                        <div class="form-row">
                            <label for="smtp_pass">SMTP Password</label>
                            <input type="password" id="smtp_pass" name="smtp_pass" value="" placeholder="Leave blank to keep current">
                        </div>
                        <div class="form-row">
                            <label for="smtp_from">From Email</label>
                            <input type="email" id="smtp_from" name="smtp_from" value="<?php echo htmlspecialchars((string)$settings['smtp_from']); ?>">
                        </div>
                        <div class="form-row">
                            <label for="smtp_from_name">From Name</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars((string)$settings['smtp_from_name']); ?>">
                        </div>
                        <div class="form-row">
                            <label for="smtp_secure">Security</label>
                            <select id="smtp_secure" name="smtp_secure">
                                <option value="" <?php echo $settings['smtp_secure']===''?'selected':''; ?>>None</option>
                                <option value="tls" <?php echo $settings['smtp_secure']==='tls'?'selected':''; ?>>TLS</option>
                                <option value="ssl" <?php echo $settings['smtp_secure']==='ssl'?'selected':''; ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <div style="margin-top:16px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <a href="<?php echo site_href('admin/panel.php'); ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>