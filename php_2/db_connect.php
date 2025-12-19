<?php
date_default_timezone_set('Asia/Seoul');
$db_file = __DIR__ . '/database.db';

if (!file_exists($db_file)) {
    header("Location: setup.php");
    exit;
}

try {
    $pdo = new PDO("sqlite:" . $db_file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA journal_mode = wal;');
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $queries = [
        "CREATE TABLE IF NOT EXISTS `youth_members` ( `member_id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_name` VARCHAR(100) NOT NULL, `points` INT NOT NULL DEFAULT 0 );",
        "CREATE TABLE IF NOT EXISTS `youth_items` ( `item_id` INTEGER PRIMARY KEY AUTOINCREMENT, `item_name` VARCHAR(255) NOT NULL, `item_description` TEXT, `price` INT NOT NULL DEFAULT 0, `stock` INT NOT NULL DEFAULT -1, `status` VARCHAR(50) DEFAULT 'selling' );",
        "CREATE TABLE IF NOT EXISTS `youth_inventory` ( `member_id` INT, `item_id` INT, `quantity` INT DEFAULT 1, PRIMARY KEY(`member_id`, `item_id`), FOREIGN KEY(`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE CASCADE, FOREIGN KEY(`item_id`) REFERENCES `youth_items`(`item_id`) ON DELETE CASCADE );",
        "CREATE TABLE IF NOT EXISTS `youth_status_types` ( `type_id` INTEGER PRIMARY KEY AUTOINCREMENT, `status_name` VARCHAR(100) NOT NULL, `max_stage` INT DEFAULT 1, `stage_intervals` TEXT, `default_duration` INT DEFAULT -1 );",
        "CREATE TABLE IF NOT EXISTS `youth_active_statuses` ( `id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` INT, `type_id` INT, `current_stage` INT DEFAULT 1, `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `next_evolve_at` TIMESTAMP NULL, FOREIGN KEY(`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE CASCADE, FOREIGN KEY(`type_id`) REFERENCES `youth_status_types`(`type_id`) ON DELETE CASCADE );",
        "CREATE TABLE IF NOT EXISTS `youth_logs` ( `log_id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` INT, `log_type` VARCHAR(50), `target_name` VARCHAR(100), `change_val` TEXT, `reason` TEXT, `log_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP );"
    ];
    foreach ($queries as $sql) { $pdo->exec($sql); }
} catch (PDOException $e) { die("DB 오류: " . $e->getMessage()); }
?>