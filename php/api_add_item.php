<?php


include 'auth_check.php'; 
include 'db_connect.php'; 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

include 'db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$response = ['status' => 'success'];
$item_name = $input['item_name'] ?? '';
$item_description = $input['item_description'] ?? '';
$price = (int)($input['price'] ?? 0);
$stock = (int)($input['stock'] ?? -1);
$status = $input['status'] ?? 'selling';

if (empty($item_name) || $price < 0 || $stock < -1) {
    $response['status'] = 'error';
    $response['message'] = '필수 값(아이템 이름, 가격 0 이상, 재고 -1 이상)이 잘못되었습니다.';

} else {
    try {
        $sql = "INSERT INTO youth_items (item_name, item_description, price, stock, status) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_name, $item_description, $price, $stock, $status]);
        
        $response['message'] = "아이템 [{$item_name}] 이(가) 등록되었습니다.";

    } catch (PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "DB 오류: " . $e->getMessage();
    }
}

echo json_encode($response);
?>