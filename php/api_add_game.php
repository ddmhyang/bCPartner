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

$game_name = $input['game_name'] ?? '';
$description = $input['description'] ?? '';
$outcomes = $input['outcomes'] ?? '';

if (empty($game_name) || empty($outcomes)) {
    $response['status'] = 'error';
    $response['message'] = '필수 값(게임 이름, 배율 목록)이 누락되었습니다.';

} elseif (!preg_match('/^([-+]?[0-9]*\.?[0-9]+,?)+$/', $outcomes)) {
    $response['status'] = 'error';
    $response['message'] = '배율 목록 형식이 잘못되었습니다. 숫자와 쉼표(,)만 사용하세요. (예: -10,-5,1,10)';

} else {
    try {
        $sql = "INSERT INTO youth_gambling_games (game_name, description, outcomes) 
                VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$game_name, $description, $outcomes]);
        
        $response['message'] = "도박 게임 [{$game_name}] 이(가) 등록되었습니다.";

    } catch (PDOException $e) {
        $response['status'] = 'error';
        $response['message'] = "DB 오류: " . $e->getMessage();
    }
}

echo json_encode($response);
?>