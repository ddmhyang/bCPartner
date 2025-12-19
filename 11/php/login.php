<?php
session_start(); 
include 'db_connect.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        try {
            $sql = "SELECT * FROM youth_admin_users WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $admin_user = $stmt->fetch();

            if ($admin_user && password_verify($password, $admin_user['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin_user['username'];
                
                header("Location: index.php");
                exit;
            
            } else {
                $error_message = "아이디 또는 비밀번호가 틀렸습니다.";
            }

        } catch (PDOException $e) {
            $error_message = "데이터베이스 오류: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>관리자 로그인</title>
    <style>
        body { font-family: sans-serif; display: grid; place-items: center; min-height: 100vh; }
        form { border: 1px solid #ccc; padding: 20px; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input { padding: 8px; width: 250px; }
        .error { color: red; }
    </style>
</head>
<body>
    <form action="login.php" method="POST">
        <h2>밴드 상점 관리자 로그인</h2>
        <div class="form-group">
            <label for="username">아이디</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">비밀번호</label>
            <input type="password" id="password" name="password" required>
        </div>
        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <button type="submit">로그인</button>
    </form>
</body>
</html>