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

if (empty($member_id)) {
    $response['status'] = 'error';
    $response['message'] = '필수 값(member_id)이 누락되었습니다.';
} else {
    try {
        $sql = "DELETE FROM youth_members WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
        
        $response['message'] = "회원 [{$member_id}] 님이 삭제되었습니다.";

    } catch (PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "DB 오류: " . $e->getMessage();
    }
}

echo json_encode($response);
?>