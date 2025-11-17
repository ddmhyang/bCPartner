<?php

include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

$response = ['status' => 'success'];

try {
    $pdo->beginTransaction();

    $pdo->exec('PRAGMA foreign_keys = OFF;');

    $pdo->exec("DELETE FROM youth_members;");
    
    $pdo->exec("DELETE FROM youth_inventory;");
    
    $pdo->exec("DELETE FROM youth_point_logs;");
    
    $pdo->exec("DELETE FROM youth_item_logs;");
    
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN (
        'youth_point_logs', 
        'youth_item_logs'
    );");
    
    $pdo->exec('PRAGMA foreign_keys = ON;');

    $pdo->commit();

    $response['message'] = "✅ 데이터 초기화 성공: 모든 회원, 인벤토리, 포인트/아이템 로그가 삭제되었습니다.";

} catch (Exception $e) {
    $pdo->rollBack();
    $pdo->exec('PRAGMA foreign_keys = ON;');
    
    $response['status'] = 'error';
    $response['message'] = "초기화 실패: " . $e->getMessage();
}

echo json_encode($response);
?>