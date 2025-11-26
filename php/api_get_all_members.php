<?php
include 'auth_check.php'; 
include 'db_connect.php'; 
include 'auto_evolve.php'; // 자동 업데이트 먼저 실행

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");

try {
    // 1. 모든 회원 조회
    $sql_members = "SELECT member_id, member_name, points FROM youth_members ORDER BY points DESC";
    $members = $pdo->query($sql_members)->fetchAll();

    // 2. 모든 활성 상태 조회 (JOIN으로 정보 다 가져오기)
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

    // 3. PHP에서 멤버별로 상태 정리 (남은 시간 계산)
    $status_map = [];
    $now = time();

    foreach ($statuses as $st) {
        $mid = $st['member_id'];
        
        // 상태 텍스트 만들기
        $info = "{$st['status_name']}({$st['current_stage']}단계";
        
        // 남은 시간 계산 (자동 악화가 있고, 아직 최대 단계가 아닐 때)
        if ($st['evolve_interval'] > 0 && $st['current_stage'] < $st['max_stage']) {
            $applied_ts = strtotime($st['applied_at']);
            $interval_sec = $st['evolve_interval'] * 3600;
            
            // 경과 시간
            $passed_sec = $now - $applied_ts;
            
            // 다음 단계가 되기 위해 필요한 총 누적 시간
            // 공식: 현재단계 * 주기 = 다음단계까지 필요한 시간
            // (1단계에서 시작하므로, 2단계가 되려면 1*주기만큼 지나야 함)
            $target_sec = $st['current_stage'] * $interval_sec;
            
            // 남은 초
            $remain_sec = $target_sec - $passed_sec;
            
            // 만약 시간 계산이 꼬여서 음수가 나오면 0으로 처리 (곧 업데이트 될 것임)
            if ($remain_sec < 0) $remain_sec = 0;
            
            // HH:MM 형식 변환
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

    // 4. 회원 리스트에 상태 붙이기
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