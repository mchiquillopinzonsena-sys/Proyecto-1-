<?php
/**
 * Database Connection Handler
 */

namespace Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    
    public static function connect(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }
        
        $config = require __DIR__ . '/../config/database.php';
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['name'],
                $config['charset']
            );
            
            self::$connection = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => false,
                ]
            );
            
            // Set timezone
            self::$connection->exec("SET SESSION time_zone = 'America/Bogota'");
            
            return self::$connection;
        } catch (PDOException $e) {
            die('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }
    
    public static function getInstance(): PDO
    {
        return self::connect();
    }
}
