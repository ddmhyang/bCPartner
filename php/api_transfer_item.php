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
    $response['message'] = '필수 값(보내는 분, 받는 분, 아이템, 수량)이 잘못되었습니다.';
    echo json_encode($response);
    exit;
}

if ($sender_id === $receiver_id) {
    $response['status'] = 'error';
    $response['message'] = '스스로에게 양도할 수 없습니다.';
    echo json_encode($response);
    exit;
}

try {
    $pdo->beginTransaction();

    $sql_sender = "SELECT quantity FROM youth_inventory WHERE member_id = ? AND item_id = ?";
    $stmt_sender = $pdo->prepare($sql_sender);
    $stmt_sender->execute([$sender_id, $item_id]);
    $sender_item = $stmt_sender->fetch();

    if (!$sender_item) {
        throw new Exception("보내는 분({$sender_id})이 해당 아이템(ID: {$item_id})을 가지고 있지 않습니다.");
    }
    
    if ($sender_item['quantity'] < $quantity) {
        throw new Exception("보내는 분의 아이템 수량이 부족합니다. (보유: {$sender_item['quantity']}개)");
    }
    
    $sql_sender_name = "SELECT member_name FROM youth_members WHERE member_id = ?";
    $stmt_sender_name = $pdo->prepare($sql_sender_name);
    $stmt_sender_name->execute([$sender_id]);
    $sender_name = $stmt_sender_name->fetchColumn(); 
    
    $sql_receiver_name = "SELECT member_name FROM youth_members WHERE member_id = ?";
    $stmt_receiver_name = $pdo->prepare($sql_receiver_name);
    $stmt_receiver_name->execute([$receiver_id]);
    $receiver_name = $stmt_receiver_name->fetchColumn(); 
    if (!$sender_name || !$receiver_name) {
        throw new Exception("회원 정보를 찾을 수 없습니다.");
    }

    if ($sender_item['quantity'] == $quantity) {
        $sql_update_sender = "DELETE FROM youth_inventory WHERE member_id = ? AND item_id = ?";
        $pdo->prepare($sql_update_sender)->execute([$sender_id, $item_id]);
    } else {
        $sql_update_sender = "UPDATE youth_inventory SET quantity = quantity - ? WHERE member_id = ? AND item_id = ?";
        $pdo->prepare($sql_update_sender)->execute([$quantity, $sender_id, $item_id]);
    }
    
    $sql_update_receiver = "INSERT INTO youth_inventory (member_id, item_id, quantity)
                            VALUES (?, ?, ?)
                            ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity";
    
    $pdo->prepare($sql_update_receiver)->execute([$receiver_id, $item_id, $quantity]);
    
    $reason_sender = "{$receiver_name}({$receiver_id})님에게 양도";
    $sql_log_sender = "INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)";
    $pdo->prepare($sql_log_sender)->execute([$sender_id, $item_id, -$quantity, $reason_sender]);
    
    $reason_receiver = "{$sender_name}({$sender_id})님으로부터 받음";
    $sql_log_receiver = "INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)";
    $pdo->prepare($sql_log_receiver)->execute([$receiver_id, $item_id, $quantity, $reason_receiver]);
    
    $pdo->commit();

    $response['message'] = "[{$sender_name}] 님이 [{$receiver_name}] 님에게 [아이템 ID: {$item_id}] {$quantity}개 양도 완료.";

} catch (Exception $e) {
    $pdo->rollBack();
    $response['status'] = 'error';
    $response['message'] = "아이템 양도 실패: " . $e->getMessage();
}

echo json_encode($response);
?>