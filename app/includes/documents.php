<?php

declare(strict_types=1);

/**
 * Shared helpers for printable receipts & lab reports (header/footer branding).
 * Used by Collection Center and Laboratory portals.
 */

/**
 * Normalize lab settings for documents.
 */
function branding_settings(?string $orgId = null): array
{
    $orgId = $orgId ?? (current_user()['organization_id'] ?? 'ORG-001');
    $set = setting_repo()->getSettings($orgId);
    return [
        'name' => $set['lab_name'] ?? 'Health LMS Pro Diagnostics',
        'address' => $set['address'] ?? '',
        'phone' => $set['phone'] ?? '',
        'email' => $set['email'] ?? '',
        'header' => $set['header_text'] ?? '',
        'footer' => $set['footer_text'] ?? '',
        'logo_text' => $set['logo_text'] ?? 'HLP',
        'bill_header' => $set['bill_header_text'] ?? $set['header_text'] ?? '',
        'bill_footer' => $set['bill_footer_text'] ?? $set['footer_text'] ?? '',
    ];
}

/**
 * Load entry + patient + result lines for a lab number (for report/receipt preview).
 *
 * @return array{settings: array, patient: array, entry: ?array, lines: array}
 */
function load_document_context(?string $labNo): array
{
    $settings = branding_settings();
    $entry = ($labNo !== null && $labNo !== '') ? lab_repo()->findByLabNo($labNo) : null;

    if (!$entry) {
        $all = lab_repo()->getAll();
        $entry = $all[0] ?? null;
    }

    $patient = [
        'id' => '',
        'name' => '—',
        'title' => '',
        'age' => '',
        'gender' => '',
        'phone' => '',
        'cnic' => '',
        'blood_group' => '',
        'email' => '',
        'address' => '',
    ];

    if ($entry) {
        $patient['name'] = $entry['patient_name'] ?? '—';
        $patient['id'] = $entry['patient_id'] ?? '';
        $p = patient_repo()->findById((string)($entry['patient_id'] ?? ''));
        if ($p) {
            $patient = [
                'id' => $p['patient_no'] ?? $p['id'] ?? '',
                'name' => trim(($p['title'] ?? '') . ' ' . ($p['full_name'] ?? '')),
                'title' => $p['title'] ?? '',
                'age' => (string)($p['age'] ?? ''),
                'gender' => $p['gender'] ?? '',
                'phone' => $p['phone'] ?? '',
                'cnic' => $p['cnic'] ?? '',
                'blood_group' => $p['blood_group'] ?? '',
                'email' => $p['email'] ?? '',
                'address' => $p['address'] ?? '',
            ];
        }
    }

    $lines = [];
    if ($entry) {
        $results = result_repo()->getResultsByLabNo((string)$entry['lab_no']);
        foreach ($results as $r) {
            if (($r['value'] ?? '') === '' && ($r['parameter'] ?? '') === '') {
                continue;
            }
            $lines[] = [
                'test' => $r['parameter'] ?: ($r['test'] ?? 'Result'),
                'result' => $r['value'] ?? 'Pending',
                'unit' => $r['unit'] ?? '—',
                'range' => '—',
                'flag' => $r['flag'] ?? '',
            ];
        }
        if ($lines === []) {
            $tests = array_filter(array_map('trim', explode(',', (string)($entry['tests'] ?? ''))));
            foreach ($tests as $tName) {
                $meta = null;
                foreach (mock('mock_tests') as $t) {
                    if (strcasecmp($t['name'], $tName) === 0 || strcasecmp($t['code'], $tName) === 0) {
                        $meta = $t;
                        break;
                    }
                }
                $lines[] = [
                    'test' => $tName,
                    'result' => 'Pending',
                    'unit' => $meta['unit'] ?? '—',
                    'range' => $meta['range'] ?? '—',
                    'flag' => '',
                ];
            }
        }
    }

    return [
        'settings' => $settings,
        'patient' => $patient,
        'entry' => $entry,
        'lines' => $lines,
    ];
}

function render_branded_header(array $settings, bool $forBill = false): string
{
    $logoImg = brand_logo('brand-logo brand-logo--report');
    $name = e($settings['name'] ?? '');
    $header = e($forBill ? ($settings['bill_header'] ?: $settings['header'] ?? '') : ($settings['header'] ?? ''));
    $address = e($settings['address'] ?? '');
    $phone = e($settings['phone'] ?? '');
    $email = e($settings['email'] ?? '');

    $extra = '';
    if ($address !== '') {
        $extra .= '<p class="font-medium">' . $address . '</p>';
    }
    if ($phone !== '') {
        $extra .= '<p>' . $phone . '</p>';
    }
    if ($email !== '') {
        $extra .= '<p>' . $email . '</p>';
    }

    return <<<HTML
    <div class="border-b-2 border-black pb-4">
        <div class="flex items-start justify-between gap-4">
            <div class="h-14 w-14 overflow-hidden border-2 border-black shrink-0">{$logoImg}</div>
            <div class="text-right text-sm leading-snug flex-1">
                <p class="text-lg font-bold">{$name}</p>
                <p>{$header}</p>
                {$extra}
            </div>
        </div>
    </div>
    HTML;
}

function render_branded_footer(array $settings, bool $forBill = false): string
{
    $footer = e($forBill ? ($settings['bill_footer'] ?: $settings['footer'] ?? '') : ($settings['footer'] ?? ''));
    $labLine = $footer !== ''
        ? '<p class="mt-4 text-xs leading-relaxed">' . $footer . '</p>'
        : '';
    // Always show vendor credit under lab footer (receipts & reports)
    return $labLine . software_credit_footer(true);
}

function render_report_document(array $settings, array $patient, array $resultLines, ?array $entry = null): string
{
    $headerHtml = render_branded_header($settings, false);
    $footerHtml = render_branded_footer($settings, false);

    $pname = e($patient['name'] ?? '—');
    $pid = e($patient['id'] ?? '—');
    $page = e((string)($patient['age'] ?? '—'));
    $pgender = e($patient['gender'] ?? '—');
    $pphone = e($patient['phone'] ?? '—');
    $labNo = e($entry['lab_no'] ?? '—');
    $reportDate = e(isset($entry['created_at']) ? format_date($entry['created_at']) : date('d M Y'));
    $doctor = e($entry['doctor'] ?? '—');

    // Conditional patient fields — only when filled
    $optional = '';
    if (!empty($patient['cnic'])) {
        $optional .= '<p><strong>CNIC:</strong> ' . e($patient['cnic']) . '</p>';
    }
    if (!empty($patient['blood_group'])) {
        $optional .= '<p><strong>Blood group:</strong> ' . e($patient['blood_group']) . '</p>';
    }
    if (!empty($patient['email'])) {
        $optional .= '<p><strong>Email:</strong> ' . e($patient['email']) . '</p>';
    }

    $rows = '';
    foreach ($resultLines as $line) {
        $flagRaw = strtolower((string)($line['flag'] ?? ''));
        $flag = in_array($flagRaw, ['critical', 'h', 'l'], true)
            ? '<span class="font-bold text-black">' . e(strtoupper($flagRaw === 'critical' ? 'CRITICAL' : $flagRaw)) . '</span>'
            : e(($line['flag'] ?? '') !== '' ? (string)$line['flag'] : '—');
        $rows .= '<tr class="border-b border-black">
            <td class="py-2 pr-4 text-sm">' . e($line['test'] ?? '') . '</td>
            <td class="py-2 pr-4 text-sm font-semibold">' . e($line['result'] ?? '') . '</td>
            <td class="py-2 pr-4 text-sm">' . e($line['unit'] ?? '') . '</td>
            <td class="py-2 pr-4 text-sm">' . e($line['range'] ?? '') . '</td>
            <td class="py-2 text-sm">' . $flag . '</td>
        </tr>';
    }
    if ($rows === '') {
        $rows = '<tr><td colspan="5" class="py-4 text-sm text-center">No tests on this entry yet.</td></tr>';
    }

    return <<<HTML
    <div class="print-area mx-auto max-w-3xl border-2 border-black bg-white p-6 text-black sm:p-8">
        {$headerHtml}

        <div class="mt-4 grid gap-2 border border-black p-4 text-sm sm:grid-cols-2">
            <p><strong>Patient:</strong> {$pname}</p>
            <p><strong>Patient ID:</strong> {$pid}</p>
            <p><strong>Age / Sex:</strong> {$page} / {$pgender}</p>
            <p><strong>Phone:</strong> {$pphone}</p>
            <p><strong>Lab No:</strong> {$labNo}</p>
            <p><strong>Report Date:</strong> {$reportDate}</p>
            <p><strong>Referring doctor:</strong> {$doctor}</p>
            {$optional}
        </div>

        <table class="mt-6 w-full border-collapse text-left">
            <thead>
                <tr class="border-b-2 border-black">
                    <th class="pb-2 text-xs font-bold uppercase">Test</th>
                    <th class="pb-2 text-xs font-bold uppercase">Result</th>
                    <th class="pb-2 text-xs font-bold uppercase">Unit</th>
                    <th class="pb-2 text-xs font-bold uppercase">Reference</th>
                    <th class="pb-2 text-xs font-bold uppercase">Flag</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>

        <div class="mt-8 border-t border-black pt-4 text-sm">
            <p class="font-semibold">Electronically prepared — verify at laboratory before clinical use.</p>
            {$footerHtml}
        </div>
    </div>
    HTML;
}

function render_receipt_document(array $settings, array $entry, array $patient): string
{
    $headerHtml = render_branded_header($settings, true);
    $footerHtml = render_branded_footer($settings, true);
    $labNo = e($entry['lab_no'] ?? '—');
    $pname = e($patient['name'] ?? ($entry['patient_name'] ?? '—'));
    $tests = e($entry['tests'] ?? '—');
    $date = e(isset($entry['created_at']) ? format_date($entry['created_at']) : date('d M Y'));
    $amount = e(format_money((float)($entry['amount'] ?? 0)));
    $paid = e(format_money((float)($entry['paid'] ?? 0)));
    $discount = e(format_money((float)($entry['discount'] ?? 0)));
    $due = e(format_money(max(0, (float)($entry['amount'] ?? 0) - (float)($entry['paid'] ?? 0))));
    $receiptNo = 'R-' . preg_replace('/\D/', '', $labNo);

    return <<<HTML
    <div class="print-area mx-auto max-w-lg border-2 border-black bg-white p-6 text-black">
        {$headerHtml}
        <p class="mt-3 text-center text-xs font-bold uppercase tracking-widest">Cash Receipt</p>
        <div class="mt-4 space-y-1 text-sm">
            <p><strong>Receipt #:</strong> {$receiptNo}</p>
            <p><strong>Lab No:</strong> {$labNo}</p>
            <p><strong>Patient:</strong> {$pname}</p>
            <p><strong>Tests:</strong> {$tests}</p>
            <p><strong>Date:</strong> {$date}</p>
        </div>
        <div class="mt-4 border-t border-black pt-3 text-sm space-y-1">
            <p class="flex justify-between"><span>Total</span><strong>{$amount}</strong></p>
            <p class="flex justify-between"><span>Discount</span><strong>{$discount}</strong></p>
            <p class="flex justify-between"><span>Paid</span><strong>{$paid}</strong></p>
            <p class="flex justify-between"><span>Due</span><strong>{$due}</strong></p>
        </div>
        <div class="mt-6 border-t border-black pt-3">{$footerHtml}</div>
    </div>
    HTML;
}
