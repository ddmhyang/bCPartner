<?php
include 'auth_check.php';
include 'db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);

$type_id = $input['type_id'] ?? null;
$name = $input['status_name'] ?? '';
$max_stage = (int)($input['max_stage'] ?? 1);
$default_duration = (int)($input['default_duration'] ?? -1);
$can_evolve = (int)($input['can_evolve'] ?? 0);
$evolve_interval = (int)($input['evolve_interval'] ?? 0);

if (empty($type_id) || empty($name)) {
    echo json_encode(['status' => 'error', 'message' => '필수 값이 누락되었습니다.']);
    exit;
}

try {
    $sql = "UPDATE youth_status_types 
            SET status_name = ?, max_stage = ?, default_duration = ?, can_evolve = ?, evolve_interval = ?
            WHERE type_id = ?";
    
    $pdo->prepare($sql)->execute([$name, $max_stage, $default_duration, $can_evolve, $evolve_interval, $type_id]);
    
    echo json_encode(['status' => 'success', 'message' => "상태 정보가 수정되었습니다."]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => "DB 오류: " . $e->getMessage()]);
}
?>