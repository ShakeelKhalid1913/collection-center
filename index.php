<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/head.php';

if (!empty($_SESSION['user'])) {
    $portal = $_SESSION['portal'] ?? 'collection-center';
    header('Location: ' . (PORTALS[$portal]['path'] ?? '/portals/collection-center/dashboard.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php render_head('City Diagnostic Laboratory — Lab Management System'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body class="landing">
    <header class="lp-nav" id="top">
        <a class="lp-nav__brand" href="/">
            <span class="lp-nav__mark"><?= brand_logo('brand-logo brand-logo--nav') ?></span>
            <span>
                <strong>City Diagnostic Lab</strong>
                <small>Laboratory Management</small>
            </span>
        </a>
        <button type="button" class="lp-nav__toggle" id="lp-nav-toggle" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="lp-nav__links" id="lp-nav-links">
            <a href="#about">About</a>
            <a href="#services">Services</a>
            <a href="#workflow">Workflow</a>
            <a href="#contact">Contact</a>
            <a class="lp-nav__login" href="/login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
        </nav>
    </header>

    <section class="lp-hero">
        <div class="lp-hero__media" role="img" aria-label="Laboratory scientist working with samples">
            <img
                src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=2000&q=80"
                alt="Modern diagnostic laboratory"
                width="2000"
                height="1333"
                fetchpriority="high"
            >
            <div class="lp-hero__scrim"></div>
        </div>
        <div class="lp-hero__content">
            <p class="lp-hero__brand">City Diagnostic Laboratory</p>
            <h1>Precise diagnostics.<br>One connected lab system.</h1>
            <p class="lp-hero__lead">
                Collect samples, process pathology, imaging, and deliver reports — with print-ready PDFs and WhatsApp delivery built in.
            </p>
            <div class="lp-hero__cta">
                <a class="lp-btn lp-btn--primary" href="#contact">Contact us</a>
                <a class="lp-btn lp-btn--ghost" href="/login.php">Staff login</a>
            </div>
        </div>
    </section>

    <section class="lp-section" id="about">
        <div class="lp-wrap lp-about">
            <div class="lp-about__copy">
                <p class="lp-eyebrow">About us</p>
                <h2>Built for collection centers, main labs, and imaging under one roof.</h2>
                <p>
                    City Diagnostic Laboratory runs a focused Lab Management System that keeps patients, tests, samples,
                    results, and reports in a single workflow — across branches and departments.
                </p>
                <p>
                    From quick registration at the collection desk to pathologist verification and WhatsApp report delivery,
                    every step is designed for speed, clarity, and high-contrast printing.
                </p>
            </div>
            <div class="lp-about__visual">
                <img
                    src="https://images.unsplash.com/photo-1582719471384-894fbb16e074?auto=format&fit=crop&w=1200&q=80"
                    alt="Laboratory equipment and diagnostics"
                    width="1200"
                    height="900"
                    loading="lazy"
                >
            </div>
        </div>
    </section>

    <section class="lp-section lp-section--muted" id="services">
        <div class="lp-wrap">
            <p class="lp-eyebrow">What we offer</p>
            <h2 class="lp-section__title">Four connected portals</h2>
            <p class="lp-section__lead">Role-based access for every part of the laboratory operation.</p>
            <div class="lp-services">
                <article>
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <h3>Collection Center</h3>
                    <p>Patient registration, tests &amp; packages, receipts, sample status, and billing with discounts.</p>
                </article>
                <article>
                    <i class="fa-solid fa-flask" aria-hidden="true"></i>
                    <h3>Main Lab</h3>
                    <p>Sample receiving, results entry, verification, critical alerts, and branded report generation.</p>
                </article>
                <article>
                    <i class="fa-solid fa-x-ray" aria-hidden="true"></i>
                    <h3>X-Ray / CT</h3>
                    <p>Imaging appointments, scan status, findings, and radiology reports on the same patient record.</p>
                </article>
                <article>
                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                    <h3>Admin</h3>
                    <p>Labs, branches, users, permissions, test catalog, packages, and report branding settings.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-section" id="workflow">
        <div class="lp-wrap">
            <p class="lp-eyebrow">How it works</p>
            <h2 class="lp-section__title">Patient to report — without the chaos</h2>
            <ol class="lp-steps">
                <li><span>01</span><strong>Register</strong><em>Capture demographics, referral, and ordered tests.</em></li>
                <li><span>02</span><strong>Collect</strong><em>Track samples from desk to main lab benches.</em></li>
                <li><span>03</span><strong>Verify</strong><em>Enter results and release after pathologist sign-off.</em></li>
                <li><span>04</span><strong>Deliver</strong><em>Print, PDF, or send reports on WhatsApp instantly.</em></li>
            </ol>
        </div>
    </section>

    <section class="lp-section lp-section--muted" id="contact">
        <div class="lp-wrap lp-contact">
            <div>
                <p class="lp-eyebrow">Contact us</p>
                <h2 class="lp-section__title">Talk to our lab team</h2>
                <p class="lp-section__lead">Questions about services, branches, or staff access? Send a message or reach us directly.</p>
                <ul class="lp-contact__details">
                    <li>
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        <span>12-A Main Boulevard, Faisalabad</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-phone" aria-hidden="true"></i>
                        <a href="tel:+9242111222333">+92 42 111 222 333</a>
                    </li>
                    <li>
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                        <a href="https://wa.me/923065193582" target="_blank" rel="noopener">+92 306 5193582</a>
                    </li>
                    <li>
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <a href="mailto:reports@citylab.pk">reports@citylab.pk</a>
                    </li>
                </ul>
            </div>
            <form class="lp-contact__form" method="post" action="#contact" onsubmit="event.preventDefault(); this.querySelector('[data-sent]').hidden=false;">
                <label>
                    <span>Full name</span>
                    <input class="field" type="text" name="name" required placeholder="Your name">
                </label>
                <label>
                    <span>Phone</span>
                    <input class="field" type="tel" name="phone" required placeholder="03xx-xxxxxxx">
                </label>
                <label>
                    <span>Email</span>
                    <input class="field" type="email" name="email" placeholder="you@email.com">
                </label>
                <label>
                    <span>Message</span>
                    <textarea class="field" name="message" rows="4" required placeholder="How can we help?"></textarea>
                </label>
                <button type="submit" class="lp-btn lp-btn--primary"><i class="fa-solid fa-paper-plane"></i> Send message</button>
                <p class="lp-form-note" data-sent hidden><i class="fa-solid fa-circle-check"></i> Thanks — we will get back to you shortly. (Demo form)</p>
            </form>
        </div>
    </section>

    <footer class="lp-footer">
        <div class="lp-wrap lp-footer__top">
            <div>
                <div class="lp-nav__brand lp-footer__brand">
                    <span class="lp-nav__mark"><?= brand_logo('brand-logo brand-logo--nav') ?></span>
                    <span>
                        <strong>City Diagnostic Lab</strong>
                        <small>Trusted pathology &amp; imaging</small>
                    </span>
                </div>
                <p class="lp-footer__tag">Patient → Tests → Sample → Result → Report</p>
            </div>
            <div class="lp-footer__cols">
                <div>
                    <h4>Explore</h4>
                    <a href="#about">About</a>
                    <a href="#services">Services</a>
                    <a href="#workflow">Workflow</a>
                    <a href="#contact">Contact</a>
                </div>
                <div>
                    <h4>Staff</h4>
                    <a href="/login.php">Portal login</a>
                    <a href="https://wa.me/923065193582" target="_blank" rel="noopener">WhatsApp support</a>
                </div>
            </div>
        </div>
        <div class="lp-footer__bottom">
            <div class="lp-wrap lp-footer__bottom-inner">
                <p>&copy; <?= date('Y') ?> City Diagnostic Laboratory. All rights reserved.</p>
                <p class="lp-footer__credit">
                    Software by <strong>Shakeel Khalid</strong>
                    · <a href="tel:03283070070">03283070070</a>
                    · <a href="mailto:shakeelkhalid786@gmail.com">shakeelkhalid786@gmail.com</a>
                </p>
            </div>
        </div>
    </footer>

    <?= floating_whatsapp_button('+92 306 5193582') ?>

    <script>
      (function () {
        const toggle = document.getElementById('lp-nav-toggle');
        const links = document.getElementById('lp-nav-links');
        toggle?.addEventListener('click', () => links?.classList.toggle('is-open'));
        links?.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => links.classList.remove('is-open')));
        const nav = document.querySelector('.lp-nav');
        const onScroll = () => nav?.classList.toggle('is-scrolled', window.scrollY > 24);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
      })();
    </script>
</body>
</html>
