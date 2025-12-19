<?php
include 'db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_username'])) {
    die(json_encode(['status' => 'error', 'message' => '로그인 세션 만료']));
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        // [조회 기능]
        case 'get_members':
            $stmt = $pdo->query("SELECT member_id, member_name, points FROM youth_members ORDER BY member_id ASC");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        case 'get_items':
            echo json_encode(['status' => 'success', 'data' => $pdo->query("SELECT * FROM youth_items")->fetchAll()]);
            break;

        case 'get_status_types':
            echo json_encode(['status' => 'success', 'data' => $pdo->query("SELECT type_id, type_name as status_name FROM youth_status_types")->fetchAll()]);
            break;

        case 'get_member_detail':
            $id = $_GET['member_id'];
            $stmt = $pdo->prepare("SELECT * FROM youth_members WHERE member_id = ?");
            $stmt->execute([$id]);
            $res = $stmt->fetch();
            $inv = $pdo->prepare("SELECT i.item_id, i.item_name, inv.quantity FROM youth_inventory inv JOIN youth_items i ON inv.item_id = i.item_id WHERE inv.member_id = ?");
            $inv->execute([$id]);
            $res['inventory'] = $inv->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $res]);
            break;

        // [핵심 액션 기능]
        case 'bulk_point':
            $pdo->beginTransaction();
            foreach ($input['targets'] as $tid) {
                $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")->execute([$input['amount'], $tid]);
                $pdo->prepare("INSERT INTO youth_point_logs (member_id, points_change, reason) VALUES (?, ?, ?)")->execute([$tid, $input['amount'], $input['reason'] ?? '관리자 조작']);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
            break;

        case 'transfer_points_multi':
            $pdo->beginTransaction();
            foreach ($input['receivers'] as $r) {
                $pdo->prepare("UPDATE youth_members SET points = points - ? WHERE member_id = ?")->execute([$r['amount'], $input['sender_id']]);
                $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")->execute([$r['amount'], $r['id']]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
            break;

        case 'set_member_status':
            $mid = $input['member_id']; $tid = $input['type_id']; $mode = $input['action'];
            if($mode == 'add') $pdo->prepare("INSERT OR IGNORE INTO youth_member_status (member_id, type_id) VALUES (?,?)")->execute([$mid, $tid]);
            elseif($mode == 'cure') $pdo->prepare("DELETE FROM youth_member_status WHERE member_id = ? AND type_id = ?")->execute([$mid, $tid]);
            echo json_encode(['status' => 'success', 'message' => '상태 변경 완료']);
            break;

        case 'admin_give_item':
        case 'buy_item':
            $mid = $input['member_id']; $iid = $input['item_id']; $qty = $input['quantity'];
            $pdo->beginTransaction();
            if ($action == 'buy_item') {
                $item = $pdo->prepare("SELECT price FROM youth_items WHERE item_id = ?"); $item->execute([$iid]);
                $cost = $item->fetchColumn() * $qty;
                $pdo->prepare("UPDATE youth_members SET points = points - ? WHERE member_id = ?")->execute([$cost, $mid]);
            }
            $pdo->prepare("INSERT INTO youth_inventory (member_id, item_id, quantity) VALUES (?,?,?) ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + EXCLUDED.quantity")->execute([$mid, $iid, $qty]);
            $pdo->commit();
            echo json_encode(['status' => 'success']);
            break;

        case 'run_gamble':
            $arr = explode(',', str_replace(' ', '', $input['outcomes']));
            $multiplier = (float)$arr[array_rand($arr)];
            $diff = floor($input['bet_amount'] * $multiplier) - $input['bet_amount'];
            $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")->execute([$diff, $input['member_id']]);
            echo json_encode(['status' => 'success', 'multiplier' => $multiplier]);
            break;

        default: echo json_encode(['status' => 'error', 'message' => '지원하지 않는 액션']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}