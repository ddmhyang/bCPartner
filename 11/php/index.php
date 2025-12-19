<?php
/**
 * [RPG/커뮤니티 통합 관리 시스템]
 * 기술 스택: PHP, SQLite3, Vanilla JS, CSS3
 * 특징: 단일 파일 구성, 모바일 반응형, 데이터베이스 자동 생성
 */

// --- [1. 데이터베이스 설정 및 초기화] ---
$dbFile = 'database.db';
$db = new SQLite3($dbFile);

// 테이블 생성 (초기 1회)
$db->exec("CREATE TABLE IF NOT EXISTS characters (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, points INTEGER DEFAULT 0, status_json TEXT DEFAULT '[]', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT, price INTEGER, stock INTEGER, is_selling INTEGER DEFAULT 1)");
$db->exec("CREATE TABLE IF NOT EXISTS inventory (id INTEGER PRIMARY KEY AUTOINCREMENT, char_id INTEGER, item_name TEXT, quantity INTEGER)");
$db->exec("CREATE TABLE IF NOT EXISTS status_configs (id INTEGER PRIMARY KEY AUTOINCREMENT, step INTEGER, next_step_time TEXT)"); // h:m 형식
$db->exec("CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT, char_id INTEGER, char_name TEXT, change_detail TEXT, reason TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

// --- [2. 백엔드 로직 처리 (API)] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'add_character') {
        $stmt = $db->prepare("INSERT INTO characters (name) VALUES (:name)");
        $stmt->bindValue(':name', $data['name'], SQLITE3_TEXT);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    }
    
    if ($action === 'bulk_action') {
        $ids = $data['ids'];
        $type = $data['type']; // point, item, status
        
        foreach ($ids as $id) {
            // 캐릭터 이름 가져오기 (로그용)
            $char = $db->querySingle("SELECT name FROM characters WHERE id = $id", true);
            
            if ($type === 'point') {
                $amount = intval($data['amount']);
                $db->exec("UPDATE characters SET points = points + ($amount) WHERE id = $id");
                $logReason = ($amount >= 0 ? "포인트 지급" : "포인트 회수") . ": $amount";
                $db->exec("INSERT INTO logs (char_id, char_name, change_detail, reason) VALUES ($id, '{$char['name']}', '$logReason', '관리자 일괄 작업')");
            }
            
            if ($type === 'item') {
                foreach ($data['items'] as $item) {
                    $stmt = $db->prepare("INSERT INTO inventory (char_id, item_name, quantity) VALUES (:cid, :iname, :qty)");
                    $stmt->bindValue(':cid', $id, SQLITE3_INTEGER);
                    $stmt->bindValue(':iname', $item['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':qty', $item['qty'], SQLITE3_INTEGER);
                    $stmt->execute();
                    $db->exec("INSERT INTO logs (char_id, char_name, change_detail, reason) VALUES ($id, '{$char['name']}', '아이템 지급: {$item['name']}({$item['qty']})', '관리자 일괄 작업')");
                }
            }
        }
        echo json_encode(['status' => 'success']);
    }

    if ($action === 'add_item') {
        $stmt = $db->prepare("INSERT INTO items (name, description, price, stock, is_selling) VALUES (:n, :d, :p, :s, :v)");
        $stmt->bindValue(':n', $data['name'], SQLITE3_TEXT);
        $stmt->bindValue(':d', $data['desc'], SQLITE3_TEXT);
        $stmt->bindValue(':p', $data['price'], SQLITE3_INTEGER);
        $stmt->bindValue(':s', $data['stock'], SQLITE3_INTEGER);
        $stmt->bindValue(':v', $data['is_selling'], SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    }

    if ($action === 'reset_season') {
        $db->exec("DELETE FROM characters");
        $db->exec("DELETE FROM inventory");
        $db->exec("DELETE FROM logs");
        echo json_encode(['status' => 'success']);
    }

    if ($action === 'full_reset') {
        $db->close();
        unlink($dbFile);
        echo json_encode(['status' => 'success']);
    }
    exit;
}

// 초기 데이터 로드
$chars = [];
$res = $db->query("SELECT * FROM characters");
while($row = $res->fetchArray(SQLITE3_ASSOC)) $chars[] = $row;

$items = [];
$res = $db->query("SELECT * FROM items");
while($row = $res->fetchArray(SQLITE3_ASSOC)) $items[] = $row;

$logs = [];
$res = $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 100");
while($row = $res->fetchArray(SQLITE3_ASSOC)) $logs[] = $row;

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG 통합 관리 시스템</title>
    <style>
        :root { --p: #5c67f2; --bg: #f8f9fa; --card: #ffffff; --border: #e9ecef; }
        body { font-family: 'Pretendard', sans-serif; background: var(--bg); margin: 0; padding: 0; color: #333; }
        header { background: var(--p); color: white; padding: 1rem; position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center; }
        nav { display: flex; overflow-x: auto; background: white; border-bottom: 1px solid var(--border); }
        nav button { border: none; background: none; padding: 1rem; cursor: pointer; white-space: nowrap; font-weight: 600; }
        nav button.active { color: var(--p); border-bottom: 3px solid var(--p); }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 1rem; }
        .section { display: none; }
        .section.active { display: block; }
        
        .card { background: var(--card); border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem; }
        th, td { padding: 12px; border-bottom: 1px solid var(--border); text-align: left; }
        th { cursor: pointer; background: #f1f3f5; }
        
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 600; }
        .btn-p { background: var(--p); color: white; }
        .btn-red { background: #ff5e5e; color: white; }
        
        .input-group { margin-bottom: 1rem; }
        .input-group label { display: block; margin-bottom: 0.5rem; font-size: 0.85rem; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; }
        
        /* 반응형 모바일 */
        @media (max-width: 600px) {
            th:nth-child(4), td:nth-child(4) { display: none; } /* 모바일에서 일부 열 숨김 */
        }
        
        .search-box { margin-bottom: 1rem; display: flex; gap: 10px; }
        .modal { display:none; position:fixed; z-index:200; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
        .modal-content { background:white; margin:10% auto; padding:20px; border-radius:12px; width:90%; max-width:500px; }
        
        .item-row { display: flex; gap: 5px; margin-bottom: 5px; }
    </style>
</head>
<body>

<header>
    <strong>RPG Admin</strong>
    <button class="btn" style="background:rgba(255,255,255,0.2); color:white" onclick="location.reload()">새로고침</button>
</header>

<nav id="mainNav">
    <button onclick="showSection('charSection')" class="active">캐릭터</button>
    <button onclick="showSection('shopSection')">상점</button>
    <button onclick="showSection('gambleSection')">도박</button>
    <button onclick="showSection('statusSection')">상태</button>
    <button onclick="showSection('transferSection')">양도</button>
    <button onclick="showSection('logSection')">로그</button>
    <button onclick="showSection('configSection')">설정</button>
</nav>

<div class="container">
    <!-- 1. 캐릭터 관리 -->
    <div id="charSection" class="section active">
        <div class="card">
            <h3>캐릭터 추가</h3>
            <div class="input-group" style="display:flex; gap:10px;">
                <input type="text" id="newCharName" placeholder="캐릭터 이름을 입력하세요">
                <button class="btn btn-p" onclick="addCharacter()">추가</button>
            </div>
        </div>

        <div class="card">
            <h3>캐릭터 목록</h3>
            <div class="search-box">
                <input type="text" id="charSearch" placeholder="이름 검색..." onkeyup="filterTable('charTable', 2)">
            </div>
            <div style="margin-bottom:10px; display:flex; gap:5px;">
                <button class="btn btn-p" onclick="openBulkModal('point')">포인트 지급/회수</button>
                <button class="btn btn-p" onclick="openBulkModal('item')">아이템/상태 부여</button>
            </div>
            <div style="overflow-x: auto;">
                <table id="charTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="toggleAll(this)"></th>
                            <th onclick="sortTable('charTable', 1)">번호 ↕</th>
                            <th onclick="sortTable('charTable', 2)">이름 ↕</th>
                            <th onclick="sortTable('charTable', 3)">포인트 ↕</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody id="charList">
                        <?php foreach($chars as $c): ?>
                        <tr>
                            <td><input type="checkbox" class="char-check" value="<?= $c['id'] ?>"></td>
                            <td><?= $c['id'] ?></td>
                            <td><?= $c['name'] ?></td>
                            <td><?= number_format($c['points'] ?? 0) ?></td>
                            <td><button class="btn" onclick="viewDetails(<?= $c['id'] ?>)">수정</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 2. 상점 관리 -->
    <div id="shopSection" class="section">
        <div class="card">
            <h3>아이템 추가</h3>
            <div class="input-group"><input type="text" id="itName" placeholder="아이템 이름"></div>
            <div class="input-group"><input type="number" id="itPrice" placeholder="가격"></div>
            <div class="input-group"><input type="number" id="itStock" placeholder="재고"></div>
            <button class="btn btn-p" onclick="addItem()">아이템 등록</button>
        </div>
        <div class="card">
            <h3>아이템 목록</h3>
            <table>
                <thead>
                    <tr>
                        <th onclick="sortTable('itemTable', 0)">번호</th>
                        <th onclick="sortTable('itemTable', 1)">이름</th>
                        <th onclick="sortTable('itemTable', 2)">가격</th>
                        <th>재고</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $i): ?>
                    <tr>
                        <td><?= $i['id'] ?></td>
                        <td><?= $i['name'] ?></td>
                        <td><?= $i['price'] ?></td>
                        <td><?= $i['stock'] ?></td>
                        <td><button class="btn">수정</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. 도박 관리 -->
    <div id="gambleSection" class="section">
        <div class="card">
            <h3>룰렛 설정</h3>
            <p>배율 목록을 띄어쓰기나 콤마로 구분하여 입력하세요.</p>
            <input type="text" id="rouletteRates" placeholder="ex) -5, 0, 2, 5">
            <button class="btn btn-p" onclick="saveGamble('roulette')">저장</button>
        </div>
        <div class="card">
            <h3>블랙잭/홀짝 활성화</h3>
            <label><input type="checkbox" checked> 홀짝 게임 활성화</label><br><br>
            <label><input type="checkbox" checked> 블랙잭 활성화</label>
        </div>
    </div>

    <!-- 8. 로그 -->
    <div id="logSection" class="section">
        <div class="card">
            <h3>활동 로그</h3>
            <div class="search-box">
                <input type="text" id="logSearch" placeholder="이름 또는 사유 검색..." onkeyup="filterTable('logTable', -1)">
                <input type="date" id="logDate" onchange="filterLogDate()">
            </div>
            <table id="logTable">
                <thead>
                    <tr>
                        <th>시간</th>
                        <th>캐릭터</th>
                        <th>변동 내용</th>
                        <th>사유</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $l): ?>
                    <tr data-date="<?= substr($l['created_at'], 0, 10) ?>">
                        <td><?= $l['created_at'] ?></td>
                        <td><?= $l['char_name'] ?> (<?= $l['char_id'] ?>)</td>
                        <td><?= $l['change_detail'] ?></td>
                        <td><?= $l['reason'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 9. 설정 -->
    <div id="configSection" class="section">
        <div class="card">
            <h3>데이터 관리</h3>
            <button class="btn btn-red" onclick="confirmAction('reset_season', '시즌을 초기화할까요? 캐릭터와 로그가 삭제됩니다.')">시즌 초기화</button>
            <button class="btn btn-red" style="background:#000" onclick="confirmAction('full_reset', '모든 DB 파일을 삭제하시겠습니까? 복구할 수 없습니다.')">전체 초기화 (DB 삭제)</button>
        </div>
    </div>
</div>

<!-- 일괄 작업 모달 -->
<div id="bulkModal" class="modal">
    <div class="modal-content">
        <h3 id="bulkTitle">일괄 작업</h3>
        <div id="bulkPointUI" style="display:none">
            <input type="number" id="bulkAmount" placeholder="지급할 포인트 (차감은 마이너스)">
        </div>
        <div id="bulkItemUI" style="display:none">
            <p>아이템 선택 (M:N)</p>
            <div id="bulkItemRows">
                <div class="item-row">
                    <select class="sel-item">
                        <?php foreach($items as $i): echo "<option>{$i['name']}</option>"; endforeach; ?>
                    </select>
                    <input type="number" class="sel-qty" value="1" style="width:60px">
                </div>
            </div>
            <button class="btn" onclick="addItemRow()">+ 아이템 추가</button>
        </div>
        <br>
        <button class="btn btn-p" onclick="submitBulk()">실행</button>
        <button class="btn" onclick="closeModal()">취소</button>
    </div>
</div>

<script>
    // --- [섹션 전환] ---
    function showSection(id) {
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('#mainNav button').forEach(b => b.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        event.target.classList.add('active');
        localStorage.setItem('lastSection', id);
    }

    // --- [데이터 처리] ---
    async function api(data) {
        const res = await fetch('index.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        return res.json();
    }

    function addCharacter() {
        const name = document.getElementById('newCharName').value;
        if(!name) return;
        api({action: 'add_character', name}).then(() => location.reload());
    }

    function addItem() {
        const name = document.getElementById('itName').value;
        const price = document.getElementById('itPrice').value;
        const stock = document.getElementById('itStock').value;
        api({action: 'add_item', name, price, stock, desc: '', is_selling: 1}).then(() => location.reload());
    }

    // --- [일괄 작업 모달 제어] ---
    let currentBulkType = '';
    function openBulkModal(type) {
        const checks = document.querySelectorAll('.char-check:checked');
        if(checks.length === 0) return alert('캐릭터를 선택해주세요.');
        
        currentBulkType = type;
        document.getElementById('bulkModal').style.display = 'block';
        document.getElementById('bulkPointUI').style.display = type === 'point' ? 'block' : 'none';
        document.getElementById('bulkItemUI').style.display = type === 'item' ? 'block' : 'none';
        document.getElementById('bulkTitle').innerText = type === 'point' ? '포인트 일괄 지급/회수' : '아이템/상태 일괄 부여';
    }

    function addItemRow() {
        const container = document.getElementById('bulkItemRows');
        const firstRow = container.querySelector('.item-row').cloneNode(true);
        container.appendChild(firstRow);
    }

    function submitBulk() {
        const ids = Array.from(document.querySelectorAll('.char-check:checked')).map(c => c.value);
        let payload = { action: 'bulk_action', ids, type: currentBulkType };

        if(currentBulkType === 'point') {
            payload.amount = document.getElementById('bulkAmount').value;
        } else {
            payload.items = Array.from(document.querySelectorAll('#bulkItemRows .item-row')).map(row => ({
                name: row.querySelector('.sel-item').value,
                qty: row.querySelector('.sel-qty').value
            }));
        }

        api(payload).then(() => {
            alert('작업이 완료되었습니다.');
            location.reload();
        });
    }

    // --- [유틸리티: 정렬, 검색, 초기화] ---
    function sortTable(tableId, n) {
        const table = document.getElementById(tableId);
        let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
        switching = true;
        dir = "asc";
        while (switching) {
            switching = false;
            rows = table.rows;
            for (i = 1; i < (rows.length - 1); i++) {
                shouldSwitch = false;
                x = rows[i].getElementsByTagName("TD")[n];
                y = rows[i + 1].getElementsByTagName("TD")[n];
                let xVal = isNaN(x.innerText.replace(/,/g,'')) ? x.innerText.toLowerCase() : parseFloat(x.innerText.replace(/,/g,''));
                let yVal = isNaN(y.innerText.replace(/,/g,'')) ? y.innerText.toLowerCase() : parseFloat(y.innerText.replace(/,/g,''));
                if (dir == "asc") {
                    if (xVal > yVal) { shouldSwitch = true; break; }
                } else if (dir == "desc") {
                    if (xVal < yVal) { shouldSwitch = true; break; }
                }
            }
            if (shouldSwitch) {
                rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                switching = true;
                switchcount ++;
            } else {
                if (switchcount == 0 && dir == "asc") { dir = "desc"; switching = true; }
            }
        }
        localStorage.setItem('sort_'+tableId, JSON.stringify({col: n, dir: dir}));
    }

    function filterTable(tableId, col) {
        const input = event.target;
        const filter = input.value.toUpperCase();
        const rows = document.getElementById(tableId).querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = col === -1 ? row.innerText : row.cells[col].innerText;
            row.style.display = text.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        });
    }

    function filterLogDate() {
        const date = document.getElementById('logDate').value;
        const rows = document.querySelectorAll('#logTable tbody tr');
        rows.forEach(row => {
            row.style.display = (!date || row.dataset.date === date) ? "" : "none";
        });
    }

    function toggleAll(el) {
        document.querySelectorAll('.char-check').forEach(c => c.checked = el.checked);
    }

    function confirmAction(action, msg) {
        if(confirm(msg)) api({action}).then(() => location.reload());
    }

    function closeModal() { document.getElementById('bulkModal').style.display = 'none'; }

    // 페이지 로드 시 마지막 섹션 및 정렬 복구
    window.onload = () => {
        const lastSection = localStorage.getItem('lastSection');
        if(lastSection) {
            const btn = Array.from(document.querySelectorAll('#mainNav button')).find(b => b.onclick.toString().includes(lastSection));
            if(btn) btn.click();
        }
    };
</script>

</body>
</html>