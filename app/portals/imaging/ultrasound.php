<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_auth(['imaging', 'admin']);

header('Location: /portals/pending-scans.php?modality=us');
exit;
