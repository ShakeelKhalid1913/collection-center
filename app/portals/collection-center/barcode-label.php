<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/barcode.php';

$labNo = trim((string)($_GET['lab_no'] ?? ''));
if ($labNo === '') {
    die('Lab number required.');
}

$entry = lab_repo()->findByLabNo($labNo);
if (!$entry) {
    die('Lab entry not found.');
}

$patientName = (string)($entry['patient_name'] ?? '—');
$patientId = (string)($entry['patient_id'] ?? '—');
$tests = (string)($entry['tests'] ?? '—');
$created = date('d-M-Y H:i', strtotime((string)($entry['created_at'] ?? 'now')));
$barcodeSvg = generate_barcode_svg($labNo, 40, 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barcode Label - <?= htmlspecialchars($labNo, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        @page {
            size: 50mm 30mm;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }
        body {
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .barcode-sticker {
            width: 50mm;
            height: 30mm;
            background: #ffffff;
            border: 1px dashed #94a3b8;
            padding: 2mm 2.5mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .sticker-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-bottom: 0.5px solid #000;
            padding-bottom: 1px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .patient-info {
            font-size: 8px;
            line-height: 1.15;
            margin-top: 1px;
            font-weight: 700;
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .barcode-img {
            width: 100%;
            display: flex;
            justify-content: center;
            margin: 1px 0;
        }
        .sticker-footer {
            display: flex;
            justify-content: space-between;
            font-size: 7px;
            font-weight: 600;
            color: #334155;
            border-top: 0.5px solid #000;
            padding-top: 1px;
        }
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #0f172a;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        @media print {
            body {
                background: #fff;
                min-height: auto;
            }
            .controls {
                display: none;
            }
            .barcode-sticker {
                border: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <button class="btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Sticker (50x30mm)</button>
    </div>

    <div class="barcode-sticker">
        <div class="sticker-header">
            <span><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></span>
            <span>MR: <?= htmlspecialchars($patientId, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="patient-info">
            <?= htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="barcode-img">
            <?= $barcodeSvg ?>
        </div>

        <div class="sticker-footer">
            <span style="max-width:32mm;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($tests, ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars($created, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</body>
</html>
