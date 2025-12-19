<?php
include 'auth_check.php'; 
include 'db_connect.php'; 
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);
$response = ['status' => 'success'];

// member_id 검사 로직 삭제함
if (!isset($input['member_name']) || empty($input['member_name'])) {
    $response['status'] = 'error';
    $response['message'] = '이름을 입력해주세요.';
} else {
    try {
        // ✨ SQL 수정: member_id를 넣지 않습니다. (DB가 알아서 함)
        $sql = "INSERT INTO youth_members (member_name, points) VALUES (?, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$input['member_name']]);
        
        $response['message'] = "회원 [{$input['member_name']}] 님이 등록되었습니다.";
    } catch (PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "DB 오류: " . $e->getMessage();
    }
}
echo json_encode($response);
?>