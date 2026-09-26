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
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $portal = $_POST['portal'] ?? 'collection-center';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields (Full Name, Email, Password).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters long.';
    } elseif (!isset(PORTALS[$portal])) {
        $error = 'Please select a valid user type / portal.';
    } else {
        $result = user_repo()->createUser([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'portal' => $portal, // UserRepository normalizes to underscore
            'role' => PORTALS[$portal]['label'] . ' Staff',
            'organization_id' => 'ORG-001',
            'branch_id' => ($portal === 'main-lab' ? 'BR-MAIN-LAB' : ($portal === 'imaging' ? 'BR-IMAGING' : ($portal === 'admin' ? 'BR-MAIN-LAB' : 'BR-GULBERG'))),
        ]);

        if ($result['success']) {
            start_user_session($result['user']);
            header('Location: ' . (PORTALS[current_portal()]['path'] ?? '/portals/collection-center/dashboard.php'));
            exit;
        }
        $error = $result['error'] ?? 'Signup failed. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <?php render_head('Create Account · Health LMS Pro'); ?>
</head>
<body class="app-body">
<div class="login-page">
    <section class="login-brand">
        <div>
            <div class="login-brand__logo">
                <div class="login-brand__mark"><?= brand_logo('brand-logo brand-logo--login') ?></div>
                <div>
                    <h1>Health LMS Pro</h1>
                    <p class="login-brand__tagline">Register a staff account locked to one portal type.</p>
                </div>
            </div>
        </div>
        <ul class="login-brand__features">
            <li><i class="fa-solid fa-user-plus" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Create Staff Account</strong> — MariaDB PDO Secured</li>
            <li><i class="fa-solid fa-lock" style="margin-right:0.4rem;opacity:0.9"></i> <strong>BCrypt Password Hashing</strong></li>
            <li><i class="fa-solid fa-shield-halved" style="margin-right:0.4rem;opacity:0.9"></i> <strong>Portal access lock</strong> — one type per account</li>
        </ul>
    </section>

    <section class="login-form-side">
        <div class="login-mobile-title">
            <div class="app-brand__icon" style="margin:0 auto 0.75rem;width:3.25rem;height:3.25rem"><?= brand_logo('brand-logo brand-logo--login') ?></div>
            <strong style="font-size:1.125rem">Health LMS Pro</strong>
        </div>

        <div class="login-card">
            <div class="login-card__head">
                <h2><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Create Staff Account</h2>
                <p>Choose your portal carefully — you will only access that portal after signup.</p>
                <p style="margin:0.5rem 0 0;font-size:0.8125rem">Already have an account? <a href="/login.php" style="color:#0f766e;font-weight:600;text-decoration:none">Sign in here <i class="fa-solid fa-arrow-right"></i></a></p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error" style="margin-top:1rem"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" style="margin-top:1rem">
                <div style="margin-bottom:1rem">
                    <label class="field-label" for="name"><i class="fa-solid fa-user"></i> Full Name</label>
                    <input type="text" id="name" name="name" required class="field" placeholder="e.g. Dr. Sarah Ahmed" value="<?= e($_POST['name'] ?? '') ?>">
                </div>

                <div style="margin-bottom:1rem">
                    <label class="field-label" for="email"><i class="fa-solid fa-envelope"></i> Work Email</label>
                    <input type="email" id="email" name="email" required class="field" autocomplete="username" placeholder="name@citylab.pk" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div style="margin-bottom:1rem">
                    <label class="field-label" for="portal"><i class="fa-solid fa-user-tag"></i> Portal (locked to this account)</label>
                    <select id="portal" name="portal" required class="field">
                        <?php
                        $order = ['collection-center', 'main-lab', 'imaging', 'admin'];
                        foreach ($order as $key):
                            if (!isset(PORTALS[$key])) continue;
                            $portalMeta = PORTALS[$key];
                            ?>
                            <option value="<?= e($key) ?>" <?= ($_POST['portal'] ?? 'collection-center') === $key ? 'selected' : '' ?>>
                                <?= e($portalMeta['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:1.25rem">
                    <label class="field-label" for="password"><i class="fa-solid fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required class="field" autocomplete="new-password" placeholder="Create a secure password">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%"><i class="fa-solid fa-user-check"></i> Register Account</button>
            </form>
        </div>
    </section>
</div>
<?= floating_whatsapp_button('+92 306 5193582') ?>
</body>
</html>
