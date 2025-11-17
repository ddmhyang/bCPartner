<?php
include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");
$input = json_decode(file_get_contents('php://input'), true);
$response = ['status' => 'success'];
$member_id = $input['member_id'] ?? null;
$new_member_name = $input['member_name'] ?? null;
$new_points = $input['points'] ?? null;
if (empty($member_id) || empty($new_member_name) || $new_points === null) { /*  */ exit; }

try {
    $pdo->beginTransaction();
    
    $sql_get = "SELECT points FROM youth_members WHERE member_id = ?";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([$member_id]);
    $member = $stmt_get->fetch();
    
    if (!$member) { throw new Exception("존재하지 않는 회원입니다."); }
    
    $old_points = (int)$member['points'];
    $new_points_int = (int)$new_points;
    $point_change = $new_points_int - $old_points;

    if ($point_change != 0) {
        $sql_log = "INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)";
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute([$member_id, $point_change, "관리자 포인트 수정"]);
    }
    
    $sql_update = "UPDATE youth_members SET member_name = ?, points = ? WHERE member_id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$new_member_name, $new_points_int, $member_id]);
    
    $pdo->commit();
    
    $response['message'] = "회원 [{$new_member_name}] 님의 정보가 수정되었습니다.";
    if ($point_change != 0) {
         $response['message'] .= " (포인트 변동: {$point_change}P 로그 기록됨)";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    $response['status'] = 'error';
    $response['message'] = "DB 오류: " . $e->getMessage();
}

echo json_encode($response);
?>