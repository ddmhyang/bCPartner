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

if (!isset($input['member_id']) || !isset($input['member_name']) || 
    empty($input['member_id']) || empty($input['member_name'])) {
    
    $response['status'] = 'error';
    $response['message'] = '필수 값(member_id, member_name)이 누락되었습니다.';

} else {
    try {
        $sql = "INSERT INTO youth_members (member_id, member_name, points) VALUES (?, ?, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$input['member_id'], $input['member_name']]);
        
        $response['message'] = "회원 [{$input['member_name']}] 님이 등록되었습니다.";

    } catch (PDOException $e) {
        $response['status'] = 'error';
        if ($e->getCode() == 23000) {
            $response['message'] = "이미 존재하는 회원 ID입니다.";
        } else {
            $response['message'] = "DB 오류: " . $e->getMessage();
        }
    }
}

echo json_encode($response);
?>