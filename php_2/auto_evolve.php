<?php
if (!isset($pdo)) { include 'db_connect.php'; }

try {
    $pdo->beginTransaction();

    $sql = "SELECT 
                s.id, 
                s.member_id, 
                s.current_stage, 
                s.applied_at, 
                t.status_name, 
                t.max_stage, 
                t.evolve_interval
            FROM youth_active_statuses s
            JOIN youth_status_types t ON s.type_id = t.type_id
            WHERE t.can_evolve = 1 AND t.evolve_interval > 0";
            
    $stmt = $pdo->query($sql);
    $active_statuses = $stmt->fetchAll();
    
    $updated_count = 0;

    foreach ($active_statuses as $status) {
        $time_diff_hours = (time() - strtotime($status['applied_at'])) / 3600;
        
        $target_stage = 1 + floor($time_diff_hours / $status['evolve_interval']);
        
        if ($target_stage > $status['max_stage']) {
            $target_stage = $status['max_stage'];
        }

        if ($target_stage > $status['current_stage']) {
            $sql_update = "UPDATE youth_active_statuses SET current_stage = ? WHERE id = ?";
            $pdo->prepare($sql_update)->execute([$target_stage, $status['id']]);

            $log_msg = "시간 경과로 자동 악화 ({$status['current_stage']}단계 → {$target_stage}단계)";
            $sql_log = "INSERT INTO youth_status_logs (member_id, status_name, action_detail) VALUES (?, ?, ?)";
            $pdo->prepare($sql_log)->execute([$status['member_id'], $status['status_name'], $log_msg]);
            
            $updated_count++;
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
}
?>