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
    $sql = "SELECT game_id, game_name, description, outcomes 
            FROM youth_gambling_games 
            ORDER BY game_id DESC";
    $stmt = $pdo->query($sql);
    $games_list = $stmt->fetchAll();
    
    $response['data'] = $games_list;

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = '게임 목록 조회 실패: ' . $e->getMessage();
}

echo json_encode($response);
?>