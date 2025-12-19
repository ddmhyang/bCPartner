<?php
include 'auth_check.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

$db_file = __DIR__ . '/database.db';

try {
    if (file_exists($db_file)) {
        $pdo = null;
        
        if (unlink($db_file)) {
            session_destroy();
            echo json_encode(['status' => 'success', 'message' => '시스템이 완전히 초기화되었습니다. 설치 페이지로 이동합니다.']);
        } else {
            throw new Exception("파일 삭제 권한이 없거나 파일이 사용 중입니다.");
        }
    } else {
        echo json_encode(['status' => 'success', 'message' => '이미 DB 파일이 없습니다.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '초기화 실패: ' . $e->getMessage()]);
}
?>