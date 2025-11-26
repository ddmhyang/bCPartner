<?php
include 'auth_check.php'; 
include 'db_connect.php'; 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['member_id']) || !isset($input['points']) || !isset($input['reason'])) {
    echo json_encode(['status' => 'error', 'message' => '필수 값 누락']);
    exit; 
}

$member_id = $input['member_id'];
$points_change = (int)$input['points'];
$reason = $input['reason'];

try {
    $pdo->beginTransaction();

    $stmt_m = $pdo->prepare("SELECT member_name FROM youth_members WHERE member_id = ?");
    $stmt_m->execute([$member_id]);
    $member_name = $stmt_m->fetchColumn() ?: $member_id;

    $sql_update = "UPDATE youth_members SET points = points + ? WHERE member_id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$points_change, $member_id]);

    $sql_log = "INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)";
    $stmt_log = $pdo->prepare($sql_log);
    $stmt_log->execute([$member_id, $points_change, $reason]);

    $pdo->commit();

    $action = ($points_change >= 0) ? "지급" : "회수";
    $message = "[{$member_name}] 님에게 {$points_change}P를 [{$reason}] 사유로 {$action}했습니다.";
    
    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'member_id' => $member_id,
        'points_change' => $points_change
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'DB 작업 실패: ' . $e->getMessage()]);
}
?>