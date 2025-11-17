<?php
include 'auth_check.php'; 
include 'db_connect.php'; 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

include 'db_connect.php';
if (!isset($_GET['member_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => '조회할 member_id가 필요합니다.'
    ]);
    exit;
}
$member_id = $_GET['member_id'];

$response_data = [];

try {
    $sql_member = "SELECT member_name, points FROM youth_members WHERE member_id = ?";
    $stmt_member = $pdo->prepare($sql_member);
    $stmt_member->execute([$member_id]);
    $member_info = $stmt_member->fetch();

    if (!$member_info) {
        throw new Exception("해당 ID의 회원을 찾을 수 없습니다.");
    }

    $response_data['member_id'] = $member_id;
    $response_data['member_name'] = $member_info['member_name'];
    $response_data['points'] = $member_info['points'];

    $sql_inventory = "SELECT T2.item_name, T1.quantity 
                      FROM youth_inventory AS T1
                      JOIN youth_items AS T2 ON T1.item_id = T2.item_id
                      WHERE T1.member_id = ?";
    
    $stmt_inventory = $pdo->prepare($sql_inventory);
    $stmt_inventory->execute([$member_id]);
    $inventory_list = $stmt_inventory->fetchAll();

    $response_data['inventory'] = $inventory_list;

    echo json_encode([
        'status' => 'success',
        'data' => $response_data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => '조회 실패: ' . $e->getMessage(),
        'member_id' => $member_id
    ]);
}
?>