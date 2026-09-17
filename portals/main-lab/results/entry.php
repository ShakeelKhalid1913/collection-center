<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/layout.php';
require_once __DIR__ . '/../../../includes/components.php';

$content = page_header('Results Entry', 'Lab No L-2026-0891 · Ayesha Khan · CBC');

$content .= card(
    '<div class="overflow-x-auto">' .
    '<table class="min-w-full text-sm"><thead class="bg-slate-50"><tr>' .
    '<th class="px-4 py-3 text-left">Parameter</th><th class="px-4 py-3 text-left">Result</th><th class="px-4 py-3 text-left">Unit</th><th class="px-4 py-3 text-left">Normal range</th>' .
    '</tr></thead><tbody>' .
    '<tr class="border-t"><td class="px-4 py-3">Hemoglobin</td><td class="px-4 py-3"><input class="field w-28" value="14.2"></td><td class="px-4 py-3">g/dL</td><td class="px-4 py-3">13–17</td></tr>' .
    '<tr class="border-t"><td class="px-4 py-3">WBC</td><td class="px-4 py-3"><input class="field w-28" value="7.1"></td><td class="px-4 py-3">10³/µL</td><td class="px-4 py-3">4–11</td></tr>' .
    '<tr class="border-t"><td class="px-4 py-3">Platelets</td><td class="px-4 py-3"><input class="field w-28" value="250"></td><td class="px-4 py-3">10³/µL</td><td class="px-4 py-3">150–450</td></tr>' .
    '</tbody></table></div>' .
    '<div class="border-t border-gray-300 p-4 flex flex-wrap gap-2">' .
    btn_submit('Save draft') .
    btn_secondary('/portals/main-lab/results/verification.php', 'Send to verification') .
    '</div>'
);

render_page('Results Entry', 'main-lab', 'results-entry', $content);
