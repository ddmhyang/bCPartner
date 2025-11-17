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
$amount = (int)($input['amount'] ?? 0);

if (empty($sender_id) || empty($receiver_id) || $amount <= 0) {
    $response['status'] = 'error';
    $response['message'] = '필수 값(보내는 분, 받는 분)이 없거나, 금액이 0 이하입니다.';
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
    $sql_sender = "SELECT points, member_name FROM youth_members WHERE member_id = ?";
    $stmt_sender = $pdo->prepare($sql_sender);
    $stmt_sender->execute([$sender_id]);
    $sender = $stmt_sender->fetch();

    if (!$sender) {
        throw new Exception("보내는 분({$sender_id})을 찾을 수 없습니다.");
    }
    
    if ($sender['points'] < $amount) {
        throw new Exception("보내는 분의 포인트가 부족합니다. (보유: {$sender['points']}P)");
    }
    
    $sql_receiver = "SELECT member_name FROM youth_members WHERE member_id = ?";
    $stmt_receiver = $pdo->prepare($sql_receiver);
    $stmt_receiver->execute([$receiver_id]);
    $receiver = $stmt_receiver->fetch();
    
    if (!$receiver) {
        throw new Exception("받는 분({$receiver_id})을 찾을 수 없습니다.");
    }

    $sender_name = $sender['member_name'];
    $receiver_name = $receiver['member_name'];

    $sql_update_sender = "UPDATE youth_members SET points = points - ? WHERE member_id = ?";
    $pdo->prepare($sql_update_sender)->execute([$amount, $sender_id]);
    
    $sql_update_receiver = "UPDATE youth_members SET points = points + ? WHERE member_id = ?";
    $pdo->prepare($sql_update_receiver)->execute([$amount, $receiver_id]);
    
    $reason_sender = "{$receiver_name}({$receiver_id})님에게 양도";
    $sql_log_sender = "INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)";
    $pdo->prepare($sql_log_sender)->execute([$sender_id, -$amount, $reason_sender]);
    
    $reason_receiver = "{$sender_name}({$sender_id})님으로부터 받음";
    $sql_log_receiver = "INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)";
    $pdo->prepare($sql_log_receiver)->execute([$receiver_id, $amount, $reason_receiver]);

    $pdo->commit();

    $response['message'] = "[{$sender_name}] 님이 [{$receiver_name}] 님에게 {$amount}P 양도 완료.";

} catch (Exception $e) {
    $pdo->rollBack();
    $response['status'] = 'error';
    $response['message'] = "양도 실패: " . $e->getMessage();
}

echo json_encode($response);
?>