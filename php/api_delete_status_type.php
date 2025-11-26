<?php
include 'auth_check.php';
include 'db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);
$type_id = $input['type_id'] ?? null;

if (empty($type_id)) {
    echo json_encode(['status' => 'error', 'message' => '삭제할 상태 ID가 누락되었습니다.']);
    exit;
}

try {
    $sql = "DELETE FROM youth_status_types WHERE type_id = ?";
    $pdo->prepare($sql)->execute([$type_id]);
    
    echo json_encode(['status' => 'success', 'message' => "상태 종류가 삭제되었습니다."]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => "DB 오류: " . $e->getMessage()]);
}
?>