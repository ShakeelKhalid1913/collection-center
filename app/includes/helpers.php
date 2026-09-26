<?php

declare(strict_types=1);

const FIELD_CLASS = 'field';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function brand_logo(string $class = 'brand-logo', string $alt = 'Health LMS Pro'): string
{
    return '<img src="/assets/logo.png" alt="' . e($alt) . '" class="' . e($class) . '" width="128" height="128" decoding="async">';
}

function nav_item(string $href, string $label, string $activeKey, string $key, ?string $badge = null, string $icon = 'fa-solid fa-circle'): string
{
    $active = $activeKey === $key ? ' is-active' : '';
    $badgeHtml = $badge
        ? '<span class="ml-auto min-w-[1.25rem] rounded-full bg-teal-100 px-1.5 py-0.5 text-center text-[10px] font-bold text-teal-800">' . e($badge) . '</span>'
        : '';
    $iconHtml = '<i class="' . e($icon) . ' nav-link__icon" aria-hidden="true"></i>';
    return sprintf(
        '<a href="%s" class="nav-link%s">%s<span class="nav-link__text">%s</span>%s</a>',
        e($href),
        $active,
        $iconHtml,
        e($label),
        $badgeHtml
    );
}

function nav_group(string $title, array $items): string
{
    $html = $title !== '' ? '<p class="nav-section">' . e($title) . '</p>' : '';
    foreach ($items as $item) {
        $html .= $item;
    }
    return $html;
}

function status_badge(string $status): string
{
    $map = [
        'pending' => 'badge-pending',
        'collected' => 'badge-collected',
        'received' => 'badge-received',
        'processing' => 'badge-processing',
        'completed' => 'badge-completed',
        'verified' => 'badge-verified',
        'critical' => 'badge-critical',
        'paid' => 'badge-paid',
        'partial' => 'badge-partial',
        'active' => 'badge-active',
        'normal' => 'badge-default',
        'urgent' => 'badge-pending',
        'stat' => 'badge-critical',
    ];
    $class = $map[strtolower($status)] ?? 'badge-default';
    return '<span class="badge ' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function stat_card(string $label, string $value, string $hint = '', string $tone = 'teal', string $icon = 'fa-solid fa-chart-simple'): string
{
    $tones = ['teal', 'blue', 'amber', 'violet', 'rose'];
    if (!in_array($tone, $tones, true)) {
        $tone = 'teal';
    }
    $label = e($label);
    $value = e($value);
    $hint = e($hint);
    $icon = e($icon);
    return <<<HTML
    <div class="stat-card stat-card--{$tone}">
        <div class="stat-card__icon" aria-hidden="true"><i class="{$icon}"></i></div>
        <div>
            <div class="stat-card__label">{$label}</div>
            <div class="stat-card__value">{$value}</div>
            <div class="stat-card__hint">{$hint}</div>
        </div>
    </div>
    HTML;
}

function page_header(string $title, string $subtitle = '', ?string $actionHtml = null): string
{
    $title = e($title);
    $subtitle = e($subtitle);
    $action = $actionHtml ? '<div class="flex shrink-0 flex-wrap gap-2">' . $actionHtml . '</div>' : '';
    return <<<HTML
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">{$title}</h1>
            <p class="page-subtitle">{$subtitle}</p>
        </div>
        {$action}
    </div>
    HTML;
}

function btn_primary(string $href, string $label, string $icon = 'fa-solid fa-arrow-right'): string
{
    return '<a href="' . e($href) . '" class="btn btn-primary"><i class="' . e($icon) . '" aria-hidden="true"></i> ' . e($label) . '</a>';
}

function btn_secondary(string $href, string $label, string $icon = 'fa-solid fa-arrow-up-right-from-square'): string
{
    return '<a href="' . e($href) . '" class="btn btn-secondary"><i class="' . e($icon) . '" aria-hidden="true"></i> ' . e($label) . '</a>';
}

function btn_submit(string $label, string $extraClass = '', string $icon = 'fa-solid fa-floppy-disk'): string
{
    return '<button type="submit" class="btn btn-primary ' . e($extraClass) . '"><i class="' . e($icon) . '" aria-hidden="true"></i> ' . e($label) . '</button>';
}

function link_action(string $label, string $icon = 'fa-solid fa-pen-to-square'): string
{
    return '<button type="button" class="link-action"><i class="' . e($icon) . '" aria-hidden="true"></i> ' . e($label) . '</button>';
}

function user_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $initials .= strtoupper(substr($p, 0, 1));
    }
    return $initials !== '' ? $initials : 'U';
}
