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
$item_id = $input['item_id'] ?? null;
if (empty($member_id) || empty($item_id)) { /*  */ exit; }

try {
    $pdo->beginTransaction();

    $sql_get = "SELECT quantity FROM youth_inventory WHERE member_id = ? AND item_id = ?";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute([$member_id, (int)$item_id]);
    $item = $stmt_get->fetch();
    
    $deleted_quantity = 0;
    if ($item) { $deleted_quantity = (int)$item['quantity']; }
    if ($deleted_quantity <= 0) { throw new Exception("삭제할 아이템이 없습니다. (이미 0개)"); }
    
    $sql_delete = "DELETE FROM youth_inventory WHERE member_id = ? AND item_id = ?";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([$member_id, (int)$item_id]);
    
    $reason_item = "관리자가 회수/삭제";
    $sql_log_item = "INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)";
    $pdo->prepare($sql_log_item)->execute([$member_id, (int)$item_id, -$deleted_quantity, $reason_item]);
    
    $pdo->commit();
    
    $response['message'] = "[{$member_id}] 님의 [아이템 ID: {$item_id}] (수량: {$deleted_quantity}개) 삭제 완료.";

} catch (Exception $e) {
    $pdo->rollBack();
    $response['status'] = 'error';
    $response['message'] = "DB 오류: " . $e->getMessage();
}

echo json_encode($response);
?>