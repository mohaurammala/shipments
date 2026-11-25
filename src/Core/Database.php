<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Pdo\Mysql;

/**
 * Lazily opened database connection: requests that never touch the database never connect.
 */
final class Database
{
    private ?PDO $pdo = null;
    
    /**
     * @param Config $config Configuration used by the instance.
     * @param string|null $database Specify which DB to use between app vs tests, an audit one could be swapped in here.
     */
    public function __construct(
        private readonly Config  $config,
        private readonly ?string $database = null,
    )
    {
    }
    
    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }
    
    private function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->get('DB_HOST'),
            $this->config->int('DB_PORT', 3306),
            $this->database ?? $this->config->get('DB_DATABASE'),
        );
        
        return PDO::connect($dsn, $this->config->get('DB_USERNAME'), $this->config->get('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            Mysql::ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);
    }
}