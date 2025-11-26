<?php
include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);
$response = ['status' => 'success'];

$sender_id = $input['sender_id'] ?? null;
$receiver_id = $input['receiver_id'] ?? null;
$item_id = (int)($input['item_id'] ?? 0);
$quantity = (int)($input['quantity'] ?? 0);

if (empty($sender_id) || empty($receiver_id) || $item_id <= 0 || $quantity <= 0) {
    $response['status'] = 'error';
    $response['message'] = '필수 값 오류';
    echo json_encode($response); exit;
}
if ($sender_id === $receiver_id) {
    $response['status'] = 'error';
    $response['message'] = '스스로에게 양도할 수 없습니다.';
    echo json_encode($response); exit;
}

try {
    $pdo->beginTransaction();

    $sql_sender = "SELECT quantity FROM youth_inventory WHERE member_id = ? AND item_id = ?";
    $stmt_sender = $pdo->prepare($sql_sender);
    $stmt_sender->execute([$sender_id, $item_id]);
    $sender_item = $stmt_sender->fetch();

    if (!$sender_item || $sender_item['quantity'] < $quantity) {
        throw new Exception("보내는 분의 아이템 수량이 부족합니다.");
    }
    
    $sender_name = $pdo->prepare("SELECT member_name FROM youth_members WHERE member_id = ?");
    $sender_name->execute([$sender_id]);
    $sender_name = $sender_name->fetchColumn(); 
    
    $receiver_name = $pdo->prepare("SELECT member_name FROM youth_members WHERE member_id = ?");
    $receiver_name->execute([$receiver_id]);
    $receiver_name = $receiver_name->fetchColumn(); 
    
    $item_name_stmt = $pdo->prepare("SELECT item_name FROM youth_items WHERE item_id = ?");
    $item_name_stmt->execute([$item_id]);
    $item_name = $item_name_stmt->fetchColumn() ?: "ID:{$item_id}";

    if (!$sender_name || !$receiver_name) { throw new Exception("회원 정보를 찾을 수 없습니다."); }

    if ($sender_item['quantity'] == $quantity) {
        $pdo->prepare("DELETE FROM youth_inventory WHERE member_id = ? AND item_id = ?")->execute([$sender_id, $item_id]);
    } else {
        $pdo->prepare("UPDATE youth_inventory SET quantity = quantity - ? WHERE member_id = ? AND item_id = ?")->execute([$quantity, $sender_id, $item_id]);
    }
    
    $pdo->prepare("INSERT INTO youth_inventory (member_id, item_id, quantity) VALUES (?, ?, ?) ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity")->execute([$receiver_id, $item_id, $quantity]);
    
    $pdo->prepare("INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)")->execute([$sender_id, $item_id, -$quantity, "{$receiver_name}님에게 양도"]);
    $pdo->prepare("INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)")->execute([$receiver_id, $item_id, $quantity, "{$sender_name}님에게 받음"]);
    
    $pdo->commit();

    $response['message'] = "[{$sender_name}] 님이 [{$receiver_name}] 님에게 [{$item_name}] {$quantity}개 양도 완료.";

} catch (Exception $e) {
    $pdo->rollBack();
    $response['status'] = 'error';
    $response['message'] = "양도 실패: " . $e->getMessage();
}

echo json_encode($response);
?>