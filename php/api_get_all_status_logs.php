<?php
include 'auth_check.php';
include 'db_connect.php'; 
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

try {
    $sql = "SELECT 
                L.log_time,
                L.member_id,
                L.status_name,
                L.action_detail,
                M.member_name
            FROM youth_status_logs AS L
            LEFT JOIN youth_members AS M ON L.member_id = M.member_id
            ORDER BY L.log_time DESC";
            
    $stmt = $pdo->query($sql);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '로그 조회 실패: ' . $e->getMessage()]);
}
?>