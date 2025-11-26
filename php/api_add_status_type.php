<?php
include 'auth_check.php';
include 'db_connect.php';
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);
$name = $input['status_name'] ?? '';
$duration = (int)($input['default_duration'] ?? -1);
$max_stage = (int)($input['max_stage'] ?? 1);
$can_evolve = (int)($input['can_evolve'] ?? 0);
$evolve_interval = (int)($input['evolve_interval'] ?? 0);

if (empty($name)) {
    echo json_encode(['status' => 'error', 'message' => '상태 이름이 필요합니다.']);
    exit;
}

try {
    $sql = "INSERT INTO youth_status_types (status_name, default_duration, max_stage, can_evolve, evolve_interval) VALUES (?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$name, $duration, $max_stage, $can_evolve, $evolve_interval]);
    echo json_encode(['status' => 'success', 'message' => "상태 [{$name}] 등록 완료."]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>