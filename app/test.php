<?php

echo '<pre>';
echo 'PHP: ' . PHP_VERSION . "\n";
echo 'SAPI: ' . PHP_SAPI . "\n";
echo 'INI: ' . php_ini_loaded_file() . "\n";
echo 'PDO MySQL: ' . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "\n";
print_r(PDO::getAvailableDrivers());
echo '</pre>';
