<?php
$db_file = __DIR__ . '/database.db';

if (!file_exists($db_file)) {
    header("Location: setup.php");
    exit;
}

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
     
     $commands = [
        "CREATE TABLE IF NOT EXISTS `youth_status_types` ( `type_id` INTEGER PRIMARY KEY AUTOINCREMENT, `status_name` VARCHAR(100) NOT NULL, `default_duration` INT DEFAULT -1, `max_stage` INT DEFAULT 1, `can_evolve` INT DEFAULT 0, `evolve_interval` INT DEFAULT 0 );",
        "CREATE TABLE IF NOT EXISTS `youth_active_statuses` ( `id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` VARCHAR(100) NOT NULL, `type_id` INTEGER NOT NULL, `current_stage` INT DEFAULT 1, `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `expires_at` TIMESTAMP NULL, FOREIGN KEY (`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE CASCADE, FOREIGN KEY (`type_id`) REFERENCES `youth_status_types`(`type_id`) ON DELETE CASCADE );",
        "CREATE TABLE IF NOT EXISTS `youth_status_logs` ( `log_id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` VARCHAR(100), `status_name` VARCHAR(100), `action_detail` VARCHAR(255), `log_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE SET NULL );"
     ];
     foreach ($commands as $cmd) { $pdo->exec($cmd); }

     try {
        $pdo->exec("ALTER TABLE youth_status_types ADD COLUMN evolve_interval INT DEFAULT 0;");
     } catch (Exception $e) {}

     try {
        $pdo->exec("ALTER TABLE youth_status_types ADD COLUMN can_evolve INT DEFAULT 0;");
     } catch (Exception $e) {}

} catch (\PDOException $e) {
     echo "DB 연결 오류: " . $e->getMessage();
     exit;
}
?>