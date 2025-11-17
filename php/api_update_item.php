<?php

include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);

$response = ['status' => 'success'];

$item_id = $input['item_id'] ?? null;
$item_name = $input['item_name'] ?? null;
$item_description = $input['item_description'] ?? null;
$price = $input['price'] ?? null;
$stock = $input['stock'] ?? null;
$status = $input['status'] ?? null;

if (empty($item_id) || empty($item_name) || $price === null || $stock === null || empty($status)) {
    $response['status'] = 'error';
    $response['message'] = '필수 값이 누락되었습니다.';
} else {
    try {
        $sql = "UPDATE youth_items 
                SET item_name = ?, item_description = ?, price = ?, stock = ?, status = ?
                WHERE item_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $item_name, 
            $item_description, 
            (int)$price, 
            (int)$stock, 
            $status, 
            (int)$item_id
        ]);
        
        $response['message'] = "아이템 [{$item_name}] (이)가 수정되었습니다.";

    } catch (PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "DB 오류: " . $e->getMessage();
    }
}

echo json_encode($response);
?>