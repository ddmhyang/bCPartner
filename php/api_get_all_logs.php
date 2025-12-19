<?php
include 'auth_check.php'; 
include 'db_connect.php'; 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$response = ['status' => 'success', 'data' => []];

try {
    // ✨ [수정됨] datetime(T1.log_time, '+9 hours') as log_time
    $sql = "SELECT T1.log_id, 
                   datetime(T1.log_time, '+9 hours') as log_time, 
                   T1.member_id, 
                   T1.point_change, 
                   T1.reason, 
                   T2.member_name
            FROM youth_point_logs AS T1
            LEFT JOIN youth_members AS T2 ON T1.member_id = T2.member_id
            ORDER BY T1.log_time DESC";
    $stmt = $pdo->query($sql);
    $logs_list = $stmt->fetchAll();
    
    $response['data'] = $logs_list;

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = '로그 조회 실패: ' . $e->getMessage();
}

echo json_encode($response);
?>