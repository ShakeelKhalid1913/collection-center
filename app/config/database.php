<?php

declare(strict_types=1);

/**
 * MariaDB Database Configuration
 * Supports DB_* and Render MySQL Blueprint MYSQL_* env vars.
 */
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
