<?php
include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);
$member_id = $input['member_id'];
$item_id = (int)$input['item_id'];
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
if ($quantity <= 0) { /*  */ exit; }

try {
    $pdo->beginTransaction();

    $sql_item = "SELECT item_name, price, stock, status FROM youth_items WHERE item_id = ?";
    $stmt_item = $pdo->prepare($sql_item);
    $stmt_item->execute([$item_id]);
    $item = $stmt_item->fetch();
    if (!$item) { throw new Exception("존재하지 않는 아이템입니다."); }
    if ($item['status'] !== 'selling') { throw new Exception("판매중인 아이템이 아닙니다."); }

    if ($item['stock'] != -1 && $item['stock'] < $quantity) {
        throw new Exception("아이템 재고가 부족합니다. (남은 재고: {$item['stock']}개)");
    }

    $sql_member = "SELECT points FROM youth_members WHERE member_id = ?";
    $stmt_member = $pdo->prepare($sql_member);
    $stmt_member->execute([$member_id]);
    $member = $stmt_member->fetch();
    if (!$member) { throw new Exception("존재하지 않는 회원입니다."); }

    $total_price = $item['price'] * $quantity;
    if ($member['points'] < $total_price) {
        throw new Exception("포인트가 부족합니다. (보유: {$member['points']}P, 필요: {$total_price}P)");
    }

    $sql_update_member = "UPDATE youth_members SET points = points - ? WHERE member_id = ?";
    $pdo->prepare($sql_update_member)->execute([$total_price, $member_id]);

    $sql_inventory = "INSERT INTO youth_inventory (member_id, item_id, quantity)
                      VALUES (?, ?, ?)
                      ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity";
    $pdo->prepare($sql_inventory)->execute([$member_id, $item_id, $quantity]);

    if ($item['stock'] != -1) {
        $sql_update_stock = "UPDATE youth_items SET stock = stock - ? WHERE item_id = ?";
        $pdo->prepare($sql_update_stock)->execute([$quantity, $item_id]);
    }

    $reason_point = "{$item['item_name']} ({$quantity}개) 구매";
    $sql_log_point = "INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)";
    $pdo->prepare($sql_log_point)->execute([$member_id, -$total_price, $reason_point]);
    
    $reason_item = "상점에서 구매";
    $sql_log_item = "INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)";
    $pdo->prepare($sql_log_item)->execute([$member_id, $item_id, $quantity, $reason_item]);

    $pdo->commit();

    $message = "[{$member_id}] 님이 [{$item['item_name']} x{$quantity}] 구매 완료! (-{$total_price}P)";
    echo json_encode([ 'status' => 'success', 'message' => $message ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([ 'status' => 'error', 'message' => '구매 실패: ' . $e->getMessage() ]);
}
?>