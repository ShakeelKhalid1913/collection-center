<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/head.php';

if (!empty($_SESSION['user'])) {
    $portal = $_SESSION['portal'] ?? 'collection-center';
    header('Location: ' . (PORTALS[$portal]['path'] ?? '/portals/collection-center/dashboard.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $portal = $_POST['portal'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!isset(PORTALS[$portal])) {
        $error = 'Please select a valid portal.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter email and password (demo: any values).';
    } else {
        $_SESSION['portal'] = $portal;
        $_SESSION['user'] = [
            'name' => explode('@', $email)[0] ?: 'Demo User',
            'email' => $email,
            'role' => PORTALS[$portal]['label'] . ' Staff',
            'organization_id' => 'ORG-001',
            'branch_id' => 'BR-GULBERG',
        ];
        header('Location: ' . PORTALS[$portal]['path']);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php render_head('Sign in · Lab Management'); ?>
</head>
<body class="app-body">
<div class="login-page">
    <section class="login-brand">
        <div>
            <div class="login-brand__logo">
                <div class="login-brand__mark"><?= brand_logo('brand-logo brand-logo--login') ?></div>
                <div>
                    <h1>Lab Management System</h1>
                    <p class="login-brand__tagline">Pathology, collection centers, and imaging — one patient record, one workflow.</p>
                </div>
            </div>
        </div>
        <ul class="login-brand__features">
            <li><i class="fa-solid fa-user-plus" style="margin-right:0.4rem;opacity:0.9"></i> Register patients, order tests, and print receipts at collection points</li>
            <li><i class="fa-solid fa-vial" style="margin-right:0.4rem;opacity:0.9"></i> Track samples, enter results, verify reports at the main laboratory</li>
            <li><i class="fa-brands fa-whatsapp" style="margin-right:0.4rem;opacity:0.9"></i> Deliver branded PDF reports via print or WhatsApp</li>
        </ul>
        <p style="margin:0;font-size:0.75rem;opacity:0.75">MVP preview · MySQL backend next</p>
    </section>

    <section class="login-form-side">
        <div class="login-mobile-title">
            <div class="app-brand__icon" style="margin:0 auto 0.75rem;width:3.25rem;height:3.25rem"><?= brand_logo('brand-logo brand-logo--login') ?></div>
            <strong style="font-size:1.125rem">Lab Management System</strong>
        </div>

        <div class="login-card">
            <div class="login-card__head">
                <h2><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in</h2>
                <p>Choose your portal — demo accepts any email and password.</p>
                <p style="margin:0.5rem 0 0;font-size:0.8125rem"><a href="/" style="color:#0f766e;font-weight:600;text-decoration:none"><i class="fa-solid fa-arrow-left"></i> Back to website</a></p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" style="margin-top:1rem"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <div style="margin-bottom:1rem">
                    <label class="field-label" for="portal"><i class="fa-solid fa-door-open"></i> Portal</label>
                    <select id="portal" name="portal" required class="field">
                        <?php foreach (PORTALS as $key => $portal): ?>
                            <option value="<?= e($key) ?>" <?= ($_POST['portal'] ?? 'collection-center') === $key ? 'selected' : '' ?>><?= e($portal['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:1rem">
                    <label class="field-label" for="email"><i class="fa-solid fa-envelope"></i> Work email</label>
                    <input type="email" id="email" name="email" class="field" autocomplete="username" value="<?= e($_POST['email'] ?? 'staff@citylab.pk') ?>">
                </div>
                <div style="margin-bottom:1.25rem">
                    <label class="field-label" for="password"><i class="fa-solid fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="field" autocomplete="current-password" value="demo">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%"><i class="fa-solid fa-arrow-right"></i> Continue to dashboard</button>
            </form>
        </div>
    </section>
</div>
<?= floating_whatsapp_button('+92 306 5193582') ?>
</body>
</html>
