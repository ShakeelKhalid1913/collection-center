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
<html lang="en" class="scroll-smooth">
<head>
    <?php render_head('Lab Dash Pro — Next-Gen Diagnostic, Laboratory & Collection Platform'); ?>
    <link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body class="landing text-slate-800 antialiased selection:bg-[#c2f13c] selection:text-slate-950">

    <!-- Top Navigation Bar -->
    <header class="lp-nav" id="top">
        <a class="lp-nav__brand" href="/">
            <span class="lp-nav__mark"><?= brand_logo('brand-logo brand-logo--nav') ?></span>
            <div>
                <strong>
                    <span>Lab Dash Pro</span>
                    <span class="text-[9px] uppercase font-black px-1.5 py-0.5 rounded-full bg-[#c2f13c] text-slate-950">PRO</span>
                </strong>
                <small>Clinical Diagnostic Platform</small>
            </div>
        </a>

        <button type="button" class="lp-nav__toggle" id="lp-nav-toggle" aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav class="lp-nav__links" id="lp-nav-links">
            <a href="#portals">Portals</a>
            <a href="#modalities">Modalities</a>
            <a href="#workflow">Workflow</a>
            <a href="#contact">Contact</a>
            <a class="lp-nav__login" href="/login.php">
                <i class="fa-solid fa-right-to-bracket text-xs"></i>
                <span>Staff Login</span>
            </a>
        </nav>
    </header>

    <!-- Hero Section (Real Clinical Laboratory Photography with Professional Scrim) -->
    <section class="lp-hero">
        <div class="lp-wrap relative z-10 w-full pt-16 pb-12 lg:pt-24 lg:pb-16">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <!-- Left Hero Copy -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/20 text-xs font-bold text-[#c2f13c] backdrop-blur-md">
                        <i class="fa-solid fa-certificate text-[11px]"></i>
                        <span>ISO 15189 Compliant Diagnostic Operating System</span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.1]">
                        Precise Diagnostics. <br>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#c2f13c] via-emerald-300 to-teal-200">
                            Unified in Real Time.
                        </span>
                    </h1>

                    <p class="text-base sm:text-lg text-slate-200 font-normal max-w-2xl leading-relaxed">
                        An intelligent medical operations platform connecting <strong>Collection Centers</strong>, <strong>Pathology Laboratories</strong>, and <strong>Diagnostic Imaging</strong> on one live database. Automated biomarker reference ranges, electronic pathologist verification, and instant patient reporting.
                    </p>

                    <!-- Interactive CTAs -->
                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <a href="/login.php" class="lp-btn lp-btn--lime">
                            <i class="fa-solid fa-right-to-bracket mr-1"></i>
                            <span>Launch Staff Portal</span>
                            <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                        </a>

                        <a href="#portals" class="lp-btn lp-btn--ghost">
                            <i class="fa-solid fa-layer-group text-slate-400 mr-1"></i>
                            <span>Explore 3 Portals</span>
                        </a>
                    </div>

                    <!-- Live Trust Metrics Pill -->
                    <div class="pt-6 border-t border-slate-700/60 grid grid-cols-3 gap-4 max-w-lg">
                        <div>
                            <div class="text-2xl sm:text-3xl font-black text-white">100%</div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-300 mt-0.5">Live Database</div>
                        </div>
                        <div>
                            <div class="text-2xl sm:text-3xl font-black text-[#c2f13c]">14 min</div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-300 mt-0.5">Average TAT</div>
                        </div>
                        <div>
                            <div class="text-2xl sm:text-3xl font-black text-teal-300">99.4%</div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-300 mt-0.5">Verified Precision</div>
                        </div>
                    </div>
                </div>

                <!-- Right Hero: Authentic Clinical Laboratory Photography Showcase -->
                <div class="lg:col-span-5 relative">
                    <div class="relative">
                        <!-- Primary Photography Card -->
                        <div class="relative rounded-3xl overflow-hidden border border-white/20 shadow-2xl bg-slate-900 group">
                            <img 
                                src="https://images.unsplash.com/photo-1582719471384-894fbb16e074?auto=format&fit=crop&w=1200&q=80" 
                                alt="Clinical Laboratory Diagnostic Analyzers" 
                                class="w-full h-80 sm:h-96 object-cover transition-transform duration-500 group-hover:scale-105"
                                loading="eager"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/30 to-transparent"></div>
                            
                            <!-- Overlay Information Tag -->
                            <div class="absolute bottom-4 left-4 right-4 p-4 rounded-2xl bg-slate-900/80 backdrop-blur-md border border-white/10 text-white">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#c2f13c] animate-pulse"></span>
                                        <span class="text-xs font-bold text-white uppercase tracking-wider">Automated Chemistry &amp; CBC</span>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">STAT Active</span>
                                </div>
                                <p class="text-xs text-slate-300 font-medium">Direct bidirectional analyzer interfacing with automated abnormal flag detection.</p>
                            </div>
                        </div>

                        <!-- Secondary Floating Accreditation Badge -->
                        <div class="absolute -top-4 -left-4 hidden sm:flex items-center gap-3 p-3.5 rounded-2xl bg-slate-900/90 backdrop-blur-md border border-white/15 shadow-xl text-white">
                            <div class="w-10 h-10 rounded-xl bg-[#c2f13c] text-slate-950 flex items-center justify-center font-black text-lg">
                                <i class="fa-solid fa-microscope"></i>
                            </div>
                            <div>
                                <div class="text-xs font-black">CAP &amp; ISO Calibrated</div>
                                <div class="text-[11px] text-slate-400">Zero-transcription errors</div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- The 3 Core Portals Section -->
    <section class="lp-section bg-white" id="portals">
        <div class="lp-wrap space-y-12">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <span class="lp-eyebrow">Enterprise Architecture</span>
                <h2 class="lp-section__title">Three Unified Portals. One Source of Truth.</h2>
                <p class="lp-section__lead">Staff sign in under their specific operational role with strict permission boundaries, while patient histories stay synchronized across all branches.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
                
                <!-- Portal 1: Laboratory -->
                <div class="lp-portal-card border-t-4 border-t-slate-900">
                    <div>
                        <div class="lp-portal-card__icon bg-slate-900 text-[#c2f13c]">
                            <i class="fa-solid fa-flask-vial"></i>
                        </div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-100 text-slate-800">Pathology Engine</span>
                        <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-3 mb-2">Main Laboratory</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Specimen queue management, automated biomarker calculations, reference range auto-flagging, critical value alerts, and multi-parameter test sheets.
                        </p>
                        <ul class="space-y-2 mt-5 text-xs font-medium text-slate-700">
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-slate-900"></i> Auto-expanding panels (CBC, LFT, RFT)</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-slate-900"></i> Pathologist electronic sign-offs</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-slate-900"></i> Automated Normal/High/Critical flags</li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-mono text-slate-400">lab@citylab.pk</span>
                        <a href="/login.php" class="text-xs font-extrabold text-slate-900 hover:text-emerald-700 flex items-center gap-1">Open Portal &rarr;</a>
                    </div>
                </div>

                <!-- Portal 2: Diagnostic Imaging -->
                <div class="lp-portal-card border-t-4 border-t-[#c2f13c]">
                    <div>
                        <div class="lp-portal-card__icon bg-slate-900 text-[#c2f13c]">
                            <i class="fa-solid fa-x-ray"></i>
                        </div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-900 text-[#c2f13c]">Radiology PACS</span>
                        <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-3 mb-2">Diagnostic Center</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Full workflow for Digital X-Ray, Multi-Slice CT Scan, 4D Ultrasound, and 12-Lead ECG. Structured consultant findings and printable impressions.
                        </p>
                        <ul class="space-y-2 mt-5 text-xs font-medium text-slate-700">
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-slate-900"></i> Modality queue (X-Ray, CT, US, ECG)</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-slate-900"></i> Consultant radiologist impressions</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-slate-900"></i> Branded diagnostic reports with QR</li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-mono text-slate-400">imaging@citylab.pk</span>
                        <a href="/login.php" class="text-xs font-extrabold text-slate-900 hover:text-emerald-700 flex items-center gap-1">Open Portal &rarr;</a>
                    </div>
                </div>

                <!-- Portal 3: Collection Center -->
                <div class="lp-portal-card border-t-4 border-t-emerald-600">
                    <div>
                        <div class="lp-portal-card__icon bg-emerald-50 text-emerald-700 border border-emerald-100">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-800">Front Desk &amp; Billing</span>
                        <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-3 mb-2">Collection Center</h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Walk-in patient registration, test catalog search, discount calculation, thermal payment slips, tube barcode tagging, and WhatsApp report sending.
                        </p>
                        <ul class="space-y-2 mt-5 text-xs font-medium text-slate-700">
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-700"></i> Quick registration in under 30 seconds</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-700"></i> Instant thermal bill &amp; catalog pricing</li>
                            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-emerald-700"></i> Sample collection &amp; batch transit tracking</li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-xs font-mono text-slate-400">staff@citylab.pk</span>
                        <a href="/login.php" class="text-xs font-extrabold text-emerald-700 hover:text-emerald-900 flex items-center gap-1">Open Portal &rarr;</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Modalities & Test Profiles Spectrum -->
    <section class="lp-section lp-section--muted" id="modalities">
        <div class="lp-wrap space-y-10">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <span class="lp-eyebrow">Diagnostic Capability</span>
                    <h2 class="lp-section__title">Comprehensive Testing Spectrum</h2>
                </div>
                <p class="text-xs sm:text-sm text-slate-500 max-w-md">Equipped with automated parameter profiles conforming to international reference range standards.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
                <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-sm text-center">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-2 text-lg">
                        <i class="fa-solid fa-droplet"></i>
                    </div>
                    <h4 class="font-extrabold text-slate-900 text-sm">Hematology</h4>
                    <p class="text-[11px] text-slate-500 mt-1">CBC, ESR, Blood Group, TLC</p>
                </div>

                <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-sm text-center">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mx-auto mb-2 text-lg">
                        <i class="fa-solid fa-shield-virus"></i>
                    </div>
                    <h4 class="font-extrabold text-slate-900 text-sm">Liver Function</h4>
                    <p class="text-[11px] text-slate-500 mt-1">ALT, AST, Bilirubin, Albumin</p>
                </div>

                <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-sm text-center">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-2 text-lg">
                        <i class="fa-solid fa-vial-circle-check"></i>
                    </div>
                    <h4 class="font-extrabold text-slate-900 text-sm">Renal Profile</h4>
                    <p class="text-[11px] text-slate-500 mt-1">Creatinine, Urea, Uric Acid</p>
                </div>

                <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-sm text-center">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2 text-lg">
                        <i class="fa-solid fa-cube"></i>
                    </div>
                    <h4 class="font-extrabold text-slate-900 text-sm">Diabetes</h4>
                    <p class="text-[11px] text-slate-500 mt-1">HbA1c, Fasting Sugar, RBS</p>
                </div>

                <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-sm text-center">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mx-auto mb-2 text-lg">
                        <i class="fa-solid fa-heart-pulse"></i>
                    </div>
                    <h4 class="font-extrabold text-slate-900 text-sm">Lipid Profile</h4>
                    <p class="text-[11px] text-slate-500 mt-1">Cholesterol, HDL, LDL, Trig</p>
                </div>

                <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-sm text-center">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-[#c2f13c] flex items-center justify-center mx-auto mb-2 text-lg">
                        <i class="fa-solid fa-x-ray"></i>
                    </div>
                    <h4 class="font-extrabold text-slate-900 text-sm">Imaging</h4>
                    <p class="text-[11px] text-slate-500 mt-1">X-Ray, CT, Ultrasound, ECG</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Clinical Facilities & Laboratory Infrastructure (Authentic Clinical Photography) -->
    <section class="lp-section bg-slate-900 text-white" id="facilities">
        <div class="lp-wrap space-y-12">
            <div class="max-w-2xl space-y-3">
                <span class="text-xs font-bold uppercase tracking-widest text-[#c2f13c]">Laboratory Infrastructure</span>
                <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Engineered for Clinical Rigor &amp; High-Throughput Accuracy</h2>
                <p class="text-sm sm:text-base text-slate-300">Equipped with automated diagnostic instrumentation, verified multi-point quality control, and electronic path-verification.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Facility Photo 1 -->
                <div class="rounded-2xl overflow-hidden border border-slate-700/80 bg-slate-800/80 group">
                    <div class="h-56 overflow-hidden">
                        <img 
                            src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=1200&q=80" 
                            alt="Precision Diagnostic Microscopy & Pathology" 
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                        >
                    </div>
                    <div class="p-5 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#c2f13c]"></span>
                            <span class="text-[11px] font-bold text-[#c2f13c] uppercase tracking-wider">Histology &amp; Microscopy</span>
                        </div>
                        <h4 class="text-base font-bold text-white">Diagnostic Pathology Suite</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">High-magnification optical optics for fine needle aspiration, biopsy confirmation, and hematology morphology.</p>
                    </div>
                </div>

                <!-- Facility Photo 2 -->
                <div class="rounded-2xl overflow-hidden border border-slate-700/80 bg-slate-800/80 group">
                    <div class="h-56 overflow-hidden">
                        <img 
                            src="https://images.unsplash.com/photo-1582719471384-894fbb16e074?auto=format&fit=crop&w=1200&q=80" 
                            alt="Automated Chemistry & Immunoassay Analyzers" 
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                        >
                    </div>
                    <div class="p-5 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider">Automated Analyzers</span>
                        </div>
                        <h4 class="text-base font-bold text-white">Clinical Biochemistry Bench</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">Direct bi-directional machine interfacing for multi-channel chemistry, electrolytes, and automated LFT/RFT panels.</p>
                    </div>
                </div>

                <!-- Facility Photo 3 -->
                <div class="rounded-2xl overflow-hidden border border-slate-700/80 bg-slate-800/80 group">
                    <div class="h-56 overflow-hidden">
                        <img 
                            src="https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?auto=format&fit=crop&w=1200&q=80" 
                            alt="Phlebotomy & Specimen Management" 
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                        >
                    </div>
                    <div class="p-5 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                            <span class="text-[11px] font-bold text-teal-400 uppercase tracking-wider">Cold-Chain Transit</span>
                        </div>
                        <h4 class="text-base font-bold text-white">Specimen Barcode Logistics</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">Barcode-tracked specimen vacutainers with timestamped phlebotomy collection and temperature-controlled batch transit.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4-Step Clinical Workflow -->
    <section class="lp-section bg-white" id="workflow">
        <div class="lp-wrap space-y-12">
            <div class="text-center max-w-2xl mx-auto space-y-3">
                <span class="lp-eyebrow">Seamless Pipeline</span>
                <h2 class="lp-section__title">From Specimen Intake to Verified Report</h2>
                <p class="lp-section__lead">Engineered to eliminate clinical friction, avoid transcription errors, and reduce turnaround time to under 15 minutes.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="lp-step-card">
                    <div class="lp-step-card__num">01</div>
                    <h4 class="text-lg font-extrabold text-slate-900 mb-1">Intake &amp; Billing</h4>
                    <p class="text-xs text-slate-600">Quick demographic registration, catalog tests/packages selection, and thermal payment receipt generation.</p>
                </div>

                <div class="lp-step-card">
                    <div class="lp-step-card__num">02</div>
                    <h4 class="text-lg font-extrabold text-slate-900 mb-1">Phlebotomy Draw</h4>
                    <p class="text-xs text-slate-600">Unique Lab Number assignment, sample collection timestamping, and transit barcode dispatch.</p>
                </div>

                <div class="lp-step-card">
                    <div class="lp-step-card__num">03</div>
                    <h4 class="text-lg font-extrabold text-slate-900 mb-1">Results &amp; Flagging</h4>
                    <p class="text-xs text-slate-600">Batch results entry with automated High/Low/Critical detection against age/gender reference ranges.</p>
                </div>

                <div class="lp-step-card">
                    <div class="lp-step-card__num">04</div>
                    <h4 class="text-lg font-extrabold text-slate-900 mb-1">Sign-off &amp; Delivery</h4>
                    <p class="text-xs text-slate-600">Pathologist digital verification, instant printable PDF generation, and automated WhatsApp delivery.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact & Direct Inquiries Section -->
    <section class="lp-section lp-section--muted" id="contact">
        <div class="lp-wrap">
            <div class="lp-contact-card">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                    
                    <div class="lg:col-span-5 space-y-4">
                        <span class="lp-eyebrow">Connect Directly</span>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Need assistance or system access?</h2>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Reach out to our laboratory central operations or contact the development team for inquiries, branch setup, or custom clinical integrations.
                        </p>

                        <div class="space-y-3 pt-2">
                            <div class="flex items-center gap-3 text-xs sm:text-sm text-slate-700 font-semibold">
                                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-800 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-location-dot"></i>
                                </div>
                                <span>12-A Main Boulevard, Faisalabad, Pakistan</span>
                            </div>

                            <div class="flex items-center gap-3 text-xs sm:text-sm text-slate-700 font-semibold">
                                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                    <i class="fa-brands fa-whatsapp text-base"></i>
                                </div>
                                <a href="https://wa.me/923065193582" target="_blank" rel="noopener" class="hover:underline text-emerald-700 font-bold">
                                    +92 306 5193582 (Client WhatsApp)
                                </a>
                            </div>

                            <div class="flex items-center gap-3 text-xs sm:text-sm text-slate-700 font-semibold">
                                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-phone"></i>
                                </div>
                                <a href="tel:+9242111222333" class="hover:underline">+92 42 111 222 333</a>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-7 bg-slate-50 rounded-2xl p-6 border border-slate-200/80">
                        <form method="post" action="#contact" onsubmit="event.preventDefault(); document.getElementById('msg-sent').classList.remove('hidden');" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="field-label" for="c-name">Full name</label>
                                    <input type="text" id="c-name" name="name" required class="field" placeholder="Dr. / Staff Name">
                                </div>
                                <div>
                                    <label class="field-label" for="c-phone">Phone / WhatsApp</label>
                                    <input type="tel" id="c-phone" name="phone" required class="field" placeholder="03xx-xxxxxxx">
                                </div>
                            </div>
                            <div>
                                <label class="field-label" for="c-email">Email address</label>
                                <input type="email" id="c-email" name="email" class="field" placeholder="staff@citylab.pk">
                            </div>
                            <div>
                                <label class="field-label" for="c-message">Clinical inquiry / message</label>
                                <textarea id="c-message" name="message" rows="3" required class="field" placeholder="Describe inquiry or test setup requirements..."></textarea>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <button type="submit" class="btn btn-primary px-6 py-2.5">
                                    <i class="fa-solid fa-paper-plane mr-1"></i> Send Inquiry
                                </button>
                                <a href="https://wa.me/923065193582" target="_blank" rel="noopener" class="btn btn-whatsapp text-xs py-2 px-4">
                                    <i class="fa-brands fa-whatsapp mr-1"></i> Direct WhatsApp
                                </a>
                            </div>
                            <div id="msg-sent" class="hidden p-3 rounded-xl bg-emerald-100 border border-emerald-200 text-xs font-bold text-emerald-800 flex items-center gap-2">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Inquiry transmitted successfully. Our diagnostic desk will respond promptly.</span>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Dark Obsidian Footer (Matching Application Dark Sidebar) -->
    <footer class="lp-footer">
        <div class="lp-wrap py-12">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
                
                <div class="md:col-span-6 space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="lp-nav__mark"><?= brand_logo('brand-logo brand-logo--nav') ?></span>
                        <div>
                            <strong class="text-white text-lg font-black block">Lab Dash Pro</strong>
                            <span class="text-xs text-[#c2f13c] font-bold">Diagnostic · Laboratory · Collection</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 max-w-sm leading-relaxed">
                        Precision clinical laboratory management and diagnostic imaging automation system. Designed for high reliability, zero TAT lag, and seamless multi-branch synchronization.
                    </p>
                </div>

                <div class="md:col-span-3 space-y-2 text-xs">
                    <h4 class="font-extrabold text-white text-xs uppercase tracking-wider mb-3">Portals &amp; Access</h4>
                    <p><a href="/login.php" class="hover:text-[#c2f13c]">Laboratory Portal</a></p>
                    <p><a href="/login.php" class="hover:text-[#c2f13c]">Diagnostic Center (PACS)</a></p>
                    <p><a href="/login.php" class="hover:text-[#c2f13c]">Collection Center</a></p>
                    <p><a href="/login.php" class="hover:text-[#c2f13c]">Admin Management</a></p>
                </div>

                <div class="md:col-span-3 space-y-2 text-xs">
                    <h4 class="font-extrabold text-white text-xs uppercase tracking-wider mb-3">Support &amp; Location</h4>
                    <p class="text-slate-400">12-A Main Boulevard, Faisalabad</p>
                    <p><a href="tel:+9242111222333" class="hover:text-white">+92 42 111 222 333</a></p>
                    <p><a href="https://wa.me/923065193582" class="text-emerald-400 hover:underline">WhatsApp: +92 306 5193582</a></p>
                    <p><a href="/database/setup.php" class="text-slate-500 hover:text-slate-300">Database Setup Utility</a></p>
                </div>

            </div>

            <div class="mt-12 pt-6 border-t border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
                <p>&copy; <?= date('Y') ?> Lab Dash Pro. All rights reserved.</p>
                <p class="text-slate-400">
                    Software created by <strong>Shakeel Khalid</strong>
                    &middot; <a href="https://wa.me/923283070070" target="_blank" rel="noopener" class="text-emerald-400 font-bold hover:underline">WhatsApp 0328-3070070</a>
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
