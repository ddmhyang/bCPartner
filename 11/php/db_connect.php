<?php
// db_connect.php
$db_file = 'database.db';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    // 에러 발생 시 예외(Exception)를 던지도록 설정
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 결과를 배열 형태로 편하게 가져오도록 설정
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 한국 시간 설정 (SQLite용)
    $pdo->exec("PRAGMA timezone = '+09:00'");
} catch (PDOException $e) {
    die("DB 연결 실패: " . $e->getMessage());
}
?>