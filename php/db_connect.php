<?php
$db_file = __DIR__ . '/database.db';

$dsn = "sqlite:" . $db_file;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, null, null, $options);
     $pdo->exec('PRAGMA journal_mode = wal;');
     $pdo->exec('PRAGMA foreign_keys = ON;');
     
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e.getCode());
}
?>