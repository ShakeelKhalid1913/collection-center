<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/head.php';

if (!empty($_SESSION['user'])) {
    $portal = current_portal() ?: 'collection-center';
    header('Location: ' . (PORTALS[$portal]['path'] ?? '/portals/collection-center/dashboard.php'));
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
            header('Location: ' . (PORTALS[$portal]['path'] ?? '/portals/collection-center/dashboard.php'));
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
                    <p class="login-brand__tagline">Sign in — your account is locked to one portal (Collection, Laboratory, Diagnostic, or Admin).</p>
                </div>
            </div>
        </div>
        <ul class="login-brand__features">
            <li><i class="fa-solid fa-x-ray" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Diagnostic Center</strong> — imaging@citylab.pk</li>
            <li><i class="fa-solid fa-flask" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Laboratory</strong> — lab@citylab.pk</li>
            <li><i class="fa-solid fa-building" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Collection Center</strong> — staff@citylab.pk</li>
            <li><i class="fa-solid fa-shield" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Admin</strong> — admin@citylab.pk</li>
        </ul>
        <p style="margin:0;font-size:0.75rem;opacity:0.75">Seeded password: 1913</p>
    </section>

    <section class="login-form-side">
        <div class="login-mobile-title">
            <div class="app-brand__icon" style="margin:0 auto 0.75rem;width:3.25rem;height:3.25rem"><?= brand_logo('brand-logo brand-logo--login') ?></div>
            <strong style="font-size:1.125rem">Lab Dash Pro</strong>
        </div>

        <div class="login-card">
            <div class="login-card__head">
                <h2><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in</h2>
                <p>Portal access is set by your account — you cannot open other staff types.</p>
                <p style="margin:0.5rem 0 0;font-size:0.8125rem">Need an account? <a href="/signup.php" style="color:#0f766e;font-weight:700;text-decoration:none">Create Account <i class="fa-solid fa-user-plus"></i></a></p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" style="margin-top:1rem"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top:1rem">
                <div style="margin-bottom:1rem">
                    <label class="field-label" for="email"><i class="fa-solid fa-envelope"></i> Work email</label>
                    <input type="email" id="email" name="email" class="field" autocomplete="username" value="<?= e($_POST['email'] ?? 'staff@citylab.pk') ?>">
                </div>
                <div style="margin-bottom:1.25rem">
                    <label class="field-label" for="password"><i class="fa-solid fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="field" autocomplete="current-password" value="1913">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%"><i class="fa-solid fa-arrow-right"></i> Continue to dashboard</button>
            </form>
            <?= software_credit_footer() ?>
        </div>
    </section>
</div>
<?= floating_whatsapp_button('+92 306 5193582') ?>
</body>
</html>
