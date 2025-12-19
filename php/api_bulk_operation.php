<?php
include 'db_connect.php';
$data = json_decode(file_get_contents('php://input'), true);
$pdo->beginTransaction();

try {
    foreach ($data['targets'] as $mid) {
        foreach ($data['items'] as $item) {
            // 인벤토리 업데이트 (m:n)
            $sql = "INSERT INTO youth_inventory (member_id, item_id, quantity) 
                    VALUES (?, ?, ?) 
                    ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + EXCLUDED.quantity";
            $pdo->prepare($sql)->execute([$mid, $item['id'], $item['qty']]);
            
            // 로그 기록
            $pdo->prepare("INSERT INTO youth_logs (member_id, log_type, target_name, change_val, reason) VALUES (?, 'item', ?, ?, ?)")
                ->execute([$mid, $item['name'], "+".$item['qty'], $data['reason']]);
        }
    }
    $pdo->commit();
    echo json_encode(['status'=>'success']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
}
?>