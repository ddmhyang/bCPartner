<?php

include 'auth_check.php'; 
include 'db_connect.php'; 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

include 'db_connect.php';

$response = ['status' => 'success', 'data' => []];

try {
$sql = "SELECT item_id, item_name, item_description, price, stock, status 
            FROM youth_items 
            ORDER BY item_id ASC";
    $stmt = $pdo->query($sql);
    $items_list = $stmt->fetchAll();
    
    $response['data'] = $items_list;

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = '아이템 목록 조회 실패: ' . $e->getMessage();
}

echo json_encode($response);
?>