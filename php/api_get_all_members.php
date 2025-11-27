<?php
include 'auth_check.php'; 
include 'db_connect.php'; 
include 'auto_evolve.php'; 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

try {
    $sql_members = "SELECT member_id, member_name, points FROM youth_members ORDER BY member_id ASC";
    $members = $pdo->query($sql_members)->fetchAll();

    $sql_statuses = "SELECT 
                        s.member_id, 
                        s.current_stage, 
                        s.applied_at,
                        t.status_name, 
                        t.max_stage, 
                        t.evolve_interval
                     FROM youth_active_statuses s
                     JOIN youth_status_types t ON s.type_id = t.type_id";
    $statuses = $pdo->query($sql_statuses)->fetchAll();

    $status_map = [];
    $now = time();

    foreach ($statuses as $st) {
        $mid = $st['member_id'];
        
        $info = "{$st['status_name']}({$st['current_stage']}단계";
        
        if ($st['evolve_interval'] > 0 && $st['current_stage'] < $st['max_stage']) {
            $applied_ts = strtotime($st['applied_at']);
            $interval_sec = $st['evolve_interval'] * 3600;
            
            $passed_sec = $now - $applied_ts;
            
            $target_sec = $st['current_stage'] * $interval_sec;
            
            $remain_sec = $target_sec - $passed_sec;
            
            if ($remain_sec < 0) $remain_sec = 0;
            
            $h = floor($remain_sec / 3600);
            $m = floor(($remain_sec % 3600) / 60);
            $time_str = sprintf("%02d:%02d", $h, $m);
            
            $info .= ", 악화까지 {$time_str}";
        } elseif ($st['current_stage'] >= $st['max_stage']) {
            $info .= ", 최대";
        }
        
        $info .= ")";
        
        if (!isset($status_map[$mid])) {
            $status_map[$mid] = [];
        }
        $status_map[$mid][] = $info;
    }

    foreach ($members as &$member) {
        $mid = $member['member_id'];
        if (isset($status_map[$mid])) {
            $member['status_list'] = implode(', ', $status_map[$mid]);
        } else {
            $member['status_list'] = '';
        }
    }

    echo json_encode(['status' => 'success', 'data' => $members]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => '조회 실패: ' . $e->getMessage()]);
}
?>