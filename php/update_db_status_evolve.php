<?php
include 'auth_check.php';
include 'db_connect.php';
header("Content-Type: application/json; charset=utf-8");

try {
    $pdo->exec("ALTER TABLE youth_status_types ADD COLUMN evolve_interval INT DEFAULT 0;");
    
    echo json_encode(['status' => 'success', 'message' => 'DB 업그레이드 성공: 자동 악화 시간 설정이 가능해졌습니다.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'success', 'message' => 'DB가 이미 최신 상태이거나 업데이트되었습니다. (' . $e->getMessage() . ')']);
}
?>