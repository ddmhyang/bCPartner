<?php
include 'auth_check.php';
include 'db_connect.php';
header("Content-Type: application/json; charset=utf-8");

try {
    $stmt = $pdo->query("SELECT * FROM youth_status_types ORDER BY type_id ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>