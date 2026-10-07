<?php
/**
 * Configuración y conexión centralizada a la base de datos PostgreSQL etrib
 */

function getDbConfig() {
    $envFile = __DIR__ . '/.env';
    $config = array(
        'host' => '127.0.0.1',
        'port' => '5432',
        'database' => 'etrib',
        'username' => 'postgres',
        'password' => '1234'
    );

    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                if ($key === 'DB_HOST') $config['host'] = $val;
                if ($key === 'DB_PORT') $config['port'] = $val;
                if ($key === 'DB_DATABASE') $config['database'] = $val;
                if ($key === 'DB_USERNAME') $config['username'] = $val;
                if ($key === 'DB_PASSWORD') $config['password'] = $val;
            }
        }
    }

    return $config;
}

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = getDbConfig();
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $cfg['host'], $cfg['port'], $cfg['database']);
    
    $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    );

    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
    return $pdo;
}
