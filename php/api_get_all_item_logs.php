<?php
include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$response = ['status' => 'success', 'data' => []];

try {
    $sql = "SELECT 
                T1.log_id,
                T1.log_time,
                T1.quantity_change,
                T1.reason,
                T2.member_name,
                T3.item_name
            FROM youth_item_logs AS T1
            LEFT JOIN youth_members AS T2 ON T1.member_id = T2.member_id
            LEFT JOIN youth_items AS T3 ON T1.item_id = T3.item_id
            ORDER BY T1.log_time DESC";
            
    $stmt = $pdo->query($sql);
    $logs_list = $stmt->fetchAll();
    
    $response['data'] = $logs_list;

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = '아이템 로그 조회 실패: ' . $e->getMessage();
}

echo json_encode($response);
?>