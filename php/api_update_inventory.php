<?php
include 'auth_check.php';
include 'db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);

$member_id = $input['member_id'] ?? null;
$item_id = $input['item_id'] ?? null;
// 0일 수도 있으므로 isset 체크
if (!isset($input['quantity']) || empty($member_id) || empty($item_id)) {
    echo json_encode(['status' => 'error', 'message' => '필수 값이 누락되었습니다.']);
    exit;
}

$new_quantity = (int)$input['quantity'];

try {
    $pdo->beginTransaction();

    // 1. 기존 수량 확인
    $stmt = $pdo->prepare("SELECT quantity FROM youth_inventory WHERE member_id = ? AND item_id = ?");
    $stmt->execute([$member_id, $item_id]);
    $current = $stmt->fetch();

    if (!$current) {
        throw new Exception("해당 인벤토리 정보를 찾을 수 없습니다.");
    }

    $old_quantity = (int)$current['quantity'];
    $diff = $new_quantity - $old_quantity;

    if ($diff == 0) {
        throw new Exception("변경할 수량이 기존과 같습니다.");
    }

    // 2. 수량 업데이트 (0 이하면 삭제)
    if ($new_quantity <= 0) {
        $pdo->prepare("DELETE FROM youth_inventory WHERE member_id = ? AND item_id = ?")->execute([$member_id, $item_id]);
        $new_quantity = 0; // 로그용
    } else {
        $pdo->prepare("UPDATE youth_inventory SET quantity = ? WHERE member_id = ? AND item_id = ?")->execute([$new_quantity, $member_id, $item_id]);
    }

    // 3. 로그 기록
    $pdo->prepare("INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)")
        ->execute([$member_id, $item_id, $diff, "관리자 수량 변경"]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => "수량이 변경되었습니다. ({$old_quantity} -> {$new_quantity})"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>