<?php
session_start();
$message = '';
$setup_success = false;
$db_file = __DIR__ . '/database.db';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $admin_username = $_POST['username'];
        $admin_password = $_POST['password'];

        if (empty($admin_username) || empty($admin_password)) {
            $message = "<p style='color:red;'>오류: 관리자 ID와 비밀번호를 모두 입력해야 합니다.</p>";
        } else {
            try {
                $dsn = "sqlite:" . $db_file;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                $pdo = new PDO($dsn, null, null, $options);
                $pdo->exec('PRAGMA journal_mode = wal;');
                $pdo->exec('PRAGMA foreign_keys = ON;');

                $commands = [
                    "CREATE TABLE IF NOT EXISTS `youth_admin_users` ( `username` VARCHAR(100) PRIMARY KEY, `password_hash` VARCHAR(255) NOT NULL );",
                    "CREATE TABLE IF NOT EXISTS `youth_gambling_games` ( `game_id` INTEGER PRIMARY KEY AUTOINCREMENT, `game_name` VARCHAR(100) NOT NULL, `description` TEXT, `outcomes` TEXT NOT NULL );",
                    "CREATE TABLE IF NOT EXISTS `youth_items` ( `item_id` INTEGER PRIMARY KEY AUTOINCREMENT, `item_name` VARCHAR(255) NOT NULL, `item_description` TEXT, `price` INT NOT NULL DEFAULT 0, `stock` INT NOT NULL DEFAULT -1, `status` VARCHAR(50) NOT NULL DEFAULT 'selling' );",
                    "CREATE TABLE IF NOT EXISTS `youth_members` ( `member_id` VARCHAR(100) PRIMARY KEY, `member_name` VARCHAR(100) NOT NULL, `points` INT NOT NULL DEFAULT 0 );",
                    "CREATE TABLE IF NOT EXISTS `youth_inventory` ( `member_id` VARCHAR(100), `item_id` INT, `quantity` INT NOT NULL DEFAULT 1, PRIMARY KEY (`member_id`, `item_id`), FOREIGN KEY (`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE CASCADE, FOREIGN KEY (`item_id`) REFERENCES `youth_items`(`item_id`) ON DELETE CASCADE );",
                    "CREATE TABLE IF NOT EXISTS `youth_point_logs` ( `log_id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` VARCHAR(100), `point_change` INT NOT NULL, `reason` VARCHAR(255), `log_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE SET NULL );",
                    "CREATE TABLE IF NOT EXISTS `youth_item_logs` ( `log_id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` VARCHAR(100), `item_id` INT, `quantity_change` INT NOT NULL, `reason` VARCHAR(255), `log_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE SET NULL, FOREIGN KEY (`item_id`) REFERENCES `youth_items`(`item_id`) ON DELETE SET NULL );",
                    
                    "CREATE TABLE IF NOT EXISTS `youth_status_types` ( `type_id` INTEGER PRIMARY KEY AUTOINCREMENT, `status_name` VARCHAR(100) NOT NULL, `default_duration` INT DEFAULT -1, `max_stage` INT DEFAULT 1, `can_evolve` INT DEFAULT 0, `evolve_interval` INT DEFAULT 0 );",
                    "CREATE TABLE IF NOT EXISTS `youth_active_statuses` ( `id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` VARCHAR(100) NOT NULL, `type_id` INTEGER NOT NULL, `current_stage` INT DEFAULT 1, `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `expires_at` TIMESTAMP NULL, FOREIGN KEY (`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE CASCADE, FOREIGN KEY (`type_id`) REFERENCES `youth_status_types`(`type_id`) ON DELETE CASCADE );",
                    "CREATE TABLE IF NOT EXISTS `youth_status_logs` ( `log_id` INTEGER PRIMARY KEY AUTOINCREMENT, `member_id` VARCHAR(100), `status_name` VARCHAR(100), `action_detail` VARCHAR(255), `log_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`member_id`) REFERENCES `youth_members`(`member_id`) ON DELETE SET NULL );"
                ];
                foreach ($commands as $command) {
                    $pdo->exec($command);
                }
                
                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                
                $sql_admin = "INSERT OR IGNORE INTO youth_admin_users (username, password_hash) VALUES (?, ?)";
                $stmt_admin = $pdo->prepare($sql_admin);
                $stmt_admin->execute([$admin_username, $password_hash]);

                if ($stmt_admin->rowCount() > 0) {
                    $message = "<h2>✅ 설정 완료!</h2>";
                    $message .= "<p style='color:green; font-weight:bold;'>[{$admin_username}] 계정이 성공적으로 생성되었습니다.</p>";
                    $message .= "<a href='login.php' class='btn-link'>로그인 페이지로 이동</a>";
                    $setup_success = true;
                } else {
                    $message = "<p style='color:orange;'>[{$admin_username}] 계정은 이미 존재합니다.</p>";
                    $message .= "<a href='login.php' class='btn-link'>로그인 페이지로 이동</a>";
                    $setup_success = true; 
                }
                
            } catch (Exception $e) {
                $message = "<h1>❌ 설정 실패!</h1>";
                $message .= "<p style='color:red;'>오류 발생: " . $e->getMessage() . "</p>";
            }
        }
    }
} else {
    if (file_exists($db_file)) {
        $message = "<h2>⚠️ 이미 설치됨</h2>";
        $message .= "<p>'database.db' 파일이 이미 존재합니다.</p>";
        $message .= "<a href='login.php' class='btn-link'>로그인 페이지로 이동</a>";
        $setup_success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>시스템 최초 설정</title>
    <style>
        body { font-family: sans-serif; display: grid; place-items: center; min-height: 90vh; background-color: #f4f4f4; }
        .container { background: #fff; border: 1px solid #ccc; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); width: 350px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { padding: 10px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
        button:hover { background-color: #0056b3; }
        .message-box { margin-top: 20px; padding: 15px; border-radius: 5px; background-color: #f0f0f0; text-align: center; }
        
        /* [추가됨] 로그인 버튼 스타일 */
        .btn-link {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: #28a745; /* 초록색으로 구분 */
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            box-sizing: border-box;
            margin-top: 15px;
            font-weight: bold;
        }
        .btn-link:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>밴드 상점 시스템 설치</h1>
        
        <?php if ($setup_success): ?>
            <div class="message-box"><?php echo $message; ?></div>
        <?php else: ?>
            <p>시스템을 사용하기 위한 '최초 관리자' 계정을 생성합니다.</p>
            <form action="setup.php" method="POST">
                <div class="form-group">
                    <label for="username">관리자 ID</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">관리자 비밀번호</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">설치 및 계정 생성</button>
            </form>
             <?php if ($message): ?>
                <div class="message-box"><?php echo $message; ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>