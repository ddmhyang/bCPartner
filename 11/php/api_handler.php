<?php
include 'db_connect.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_username'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($action) {
        case 'get_members':
            $stmt = $pdo->query("SELECT m.*, (SELECT GROUP_CONCAT(t.type_name) FROM youth_member_status ms JOIN youth_status_types t ON ms.type_id = t.type_id WHERE ms.member_id = m.member_id) as status_names FROM youth_members m");
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
            break;

        case 'add_member':
            $pdo->prepare("INSERT INTO youth_members (member_name, points) VALUES (?, 0)")->execute([$input['name']]);
            echo json_encode(['status' => 'success']);
            break;

        case 'get_member_detail':
            $id = $_GET['member_id'];
            $m = $pdo->prepare("SELECT * FROM youth_members WHERE member_id = ?");
            $m->execute([$id]);
            $res = $m->fetch();
            $inv = $pdo->prepare("SELECT i.*, inv.quantity FROM youth_inventory inv JOIN youth_items i ON inv.item_id = i.item_id WHERE inv.member_id = ?");
            $inv->execute([$id]);
            $res['inventory'] = $inv->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $res]);
            break;

        case 'update_status_time':
            $parts = explode(':', $input['time_str']);
            $min = ($parts[0] * 60) + ($parts[1] ?? 0);
            $pdo->prepare("UPDATE youth_status_types SET evolve_interval = ? WHERE type_id = ?")->execute([$min, $input['type_id']]);
            echo json_encode(['status' => 'success']);
            break;

        case 'get_logs':
            $type = $_GET['type'];
            $table = ($type === 'points') ? 'youth_point_logs' : 'youth_item_logs';
            $sql = "SELECT l.*, m.member_name FROM $table l JOIN youth_members m ON l.member_id = m.member_id ORDER BY log_id DESC";
            echo json_encode(['status' => 'success', 'data' => $pdo->query($sql)->fetchAll()]);
            break;

        case 'download_db':
            $file = 'database.db';
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="backup_'.date('Ymd').'.db"');
            readfile($file);
            exit;

        case 'reset_season':
            $pdo->exec("DELETE FROM youth_inventory; DELETE FROM youth_point_logs; DELETE FROM youth_item_logs; DELETE FROM youth_member_status;");
            echo json_encode(['status' => 'success', 'message' => '시즌 초기화 완료']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}