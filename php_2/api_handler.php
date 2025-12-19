<?php
include 'db_connect.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch($action) {
    // 1. 캐릭터 관리
    case 'get_members':
        $stmt = $pdo->query("SELECT * FROM youth_members ORDER BY member_id ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'add_member':
        $name = $input['name'];
        $stmt = $pdo->prepare("INSERT INTO youth_members (member_name, points) VALUES (?, 0)");
        $stmt->execute([$name]);
        echo json_encode(['status' => 'success']);
        break;

    // 2. [요구사항] m:n 포인트 양도
    case 'transfer_points_multi':
        $pdo->beginTransaction();
        try {
            $sender_id = $input['sender_id'];
            foreach($input['receivers'] as $r) {
                // 발신자 차감
                $pdo->prepare("UPDATE youth_members SET points = points - ? WHERE member_id = ?")
                    ->execute([$r['amount'], $sender_id]);
                // 수신자 증가
                $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")
                    ->execute([$r['amount'], $r['id']]);
                // 로그 남기기 (생략)
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
        } catch(Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // 3. [도박] 룰렛, 홀짝, 블랙잭 통합
    case 'run_gamble':
        $member_id = $input['member_id'];
        $bet = $input['bet'];
        $type = $input['type']; // 'roulette', 'odd_even', 'blackjack'
        
        if($type == 'roulette') {
            // [요구사항] 띄어쓰기 포함 배율 인식
            $outcomes = str_replace(' ', '', $input['outcomes']);
            $arr = explode(',', $outcomes);
            $mult = (float)$arr[array_rand($arr)];
        } else if($type == 'odd_even') {
            $win = rand(0, 1) == 1;
            $mult = $win ? 2.0 : 0.0;
        }
        // 포인트 반영 로직...
        break;

    // 4. [상태 관리] h:m 시간 설정
    case 'update_status_time':
        $type_id = $input['type_id'];
        $hm = $input['time_str']; // "01:30"
        list($h, $m) = explode(':', $hm);
        $total_minutes = ($h * 60) + $m;
        
        $pdo->prepare("UPDATE youth_status_types SET evolve_interval = ? WHERE type_id = ?")
            ->execute([$total_minutes, $type_id]);
        echo json_encode(['status' => 'success']);
        break;
}