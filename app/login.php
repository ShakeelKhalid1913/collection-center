<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/head.php';

if (!empty($_SESSION['user'])) {
    $portal = current_portal() ?: 'collection-center';
    header('Location: ' . (PORTALS[$portal]['path'] ?? '/portals/dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Enter email and password.';
    } else {
        $auth = user_repo()->authenticate($email, $password);
        if ($auth['success']) {
            start_user_session($auth['user']);
            $portal = current_portal();
            header('Location: ' . (PORTALS[$portal]['path'] ?? '/portals/dashboard.php'));
            exit;
        }
        $error = $auth['error'] ?? 'Invalid email or password.';
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php render_head('Sign in · Lab Dash Pro'); ?>
</head>
<body class="app-body">
<div class="login-page">
    <section class="login-brand">
        <div>
            <div class="login-brand__logo">
                <div class="login-brand__mark"><?= brand_logo('brand-logo brand-logo--login') ?></div>
                <div>
                    <h1>Lab Dash Pro</h1>
                    <p class="login-brand__tagline">Clinical Diagnostic &amp; Laboratory Information System</p>
                </div>
            </div>
        </div>
        <ul class="login-brand__features">
            <li><i class="fa-solid fa-x-ray" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Diagnostic Center</strong> &bull; Ultrasound, X-Ray, ECG &amp; CT</li>
            <li><i class="fa-solid fa-flask" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Laboratory</strong> &bull; Pathology &amp; Biochemistry</li>
            <li><i class="fa-solid fa-building" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Collection Center</strong> &bull; Intake, Tokens &amp; Billing</li>
            <li><i class="fa-solid fa-shield" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Admin</strong> &bull; Reports &amp; Management</li>
        </ul>
        <p style="margin:0;font-size:0.75rem;opacity:0.65">Secure Enterprise Access</p>
    </section>

    <section class="login-form-side">
        <div class="login-mobile-title">
            <div class="app-brand__icon" style="margin:0 auto 0.75rem;width:3.25rem;height:3.25rem"><?= brand_logo('brand-logo brand-logo--login') ?></div>
            <strong style="font-size:1.125rem">Lab Dash Pro</strong>
        </div>

        <div class="login-card">
            <div class="login-card__head">
                <h2><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign In</h2>
                <p>Enter your credentials to access your workstation.</p>
            </div>

            <?php 
            $reqPortal = trim((string)($_GET['portal'] ?? ''));
            $portalBadges = [
                'main_lab' => ['title' => 'Laboratory Login', 'icon' => 'fa-flask', 'style' => 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;'],
                'imaging' => ['title' => 'Diagnostic Portal', 'icon' => 'fa-wand-magic-sparkles', 'style' => 'background:#faf5ff;color:#7e22ce;border:1px solid #e9d5ff;'],
                'collection_center' => ['title' => 'Clinic & Collection Center', 'icon' => 'fa-hospital-user', 'style' => 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;'],
                'pharmacy' => ['title' => 'Pharmacy Login', 'icon' => 'fa-prescription-bottle-medical', 'style' => 'background:#fffbeb;color:#b45309;border:1px solid #fde68a;'],
                'admin' => ['title' => 'Administration & Super Admin', 'icon' => 'fa-shield-halved', 'style' => 'background:#f8fafc;color:#0f172a;border:1px solid #cbd5e1;'],
            ];
            if (isset($portalBadges[$reqPortal])):
                $b = $portalBadges[$reqPortal];
            ?>
                <div style="margin-top:0.75rem;padding:0.4rem 0.75rem;border-radius:0.5rem;font-size:0.75rem;font-weight:700;display:flex;align-items:center;gap:0.4rem;<?= $b['style'] ?>">
                    <i class="fa-solid <?= $b['icon'] ?>"></i>
                    <span>Accessing <?= e($b['title']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-error" style="margin-top:1rem"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top:1.25rem">
                <div style="margin-bottom:1rem">
                    <label class="field-label" for="email"><i class="fa-solid fa-envelope"></i> Work email</label>
                    <input type="email" id="email" name="email" class="field" autocomplete="username" placeholder="staff@lab.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>
                <div style="margin-bottom:1.25rem">
                    <label class="field-label" for="password"><i class="fa-solid fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="field" autocomplete="current-password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%"><i class="fa-solid fa-arrow-right"></i> Sign In to Workstation</button>
            </form>
            <?= software_credit_footer() ?>
        </div>
    </section>
</div>
<?= floating_whatsapp_button('+92 306 5193582') ?>
</body>
</html>
