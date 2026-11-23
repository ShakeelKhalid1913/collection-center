<?php

declare(strict_types=1);

/**
 * MariaDB Database Configuration
 * Supports DB_* and Render MySQL Blueprint MYSQL_* env vars.
 */
// Load .env if present in root
$envFilePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFilePath) && is_readable($envFilePath)) {
    $envLines = @file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($envLines)) {
        foreach ($envLines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$ek, $ev] = explode('=', $line, 2);
            $ek = trim($ek);
            $ev = trim($ev, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($ek, $_SERVER) && !array_key_exists($ek, $_ENV)) {
                putenv("{$ek}={$ev}");
                $_ENV[$ek] = $ev;
                $_SERVER[$ek] = $ev;
            }
        }
    }
}
if (!function_exists('env_db')) {
    function env_db(string $key, ?string $default = null): ?string
    {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        return (string)$v;
    }
}

if (!function_exists('env_db_first')) {
    function env_db_first(array $keys, ?string $default = null): ?string
    {
        foreach ($keys as $key) {
            $v = env_db($key);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return $default;
    }
}

return [
    // Render private MySQL service hostname is usually the service name: "mysql"
    'host'     => env_db_first(['DB_HOST', 'MYSQL_HOST', 'MYSQL_HOSTNAME'], '127.0.0.1'),
    'port'     => (int)env_db_first(['DB_PORT', 'MYSQL_PORT', 'MYSQL_TCP_PORT'], '3306'),
    'dbname'   => env_db_first(['DB_NAME', 'MYSQL_DATABASE', 'MYSQL_DB'], 'collection_center_db'),
    'username' => env_db_first(['DB_USER', 'MYSQL_USER'], 'root'),
    'password' => env_db_first(['DB_PASS', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD'], '1913'),
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];
