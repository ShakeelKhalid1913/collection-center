<?php

declare(strict_types=1);

function render_head(string $title): void
{
    echo <<<HTML
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{$title}</title>
    <link rel="icon" href="/assets/logo.png" type="image/png">
    <link rel="shortcut icon" href="/assets/logo.png" type="image/png">
    <link rel="apple-touch-icon" href="/assets/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/app.css">
    HTML;
}

function floating_whatsapp_button(string $phone = '+92 306 5193582'): string
{
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    if (str_starts_with($digits, '0')) {
        $digits = '92' . substr($digits, 1);
    }
    $display = e($phone);
    $href = 'https://wa.me/' . $digits;
    $msg = rawurlencode('Hello, I need help with Health LMS Pro.');

    return <<<HTML
    <div class="wa-float no-print">
        <a href="{$href}?text={$msg}" class="wa-float__btn" target="_blank" rel="noopener" aria-label="WhatsApp {$display}">
            <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
            <span class="wa-float__label">
                <span class="wa-float__title">WhatsApp</span>
                <span class="wa-float__number">{$display}</span>
            </span>
        </a>
    </div>
    HTML;
}
