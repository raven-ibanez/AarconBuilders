<?php
/**
 * Aarcon Builders - Database Connection & Auto-Migration
 */

require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    if (DB_DRIVER === 'sqlite') {
        $dbDir = dirname(SQLITE_FILE);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $needsSeed = !file_exists(SQLITE_FILE);

        try {
            $pdo = new PDO('sqlite:' . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Ensure tables and migrations exist
            $hasUsers = false;
            try {
                $pdo->query("SELECT 1 FROM users LIMIT 1");
                $hasUsers = true;
            } catch (Exception $e) {
                $hasUsers = false;
            }

            if ($needsSeed || !$hasUsers) {
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
            }
        } catch (PDOException $e) {
            die("Database Connection Error (SQLite): " . $e->getMessage());
        }
    } else {
        // MySQL connection
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Ensure tables exist in MySQL
            try {
                $pdo->query("SELECT 1 FROM users LIMIT 1");
            } catch (Exception $e) {
                $schemaFile = __DIR__ . '/../database/schema.sql';
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $pdo->exec($sql);
                }
            }
        } catch (PDOException $e) {
            die("Database Connection Error (MySQL): " . $e->getMessage());
        }
    }

    return $pdo;
}
