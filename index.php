<?php
session_start();

/**
 * =========================================================
 *  B-PARTNER (single-file) index.php  [PATCH v4.7]
 *  - Login session
 *  - SQLite
 *  - Members / Items / Inventory
 *  - Status types / Active statuses (auto stage evolve)
 *  - Games: Roulette (preset) + stats, Odd/Even (server decides), Blackjack (client-run, server applies payout)
 *  - Bulk grant: point / item / status
 *  - M:N transfers
 *  - Logs
 * =========================================================
 */

/* ---------------------------
 *  [1] Login
 * --------------------------- */
$correct_password = '0217';

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pw'])) {
    if ($_POST['login_pw'] === $correct_password) {
        $_SESSION['auth'] = true;
        header("Location: index.php");
        exit;
    } else {
        $login_error = "비밀번호가 일치하지 않습니다.";
    }
}

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => '세션 만료']);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>Login - B-PARTNER</title></head>
    <body style="display:flex;justify-content:center;align-items:center;height:100vh;background:#f8f9fc;margin:0;font-family:sans-serif;">
        <form method="POST" style="background:white;padding:40px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.1);width:300px;">
            <h2 style="text-align:center;color:#4e73df;">B-PARTNER</h2>
            <?php if(isset($login_error)) echo "<p style='color:red;font-size:0.8rem;'>".htmlspecialchars($login_error, ENT_QUOTES)."</p>"; ?>
            <input type="password" name="login_pw" placeholder="Password" autofocus style="width:100%;padding:12px;margin:10px 0;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
            <button type="submit" style="width:100%;padding:12px;background:#4e73df;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:bold;">접속</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

/* ---------------------------
 *  [2] DB init
 * --------------------------- */
$dbFile = 'database.db';
$db = new SQLite3($dbFile);
$db->busyTimeout(5000);
$db->exec("PRAGMA foreign_keys = ON");

/* Tables */
$db->exec("CREATE TABLE IF NOT EXISTS youth_members (
    member_id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_name TEXT,
    points INTEGER DEFAULT 0
)");

$db->exec("CREATE TABLE IF NOT EXISTS youth_items (
    item_id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_name TEXT,
    item_description TEXT,
    price INTEGER DEFAULT 0,
    stock INTEGER DEFAULT -1,
    status TEXT DEFAULT 'selling'  -- selling / hidden / soldout etc.
)");

$db->exec("CREATE TABLE IF NOT EXISTS youth_inventory (
    member_id INTEGER,
    item_id INTEGER,
    quantity INTEGER DEFAULT 1,
    PRIMARY KEY(member_id, item_id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS youth_status_types (
    type_id INTEGER PRIMARY KEY AUTOINCREMENT,
    status_name TEXT,
    max_stage INTEGER DEFAULT 1,
    evolve_interval_json TEXT DEFAULT '[]'
)");

$db->exec("CREATE TABLE IF NOT EXISTS youth_active_statuses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER,
    type_id INTEGER,
    current_stage INTEGER DEFAULT 1,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS youth_logs (
    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id INTEGER,
    member_name TEXT,
    log_type TEXT,     -- point / item / game / system
    target_name TEXT,  -- item name or etc
    change_val TEXT,   -- +100 / -1 / etc
    reason TEXT,
    log_time TIMESTAMP DEFAULT (datetime('now','localtime'))
)");

/* --- [MIGRATION] youth_logs.point_after (포인트 잔액 스냅샷) --- */
try {
    $cols = [];
    $res = $db->query("PRAGMA table_info(youth_logs)");
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $cols[] = $row['name'];
    }
    if (!in_array('point_after', $cols, true)) {
        $db->exec("ALTER TABLE youth_logs ADD COLUMN point_after INTEGER");
    }
} catch (Exception $e) {
    // ignore
}


/* Roulette presets + stats */
$db->exec("CREATE TABLE IF NOT EXISTS youth_roulette_configs (
    config_id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_name TEXT,
    multipliers_text TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS youth_roulette_stats (
    config_id INTEGER PRIMARY KEY,
    play_count INTEGER DEFAULT 0,
    total_bet INTEGER DEFAULT 0,
    total_payout INTEGER DEFAULT 0,
    last_play_time TIMESTAMP
)");

/* ---------------------------
 *  [3] Helpers
 * --------------------------- */
function json_response($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}
function safe_trim($s) {
    return trim(preg_replace('/\s+/u', ' ', (string)$s));
}
function to_int($v) {
    return (int)$v;
}
function assert_positive_int($v, $name='value') {
    if (!is_int($v)) $v = (int)$v;
    if ($v <= 0) throw new Exception("$name 값이 올바르지 않습니다.");
}
function assert_nonneg_int($v, $name='value') {
    if (!is_int($v)) $v = (int)$v;
    if ($v < 0) throw new Exception("$name 값이 올바르지 않습니다.");
}
function assert_nonempty($s, $name='value') {
    if (trim((string)$s) === '') throw new Exception("$name 값이 비어 있습니다.");
}
function parse_multipliers($text) {
    $text = trim((string)$text);
    if ($text === '') return [];
    $parts = preg_split('/[\s,]+/', $text);
    $rates = [];
    foreach ($parts as $p) {
        if ($p === '') continue;
        if (!is_numeric($p)) throw new Exception("룰렛 배율에 숫자가 아닌 값이 있습니다: ".$p);
        $rates[] = (float)$p;
    }
    return $rates;
}

function parse_hm_to_minutes($hm) {
    $hm = trim((string)$hm);
    if ($hm === '') throw new Exception("시간 값이 비어 있습니다.");
    // h:m 형식 (예: 2:30, 0:45, 10:00)
    if (!preg_match('/^(\d+)\s*:\s*([0-5]?\d)$/', $hm, $m)) {
        throw new Exception("시간 형식이 올바르지 않습니다. (h:m 형식 예: 2:30)");
    }
    $h = (int)$m[1];
    $min = (int)$m[2];
    return ($h * 60) + $min;
}

function minutes_to_hm($minutes) {
    $minutes = (int)$minutes;
    if ($minutes < 0) $minutes = 0;
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf("%d:%02d", $h, $m);
}

/**
 * active_statuses의 current_stage를 applied_at + evolve_interval_json 기준으로 자동 갱신
 * - evolve_interval_json: [stage1to2_minutes, stage2to3_minutes, ...]
 */
function sync_member_status_stages($db, $memberId) {
    $stmt = $db->prepare("
        SELECT a.id, a.type_id, a.current_stage, a.applied_at, t.max_stage, t.evolve_interval_json
        FROM youth_active_statuses a
        JOIN youth_status_types t ON a.type_id = t.type_id
        WHERE a.member_id = :mid
    ");
    $stmt->bindValue(':mid', (int)$memberId, SQLITE3_INTEGER);
    $res = $stmt->execute();

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $maxStage = (int)$row['max_stage'];
        $intervals = json_decode($row['evolve_interval_json'] ?? '[]', true);
        if (!is_array($intervals)) $intervals = [];

        $appliedAt = strtotime($row['applied_at']);
        if ($appliedAt === false) continue;

        $elapsedMin = (int)floor((time() - $appliedAt) / 60);
        if ($elapsedMin < 0) $elapsedMin = 0;

        $stage = 1;
        $acc = 0;
        for ($i = 0; $i < ($maxStage - 1); $i++) {
            $step = (int)($intervals[$i] ?? 0);
            if ($step <= 0) break;
            $acc += $step;
            if ($elapsedMin >= $acc) $stage = $i + 2;
            else break;
        }

        $stage = max(1, min($stage, $maxStage));
        if ($stage !== (int)$row['current_stage']) {
            $u = $db->prepare("UPDATE youth_active_statuses SET current_stage = :s WHERE id = :id");
            $u->bindValue(':s', $stage, SQLITE3_INTEGER);
            $u->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
            $u->execute();
        }
    }
}

function clamp_int($v, $min, $max) {
    $v = (int)$v;
    if ($v < $min) return $min;
    if ($v > $max) return $max;
    return $v;
}
function log_insert($db, $memberId, $memberName, $logType, $targetName, $changeVal, $reason, $pointAfter = null) {
    $stmt = $db->prepare("
        INSERT INTO youth_logs (member_id, member_name, log_type, target_name, change_val, reason, point_after)
        VALUES (:mid, :mname, :type, :target, :chg, :reason, :pafter)
    ");
    $stmt->bindValue(':mid', (int)$memberId, SQLITE3_INTEGER);
    $stmt->bindValue(':mname', (string)$memberName, SQLITE3_TEXT);
    $stmt->bindValue(':type', (string)$logType, SQLITE3_TEXT);
    $stmt->bindValue(':target', (string)$targetName, SQLITE3_TEXT);
    $stmt->bindValue(':chg', (string)$changeVal, SQLITE3_TEXT);
    $stmt->bindValue(':reason', (string)$reason, SQLITE3_TEXT);

    // 포인트 잔액 스냅샷 (로그에 함께 저장)
    $pa = $pointAfter;
    if ($pa === null && (int)$memberId > 0) {
        $pa = (int)$db->querySingle("SELECT points FROM youth_members WHERE member_id = ".(int)$memberId);
    }
    if ($pa === null) $pa = 0;
    $stmt->bindValue(':pafter', (int)$pa, SQLITE3_INTEGER);

    $stmt->execute();
}

/* ---------------------------
 *  [4] API (POST)
 * --------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // restore db (multipart)
    if (isset($_POST['action']) && $_POST['action'] === 'restore_db') {
        if (!isset($_FILES['db_file'])) json_response(['status'=>'error','message'=>'파일 없음']);
        $db->close();
        if (move_uploaded_file($_FILES['db_file']['tmp_name'], $dbFile)) json_response(['status'=>'success']);
        json_response(['status'=>'error','message'=>'이동 실패']);
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) json_response(['status'=>'error','message'=>'잘못된 요청 형식(JSON)']);

    $action = $input['action'] ?? '';
    try {
        switch ($action) {

            /* ---------- Members ---------- */
            case 'add_member': {
                $name = safe_trim($input['name'] ?? '');
                assert_nonempty($name, 'name');

                $stmt = $db->prepare("INSERT INTO youth_members (member_name) VALUES (:name)");
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            case 'get_member_detail': {
                $mid = to_int($input['member_id'] ?? 0);
                assert_positive_int($mid, 'member_id');

                sync_member_status_stages($db, $mid);

                $stmt = $db->prepare("SELECT * FROM youth_members WHERE member_id = :id");
                $stmt->bindValue(':id', $mid, SQLITE3_INTEGER);
                $member = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$member) throw new Exception("멤버를 찾을 수 없습니다.");

                $inv = [];
                $stmt = $db->prepare("
                    SELECT i.item_name, v.quantity, i.item_id, i.price
                    FROM youth_inventory v
                    JOIN youth_items i ON v.item_id = i.item_id
                    WHERE v.member_id = :mid
                    ORDER BY i.item_id ASC
                ");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $res = $stmt->execute();
                while ($r = $res->fetchArray(SQLITE3_ASSOC)) $inv[] = $r;

                $stats = [];
                $stmt = $db->prepare("
                    SELECT a.id, t.status_name, a.type_id, a.current_stage, a.applied_at
                    FROM youth_active_statuses a
                    JOIN youth_status_types t ON a.type_id = t.type_id
                    WHERE a.member_id = :mid
                    ORDER BY a.applied_at DESC
                ");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $res = $stmt->execute();
                while ($r = $res->fetchArray(SQLITE3_ASSOC)) $stats[] = $r;

                json_response(['status'=>'success','member'=>$member,'inventory'=>$inv,'statuses'=>$stats]);
            }

            case 'update_member_detail': {
                $mid = to_int($input['member_id'] ?? 0);
                assert_positive_int($mid, 'member_id');

                $name = safe_trim($input['name'] ?? '');
                assert_nonempty($name, 'name');

                $pts = to_int($input['points'] ?? 0);

                $stmt = $db->prepare("UPDATE youth_members SET member_name = :name, points = :pts WHERE member_id = :id");
                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':pts', $pts, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $mid, SQLITE3_INTEGER);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            /* ---------- Status types ---------- */
            case 'add_status_type': {
                $name = safe_trim($input['status_name'] ?? '');
                $maxStage = to_int($input['max_stage'] ?? 1);
                $intervalsHM = $input['intervals_hm'] ?? [];

                assert_nonempty($name, 'status_name');
                if ($maxStage < 1) throw new Exception("max_stage는 1 이상이어야 합니다.");
                if (!is_array($intervalsHM)) $intervalsHM = [];

                $intervalsMin = [];
                for ($i = 0; $i < $maxStage - 1; $i++) {
                    $hm = (string)($intervalsHM[$i] ?? '0:00');
                    $intervalsMin[] = parse_hm_to_minutes($hm);
                }

                $stmt = $db->prepare("
                    INSERT INTO youth_status_types (status_name, max_stage, evolve_interval_json)
                    VALUES (:n, :ms, :j)
                ");
                $stmt->bindValue(':n', $name, SQLITE3_TEXT);
                $stmt->bindValue(':ms', $maxStage, SQLITE3_INTEGER);
                $stmt->bindValue(':j', json_encode($intervalsMin, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            case 'update_status_type': {
                $typeId = to_int($input['type_id'] ?? 0);
                assert_positive_int($typeId, 'type_id');

                $name = safe_trim($input['status_name'] ?? '');
                $maxStage = to_int($input['max_stage'] ?? 1);
                $intervalsHM = $input['intervals_hm'] ?? [];

                assert_nonempty($name, 'status_name');
                if ($maxStage < 1) throw new Exception("max_stage는 1 이상이어야 합니다.");
                if (!is_array($intervalsHM)) $intervalsHM = [];

                $intervalsMin = [];
                for ($i = 0; $i < $maxStage - 1; $i++) {
                    $hm = (string)($intervalsHM[$i] ?? '0:00');
                    $intervalsMin[] = parse_hm_to_minutes($hm);
                }

                $stmt = $db->prepare("
                    UPDATE youth_status_types
                    SET status_name = :n, max_stage = :ms, evolve_interval_json = :j
                    WHERE type_id = :id
                ");
                $stmt->bindValue(':n', $name, SQLITE3_TEXT);
                $stmt->bindValue(':ms', $maxStage, SQLITE3_INTEGER);
                $stmt->bindValue(':j', json_encode($intervalsMin, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                $stmt->bindValue(':id', $typeId, SQLITE3_INTEGER);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            case 'delete_status_type': {
                $typeId = to_int($input['type_id'] ?? 0);
                assert_positive_int($typeId, 'type_id');

                $stmt = $db->prepare("DELETE FROM youth_active_statuses WHERE type_id = :id");
                $stmt->bindValue(':id', $typeId, SQLITE3_INTEGER);
                $stmt->execute();

                $stmt = $db->prepare("DELETE FROM youth_status_types WHERE type_id = :id");
                $stmt->bindValue(':id', $typeId, SQLITE3_INTEGER);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            case 'get_status_types': {
                $types = [];
                $res = $db->query("SELECT * FROM youth_status_types ORDER BY type_id ASC");
                while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
                    $intervals = json_decode($r['evolve_interval_json'] ?? '[]', true);
                    if (!is_array($intervals)) $intervals = [];
                    $r['intervals_hm'] = array_map('minutes_to_hm', $intervals);
                    $types[] = $r;
                }
                json_response(['status'=>'success','types'=>$types]);
            }

            /* ---------- Status apply/remove/update stage (PATCH) ---------- */
            case 'grant_status': {
                $mid = to_int($input['member_id'] ?? 0);
                $typeId = to_int($input['type_id'] ?? 0);
                assert_positive_int($mid, 'member_id');
                assert_positive_int($typeId, 'type_id');

                $stmt = $db->prepare("SELECT member_name FROM youth_members WHERE member_id = :id");
                $stmt->bindValue(':id', $mid, SQLITE3_INTEGER);
                $m = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$m) throw new Exception("멤버를 찾을 수 없습니다.");
                $mName = $m['member_name'];

                $stmt = $db->prepare("SELECT status_name, max_stage FROM youth_status_types WHERE type_id = :id");
                $stmt->bindValue(':id', $typeId, SQLITE3_INTEGER);
                $t = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$t) throw new Exception("상태이상 타입을 찾을 수 없습니다.");

                $stmt = $db->prepare("SELECT id FROM youth_active_statuses WHERE member_id = :mid AND type_id = :tid LIMIT 1");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $stmt->bindValue(':tid', $typeId, SQLITE3_INTEGER);
                $exists = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                if ($exists) {
                    $u = $db->prepare("
                        UPDATE youth_active_statuses
                        SET current_stage = 1, applied_at = datetime('now','localtime')
                        WHERE id = :id
                    ");
                    $u->bindValue(':id', (int)$exists['id'], SQLITE3_INTEGER);
                    $u->execute();
                } else {
                    $ins = $db->prepare("
                        INSERT INTO youth_active_statuses (member_id, type_id, current_stage, applied_at)
                        VALUES (:mid, :tid, 1, datetime('now','localtime'))
                    ");
                    $ins->bindValue(':mid', $mid, SQLITE3_INTEGER);
                    $ins->bindValue(':tid', $typeId, SQLITE3_INTEGER);
                    $ins->execute();
                }

                log_insert($db, $mid, $mName, 'system', $t['status_name'], "+1", "상태이상 부여/갱신");

                json_response(['status'=>'success']);
            }

            case 'remove_status': {
                $mid = to_int($input['member_id'] ?? 0);
                $activeId = to_int($input['active_id'] ?? 0);
                assert_positive_int($mid, 'member_id');
                assert_positive_int($activeId, 'active_id');

                $stmt = $db->prepare("SELECT member_name FROM youth_members WHERE member_id = :id");
                $stmt->bindValue(':id', $mid, SQLITE3_INTEGER);
                $m = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$m) throw new Exception("멤버를 찾을 수 없습니다.");
                $mName = $m['member_name'];

                $stmt = $db->prepare("
                    SELECT t.status_name
                    FROM youth_active_statuses a
                    JOIN youth_status_types t ON a.type_id = t.type_id
                    WHERE a.id = :aid AND a.member_id = :mid
                ");
                $stmt->bindValue(':aid', $activeId, SQLITE3_INTEGER);
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$row) throw new Exception("해당 상태이상을 찾을 수 없습니다.");

                $stmt = $db->prepare("DELETE FROM youth_active_statuses WHERE id = :aid AND member_id = :mid");
                $stmt->bindValue(':aid', $activeId, SQLITE3_INTEGER);
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $stmt->execute();

                log_insert($db, $mid, $mName, 'system', $row['status_name'], "-1", "상태이상 해제");

                json_response(['status'=>'success']);
            }

            case 'set_status_stage': {
                $mid = to_int($input['member_id'] ?? 0);
                $activeId = to_int($input['active_id'] ?? 0);
                $stage = to_int($input['stage'] ?? 1);
                assert_positive_int($mid, 'member_id');
                assert_positive_int($activeId, 'active_id');

                $stmt = $db->prepare("
                    SELECT a.id, a.current_stage, t.status_name, t.max_stage
                    FROM youth_active_statuses a
                    JOIN youth_status_types t ON a.type_id = t.type_id
                    WHERE a.id = :aid AND a.member_id = :mid
                ");
                $stmt->bindValue(':aid', $activeId, SQLITE3_INTEGER);
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$row) throw new Exception("해당 상태이상을 찾을 수 없습니다.");

                $maxStage = (int)$row['max_stage'];
                $stage = clamp_int($stage, 1, $maxStage);

                $u = $db->prepare("UPDATE youth_active_statuses SET current_stage = :s WHERE id = :aid");
                $u->bindValue(':s', $stage, SQLITE3_INTEGER);
                $u->bindValue(':aid', $activeId, SQLITE3_INTEGER);
                $u->execute();

                $stmt = $db->prepare("SELECT member_name FROM youth_members WHERE member_id = :id");
                $stmt->bindValue(':id', $mid, SQLITE3_INTEGER);
                $m = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                $mName = $m ? $m['member_name'] : '';

                log_insert($db, $mid, $mName, 'system', $row['status_name'], (string)$stage, "상태이상 단계 수동 설정");

                json_response(['status'=>'success']);
            }

            /* ---------- Items / Shop ---------- */
            case 'add_item': {
                $name = safe_trim($input['name'] ?? '');
                $desc = trim((string)($input['desc'] ?? ''));
                assert_nonempty($name, 'item_name');

                $price = to_int($input['price'] ?? 0);
                $stock = to_int($input['stock'] ?? -1);
                $status = safe_trim($input['status'] ?? 'selling');

                if ($price < 0) throw new Exception("가격은 0 이상이어야 합니다.");
                if ($stock < -1) throw new Exception("재고는 -1(무한) 또는 0 이상이어야 합니다.");
                if ($status === '') $status = 'selling';

                $stmt = $db->prepare("
                    INSERT INTO youth_items (item_name, price, stock, status, item_description)
                    VALUES (:n, :p, :s, :st, :d)
                ");
                $stmt->bindValue(':n', $name, SQLITE3_TEXT);
                $stmt->bindValue(':p', $price, SQLITE3_INTEGER);
                $stmt->bindValue(':s', $stock, SQLITE3_INTEGER);
                $stmt->bindValue(':st', $status, SQLITE3_TEXT);
                $stmt->bindValue(':d', $desc, SQLITE3_TEXT);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            case 'update_item': {
                $iid = to_int($input['item_id'] ?? 0);
                assert_positive_int($iid, 'item_id');

                $name = safe_trim($input['name'] ?? '');
                $price = to_int($input['price'] ?? 0);
                $stock = to_int($input['stock'] ?? -1);
                $status = safe_trim($input['status'] ?? 'selling');
                $desc = trim((string)($input['desc'] ?? ''));

                assert_nonempty($name, 'item_name');
                if ($price < 0) throw new Exception("가격은 0 이상이어야 합니다.");
                if ($stock < -1) throw new Exception("재고는 -1(무한) 또는 0 이상이어야 합니다.");
                if ($status === '') $status = 'selling';

                $stmt = $db->prepare("
                    UPDATE youth_items
                    SET item_name=:n, price=:p, stock=:s, status=:st, item_description=:d
                    WHERE item_id=:id
                ");
                $stmt->bindValue(':n', $name, SQLITE3_TEXT);
                $stmt->bindValue(':p', $price, SQLITE3_INTEGER);
                $stmt->bindValue(':s', $stock, SQLITE3_INTEGER);
                $stmt->bindValue(':st', $status, SQLITE3_TEXT);
                $stmt->bindValue(':d', $desc, SQLITE3_TEXT);
                $stmt->bindValue(':id', $iid, SQLITE3_INTEGER);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            /* ---------- Inventory manage (grant/purchase/admin) ---------- */
            case 'manage_inventory': {
                $mid = to_int($input['member_id'] ?? 0);
                $iid = to_int($input['item_id'] ?? 0);
                $qty = to_int($input['quantity'] ?? 0);
                $deduct = !empty($input['deduct_point']); // purchase mode

                assert_positive_int($mid, 'member_id');
                assert_positive_int($iid, 'item_id');
                if ($qty < 0) throw new Exception("수량은 0 이상이어야 합니다.");

                $stmt = $db->prepare("SELECT member_name, points FROM youth_members WHERE member_id = :mid");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $member = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$member) throw new Exception("멤버를 찾을 수 없습니다.");

                $stmt = $db->prepare("SELECT item_name, price, stock, status FROM youth_items WHERE item_id = :iid");
                $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                $item = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$item) throw new Exception("아이템을 찾을 수 없습니다.");

                if ($qty === 0) {
                    $stmt = $db->prepare("DELETE FROM youth_inventory WHERE member_id = :mid AND item_id = :iid");
                    $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                    $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                    $stmt->execute();
                    $curr = (int)$db->querySingle("SELECT points FROM youth_members WHERE member_id = ".$mid);
                    json_response(['status'=>'success','current_points'=>$curr]);
                }

                if ($deduct) {
                    if (($item['status'] ?? 'selling') !== 'selling') {
                        throw new Exception("현재 판매중이 아닌 아이템입니다.");
                    }
                    $price = (int)$item['price'];
                    if ($price < 0) $price = 0;
                    $totalPrice = $price * $qty;

                    $stock = (int)$item['stock'];
                    if ($stock !== -1) {
                        if ($stock < $qty) throw new Exception("재고가 부족합니다. (현재 재고: {$stock})");
                    }

                    $stmt = $db->prepare("
                        UPDATE youth_members
                        SET points = points - :cost
                        WHERE member_id = :mid AND points >= :cost
                    ");
                    $stmt->bindValue(':cost', $totalPrice, SQLITE3_INTEGER);
                    $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                    $stmt->execute();
                    if ($db->changes() === 0) throw new Exception("잔액이 부족합니다. (필요: {$totalPrice} P)");

                    if ($stock !== -1) {
                        $stmt = $db->prepare("
                            UPDATE youth_items
                            SET stock = stock - :q
                            WHERE item_id = :iid AND stock >= :q
                        ");
                        $stmt->bindValue(':q', $qty, SQLITE3_INTEGER);
                        $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                        $stmt->execute();
                        if ($db->changes() === 0) throw new Exception("재고 차감 실패(동시성). 다시 시도해주세요.");
                    }

                    log_insert($db, $mid, $member['member_name'], 'point', '', "-{$totalPrice}", "{$item['item_name']} 구매");
                }

                $stmt = $db->prepare("
                    INSERT INTO youth_inventory (member_id, item_id, quantity)
                    VALUES (:mid, :iid, :q)
                    ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + :q
                ");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                $stmt->bindValue(':q', $qty, SQLITE3_INTEGER);
                $stmt->execute();

                log_insert($db, $mid, $member['member_name'], 'item', $item['item_name'], "+{$qty}", $deduct ? "구매로 지급" : "관리자 지급");

                $curr = (int)$db->querySingle("SELECT points FROM youth_members WHERE member_id = ".$mid);
                json_response(['status'=>'success','current_points'=>$curr]);
            }

            case 'consume_item': {
                $mid = to_int($input['member_id'] ?? 0);
                $iid = to_int($input['item_id'] ?? 0);
                assert_positive_int($mid, 'member_id');
                assert_positive_int($iid, 'item_id');

                $stmt = $db->prepare("SELECT member_name FROM youth_members WHERE member_id = :mid");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $mName = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$mName) throw new Exception("멤버를 찾을 수 없습니다.");
                $mName = $mName['member_name'];

                $stmt = $db->prepare("SELECT item_name FROM youth_items WHERE item_id = :iid");
                $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                $i = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$i) throw new Exception("아이템을 찾을 수 없습니다.");
                $iName = $i['item_name'];

                $stmt = $db->prepare("
                    UPDATE youth_inventory
                    SET quantity = quantity - 1
                    WHERE member_id = :mid AND item_id = :iid AND quantity > 0
                ");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                $stmt->execute();

                if ($db->changes() <= 0) throw new Exception("소모할 아이템이 없습니다.");

                $stmt = $db->prepare("DELETE FROM youth_inventory WHERE member_id = :mid AND item_id = :iid AND quantity <= 0");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                $stmt->execute();

                log_insert($db, $mid, $mName, 'item', $iName, "-1", "아이템 소모");

                json_response(['status'=>'success']);
            }

            /* ---------- Bulk grant ---------- */
            case 'bulk_action': {
                $targets = $input['targets'] ?? [];
                $mode = $input['mode'] ?? '';
                if (!is_array($targets) || count($targets) === 0) throw new Exception("대상이 없습니다.");

                if ($mode === 'point') {
                    $amt = to_int($input['amount'] ?? 0);
                    if ($amt === 0) throw new Exception("포인트 변동값이 0입니다.");

                    foreach ($targets as $tidRaw) {
                        $tid = to_int($tidRaw);
                        assert_positive_int($tid, 'member_id');

                        $stmt = $db->prepare("SELECT member_name, points FROM youth_members WHERE member_id = :id");
                        $stmt->bindValue(':id', $tid, SQLITE3_INTEGER);
                        $m = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                        if (!$m) continue;

                        if ($amt < 0) {
                            $need = abs($amt);
                            $stmt = $db->prepare("
                                UPDATE youth_members
                                SET points = points - :need
                                WHERE member_id = :id
                            ");
                            $stmt->bindValue(':need', $need, SQLITE3_INTEGER);
                            $stmt->bindValue(':id', $tid, SQLITE3_INTEGER);
                            $stmt->execute();

                            log_insert($db, $tid, $m['member_name'], 'point', '', (string)$amt, "일괄 회수");
                        } else {
                            $stmt = $db->prepare("UPDATE youth_members SET points = points + :amt WHERE member_id = :id");
                            $stmt->bindValue(':amt', $amt, SQLITE3_INTEGER);
                            $stmt->bindValue(':id', $tid, SQLITE3_INTEGER);
                            $stmt->execute();
                            log_insert($db, $tid, $m['member_name'], 'point', '', "+{$amt}", "일괄 지급");
                        }
                    }
                    json_response(['status'=>'success']);
                }

                if ($mode === 'item') {
                    $items = $input['items'] ?? [];
                    if (!is_array($items) || count($items) === 0) throw new Exception("지급할 아이템이 없습니다.");
                    $deduct = !empty($input['deduct_point']);

                    foreach ($targets as $tidRaw) {
                        $tid = to_int($tidRaw);
                        assert_positive_int($tid, 'member_id');

                        $stmt = $db->prepare("SELECT member_name, points FROM youth_members WHERE member_id = :id");
                        $stmt->bindValue(':id', $tid, SQLITE3_INTEGER);
                        $m = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                        if (!$m) continue;

                        foreach ($items as $it) {
                            $iid = to_int($it['id'] ?? 0);
                            $iqty = to_int($it['qty'] ?? 0);
                            if ($iid <= 0 || $iqty <= 0) continue;

                            $stmt = $db->prepare("SELECT item_name, price, stock, status FROM youth_items WHERE item_id = :iid");
                            $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                            $item = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                            if (!$item) continue;

                            if ($deduct) {
                                if (($item['status'] ?? 'selling') !== 'selling') continue;

                                $totalPrice = ((int)$item['price']) * $iqty;

                                $stock = (int)$item['stock'];
                                if ($stock !== -1 && $stock < $iqty) continue;

                                $stmt = $db->prepare("
                                    UPDATE youth_members
                                    SET points = points - :cost
                                    WHERE member_id = :mid AND points >= :cost
                                ");
                                $stmt->bindValue(':cost', $totalPrice, SQLITE3_INTEGER);
                                $stmt->bindValue(':mid', $tid, SQLITE3_INTEGER);
                                $stmt->execute();
                                if ($db->changes() === 0) continue;

                                if ($stock !== -1) {
                                    $stmt = $db->prepare("
                                        UPDATE youth_items
                                        SET stock = stock - :q
                                        WHERE item_id = :iid AND stock >= :q
                                    ");
                                    $stmt->bindValue(':q', $iqty, SQLITE3_INTEGER);
                                    $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                                    $stmt->execute();
                                    if ($db->changes() === 0) continue;
                                }

                                log_insert($db, $tid, $m['member_name'], 'point', '', "-{$totalPrice}", "{$item['item_name']} (일괄 구매)");
                            }

                            $stmt = $db->prepare("
                                INSERT INTO youth_inventory (member_id, item_id, quantity)
                                VALUES (:mid, :iid, :q)
                                ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + :q
                            ");
                            $stmt->bindValue(':mid', $tid, SQLITE3_INTEGER);
                            $stmt->bindValue(':iid', $iid, SQLITE3_INTEGER);
                            $stmt->bindValue(':q', $iqty, SQLITE3_INTEGER);
                            $stmt->execute();

                            log_insert($db, $tid, $m['member_name'], 'item', $item['item_name'], "+{$iqty}", $deduct ? "일괄 구매 지급" : "일괄 지급");
                        }
                    }

                    json_response(['status'=>'success']);
                }

                /* ---- PATCH: bulk status ---- */
                if ($mode === 'status') {
                    $typeId = to_int($input['type_id'] ?? 0);
                    $op = (string)($input['op'] ?? 'add'); // add / remove
                    assert_positive_int($typeId, 'type_id');
                    if ($op !== 'add' && $op !== 'remove') $op = 'add';

                    $stmt = $db->prepare("SELECT status_name FROM youth_status_types WHERE type_id = :id");
                    $stmt->bindValue(':id', $typeId, SQLITE3_INTEGER);
                    $t = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    if (!$t) throw new Exception("상태이상 타입을 찾을 수 없습니다.");
                    $tName = $t['status_name'];

                    foreach ($targets as $tidRaw) {
                        $tid = to_int($tidRaw);
                        if ($tid <= 0) continue;

                        $stmt = $db->prepare("SELECT member_name FROM youth_members WHERE member_id = :id");
                        $stmt->bindValue(':id', $tid, SQLITE3_INTEGER);
                        $m = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                        if (!$m) continue;
                        $mName = $m['member_name'];

                        if ($op === 'add') {
                            $stmt = $db->prepare("SELECT id FROM youth_active_statuses WHERE member_id = :mid AND type_id = :tid LIMIT 1");
                            $stmt->bindValue(':mid', $tid, SQLITE3_INTEGER);
                            $stmt->bindValue(':tid', $typeId, SQLITE3_INTEGER);
                            $ex = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                            if ($ex) {
                                $u = $db->prepare("
                                    UPDATE youth_active_statuses
                                    SET current_stage = 1, applied_at = datetime('now','localtime')
                                    WHERE id = :id
                                ");
                                $u->bindValue(':id', (int)$ex['id'], SQLITE3_INTEGER);
                                $u->execute();
                            } else {
                                $ins = $db->prepare("
                                    INSERT INTO youth_active_statuses (member_id, type_id, current_stage, applied_at)
                                    VALUES (:mid, :tid, 1, datetime('now','localtime'))
                                ");
                                $ins->bindValue(':mid', $tid, SQLITE3_INTEGER);
                                $ins->bindValue(':tid', $typeId, SQLITE3_INTEGER);
                                $ins->execute();
                            }

                            log_insert($db, $tid, $mName, 'system', $tName, "+1", "상태이상 일괄 부여/갱신");
                        } else {
                            $stmt = $db->prepare("DELETE FROM youth_active_statuses WHERE member_id = :mid AND type_id = :tid");
                            $stmt->bindValue(':mid', $tid, SQLITE3_INTEGER);
                            $stmt->bindValue(':tid', $typeId, SQLITE3_INTEGER);
                            $stmt->execute();

                            log_insert($db, $tid, $mName, 'system', $tName, "-1", "상태이상 일괄 해제");
                        }
                    }

                    json_response(['status'=>'success']);
                }

                throw new Exception("알 수 없는 bulk mode");
            }

            /* ---------- M:N transfer (point or item) ---------- */
            case 'execute_mn_transfer': {
                $transfers = $input['transfers'] ?? [];
                if (!is_array($transfers) || count($transfers) === 0) throw new Exception("양도 건이 없습니다.");

                $failures = [];

                foreach ($transfers as $tr) {
                    $fId = to_int($tr['from_id'] ?? 0);
                    $tId = to_int($tr['to_id'] ?? 0);
                    $val = to_int($tr['val'] ?? 0);
                    $type = $tr['type'] ?? '';

                    assert_positive_int($fId, 'from_id');
                    assert_positive_int($tId, 'to_id');
                    if ($fId === $tId) continue;
                    if ($val <= 0) continue;

                    $stmt = $db->prepare("SELECT member_name FROM youth_members WHERE member_id = :id");
                    $stmt->bindValue(':id', $fId, SQLITE3_INTEGER);
                    $f = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    if (!$f) continue;

                    $stmt = $db->prepare("SELECT member_name FROM youth_members WHERE member_id = :id");
                    $stmt->bindValue(':id', $tId, SQLITE3_INTEGER);
                    $t = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    if (!$t) continue;

                    $fName = $f['member_name'];
                    $tName = $t['member_name'];

                    if ($type === 'point') {
                        $stmt = $db->prepare("
                            UPDATE youth_members
                            SET points = points - :v
                            WHERE member_id = :fid AND points >= :v
                        ");
                        $stmt->bindValue(':v', $val, SQLITE3_INTEGER);
                        $stmt->bindValue(':fid', $fId, SQLITE3_INTEGER);
                        $stmt->execute();
                        if ($db->changes() <= 0) {
                            $failures[] = ['type'=>'point','from'=>$fName,'to'=>$tName,'val'=>$val,'reason'=>'잔액 부족'];
                            continue;
                        }

                        $stmt = $db->prepare("UPDATE youth_members SET points = points + :v WHERE member_id = :tid");
                        $stmt->bindValue(':v', $val, SQLITE3_INTEGER);
                        $stmt->bindValue(':tid', $tId, SQLITE3_INTEGER);
                        $stmt->execute();

                        log_insert($db, $fId, $fName, 'point', '', "-{$val}", "{$tName} 양도");
                        log_insert($db, $tId, $tName, 'point', '', "+{$val}", "{$fName} 수령");
                    } else if ($type === 'item') {
                        $iId = to_int($tr['item_id'] ?? 0);
                        assert_positive_int($iId, 'item_id');

                        $stmt = $db->prepare("SELECT item_name FROM youth_items WHERE item_id = :iid");
                        $stmt->bindValue(':iid', $iId, SQLITE3_INTEGER);
                        $item = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                        if (!$item) continue;
                        $iName = $item['item_name'];

                        $stmt = $db->prepare("
                            UPDATE youth_inventory
                            SET quantity = quantity - :v
                            WHERE member_id = :fid AND item_id = :iid AND quantity >= :v
                        ");
                        $stmt->bindValue(':v', $val, SQLITE3_INTEGER);
                        $stmt->bindValue(':fid', $fId, SQLITE3_INTEGER);
                        $stmt->bindValue(':iid', $iId, SQLITE3_INTEGER);
                        $stmt->execute();
                        if ($db->changes() <= 0) {
                            $failures[] = ['type'=>'point','from'=>$fName,'to'=>$tName,'val'=>$val,'reason'=>'잔액 부족'];
                            continue;
                        }

                        $stmt = $db->prepare("DELETE FROM youth_inventory WHERE member_id = :fid AND item_id = :iid AND quantity <= 0");
                        $stmt->bindValue(':fid', $fId, SQLITE3_INTEGER);
                        $stmt->bindValue(':iid', $iId, SQLITE3_INTEGER);
                        $stmt->execute();

                        $stmt = $db->prepare("
                            INSERT INTO youth_inventory (member_id, item_id, quantity)
                            VALUES (:tid, :iid, :v)
                            ON CONFLICT(member_id, item_id) DO UPDATE SET quantity = quantity + :v
                        ");
                        $stmt->bindValue(':tid', $tId, SQLITE3_INTEGER);
                        $stmt->bindValue(':iid', $iId, SQLITE3_INTEGER);
                        $stmt->bindValue(':v', $val, SQLITE3_INTEGER);
                        $stmt->execute();

                        log_insert($db, $fId, $fName, 'item', $iName, "-{$val}", "{$tName} 양도");
                        log_insert($db, $tId, $tName, 'item', $iName, "+{$val}", "{$fName} 수령");
                    }
                }

                json_response(['status'=>'success','failures'=>$failures]);
            }

            /* ---------- Roulette presets ---------- */
            case 'add_roulette_config': {
                $name = safe_trim($input['name'] ?? '');
                $text = trim((string)($input['multipliers_text'] ?? ''));
                assert_nonempty($name, '룰렛 이름');
                assert_nonempty($text, '배율 목록');
                $rates = parse_multipliers($text);
                if (count($rates) === 0) throw new Exception("배율 목록이 비어 있습니다.");

                $stmt = $db->prepare("
                    INSERT INTO youth_roulette_configs (config_name, multipliers_text)
                    VALUES (:n, :t)
                ");
                $stmt->bindValue(':n', $name, SQLITE3_TEXT);
                $stmt->bindValue(':t', $text, SQLITE3_TEXT);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            case 'update_roulette_config': {
                $id = to_int($input['config_id'] ?? 0);
                assert_positive_int($id, 'config_id');

                $name = safe_trim($input['name'] ?? '');
                $text = trim((string)($input['multipliers_text'] ?? ''));
                assert_nonempty($name, '룰렛 이름');
                assert_nonempty($text, '배율 목록');
                $rates = parse_multipliers($text);
                if (count($rates) === 0) throw new Exception("배율 목록이 비어 있습니다.");

                $stmt = $db->prepare("
                    UPDATE youth_roulette_configs
                    SET config_name = :n, multipliers_text = :t
                    WHERE config_id = :id
                ");
                $stmt->bindValue(':n', $name, SQLITE3_TEXT);
                $stmt->bindValue(':t', $text, SQLITE3_TEXT);
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            case 'delete_roulette_config': {
                $id = to_int($input['config_id'] ?? 0);
                assert_positive_int($id, 'config_id');

                $stmt = $db->prepare("DELETE FROM youth_roulette_configs WHERE config_id = :id");
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmt->execute();

                $stmt = $db->prepare("DELETE FROM youth_roulette_stats WHERE config_id = :id");
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                $stmt->execute();

                json_response(['status'=>'success']);
            }

            /* ---------- Games ---------- */
            case 'run_game': {
                $mid = to_int($input['member_id'] ?? 0);
                $bet = to_int($input['bet'] ?? 0);
                $type = (string)($input['type'] ?? '');

                assert_positive_int($mid, 'member_id');
                if ($bet <= 0) throw new Exception("배팅금액은 1 이상이어야 합니다.");

                $stmt = $db->prepare("SELECT member_name, points FROM youth_members WHERE member_id = :mid");
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $member = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                if (!$member) throw new Exception("멤버를 찾을 수 없습니다.");
                $mName = $member['member_name'];

                // 모든 게임 공통: 선차감
                $stmt = $db->prepare("
                    UPDATE youth_members
                    SET points = points - :bet
                    WHERE member_id = :mid AND points >= :bet
                ");
                $stmt->bindValue(':bet', $bet, SQLITE3_INTEGER);
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $stmt->execute();
                if ($db->changes() === 0) throw new Exception("잔액이 부족하여 게임을 시작할 수 없습니다.");

                $winAmt = 0; // 서버가 “지급액(payout)”으로 내려줄 값
                $msg = '';

                if ($type === 'roulette') {
                    $cfgId = to_int($input['config_id'] ?? 0);
                    assert_positive_int($cfgId, 'config_id');

                    $stmt = $db->prepare("SELECT config_name, multipliers_text FROM youth_roulette_configs WHERE config_id = :id");
                    $stmt->bindValue(':id', $cfgId, SQLITE3_INTEGER);
                    $cfg = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
                    if (!$cfg) throw new Exception("존재하지 않는 룰렛 설정입니다.");

                    $rates = parse_multipliers($cfg['multipliers_text']);
                    if (count($rates) === 0) throw new Exception("룰렛 배율이 비어 있습니다.");

                    $multiplier = $rates[array_rand($rates)];
                    $msg = "룰렛({$cfg['config_name']}): {$multiplier}배";

                    $winAmt = (int)floor($bet * $multiplier);

                    $stmt = $db->prepare("
                        INSERT INTO youth_roulette_stats (config_id, play_count, total_bet, total_payout, last_play_time)
                        VALUES (:id, 1, :bet, :pay, datetime('now','localtime'))
                        ON CONFLICT(config_id) DO UPDATE SET
                            play_count = play_count + 1,
                            total_bet = total_bet + :bet,
                            total_payout = total_payout + :pay,
                            last_play_time = datetime('now','localtime')
                    ");
                    $stmt->bindValue(':id', $cfgId, SQLITE3_INTEGER);
                    $stmt->bindValue(':bet', $bet, SQLITE3_INTEGER);
                    $stmt->bindValue(':pay', $winAmt, SQLITE3_INTEGER);
                    $stmt->execute();

                } else if ($type === 'odd_even') {
                    $pick = (string)($input['pick'] ?? '');
                    if ($pick !== '홀' && $pick !== '짝') throw new Exception("홀/짝 선택이 올바르지 않습니다.");

                    $res = (random_int(0, 1) === 0) ? '홀' : '짝';
                    $win = ($pick === $res);
                    $winAmt = $win ? ($bet * 2) : 0;
                    $msg = "홀짝 결과: [{$res}] - ".($win ? "승리" : "패배");

                } else if ($type === 'blackjack') {
                    $winAmtInput = to_int($input['win_amt'] ?? 0);
                    $winAmt = clamp_int($winAmtInput, 0, $bet * 2);
                    $msg = safe_trim($input['msg'] ?? '블랙잭 결과');

                } else {
                    throw new Exception("정의되지 않은 게임 타입");
                }

                $stmt = $db->prepare("UPDATE youth_members SET points = points + :win WHERE member_id = :mid");
                $stmt->bindValue(':win', $winAmt, SQLITE3_INTEGER);
                $stmt->bindValue(':mid', $mid, SQLITE3_INTEGER);
                $stmt->execute();

                $net = $winAmt - $bet;
                log_insert($db, $mid, $mName, 'game', '', (string)$net, $msg);

                $newPoints = (int)$db->querySingle("SELECT points FROM youth_members WHERE member_id = ".$mid);

                json_response([
                    'status'=>'success',
                    'points'=>$newPoints,
                    'win_amt'=>$winAmt,
                    'msg'=>$msg
                ]);
            }

            /* ---------- Logs ---------- */
            case 'get_logs': {
                $filter = (string)($input['filter'] ?? 'all');
                $qText = trim((string)($input['q'] ?? ''));
                $dateFrom = trim((string)($input['date_from'] ?? '')); // YYYY-MM-DD
                $dateTo = trim((string)($input['date_to'] ?? ''));     // YYYY-MM-DD

                $allowed = ['all','point','item','game','system'];
                if (!in_array($filter, $allowed, true)) $filter = 'all';

                $sql = "SELECT * FROM youth_logs WHERE 1=1";
                $params = [];

                if ($filter !== 'all') {
                    $sql .= " AND log_type = :t";
                    $params[':t'] = $filter;
                }
                if ($qText !== '') {
                    $sql .= " AND (member_name LIKE :q OR reason LIKE :q OR target_name LIKE :q OR change_val LIKE :q)";
                    $params[':q'] = '%'.$qText.'%';
                }
                if ($dateFrom !== '') {
                    $sql .= " AND date(log_time) >= date(:df)";
                    $params[':df'] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $sql .= " AND date(log_time) <= date(:dt)";
                    $params[':dt'] = $dateTo;
                }

                $sql .= " ORDER BY log_time DESC LIMIT 500";

                $stmt = $db->prepare($sql);
                foreach ($params as $k=>$v) $stmt->bindValue($k, $v, SQLITE3_TEXT);
                $res = $stmt->execute();

                $logs = [];
                while ($r = $res->fetchArray(SQLITE3_ASSOC)) $logs[] = $r;

                json_response(['status'=>'success','logs'=>$logs]);
            }

            /* ---------- Resets ---------- */
            case 'reset_season': {
                $db->exec("DELETE FROM youth_members");
                $db->exec("DELETE FROM youth_inventory");
                $db->exec("DELETE FROM youth_logs");
                $db->exec("DELETE FROM youth_active_statuses");
                json_response(['status'=>'success']);
            }

            default:
                throw new Exception("정의되지 않은 기능");
        }

    } catch (Exception $e) {
        json_response(['status'=>'error','message'=>$e->getMessage()]);
    }
}

/* ---------------------------
 *  [5] Initial data (GET)
 * --------------------------- */
$members = [];
$res = $db->query("SELECT * FROM youth_members ORDER BY member_id ASC");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $members[] = $r;

$items = [];
$res = $db->query("SELECT * FROM youth_items ORDER BY item_id ASC");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $items[] = $r;

$rouletteConfigs = [];
$res = $db->query("SELECT * FROM youth_roulette_configs ORDER BY config_id ASC");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rouletteConfigs[] = $r;

$rouletteStats = [];
$res = $db->query("
    SELECT c.config_id, c.config_name, s.play_count, s.total_bet, s.total_payout, s.last_play_time
    FROM youth_roulette_configs c
    LEFT JOIN youth_roulette_stats s ON c.config_id = s.config_id
    ORDER BY c.config_id ASC
");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rouletteStats[] = $r;

$statusTypes = [];
$res = $db->query("SELECT * FROM youth_status_types ORDER BY type_id ASC");
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    $intervals = json_decode($r['evolve_interval_json'] ?? '[]', true);
    if (!is_array($intervals)) $intervals = [];
    $r['intervals_hm'] = array_map('minutes_to_hm', $intervals);
    $statusTypes[] = $r;
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-PARTNER v4.7</title>
    <style>
        :root { --p: #4e73df; --s: #1cc88a; --d: #2c3e50; --bg: #f8f9fc; --border: #e3e6f0; --casino: #1a5e3a; }
        body { font-family: 'Pretendard', sans-serif; margin: 0; display: flex; background: var(--bg); height: 100vh; overflow: hidden; }
        #sidebar { width: 240px; background: var(--d); color: white; display: flex; flex-direction: column; transition: 0.3s; z-index: 1000; }
        .sidebar-brand { padding: 20px; font-size: 1.2rem; font-weight: bold; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-item { padding: 15px 20px; cursor: pointer; color: #adb5bd; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.2s; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.05); color: white; border-left: 4px solid var(--p); }
        .sub-nav { background: rgba(0,0,0,0.2); display: none; }
        .nav-item.open + .sub-nav { display: block; }
        #content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        #topbar { height: 60px; background: white; border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 20px; gap: 10px; }
        #main-view { flex: 1; padding: 20px; overflow-y: auto; }
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; border: 1px solid var(--border); }
        .btn { padding: 10px 16px; border-radius: 8px; border: none; cursor: pointer; font-weight: bold; transition: 0.2s; font-size: 0.9rem; }
        .btn-p { background: var(--p); color: white; }
        .btn-s { background: var(--s); color: white; }
        .btn-danger { background: #e74a3b; color: white; }
        .btn:hover { filter: brightness(0.95); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8f9fc; padding: 12px; text-align: left; border-bottom: 2px solid var(--border); font-size: 0.8rem; color: #4e73df; user-select:none; }
        td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        input, select, textarea { padding: 10px; border: 1px solid var(--border); border-radius: 6px; width: 100%; box-sizing: border-box; }
        .modal { display: none; position: fixed; z-index: 2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background:white; margin:10% auto; padding:25px; border-radius:15px; width:90%; max-width:520px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .tr-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto; gap: 8px; margin-bottom: 10px; align-items: center; background: #f8f9fc; padding: 10px; border-radius: 8px; }
        #bj-board { background: var(--casino); border-radius: 15px; padding: 25px; color: white; text-align: center; border: 4px solid #d4af37; margin-top: 20px; }
        .cards-area { display: flex; justify-content: center; gap: 10px; margin: 15px 0; min-height: 90px; flex-wrap: wrap; }
        .card-obj { width: 50px; height: 75px; background: white; color: black; border-radius: 6px; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 1.2rem; box-shadow: 3px 3px 5px rgba(0,0,0,0.3); }
        .card-obj.red { color: #e74a3b; }
        .hint { font-size: 12px; color:#6c757d; margin-top:8px; }
        .clickable { cursor:pointer; }
        .sort-ind { font-size: 11px; color:#6c757d; margin-left:6px; }
        @media (max-width: 900px){
            body { flex-direction: column; height: auto; overflow: auto; }
            #sidebar { width: 100%; flex-direction: row; overflow-x:auto; }
            .sidebar-brand { display:none; }
            .nav-item { border-left:none!important; white-space:nowrap; }
            #content { height: auto; }
            #topbar { position: sticky; top: 0; z-index: 1200; }
        }
    </style>
</head>
<body>

    <div id="sidebar">
        <div class="sidebar-brand">B-PARTNER v4.7</div>
        <a href="#/members" class="nav-item">🏠 캐릭터 관리</a>
        <a href="#/manage" class="nav-item has-sub">🛠️ 시스템 설정</a>
        <div class="sub-nav">
            <a href="#/manage/shop" class="nav-item">🛒 상점/아이템</a>
            <a href="#/manage/gamble" class="nav-item">🎲 도박/게임</a>
            <a href="#/manage/status" class="nav-item">🧪 상태이상</a>
        </div>
        <a href="#/transfer" class="nav-item">💸 통합 양도</a>
        <a href="#/logs" class="nav-item">📜 로그 확인</a>
        <a href="#/settings" class="nav-item">⚙️ 환경 설정</a>
    </div>

    <div id="content">
        <div id="topbar">
            <input type="text" id="global-search" placeholder="검색..." onkeyup="App.search()" style="max-width:220px;">
            <div style="flex:1"></div>
            <button class="btn btn-p" onclick="location.reload()">새로고침</button>
        </div>
        <div id="main-view"></div>
    </div>

    <div id="modal" class="modal" onclick="if(event.target.id==='modal') App.closeModal()">
        <div class="modal-content" id="modal-body"></div>
    </div>

<script>
const State = {
    members: <?php echo json_encode($members, JSON_UNESCAPED_UNICODE); ?>,
    items: <?php echo json_encode($items, JSON_UNESCAPED_UNICODE); ?>,
    rouletteConfigs: <?php echo json_encode($rouletteConfigs, JSON_UNESCAPED_UNICODE); ?>,
    rouletteStats: <?php echo json_encode($rouletteStats, JSON_UNESCAPED_UNICODE); ?>,
    bulkTargets: [],
    blackjack: { p1:null, p2:null, deck:[], status:'idle', turn:'p1', bet:0, solo:false, revealDealer:false },
    statusTypes: <?php echo json_encode($statusTypes, JSON_UNESCAPED_UNICODE); ?>
};

const Sort = {
  get(key, fallback){ try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch { return fallback; } },
  set(key, obj){ localStorage.setItem(key, JSON.stringify(obj)); },
  toggle(key, field, fallbackDir='asc'){
    const cur = this.get(key, {field, dir:fallbackDir});
    if (cur.field === field) cur.dir = (cur.dir === 'asc' ? 'desc' : 'asc');
    else { cur.field = field; cur.dir = 'asc'; }
    this.set(key, cur);
  },
  apply(list, pref){
    const {field, dir} = pref;
    const mul = (dir === 'asc') ? 1 : -1;
    return [...list].sort((a,b)=>{
      const av = a[field], bv = b[field];
      if (typeof av === 'number' && typeof bv === 'number') return (av-bv)*mul;
      return String(av ?? '').localeCompare(String(bv ?? ''), 'ko')*mul;
    });
  }
};

const App = {
  init() {
    window.addEventListener('hashchange', () => this.router());
    this.router();
    document.querySelectorAll('.has-sub').forEach(el => {
      el.onclick = (e) => { e.preventDefault(); el.classList.toggle('open'); };
    });
  },

  async api(data) {
    const res = await fetch('index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.status === 'error') { alert("오류: " + result.message); throw new Error(result.message); }
    return result;
  },

  router() {
    const hash = location.hash || '#/members';
    const view = document.getElementById('main-view');
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));

    if (hash === '#/members') this.renderMembers(view);
    else if (hash.startsWith('#/member/')) this.renderMemberDetail(view, hash.split('/').pop());
    else if (hash === '#/manage/shop') this.renderShop(view);
    else if (hash === '#/manage/gamble') this.renderGamble(view);
    else if (hash === '#/manage/status') this.renderStatusManage(view).catch(e => console.error(e));
    else if (hash === '#/transfer') this.renderTransfer(view);
    else if (hash === '#/logs') this.renderLogs(view).catch(e => console.error(e));
    else if (hash === '#/settings') this.renderSettings(view);
  },

  /* ---------------- Views ---------------- */
  renderMembers(view) {
    const pref = Sort.get('sort_members', {field:'member_id', dir:'asc'});
    const list = Sort.apply(State.members, pref);

    const sortInd = (field) => pref.field === field ? `<span class="sort-ind">${pref.dir==='asc'?'▲':'▼'}</span>` : '';

    view.innerHTML = `
      <div class="card">
        <h3>⚡ 신규 캐릭터</h3>
        <div style="display:flex; gap:10px;">
          <input type="text" id="new-name" placeholder="캐릭터 이름">
          <button class="btn btn-p" onclick="App.addMember()">등록</button>
        </div>
      </div>

      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <h3>👥 캐릭터 목록</h3>
          <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
            <button class="btn btn-s" onclick="App.openBulk('point')">포인트 일괄</button>
            <button class="btn btn-s" onclick="App.openBulk('item')">아이템 일괄</button>
            <button class="btn btn-s" onclick="App.openBulk('status')">상태이상 일괄</button>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th style="width:40px;"><input type="checkbox" onclick="App.toggleAll(this)"></th>
              <th class="clickable" onclick="App.sortMembers('member_id')">번호 ${sortInd('member_id')}</th>
              <th class="clickable" onclick="App.sortMembers('member_name')">이름 ${sortInd('member_name')}</th>
              <th class="clickable" onclick="App.sortMembers('points')">포인트 ${sortInd('points')}</th>
              <th>관리</th>
            </tr>
          </thead>
          <tbody>
            ${list.map(m => `
              <tr>
                <td><input type="checkbox" class="mem-cb" value="${m.member_id}"></td>
                <td>${m.member_id}</td>
                <td><a href="#/member/${m.member_id}" style="color:var(--p); font-weight:bold;">${this.escape(m.member_name)}</a></td>
                <td>${Number(m.points).toLocaleString()} P</td>
                <td><button class="btn btn-p" onclick="location.hash='#/member/${m.member_id}'">상세</button></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
        <div class="hint">정렬은 브라우저에 저장됩니다(새로고침해도 유지).</div>
      </div>
    `;
  },

  async renderStatusManage(view){
    const res = await this.api({ action:'get_status_types' });
    State.statusTypes = res.types;

    view.innerHTML = `
        <div class="card">
        <h3>🧪 상태이상 타입 추가</h3>
        <div style="display:grid; grid-template-columns: 1fr 120px 2fr auto; gap:10px; align-items:end;">
            <div>
            <label>이름</label>
            <input id="st-name" placeholder="예: 중독">
            </div>
            <div>
            <label>최대 단계</label>
            <input id="st-max" type="number" value="3" min="1">
            </div>
            <div>
            <label>단계 업그레이드 시간들 (h:m, 쉼표/공백 가능)</label>
            <input id="st-times" placeholder="예: 1:00 2:30  (최대단계-1개 필요)">
            <div class="hint">max_stage=3이면 '1→2', '2→3' 두 개가 필요.</div>
            </div>
            <div>
            <button class="btn btn-p" onclick="App.addStatusType()">추가</button>
            </div>
        </div>
        </div>

        <div class="card">
        <h3>상태이상 타입 목록</h3>
        <table>
            <thead>
            <tr><th>ID</th><th>이름</th><th>최대 단계</th><th>업그레이드 시간(1→2,2→3...)</th><th>저장</th><th>삭제</th></tr>
            </thead>
            <tbody>
            ${
                State.statusTypes.map(t => `
                <tr>
                    <td>${t.type_id}</td>
                    <td><input id="stn-${t.type_id}" value="${this.escapeAttr(t.status_name)}"></td>
                    <td><input id="stm-${t.type_id}" type="number" min="1" value="${t.max_stage}"></td>
                    <td><input id="sti-${t.type_id}" value="${this.escapeAttr((t.intervals_hm||[]).join(' '))}" placeholder="예: 1:00 2:30"></td>
                    <td><button class="btn btn-s" onclick="App.saveStatusType(${t.type_id})">저장</button></td>
                    <td><button class="btn btn-danger" onclick="App.deleteStatusType(${t.type_id})">삭제</button></td>
                </tr>
                `).join('')
                || `<tr><td colspan="6">등록된 상태이상이 없습니다.</td></tr>`
            }
            </tbody>
        </table>
        <div class="hint">시간은 h:m 형식입니다. 예) 0:30, 2:00</div>
        </div>
    `;
  },

  parseTimesToArray(text){
    const parts = (text || '').trim().split(/[\s,]+/).filter(Boolean);
    return parts;
  },

  async addStatusType(){
    const name = document.getElementById('st-name').value.trim();
    const max = parseInt(document.getElementById('st-max').value);
    const timesText = document.getElementById('st-times').value;

    if (!name) return alert('이름을 입력하세요.');
    if (!Number.isFinite(max) || max < 1) return alert('최대 단계는 1 이상');

    const arr = this.parseTimesToArray(timesText);
    if (max > 1 && arr.length < (max - 1)) {
        return alert(`최대단계가 ${max}면 시간 ${max-1}개가 필요합니다.`);
    }

    await this.api({ action:'add_status_type', status_name:name, max_stage:max, intervals_hm: arr });
    alert('추가 완료');
    location.reload();
  },

  async saveStatusType(typeId){
    const name = document.getElementById(`stn-${typeId}`).value.trim();
    const max = parseInt(document.getElementById(`stm-${typeId}`).value);
    const timesText = document.getElementById(`sti-${typeId}`).value;

    if (!name) return alert('이름을 입력하세요.');
    if (!Number.isFinite(max) || max < 1) return alert('최대 단계는 1 이상');

    const arr = this.parseTimesToArray(timesText);
    if (max > 1 && arr.length < (max - 1)) {
        return alert(`최대단계가 ${max}면 시간 ${max-1}개가 필요합니다.`);
    }

    await this.api({ action:'update_status_type', type_id:typeId, status_name:name, max_stage:max, intervals_hm: arr });
    alert('저장 완료');
    location.reload();
  },

  async deleteStatusType(typeId){
    if(!confirm('이 상태이상 타입을 삭제할까요? (부여된 상태도 함께 삭제됩니다)')) return;
    await this.api({ action:'delete_status_type', type_id:typeId });
    alert('삭제 완료');
    location.reload();
  },

  async renderMemberDetail(view, id) {
    const res = await this.api({ action: 'get_member_detail', member_id: id });
    const m = res.member;

    view.innerHTML = `
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h2>👤 ${this.escape(m.member_name)} 정보 수정</h2>
        <button class="btn" onclick="history.back()">뒤로가기</button>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <div class="card">
          <h3>기본 설정</h3>
          <label>이름</label><input type="text" id="edit-name" value="${this.escapeAttr(m.member_name)}"><br><br>
          <label>포인트</label><input type="number" id="edit-points" value="${m.points}"><br><br>
          <button class="btn btn-p" style="width:100%" onclick="App.saveMember(${id})">수정 내용 저장</button>
        </div>

        <div class="card">
          <h3>아이템 지급</h3>
          <select id="grant-item-id">
            ${State.items.map(i => `<option value="${i.item_id}">${this.escape(i.item_name)} (${Number(i.price).toLocaleString()}P)</option>`).join('')}
          </select><br><br>
          <input type="number" id="grant-qty" value="1" placeholder="수량(양수)"><br><br>
          <label><input type="checkbox" id="grant-deduct"> 지급 시 포인트 차감(구매 처리)</label><br><br>
          <button class="btn btn-s" style="width:100%" onclick="App.grantItem(${id})">아이템 지급하기</button>
          <div class="hint">구매 처리 시 판매상태/재고를 체크하고 재고를 차감합니다.</div>
        </div>

        <div class="card" style="grid-column: span 2;">
          <h3>📦 소지품 (인벤토리)</h3>
          <table>
            <thead><tr><th>아이템명</th><th>수량</th><th>단가</th><th>작업</th></tr></thead>
            <tbody>
              ${res.inventory.map(i => `
                <tr>
                  <td>${this.escape(i.item_name)}</td>
                  <td>${i.quantity}개</td>
                  <td>${Number(i.price).toLocaleString()}P</td>
                  <td>
                    <button class="btn btn-s" onclick="App.consumeItem(${id}, ${i.item_id}, '${this.escapeJS(i.item_name)}')">소모</button>
                    <button class="btn btn-danger" onclick="App.deleteItem(${id}, ${i.item_id})">삭제</button>
                  </td>
                </tr>
              `).join('') || '<tr><td colspan="4">소지품이 없습니다.</td></tr>'}
            </tbody>
          </table>
        </div>

        <div class="card" style="grid-column: span 2;">
          <h3>🧪 상태이상</h3>

          <div style="display:grid; grid-template-columns: 1fr 180px auto; gap:10px; align-items:end; margin-bottom:12px;">
            <div>
              <label>부여할 상태이상 타입</label>
              <select id="st-grant-type">
                ${State.statusTypes.map(t => `<option value="${t.type_id}">${this.escape(t.status_name)} (max:${t.max_stage})</option>`).join('')}
              </select>
            </div>
            <div class="hint" style="margin:0;">이미 있으면 “재부여(리셋)”됩니다.</div>
            <div>
              <button class="btn btn-s" style="width:100%" onclick="App.grantStatus(${id})">부여/갱신</button>
            </div>
          </div>

          <table>
            <thead>
              <tr>
                <th>타입</th>
                <th style="width:140px;">현재 단계</th>
                <th style="width:180px;">적용 시각</th>
                <th style="width:160px;">단계 변경</th>
                <th style="width:120px;">해제</th>
              </tr>
            </thead>
            <tbody>
              ${
                (res.statuses||[]).map(s => `
                  <tr>
                    <td>${this.escape(s.status_name)}</td>
                    <td>${Number(s.current_stage)} 단계</td>
                    <td>${this.escape(s.applied_at)}</td>
                    <td>
                      <input type="number" min="1" value="${Number(s.current_stage)}" id="st-stage-${s.id}" style="width:90px; display:inline-block;">
                      <button class="btn btn-p" onclick="App.setStatusStage(${id}, ${s.id})">저장</button>
                    </td>
                    <td>
                      <button class="btn btn-danger" onclick="App.removeStatus(${id}, ${s.id}, '${this.escapeJS(s.status_name)}')">해제</button>
                    </td>
                  </tr>
                `).join('')
                || `<tr><td colspan="5">부여된 상태이상이 없습니다.</td></tr>`
              }
            </tbody>
          </table>

          <div class="hint">단계는 1~max_stage 범위로 자동 보정됩니다. 자동 업그레이드는 applied_at 기준으로 서버가 sync합니다.</div>
        </div>

      </div>
    `;
  },

  renderShop(view) {
    const pref = Sort.get('sort_items', {field:'item_id', dir:'asc'});
    const list = Sort.apply(State.items, pref);
    const sortInd = (field) => pref.field === field ? `<span class="sort-ind">${pref.dir==='asc'?'▲':'▼'}</span>` : '';

    view.innerHTML = `
      <div class="card">
        <h3>🛒 새 아이템 등록</h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
          <input type="text" id="item-name" placeholder="아이템 이름">
          <input type="number" id="item-price" placeholder="가격">
          <input type="number" id="item-stock" placeholder="재고 (-1은 무한)">
        </div><br>
        <select id="item-status">
          <option value="selling">selling(판매중)</option>
          <option value="hidden">hidden(숨김)</option>
          <option value="soldout">soldout(품절표시)</option>
        </select><br><br>
        <textarea id="item-desc" placeholder="아이템 설명"></textarea><br><br>
        <button class="btn btn-p" style="width:100%" onclick="App.addItem()">상점 아이템 추가</button>
      </div>

      <div class="card">
        <h3>아이템 목록</h3>
        <table>
        <thead>
        <tr>
            <th class="clickable" onclick="App.sortItems('item_id')">ID ${sortInd('item_id')}</th>
            <th class="clickable" onclick="App.sortItems('item_name')">이름 ${sortInd('item_name')}</th>
            <th class="clickable" onclick="App.sortItems('price')">가격 ${sortInd('price')}</th>
            <th class="clickable" onclick="App.sortItems('stock')">재고 ${sortInd('stock')}</th>
            <th class="clickable" onclick="App.sortItems('status')">상태 ${sortInd('status')}</th>
            <th>설명</th>
            <th>관리</th>
        </tr>
        </thead>

          <tbody>
            ${list.map(i => `
              <tr>
                <td>${i.item_id}</td>
                <td>${this.escape(i.item_name)}</td>
                <td>${Number(i.price).toLocaleString()}P</td>
                <td>${Number(i.stock) === -1 ? '무제한' : Number(i.stock)}</td>
                <td>${this.escape(i.status)}</td>
                <td style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                ${this.escape(i.item_description || '')}
                </td>
                <td><button class="btn btn-s" onclick="App.openItemEdit(${i.item_id})">수정</button></td>
              </tr>
            `).join('')}
          </tbody>
        </table>
        <div class="hint">정렬은 브라우저에 저장됩니다(새로고침해도 유지).</div>
      </div>
    `;
  },

  renderGamble(view) {
    const hasRoulette = State.rouletteConfigs.length > 0;
    view.innerHTML = `
      <div class="card">
        <h3>🎰 게임장</h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
        <div>
            <label>플레이어 (P1)</label>
            <select id="g-p1">
            ${State.members.map(m => `<option value="${m.member_id}">${m.member_name} (${m.points}P)</option>`).join('')}
            </select>
        </div>

        <div>
            <label>상대 (P2/딜러)</label>
            <select id="g-p2">
            <option value="0">--- 시스템 딜러 (1인용) ---</option>
            ${State.members.map(m => `<option value="${m.member_id}">${m.member_name}</option>`).join('')}
            </select>
        </div>
        </div><br>

        <label>배팅금액</label><input type="number" id="g-bet" value="100"><br><br>

        <label>게임 선택</label>
        <select id="g-type" onchange="App.toggleGambleUI(this.value)">
          <option value="roulette">룰렛(프리셋)</option>
          <option value="odd_even">홀짝(서버 판정)</option>
          <option value="blackjack">블랙잭(운영자 진행)</option>
        </select><br><br>

        <div id="roulette-ui">
          <label>룰렛 선택</label>
          <select id="g-roulette-id" ${hasRoulette ? '' : 'disabled'}>
            ${State.rouletteConfigs.map(c => `<option value="${c.config_id}">${this.escape(c.config_name)}</option>`).join('')}
          </select>
          ${hasRoulette ? `<div class="hint">배율은 공백/콤마 모두 허용 (예: 0 0 1.5 2 / 0,0,1.5,2)</div>` : `<div class="hint" style="color:#e74a3b;">룰렛 프리셋이 없습니다. 아래에서 A룰렛/B룰렛 등을 먼저 추가하세요.</div>`}
        </div>

        <div id="odd-even-ui" style="display:none;">
          <label>선택</label>
          <select id="g-pick"><option value="홀">홀</option><option value="짝">짝</option></select>
          <div class="hint">결과는 서버에서 결정됩니다(치팅 방지).</div>
        </div>

        <div id="bj-ui" style="display:none;">
          <div class="hint">블랙잭은 운영자가 HIT/스탠드로 진행하고, 결과만 서버에 반영됩니다.</div>
        </div>

        <br>
        <button class="btn btn-p" style="width:100%; height:50px;" onclick="App.runGame()">게임 시작하기</button>
      </div>

      <div id="bj-board" style="display:none;"></div>

      <div class="card">
        <h3>🎯 룰렛 프리셋 관리 (A 룰렛 / B 룰렛 ...)</h3>

        <div style="display:grid; grid-template-columns: 1fr 2fr auto; gap:10px;">
          <input id="rc-name" placeholder="예: A 룰렛">
          <input id="rc-text" placeholder="배율들 (예: 0 0 1.5 2 또는 0,0,1.5,2)">
          <button class="btn btn-p" onclick="App.addRouletteConfig()">추가</button>
        </div>

        <table style="margin-top:12px;">
          <thead><tr><th>ID</th><th>이름</th><th>배율 목록</th><th>저장</th><th>삭제</th></tr></thead>
          <tbody>
            ${State.rouletteConfigs.map(c => `
              <tr>
                <td>${c.config_id}</td>
                <td><input id="rcn-${c.config_id}" value="${this.escapeAttr(c.config_name)}"></td>
                <td><input id="rct-${c.config_id}" value="${this.escapeAttr(c.multipliers_text || '')}"></td>
                <td><button class="btn btn-s" onclick="App.saveRouletteConfig(${c.config_id})">저장</button></td>
                <td><button class="btn btn-danger" onclick="App.deleteRouletteConfig(${c.config_id})">삭제</button></td>
              </tr>
            `).join('') || `<tr><td colspan="5">프리셋이 없습니다.</td></tr>`}
          </tbody>
        </table>
      </div>

      <div class="card">
        <h3>📊 룰렛 프리셋별 사용 횟수</h3>
        <table>
          <thead><tr><th>ID</th><th>이름</th><th>사용 횟수</th><th>총 배팅</th><th>총 지급</th><th>마지막 사용</th></tr></thead>
          <tbody>
            ${State.rouletteStats.map(s => `
              <tr>
                <td>${s.config_id}</td>
                <td>${this.escape(s.config_name)}</td>
                <td>${Number(s.play_count || 0).toLocaleString()}</td>
                <td>${Number(s.total_bet || 0).toLocaleString()} P</td>
                <td>${Number(s.total_payout || 0).toLocaleString()} P</td>
                <td>${this.escape(s.last_play_time || '-')}</td>
              </tr>
            `).join('') || `<tr><td colspan="6">통계가 없습니다.</td></tr>`}
          </tbody>
        </table>
        <div class="hint">사용 횟수는 룰렛 실행 시 자동 누적됩니다.</div>
      </div>
    `;
  },

  renderTransfer(view) {
    view.innerHTML = `
      <div class="card">
        <h3>💸 M:N 통합 양도 시스템</h3>
        <button class="btn btn-s" onclick="App.addTrRow()">+ 양도 건 추가</button>
        <div id="tr-list" style="margin-top:15px;"></div>
        <button class="btn btn-p" style="width:100%; margin-top:20px; height:50px;" onclick="App.execMN()">양도 일괄 실행</button>
        <div class="hint">포인트/아이템 모두 가능. 값이 0 이하면 무시됩니다.</div>
      </div>`;
    this.addTrRow();
  },

  async renderLogs(view) {
    const res = await this.api({ action: 'get_logs', filter:'all' });

    view.innerHTML = `
      <div class="card">
        <h3>📜 시스템 로그 (최근 500건)</h3>

        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
          <select id="log-filter">
            <option value="all">전체</option>
            <option value="point">포인트</option>
            <option value="item">아이템</option>
            <option value="game">게임</option>
            <option value="system">시스템</option>
          </select>
          <input id="log-q" placeholder="사유/이름/아이템 검색">
          <input id="log-from" type="date">
          <input id="log-to" type="date">
        </div>

        <button class="btn btn-s" onclick="App.loadLog()">필터 적용</button>

        <div id="log-area" style="margin-top:12px;">
          ${this.renderLogTable(res.logs)}
        </div>
      </div>
    `;
  },

  renderLogTable(logs){
    return `
      <table>
        <thead><tr><th>시간</th><th>캐릭터</th><th>변동/대상</th><th>잔액</th><th>사유</th></tr></thead>
        <tbody>
          ${(logs||[]).map(l => `
            <tr>
              <td>${this.escape(l.log_time)}</td>
              <td>${this.escape(l.member_name)}</td>
              <td>${this.escape(l.change_val || l.target_name || '')}</td>
              <td>${(l.point_after===undefined||l.point_after===null)?'':this.escape(String(l.point_after))}</td>
              <td>${this.escape(l.reason || '')}</td>
            </tr>
          `).join('') || '<tr><td colspan="5">로그가 없습니다.</td></tr>'}
        </tbody>
      </table>
    `;
  },

  renderSettings(view) {
    view.innerHTML = `
      <div class="card">
        <h3>⚙️ 시스템 관리</h3>
        <p>주의: 아래 버튼은 데이터를 영구적으로 변경합니다.</p>
        <button class="btn btn-danger" onclick="App.resetSeason()">시즌 데이터 초기화 (멤버/로그/인벤/상태부여)</button>
        <br><br>
        <button class="btn btn-p" onclick="location.href='index.php?logout=1'">로그아웃</button>
      </div>
    `;
  },

  /* ---------------- Business Logic ---------------- */
  async addMember() {
    const name = document.getElementById('new-name').value.trim();
    if(!name) return;
    await this.api({ action: 'add_member', name });
    location.reload();
  },

  async saveMember(id) {
    await this.api({
      action: 'update_member_detail',
      member_id: id,
      name: document.getElementById('edit-name').value,
      points: document.getElementById('edit-points').value
    });
    alert("수정되었습니다.");
    location.reload();
  },

  async grantItem(mid) {
    const iid = parseInt(document.getElementById('grant-item-id').value);
    const qty = parseInt(document.getElementById('grant-qty').value);
    const deduct = document.getElementById('grant-deduct').checked;
    if (!Number.isFinite(qty) || qty <= 0) return alert("수량은 1 이상이어야 합니다.");

    const res = await this.api({ action: 'manage_inventory', member_id: mid, item_id: iid, quantity: qty, deduct_point: deduct });
    alert(`처리 완료\n현재 포인트: ${Number(res.current_points).toLocaleString()} P`);
    this.router();
  },

  async consumeItem(mid, iid, name) {
    if(!confirm(`[${name}] 1개를 사용하시겠습니까?`)) return;
    await this.api({ action: 'consume_item', member_id: mid, item_id: iid });
    this.router();
  },

  async deleteItem(mid, iid) {
    if(!confirm("정말 삭제하시겠습니까?")) return;
    await this.api({ action: 'manage_inventory', member_id: mid, item_id: iid, quantity: 0 });
    this.router();
  },

  async addItem() {
    const name = document.getElementById('item-name').value;
    const price = document.getElementById('item-price').value;
    const stock = document.getElementById('item-stock').value;
    const status = document.getElementById('item-status').value;
    const desc = document.getElementById('item-desc').value;

    await this.api({ action: 'add_item', name, price, stock, status, desc });
    location.reload();
  },

  openItemEdit(itemId){
    const it = State.items.find(x => Number(x.item_id) === Number(itemId));
    if (!it) return;

    const body = document.getElementById('modal-body');
    document.getElementById('modal').style.display = 'block';
    body.innerHTML = `
      <h3>🛠️ 아이템 수정 (ID: ${it.item_id})</h3>
      <label>이름</label><input id="e-in" value="${this.escapeAttr(it.item_name)}"><br><br>
      <label>가격</label><input id="e-ip" type="number" value="${it.price}"><br><br>
      <label>재고(-1 무한)</label><input id="e-is" type="number" value="${it.stock}"><br><br>
      <label>판매 상태</label>
      <select id="e-ist">
        <option value="selling" ${it.status==='selling'?'selected':''}>selling</option>
        <option value="hidden" ${it.status==='hidden'?'selected':''}>hidden</option>
        <option value="soldout" ${it.status==='soldout'?'selected':''}>soldout</option>
      </select><br><br>
      <label>설명</label><textarea id="e-id">${this.escape(it.item_description || '')}</textarea><br><br>
      <button class="btn btn-p" style="width:100%" onclick="App.saveItem(${it.item_id})">저장</button>
      <div class="hint">판매 상태/재고는 “구매 처리”시에만 강제 적용됩니다.</div>
    `;
  },

  async saveItem(itemId){
    await this.api({
      action:'update_item',
      item_id: itemId,
      name: document.getElementById('e-in').value,
      price: document.getElementById('e-ip').value,
      stock: document.getElementById('e-is').value,
      status: document.getElementById('e-ist').value,
      desc: document.getElementById('e-id').value
    });
    alert('저장 완료');
    location.reload();
  },

  /* ---- PATCH: status actions ---- */
  async grantStatus(memberId){
    const typeId = parseInt(document.getElementById('st-grant-type').value);
    if (!Number.isFinite(typeId) || typeId <= 0) return alert('타입을 선택하세요.');
    await this.api({ action:'grant_status', member_id: memberId, type_id: typeId });
    alert('상태이상 부여/갱신 완료');
    this.router();
  },

  async removeStatus(memberId, activeId, name){
    if(!confirm(`[${name}] 상태이상을 해제할까요?`)) return;
    await this.api({ action:'remove_status', member_id: memberId, active_id: activeId });
    alert('해제 완료');
    this.router();
  },

  async setStatusStage(memberId, activeId){
    const v = parseInt(document.getElementById(`st-stage-${activeId}`).value);
    if (!Number.isFinite(v) || v <= 0) return alert('단계는 1 이상');
    await this.api({ action:'set_status_stage', member_id: memberId, active_id: activeId, stage: v });
    alert('단계 저장 완료');
    this.router();
  },

  addTrRow() {
    const div = document.createElement('div');
    div.className = 'tr-row';
    div.innerHTML = `
      <select class="f">${State.members.map(m => `<option value="${m.member_id}">${this.escape(m.member_name)}</option>`).join('')}</select>
      <select class="t">${State.members.map(m => `<option value="${m.member_id}">${this.escape(m.member_name)}</option>`).join('')}</select>
      <select class="ty" onchange="this.parentElement.querySelector('.it').style.display=(this.value==='item'?'block':'none')">
        <option value="point">포인트</option><option value="item">아이템</option>
      </select>
      <select class="it" style="display:none;">
        ${State.items.map(i => `<option value="${i.item_id}">${this.escape(i.item_name)}</option>`).join('')}
      </select>
      <input type="number" class="v" value="0">
      <button class="btn btn-danger" onclick="this.parentElement.remove()">X</button>
    `;
    document.getElementById('tr-list').appendChild(div);
  },

  async execMN() {
    const transfers = Array.from(document.querySelectorAll('.tr-row')).map(r => ({
      from_id: r.querySelector('.f').value,
      to_id: r.querySelector('.t').value,
      type: r.querySelector('.ty').value,
      item_id: r.querySelector('.it').value,
      val: r.querySelector('.v').value
    }));
    const r = await this.api({ action: 'execute_mn_transfer', transfers });
    if (r && r.failures && r.failures.length) {
      const lines = r.failures.slice(0, 20).map(f => `- ${f.type}: ${f.from} → ${f.to} (${f.val}) : ${f.reason}`);
      alert(`완료 (실패 ${r.failures.length}건 포함)\n\n` + lines.join('\n') + (r.failures.length>20?'\n...':'') );
    } else {
      alert("양도가 완료되었습니다.");
    }
    location.reload();
  },

  toggleGambleUI(type) {
    document.getElementById('roulette-ui').style.display = (type === 'roulette' ? 'block' : 'none');
    document.getElementById('odd-even-ui').style.display = (type === 'odd_even' ? 'block' : 'none');
    document.getElementById('bj-ui').style.display = (type === 'blackjack' ? 'block' : 'none');
    document.getElementById('bj-board').style.display = 'none';
  },

  async runGame() {
    const mid = document.getElementById('g-p1').value;
    const bet = parseInt(document.getElementById('g-bet').value);
    const type = document.getElementById('g-type').value;
    if (!Number.isFinite(bet) || bet <= 0) return alert("배팅금액은 1 이상이어야 합니다.");

    if (type === 'blackjack') {
      this.bjStart();
      return;
    }

    if (type === 'roulette') {
      const cfgId = document.getElementById('g-roulette-id').value;
      if (!cfgId) return alert("룰렛 프리셋을 선택하세요.");
      const res = await this.api({ action:'run_game', member_id: mid, bet, type:'roulette', config_id: cfgId });
      const mult = (res.win_amt / bet).toFixed(2);
      alert(`🎮 룰렛 결과\n----------------\n${res.msg}\n배율: ${mult}배\n현재 포인트: ${Number(res.points).toLocaleString()} P`);
      location.reload();
      return;
    }

    if (type === 'odd_even') {
      const pick = document.getElementById('g-pick').value;
      const res = await this.api({ action:'run_game', member_id: mid, bet, type:'odd_even', pick });
      const mult = (res.win_amt / bet).toFixed(2);
      alert(`🎮 홀짝 결과\n----------------\n${res.msg}\n배율: ${mult}배\n현재 포인트: ${Number(res.points).toLocaleString()} P`);
      location.reload();
      return;
    }
  },

  /* --------- Blackjack (client-run) --------- */
  bjStart() {
    const p1Id = document.getElementById('g-p1').value;
    const bet = parseInt(document.getElementById('g-bet').value);
    if (!Number.isFinite(bet) || bet <= 0) return alert("배팅금액은 1 이상이어야 합니다.");

    const p1 = State.members.find(m => m.member_id == p1Id);
    if (!p1) return alert("플레이어를 찾을 수 없습니다.");

    State.blackjack = {
      p1: { id: p1Id, name: p1.member_name, hand: [], score: 0 },
      p2: { id: 0, name: "운영/딜러", hand: [], score: 0 },
      bet, solo: true, status: 'playing', turn: 'p1', deck: [], revealDealer: false
    };

    const s = ['♠', '♥', '♣', '♦'], v = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    for (let x of s) for (let y of v) State.blackjack.deck.push({x, y});
    State.blackjack.deck.sort(() => Math.random() - 0.5);

    State.blackjack.p1.hand.push(State.blackjack.deck.pop(), State.blackjack.deck.pop());
    State.blackjack.p2.hand.push(State.blackjack.deck.pop(), State.blackjack.deck.pop());

    document.getElementById('bj-board').style.display = 'block';
    this.bjUI();
  },

  bjCalc(hand) {
    let score = 0, aces = 0;
    hand.forEach(c => {
      if (c.y === 'A') { score += 11; aces++; }
      else if (['J', 'Q', 'K'].includes(c.y)) score += 10;
      else score += parseInt(c.y);
    });
    while (score > 21 && aces > 0) { score -= 10; aces--; }
    return score;
  },

  bjUI() {
    const bj = State.blackjack; // ✅ FIX: bj 미정의 때문에 카드가 안 뜨던 문제 해결
    if (!bj || !bj.p1 || !bj.p2) return;

    bj.p1.score = this.bjCalc(bj.p1.hand);
    bj.p2.score = this.bjCalc(bj.p2.hand);

    const dealerCardsHtml = bj.p2.hand.map((c, idx) => {
      if (!bj.revealDealer && idx === 1) return `<div class="card-obj">🂠</div>`;
      return `<div class="card-obj ${['♥','♦'].includes(c.x)?'red':''}">${c.x}${c.y}</div>`;
    }).join('');

    document.getElementById('bj-board').innerHTML = `
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <div>
          <strong>${this.escape(bj.p1.name)}</strong> (${bj.p1.score})
          <div class="cards-area">${bj.p1.hand.map(c => `<div class="card-obj ${['♥','♦'].includes(c.x)?'red':''}">${c.x}${c.y}</div>`).join('')}</div>
        </div>
        <div>
          <button class="btn" onclick="State.blackjack.revealDealer = !State.blackjack.revealDealer; App.bjUI();">
            딜러 공개: ${bj.revealDealer ? 'ON' : 'OFF'}
          </button>
          <div style="margin-top:10px;"><strong>${this.escape(bj.p2.name)}</strong> (${bj.p2.score})</div>
          <div class="cards-area">${dealerCardsHtml}</div>
        </div>
      </div>

      <div style="margin-top:20px;">
        ${bj.status === 'playing' ? `
          <p>운영 진행: 필요한 만큼 HIT/스탠드로 진행 후 결과 처리</p>
          <button class="btn btn-s" onclick="App.bjHit()">HIT</button>
          <button class="btn btn-p" onclick="App.bjStand()">STAND</button>
        ` : `<button class="btn btn-p" onclick="location.reload()">종료</button>`}
      </div>
    `;

    if (bj.p1.score > 21) this.bjEnd('p1_bust');
    else if (bj.p2.score > 21) this.bjEnd('p2_bust');
  },

  bjHit() {
    const bj = State.blackjack;
    const target = (bj.turn === 'p1') ? bj.p1 : bj.p2;
    target.hand.push(bj.deck.pop());
    this.bjUI();
  },

  bjStand() {
    const bj = State.blackjack;
    if (bj.turn === 'p1') bj.turn = 'p2';
    else this.bjProcessResult();
    this.bjUI();
  },

  bjProcessResult() {
    const bj = State.blackjack;
    const s1 = this.bjCalc(bj.p1.hand);
    const s2 = this.bjCalc(bj.p2.hand);
    if (s1 > 21) this.bjEnd('p1_bust');
    else if (s2 > 21) this.bjEnd('p2_bust');
    else if (s1 > s2) this.bjEnd('p1_win');
    else if (s1 < s2) this.bjEnd('p2_win');
    else this.bjEnd('draw');
  },

  async bjEnd(res) {
    const bj = State.blackjack;
    if (bj.status === 'ended') return;
    bj.status = 'ended';
    bj.revealDealer = true; // 끝나면 공개
    this.bjUI();

    let multiplier = 0;
    let msg = "";

    if (res === 'p1_win' || res === 'p2_bust') { multiplier = 2; msg = "블랙잭 승리"; }
    else if (res === 'draw') { multiplier = 1; msg = "블랙잭 무승부"; }
    else { multiplier = 0; msg = "블랙잭 패배"; }

    const winAmt = Math.floor(bj.bet * multiplier);

    const apiRes = await this.api({
      action: 'run_game',
      member_id: bj.p1.id,
      bet: bj.bet,
      type: 'blackjack',
      win_amt: winAmt,
      msg
    });

    alert(`🃏 블랙잭 결과: ${msg}\n배율: ${multiplier}배\n현재 포인트: ${Number(apiRes.points).toLocaleString()} P`);
    location.reload();
  },

  /* -------- Roulette presets CRUD -------- */
  async addRouletteConfig(){
    const name = document.getElementById('rc-name').value.trim();
    const multipliers_text = document.getElementById('rc-text').value.trim();
    if (!name) return alert('이름을 입력하세요.');
    if (!multipliers_text) return alert('배율 목록을 입력하세요.');
    await this.api({ action:'add_roulette_config', name, multipliers_text });
    alert('추가 완료');
    location.reload();
  },
  async saveRouletteConfig(id){
    const name = document.getElementById(`rcn-${id}`).value.trim();
    const multipliers_text = document.getElementById(`rct-${id}`).value.trim();
    if (!name) return alert('이름을 입력하세요.');
    if (!multipliers_text) return alert('배율 목록을 입력하세요.');
    await this.api({ action:'update_roulette_config', config_id:id, name, multipliers_text });
    alert('저장 완료');
    location.reload();
  },
  async deleteRouletteConfig(id){
    if(!confirm('삭제할까요?')) return;
    await this.api({ action:'delete_roulette_config', config_id:id });
    alert('삭제 완료');
    location.reload();
  },

  /* -------- Logs filter -------- */
  async loadLog(){
    const filter = document.getElementById('log-filter').value;
    const q = document.getElementById('log-q').value;
    const date_from = document.getElementById('log-from').value;
    const date_to = document.getElementById('log-to').value;
    const res = await this.api({ action:'get_logs', filter, q, date_from, date_to });
    document.getElementById('log-area').innerHTML = this.renderLogTable(res.logs);
  },

  /* -------- Settings -------- */
  async resetSeason() {
    if (!confirm("정말 시즌 데이터를 초기화할까요?")) return;
    await this.api({ action: 'reset_season' });
    alert("초기화 완료");
    location.reload();
  },

  /* ---------------- Utilities ---------------- */
  toggleAll(m) { document.querySelectorAll('.mem-cb').forEach(c => c.checked = m.checked); },

  openBulk(mode) {
    const targets = Array.from(document.querySelectorAll('.mem-cb:checked')).map(c => c.value);
    if (!targets.length) return alert("캐릭터를 선택하세요.");
    State.bulkTargets = targets;
    const body = document.getElementById('modal-body');
    document.getElementById('modal').style.display = 'block';

    if (mode === 'point') {
      body.innerHTML = `
        <h3>💰 포인트 일괄 지급/회수</h3>
        <input type="number" id="b-v" placeholder="예: 100 / -100"><br><br>
        <button class="btn btn-p" style="width:100%" onclick="App.execBulk('point')">적용</button>
        <div class="hint">음수면 회수(필요하면 0 아래로도 내려갈 수 있음).</div>
      `;
    } 
    else if (mode === 'status') {
      body.innerHTML = `
        <h3>🧪 상태이상 일괄 부여/해제</h3>
        <label>타입 선택</label>
        <select id="b-st-type">
          ${State.statusTypes.map(t => `<option value="${t.type_id}">${this.escape(t.status_name)} (max:${t.max_stage})</option>`).join('')}
        </select>
        <br><br>
        <label>작업</label>
        <select id="b-st-op">
          <option value="add">부여/갱신(리셋)</option>
          <option value="remove">해제</option>
        </select>
        <br><br>
        <button class="btn btn-p" style="width:100%" onclick="App.execBulk('status')">적용</button>
        <div class="hint">부여는 이미 있으면 stage=1 + applied_at 갱신됩니다.</div>
      `;
    }
    else {
      body.innerHTML = `
        <h3>🎁 아이템 일괄 지급</h3>
        <div id="b-l">
          <div class="tr-row" style="grid-template-columns:1fr 1fr auto;">
            <select class="it">${State.items.map(i => `<option value="${i.item_id}">${this.escape(i.item_name)}</option>`).join('')}</select>
            <input type="number" class="v" value="1">
            <button class="btn btn-danger" onclick="this.closest('.tr-row').remove()">X</button>
          </div>
        </div>
        <button class="btn btn-s" style="margin-top:10px;" onclick="App.addBulkItemRow()">+ 아이템 추가</button>
        <br><br>
        <label><input type="checkbox" id="b-deduct"> 포인트 차감(구매 처리)</label><br><br>
        <button class="btn btn-p" style="width:100%" onclick="App.execBulk('item')">지급</button>
      `;
    }
  },

  addBulkItemRow(){
    const wrap = document.getElementById('b-l');
    const row = document.createElement('div');
    row.className = 'tr-row';
    row.style.gridTemplateColumns = '1fr 1fr auto';
    row.innerHTML = `
      <select class="it">${State.items.map(i => `<option value="${i.item_id}">${this.escape(i.item_name)}</option>`).join('')}</select>
      <input type="number" class="v" value="1">
      <button class="btn btn-danger" onclick="this.closest('.tr-row').remove()">X</button>
    `;
    wrap.appendChild(row);
  },

  async execBulk(mode) {
    const p = { action: 'bulk_action', mode, targets: State.bulkTargets };

    if (mode === 'point') {
      const amt = parseInt(document.getElementById('b-v').value);
      if (!Number.isFinite(amt) || amt === 0) return alert('0은 불가');
      p.amount = amt;
    } 
    else if (mode === 'status') {
      const typeId = parseInt(document.getElementById('b-st-type').value);
      const op = document.getElementById('b-st-op').value;
      if (!Number.isFinite(typeId) || typeId <= 0) return alert('타입 선택');
      p.type_id = typeId;
      p.op = op;
    }
    else {
      p.deduct_point = document.getElementById('b-deduct').checked;
      p.items = Array.from(document.querySelectorAll('#b-l .tr-row')).map(r => ({
        id: r.querySelector('.it').value,
        qty: r.querySelector('.v').value
      }));
    }

    await this.api(p);
    location.reload();
  },

  closeModal(){ document.getElementById('modal').style.display = 'none'; },

  search(){
    const q = (document.getElementById('global-search').value || '').trim().toLowerCase();
    const root = document.getElementById('main-view');
    const rows = root.querySelectorAll('tbody tr');
    rows.forEach(tr=>{
      const text = tr.innerText.toLowerCase();
      tr.style.display = (q === '' || text.includes(q)) ? '' : 'none';
    });
  },

  sortMembers(field){ Sort.toggle('sort_members', field); this.renderMembers(document.getElementById('main-view')); },
  sortItems(field){ Sort.toggle('sort_items', field); this.renderShop(document.getElementById('main-view')); },

  /* --------- Escapes --------- */
  escape(s){ return String(s ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'); },
  escapeAttr(s){ return this.escape(s).replaceAll('"','&quot;'); },
  escapeJS(s){ return String(s ?? '').replaceAll('\\','\\\\').replaceAll("'","\\'").replaceAll('\n','\\n'); }
};

window.onload = () => App.init();
</script>
</body>
</html>
