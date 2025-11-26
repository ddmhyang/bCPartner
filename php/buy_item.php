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

try {
    $pdo->beginTransaction();

    $stmt_item = $pdo->prepare("SELECT item_name, price, stock, status FROM youth_items WHERE item_id = ?");
    $stmt_item->execute([$item_id]);
    $item = $stmt_item->fetch();
    if (!$item) { throw new Exception("존재하지 않는 아이템입니다."); }
    if ($item['status'] !== 'selling') { throw new Exception("판매중인 아이템이 아닙니다."); }
    if ($item['stock'] != -1 && $item['stock'] < $quantity) { throw new Exception("재고 부족"); }

    $stmt_member = $pdo->prepare("SELECT points, member_name FROM youth_members WHERE member_id = ?");
    $stmt_member->execute([$member_id]);
    $member = $stmt_member->fetch();
    if (!$member) { throw new Exception("존재하지 않는 회원입니다."); }
    
    $member_name = $member['member_name'];

    $total_price = $item['price'] * $quantity;
    if ($member['points'] < $total_price) { throw new Exception("포인트 부족"); }

    $pdo->prepare("UPDATE youth_members SET points = points - ? WHERE member_id = ?")->execute([$total_price, $member_id]);
    
    $pdo->prepare("INSERT INTO youth_inventory (member_id, item_id, quantity) VALUES (?, ?, ?) ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity")->execute([$member_id, $item_id, $quantity]);

    if ($item['stock'] != -1) {
        $pdo->prepare("UPDATE youth_items SET stock = stock - ? WHERE item_id = ?")->execute([$quantity, $item_id]);
    }

    $pdo->prepare("INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)")->execute([$member_id, -$total_price, "{$item['item_name']} 구매"]);
    $pdo->prepare("INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)")->execute([$member_id, $item_id, $quantity, "상점 구매"]);

    $pdo->commit();

    $message = "[{$member_name}] 님이 [{$item['item_name']} x{$quantity}] 구매 완료! (-{$total_price}P)";
    echo json_encode([ 'status' => 'success', 'message' => $message ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([ 'status' => 'error', 'message' => '구매 실패: ' . $e->getMessage() ]);
}
?>