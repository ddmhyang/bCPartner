<?php
/**
 * 모든 개별 API를 하나로 합친 통합 핸들러
 */
include 'db_connect.php';
session_start();

// 보안: 관리자 세션 체크
if (!isset($_SESSION['admin_username'])) {
    echo json_encode(['status' => 'error', 'message' => '권한이 없습니다.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        // --- 캐릭터 관리 ---
        case 'get_members':
            $stmt = $pdo->query("SELECT m.*, 
                (SELECT GROUP_CONCAT(t.type_name) FROM youth_member_status s 
                 JOIN youth_status_types t ON s.type_id = t.type_id 
                 WHERE s.member_id = m.member_id) as status_names
                FROM youth_members m ORDER BY m.member_id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'add_member':
            $name = $input['name'] ?? '';
            $stmt = $pdo->prepare("INSERT INTO youth_members (member_name, points) VALUES (?, 0)");
            $stmt->execute([$name]);
            echo json_encode(['status' => 'success', 'member_id' => $pdo->lastInsertId()]);
            break;

        case 'get_member_detail':
            $id = $_GET['member_id'];
            // 기본정보
            $m = $pdo->prepare("SELECT * FROM youth_members WHERE member_id = ?");
            $m->execute([$id]);
            $member = $m->fetch(PDO::FETCH_ASSOC);
            // 인벤토리
            $inv = $pdo->prepare("SELECT i.*, inv.quantity FROM youth_inventory inv JOIN youth_items i ON inv.item_id = i.item_id WHERE inv.member_id = ?");
            $inv->execute([$id]);
            $member['inventory'] = $inv->fetchAll(PDO::FETCH_ASSOC);
            // 상태
            $st = $pdo->prepare("SELECT t.* FROM youth_member_status ms JOIN youth_status_types t ON ms.type_id = t.type_id WHERE ms.member_id = ?");
            $st->execute([$id]);
            $member['statuses'] = $st->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'success', 'data' => $member]);
            break;

        // --- 도박 시스템 (룰렛/홀짝/블랙잭) ---
        case 'run_gamble':
            $mid = $input['member_id'];
            $bet = (int)$input['bet_amount'];
            $type = $input['game_type'];
            
            $pdo->beginTransaction();
            
            $multiplier = 0;
            if ($type === 'roulette') {
                $outcomes = str_replace(' ', '', $input['outcomes']);
                $arr = explode(',', $outcomes);
                $multiplier = (float)$arr[array_rand($arr)];
            } elseif ($type === 'odd_even') {
                $multiplier = (rand(0, 1) === 0) ? 2.0 : 0.0;
            } elseif ($type === 'blackjack') {
                // 단순 승패 시뮬레이션 (21에 가까운 쪽 승리)
                $p = rand(12, 25); $d = rand(17, 23);
                if ($p > 21) $multiplier = 0;
                elseif ($d > 21 || $p > $d) $multiplier = 2.0;
                else $multiplier = 0;
            }

            $diff = floor($bet * $multiplier) - $bet;
            $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")->execute([$diff, $mid]);
            
            // 로그 기록
            $pdo->prepare("INSERT INTO youth_point_logs (member_id, points_change, reason) VALUES (?, ?, ?)")
                ->execute([$mid, $diff, "도박({$type}) 결과: {$multiplier}배"]);
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'multiplier' => $multiplier, 'diff' => $diff]);
            break;

        // --- 상태 관리 (h:m 변환 저장) ---
        case 'get_status_types':
            $stmt = $pdo->query("SELECT * FROM youth_status_types");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'update_status_time':
            $tid = $input['type_id'];
            $hm = $input['time_str']; // "1:30"
            $parts = explode(':', $hm);
            $total_min = ($parts[0] * 60) + ($parts[1] ?? 0);
            
            $pdo->prepare("UPDATE youth_status_types SET evolve_interval = ? WHERE type_id = ?")
                ->execute([$total_min, $tid]);
            echo json_encode(['status' => 'success']);
            break;

        // --- 설정 및 백업 ---
        case 'download_db':
            $file = 'database.db';
            if (file_exists($file)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="backup_'.date('Ymd').'.db"');
                readfile($file);
                exit;
            }
            break;

        case 'reset_season':
            $pdo->exec("DELETE FROM youth_inventory; DELETE FROM youth_point_logs; DELETE FROM youth_item_logs; DELETE FROM youth_member_status;");
            echo json_encode(['status' => 'success', 'message' => '시즌 데이터가 초기화되었습니다.']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => '알 수 없는 요청']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}