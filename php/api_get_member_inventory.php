<?php
include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$response = ['status' => 'success', 'data' => []];

$member_id = $_GET['member_id'] ?? null;

if (empty($member_id)) {
    $response['status'] = 'error';
    $response['message'] = '필수 값(member_id)이 누락되었습니다.';
} else {
    try {
        $sql = "SELECT 
                    T1.item_id, 
                    T1.quantity,
                    T2.item_name
                FROM youth_inventory AS T1
                JOIN youth_items AS T2 ON T1.item_id = T2.item_id
                WHERE T1.member_id = ?
                ORDER BY T2.item_name";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
        $inventory_list = $stmt->fetchAll();
        
        $response['data'] = $inventory_list;

    } catch (Exception $e) {
        $response['status'] = 'error';
        $response['message'] = '인벤토리 조회 실패: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>