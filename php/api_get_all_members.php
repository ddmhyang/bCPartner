<?php

include 'auth_check.php'; 
include 'db_connect.php'; 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

include 'db_connect.php';

$response = ['status' => 'success', 'data' => []];

try {
    $sql = "SELECT member_id, member_name, points FROM youth_members ORDER BY points DESC";
    $stmt = $pdo->query($sql);
    $members_list = $stmt->fetchAll();
    
    $response['data'] = $members_list;

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = '회원 목록 조회 실패: ' . $e->getMessage();
}

echo json_encode($response);
?>