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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;0,900;1,400;1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['"Roboto"', 'system-ui', '-apple-system', 'sans-serif'],
              display: ['"Roboto"', 'system-ui', '-apple-system', 'sans-serif'],
            },
            colors: {
              dash: {
                dark: '#0c1322',
                sidebar: '#0f172a',
                card: '#1e293b',
                lime: '#c2f13c',
                'lime-dark': '#1c2807',
                'lime-hover': '#b2e62a',
                coral: '#f87171',
                amber: '#fbbf24',
                blue: '#2563eb',
                cyan: '#06b6d4',
                purple: '#8b5cf6',
                canvas: '#f4f6fb',
              }
            },
            borderRadius: {
              '2xl': '1rem',
              '3xl': '1.5rem',
              '4xl': '2rem',
            },
            boxShadow: {
              'card': '0 4px 25px -4px rgba(15, 23, 42, 0.05), 0 2px 10px -2px rgba(15, 23, 42, 0.03)',
              'card-hover': '0 20px 35px -8px rgba(15, 23, 42, 0.09), 0 4px 14px -3px rgba(15, 23, 42, 0.04)',
              'glow-lime': '0 0 20px rgba(194, 241, 60, 0.4)',
              'glow-blue': '0 0 20px rgba(37, 99, 235, 0.35)',
            }
          }
        }
      }
    </script>
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
    $msg = rawurlencode('Hello, I need help with Lab Dash Pro.');

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
