<?php
include 'auth_check.php';
include 'db_connect.php';
header("Content-Type: application/json; charset=utf-8");

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'add'; 
$member_id = $input['member_id'] ?? null;
$type_id = $input['type_id'] ?? null;

if (empty($member_id) || empty($type_id)) {
    echo json_encode(['status' => 'error', 'message' => '필수 값 누락']);
    exit;
}

try {
    $stmt_type = $pdo->prepare("SELECT status_name, max_stage, evolve_interval FROM youth_status_types WHERE type_id = ?");
    $stmt_type->execute([$type_id]);
    $type_info = $stmt_type->fetch();
    
    if (!$type_info) { throw new Exception("존재하지 않는 상태 종류입니다."); }
    $status_name = $type_info['status_name'];
    $interval = (int)$type_info['evolve_interval']; 

    $pdo->beginTransaction();

    $now_str = date("Y-m-d H:i:s");
    $now_ts = time();

    if ($action === 'add') {
        $stmt_check = $pdo->prepare("SELECT id FROM youth_active_statuses WHERE member_id = ? AND type_id = ?");
        $stmt_check->execute([$member_id, $type_id]);
        if ($stmt_check->fetch()) { throw new Exception("이미 해당 상태가 적용되어 있습니다."); }

        $duration = (int)($input['duration'] ?? -1);
        $expires_at = ($duration === -1) ? null : date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));
        
        $sql = "INSERT INTO youth_active_statuses (member_id, type_id, current_stage, applied_at, expires_at) VALUES (?, ?, 1, ?, ?)";
        $pdo->prepare($sql)->execute([$member_id, $type_id, $now_str, $expires_at]);
        
        $log_msg = "상태 부여 (1단계 시작)";
        $pdo->prepare("INSERT INTO youth_status_logs (member_id, status_name, action_detail) VALUES (?, ?, ?)")
            ->execute([$member_id, $status_name, $log_msg]);

        $msg = "상태 부여 완료.";

    } elseif ($action === 'evolve' || $action === 'decrease') {
        $sql_check = "SELECT id, current_stage FROM youth_active_statuses WHERE member_id = ? AND type_id = ?";
        $stmt = $pdo->prepare($sql_check);
        $stmt->execute([$member_id, $type_id]);
        $status = $stmt->fetch();

        if ($status) {
            $current_stage = (int)$status['current_stage'];
            $new_stage = $current_stage;
            $action_text = "";

            if ($action === 'evolve') {
                if ($current_stage < $type_info['max_stage']) {
                    $new_stage++;
                    $action_text = "악화";
                } else {
                    throw new Exception("이미 최대 단계입니다.");
                }
            } else { 
                if ($current_stage > 1) {
                    $new_stage--;
                    $action_text = "완화";
                } else {
                    throw new Exception("이미 1단계라 더 내릴 수 없습니다. (치료를 사용하세요)");
                }
            }

            if ($interval > 0) {
                $adjusted_time = $now_ts - (($new_stage - 1) * $interval * 3600);
                $new_applied_at = date("Y-m-d H:i:s", $adjusted_time);
            } else {
                $new_applied_at = $now_str;
            }

            $pdo->prepare("UPDATE youth_active_statuses SET current_stage = ?, applied_at = ? WHERE id = ?")
                ->execute([$new_stage, $new_applied_at, $status['id']]);
            
            $log_msg = "관리자 {$action_text} ({$current_stage}단계 → {$new_stage}단계)";
            $pdo->prepare("INSERT INTO youth_status_logs (member_id, status_name, action_detail) VALUES (?, ?, ?)")
                ->execute([$member_id, $status_name, $log_msg]);

            $msg = "상태가 {$new_stage}단계로 {$action_text}되었습니다.";
        } else {
             $msg = "해당 상태가 없습니다.";
        }

    } elseif ($action === 'cure') {
        $pdo->prepare("DELETE FROM youth_active_statuses WHERE member_id = ? AND type_id = ?")->execute([$member_id, $type_id]);
        
        $log_msg = "상태 치료 (제거)";
        $pdo->prepare("INSERT INTO youth_status_logs (member_id, status_name, action_detail) VALUES (?, ?, ?)")
            ->execute([$member_id, $status_name, $log_msg]);

        $msg = "상태가 치료되었습니다.";
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => $msg]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>