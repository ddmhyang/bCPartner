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

if (empty($item_id)) {
    $response['status'] = 'error';
    $response['message'] = '필수 값(item_id)이 누락되었습니다.';
} else {
    try {
        $sql = "DELETE FROM youth_items WHERE item_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$item_id]);
        
        $response['message'] = "[아이템 ID: {$item_id}] (이)가 삭제되었습니다.";

    } catch (PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "DB 오류: " . $e->getMessage();
    }
}

echo json_encode($response);
?>