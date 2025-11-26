<?php
include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);
$member_id = $input['member_id'] ?? null;
$item_id = $input['item_id'] ?? null;
$quantity = (int)($input['quantity'] ?? 0);
if (empty($member_id) || empty($item_id) || $quantity <= 0) { exit; }

try {
    $pdo->beginTransaction();
    
    $stmt_m = $pdo->prepare("SELECT member_name FROM youth_members WHERE member_id = ?");
    $stmt_m->execute([$member_id]);
    $member_name = $stmt_m->fetchColumn() ?: $member_id;

    $stmt_i = $pdo->prepare("SELECT item_name FROM youth_items WHERE item_id = ?");
    $stmt_i->execute([$item_id]);
    $item_name = $stmt_i->fetchColumn() ?: "아이템(ID:{$item_id})";

    $sql_give = "INSERT INTO youth_inventory (member_id, item_id, quantity)
                 VALUES (?, ?, ?)
                 ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity";
            
    $stmt = $pdo->prepare($sql_give);
    $stmt->execute([$member_id, (int)$item_id, $quantity]);
    
    $reason_item = "관리자 지급";
    $sql_log_item = "INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)";
    $pdo->prepare($sql_log_item)->execute([$member_id, (int)$item_id, $quantity, $reason_item]);
    
    $pdo->commit();
    
    $response['message'] = "[{$member_name}] 님에게 [{$item_name}] {$quantity}개 지급 완료.";

} catch (PDOException $e) {
    $pdo->rollBack();
    $response['status'] = 'error';
    $response['message'] = "DB 오류: " . $e->getMessage();
}

echo json_encode($response);
?>