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
                T1.member_id, 
                T1.item_id, 
                T1.quantity,
                T2.member_name,
                T3.item_name
            FROM youth_inventory AS T1
            LEFT JOIN youth_members AS T2 ON T1.member_id = T2.member_id
            LEFT JOIN youth_items AS T3 ON T1.item_id = T3.item_id
            ORDER BY T2.member_name, T3.item_name";
    $stmt = $pdo->query($sql);
    $logs_list = $stmt->fetchAll();
    
    $response['data'] = array_filter($logs_list, function($item) {
        return $item['member_name'] !== null && $item['item_name'] !== null;
    });

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = '인벤토리 조회 실패: ' . $e->getMessage();
}

echo json_encode($response);
?>