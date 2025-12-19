<?php
include 'auth_check.php';
include 'db_connect.php';
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);

// 1. 무엇을 할지(type), 누구에게(targets) 할지 받음
$type = $input['type'] ?? ''; // 'point', 'item', 'status'
$targets = $input['targets'] ?? []; // 회원 ID 배열 [1, 2, 5]
$data = $input['data'] ?? []; // 수량, 이유 등 세부 정보

if (empty($targets) || empty($type)) {
    echo json_encode(['status' => 'error', 'message' => '대상이나 작업 종류가 없습니다.']);
    exit;
}

try {
    $pdo->beginTransaction(); // 트랜잭션: 중간에 에러나면 전체 취소
    $count = 0;

    foreach ($targets as $member_id) {
        $member_id = (int)$member_id;

        // A. 포인트 지급/회수 로직
        if ($type === 'point') {
            $amount = (int)$data['amount'];
            $reason = $data['reason'] ?? '단체 지급';
            
            $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")
                ->execute([$amount, $member_id]);
            
            $pdo->prepare("INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)")
                ->execute([$member_id, $amount, $reason]);
        }
        
        // B. 아이템 지급 로직
        elseif ($type === 'item') {
            $item_id = (int)$data['item_id'];
            $quantity = (int)$data['quantity'];
            
            $pdo->prepare("INSERT INTO youth_inventory (member_id, item_id, quantity) VALUES (?, ?, ?) 
                           ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + excluded.quantity")
                ->execute([$member_id, $item_id, $quantity]);

            $pdo->prepare("INSERT INTO youth_item_logs (member_id, item_id, quantity_change, reason) VALUES (?, ?, ?, ?)")
                ->execute([$member_id, $item_id, $quantity, "단체 지급"]);
        }

        // C. 상태 이상 부여
        elseif ($type === 'status') {
            $type_id = (int)$data['type_id'];
            // 이미 있는지 확인 후 없으면 추가
            $stmt = $pdo->prepare("SELECT id FROM youth_active_statuses WHERE member_id = ? AND type_id = ?");
            $stmt->execute([$member_id, $type_id]);
            if (!$stmt->fetch()) {
                $pdo->prepare("INSERT INTO youth_active_statuses (member_id, type_id, current_stage) VALUES (?, ?, 1)")
                    ->execute([$member_id, $type_id]);
            }
        }
        $count++;
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => "총 {$count}명에게 작업을 완료했습니다."]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => '오류 발생: ' . $e->getMessage()]);
}
?>