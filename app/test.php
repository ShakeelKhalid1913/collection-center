<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_auth('admin');
if (!is_super_admin()) {
    http_response_code(403);
    exit('Super Admin required');
}

echo '<pre>';
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n";
echo 'INI: ' . php_ini_loaded_file() . "\n";
echo 'PDO MySQL: ' . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "\n";
print_r(PDO::getAvailableDrivers());
echo '</pre>';
