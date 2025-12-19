const API_BASE_URL = '.'; 

const contentElement = document.getElementById('app-content');
// 체크박스로 선택된 회원들을 기억하기 위한 변수
let selectedMembers = new Set();

document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.querySelectorAll('#main-nav a[data-page]');
    
    navLinks.forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault(); 
            const pageName = event.target.dataset.page;
            navLinks.forEach(nav => nav.classList.remove('active'));
            event.target.classList.add('active');
            navigateTo(pageName);
        });
    });

    navigateTo('members');
    document.querySelector('#main-nav a[data-page="members"]').classList.add('active');
});

function navigateTo(page) {
    console.log('페이지 로드:', page);
    switch (page) {
        case 'members':
            loadMembersPage();
            break;
        case 'items':
            loadItemsPage();
            break;
        case 'games':
            loadGamesPage();
            break;
        case 'inventory':
            loadInventoryPage();
            break;
        case 'status':
            loadStatusPage();
            break;
        case 'transfer_point':
            loadTransferPointPage();
            break;
        case 'transfer_item':
            loadTransferItemPage();
            break;
        case 'logs':
            loadLogsPage();
            break;
        case 'item_logs':
            loadItemLogsPage();
            break;
        case 'status_logs':
            loadStatusLogsPage();
            break;
        case 'settings':
            loadSettingsPage();
            break;
        default:
            contentElement.innerHTML = '<h2>페이지를 찾을 수 없습니다.</h2>';
    }
}

function populateSelect(selectElement, data, valueField, textField, optionalField = null) {
    if (!data || data.length === 0) {
        selectElement.innerHTML = '<option value="">-- 데이터 없음 --</option>';
        selectElement.disabled = true;
        return;
    }
    
    const optionsHtml = data.map(item => {
        let text = item[textField];
        if (optionalField && item[optionalField]) {
            text += ` (보유: ${item[optionalField]})`;
        }
        return `<option value="${item[valueField]}" data-quantity="${item[optionalField] || 0}">${text}</option>`;
    });
    
    selectElement.innerHTML = `<option value="">-- 선택 --</option>` + optionsHtml.join('');
    selectElement.disabled = false;
}

// ---------------------------------------------------------
// 1. 회원 관리
// ---------------------------------------------------------
async function loadMembersPage() {
    selectedMembers.clear();

    const pageHtml = `
        <h2>캐릭터 관리</h2>
        
        <form id="member-form" style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <input type="hidden" id="action_mode" value="add">
            <input type="hidden" id="edit_member_id" value="">
            
            <div style="display:flex; justify-content: space-between; align-items: center;">
                <h3 style="margin:0;">새 캐릭터 등록</h3>
                <button type="button" id="form-cancel-button" style="display:none; padding:5px 10px; font-size:0.8em; background:#666;">취소</button>
            </div>
            
            <div class="form-group-inline" style="margin-top:10px; display:flex; gap:10px;">
                <input type="text" id="member_name" name="member_name" placeholder="캐릭터 이름 입력" required style="flex:1; padding:10px;">
                <input type="number" id="edit_points" name="points" placeholder="포인트 (수정 시)" style="width:100px; display:none;">
                <button type="submit" id="form-submit-button" style="width:100px;">등록</button>
            </div>
            <p id="form-message"></p>
        </form>

        <div class="bulk-actions" style="background:#e3f2fd; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #90caf9;">
            <strong>✨ 선택된 멤버 일괄 작업 (<span id="selected-count" style="color:blue; font-weight:bold;">0</span>명)</strong>
            <div style="margin-top:10px; display:flex; gap:5px; flex-wrap:wrap;">
                <button onclick="openBulkModal('point')" class="btn-action" style="background:#673ab7; color:white;">💰 포인트 지급/회수</button>
                <button onclick="openBulkModal('item')" class="btn-action" style="background:#ff5722; color:white;">🎁 아이템 지급</button>
                <button onclick="openBulkModal('status')" class="btn-action" style="background:#009688; color:white;">💊 상태 부여</button>
                <button onclick="selectAllMembers()" class="btn-action" style="background:#607d8b; color:white;">✔ 전체 선택/해제</button>
            </div>
        </div>

        <h3>전체 캐릭터 목록 (제목 클릭 시 정렬)</h3>
        <table id="members-table">
            <thead>
                <tr>
                    <th style="width:40px; text-align:center;">선택</th>
                    <th onclick="sortTable('members-table', 1, 'number')" style="cursor:pointer;">번호 ⇅</th>
                    <th onclick="sortTable('members-table', 2, 'string')" style="cursor:pointer;">이름 ⇅</th>
                    <th onclick="sortTable('members-table', 3, 'number')" style="cursor:pointer;">포인트 ⇅</th>
                    <th>현재 상태</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="6">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    
    contentElement.innerHTML = pageHtml;
    
    document.getElementById('member-form').addEventListener('submit', handleMemberSubmit);
    document.getElementById('form-cancel-button').addEventListener('click', resetMemberForm);

    await fetchAndRenderMembers();
}

async function fetchAndRenderMembers() {
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_members.php`);
        const result = await response.json();
        const tableBody = document.querySelector('#members-table tbody');

        if (result.status === 'success' && result.data.length > 0) {
            const rowsHtml = result.data.map(member => `
                <tr data-id="${member.member_id}" class="${selectedMembers.has(String(member.member_id)) ? 'selected-row' : ''}" style="${selectedMembers.has(String(member.member_id)) ? 'background-color:#e3f2fd;' : ''}">
                    <td style="text-align:center;">
                        <input type="checkbox" class="member-checkbox" value="${member.member_id}" 
                            onchange="toggleMemberSelection('${member.member_id}')">
                    </td>
                    <td>${member.member_id}</td>
                    <td>${member.member_name}</td>
                    <td>${member.points.toLocaleString()} P</td>
                    <td style="color: #d9534f; font-weight: bold;">${member.status_list || '-'}</td>
                    <td>
                        <button class="btn-action btn-edit" onclick="populateEditForm('${member.member_id}', '${member.member_name}', ${member.points})">수정</button>
                        <button class="btn-action btn-delete" onclick="handleDeleteMember('${member.member_id}')">삭제</button>
                    </td>
                </tr>
            `).join('');
            tableBody.innerHTML = rowsHtml;
            
            document.querySelectorAll('.member-checkbox').forEach(cb => {
                if(selectedMembers.has(cb.value)) cb.checked = true;
            });
            
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="6">등록된 캐릭터가 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="6" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        const tb = document.querySelector('#members-table tbody');
        if(tb) tb.innerHTML = `<tr><td colspan="6" class="error">로드 오류: ${error}</td></tr>`;
    }
}

window.toggleMemberSelection = function(id) {
    id = String(id);
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (selectedMembers.has(id)) {
        selectedMembers.delete(id);
        if(row) row.style.backgroundColor = '';
    } else {
        selectedMembers.add(id);
        if(row) row.style.backgroundColor = '#e3f2fd';
    }
    document.getElementById('selected-count').textContent = selectedMembers.size;
};

window.selectAllMembers = function() {
    const checkboxes = document.querySelectorAll('.member-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
        toggleMemberSelection(cb.value);
    });
};

window.openBulkModal = async function(type) {
    const targets = Array.from(selectedMembers);
    if (targets.length === 0) {
        alert("선택된 멤버가 없습니다.");
        return;
    }

    let data = {};
    if (type === 'point') {
        const amount = prompt(`선택된 ${targets.length}명에게 지급할 포인트 (음수는 회수):`, "1000");
        if (amount === null) return;
        const reason = prompt("사유:", "단체 지급");
        if (reason === null) return;
        data = { amount: parseInt(amount), reason: reason };
    } 
    else if (type === 'item') {
        const itemId = prompt("지급할 아이템 ID 입력:", "");
        if (!itemId) return;
        const quantity = prompt("수량 입력:", "1");
        if (!quantity) return;
        data = { item_id: parseInt(itemId), quantity: parseInt(quantity) };
    }
    else if (type === 'status') {
        const typeId = prompt("부여할 상태 종류 ID 입력:", "");
        if (!typeId) return;
        data = { type_id: parseInt(typeId) };
    }

    if (confirm(`정말 ${targets.length}명에게 실행하시겠습니까?`)) {
        await executeBulkAction(type, targets, data);
    }
};

async function executeBulkAction(type, targets, data) {
    try {
        const res = await fetch(`${API_BASE_URL}/api_bulk_operation.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type, targets, data })
        });
        const text = await res.text();
        let result;
        try { result = JSON.parse(text); } catch(e) { throw new Error(text); }

        if(result.status === 'success') {
            alert(result.message);
            await fetchAndRenderMembers();
            selectedMembers.clear();
            document.getElementById('selected-count').textContent = '0';
        } else {
            alert("실패: " + result.message);
        }
    } catch (error) {
        alert("오류 발생: " + error.message);
    }
}

async function handleMemberSubmit(event) {
    event.preventDefault();
    const messageElement = document.getElementById('form-message');
    const mode = document.getElementById('action_mode').value;
    const name = document.getElementById('member_name').value;
    
    let apiUrl = 'api_add_member.php';
    let formData = { member_name: name };

    if (mode === 'update') {
        apiUrl = 'api_update_member.php';
        formData.member_id = document.getElementById('edit_member_id').value;
        formData.points = parseInt(document.getElementById('edit_points').value);
    }

    try {
        const response = await fetch(`${API_BASE_URL}/${apiUrl}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            resetMemberForm();
            await fetchAndRenderMembers();
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}

window.populateEditForm = function(id, name, points) {
    window.scrollTo(0, 0); 
    const form = document.getElementById('member-form');
    form.querySelector('h3').textContent = '캐릭터 정보 수정';
    
    document.getElementById('action_mode').value = 'update';
    document.getElementById('edit_member_id').value = id;
    document.getElementById('member_name').value = name;
    
    const pointInput = document.getElementById('edit_points');
    pointInput.style.display = 'block';
    pointInput.value = points;

    const submitBtn = document.getElementById('form-submit-button');
    submitBtn.textContent = '수정 완료';
    submitBtn.style.backgroundColor = '#ff9800';
    
    document.getElementById('form-cancel-button').style.display = 'block';
};

window.resetMemberForm = function() {
    const form = document.getElementById('member-form');
    form.querySelector('h3').textContent = '새 캐릭터 등록';
    form.reset();
    document.getElementById('action_mode').value = 'add';
    document.getElementById('edit_points').style.display = 'none';
    const submitBtn = document.getElementById('form-submit-button');
    submitBtn.textContent = '등록';
    submitBtn.style.backgroundColor = '';
    document.getElementById('form-cancel-button').style.display = 'none';
    const msg = document.getElementById('form-message');
    if(msg) { msg.textContent = ''; msg.className = ''; }
};

async function handleDeleteMember(memberId) {
    if (!confirm(`정말 [${memberId}] 캐릭터를 삭제하시겠습니까?\n이 캐릭터의 인벤토리와 포인트 로그도 모두 삭제/수정됩니다.`)) { return; }
    try {
        const response = await fetch(`${API_BASE_URL}/api_delete_member.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ member_id: memberId })
        });
        const result = await response.json();
        if (result.status === 'success') {
            alert(result.message);
            await fetchAndRenderMembers();
        } else {
            alert(`삭제 실패: ${result.message}`);
        }
    } catch (error) {
        alert(`삭제 중 오류 발생: ${error}`);
    }
}

// ---------------------------------------------------------
// 2. 아이템 관리
// ---------------------------------------------------------
async function loadItemsPage() {
    const pageHtml = `
<h2>상점 관리</h2>
        <form id="item-form">
            <input type="hidden" id="action_mode" value="add">
            <input type="hidden" id="item_id" name="item_id" value="">
            <h3>새 아이템 등록</h3>
            <div class="form-group">
                <label for="item_name">아이템 이름</label>
                <input type="text" id="item_name" name="item_name" required>
            </div>
            <div class="form-group">
                <label for="item_description">아이템 설명</label>
                <textarea id="item_description" name="item_description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="price">가격</label>
                <input type="number" id="price" name="price" value="0" min="0" required>
            </div>
            <div class="form-group">
                <label for="stock">재고 (-1은 무한)</label>
                <input type="number" id="stock" name="stock" value="-1" min="-1" required>
            </div>
            <div class="form-group">
                <label for="status">판매 상태</label>
                <select id="status" name="status">
                    <option value="selling">판매중</option>
                    <option value="sold_out">품절</option>
                </select>
            </div>
            <button type="submit" id="form-submit-button">아이템 등록</button>
            <button type="button" id="form-cancel-button" style="display:none;">취소</button>
            <p id="form-message"></p>
        </form>
        <h3>상점 아이템 목록 (제목 클릭 시 정렬)</h3>
        <table id="items-table">
            <thead>
                <tr>
                    <th onclick="sortTable('items-table', 0, 'number')" style="cursor:pointer;">ID ⇅</th>
                    <th>이름</th>
                    <th onclick="sortTable('items-table', 2, 'number')" style="cursor:pointer;">가격 ⇅</th>
                    <th onclick="sortTable('items-table', 3, 'number')" style="cursor:pointer;">재고 ⇅</th>
                    <th>상태</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="6">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;
    document.getElementById('item-form').addEventListener('submit', handleItemSubmit);
    document.getElementById('form-cancel-button').addEventListener('click', resetItemForm);
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_items.php`);
        const result = await response.json();
        const tableBody = document.querySelector('#items-table tbody');
        if (result.status === 'success' && result.data.length > 0) {
            const rowsHtml = result.data.map(item => `
                <tr>
                    <td>${item.item_id}</td>
                    <td>${item.item_name}</td>
                    <td>${item.price.toLocaleString()} P</td>
                    <td>${item.stock == -1 ? '무한' : item.stock.toLocaleString()}</td>
                    <td>${item.status === 'selling' ? '판매중' : '품절'}</td>
                    <td>
                        <button class="btn-action btn-edit" 
                                data-item-id="${item.item_id}" 
                                data-name="${item.item_name}" 
                                data-description="${item.item_description}"
                                data-price="${item.price}"
                                data-stock="${item.stock}"
                                data-status="${item.status}">
                            수정
                        </button>
                        <button class="btn-action btn-delete" 
                                data-item-id="${item.item_id}"
                                data-name="${item.item_name}">
                            삭제
                        </button>
                    </td>
                </tr>
            `).join('');
            tableBody.innerHTML = rowsHtml;
            attachItemTableListeners();
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="6">등록된 아이템이 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="6" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        document.querySelector('#items-table tbody').innerHTML = 
            `<tr><td colspan="6" class="error">데이터 로드 오류: ${error}</td></tr>`;
    }
}

async function handleItemSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('form-message');
    const mode = document.getElementById('action_mode').value;
    const apiUrl = (mode === 'add') ? 'api_add_item.php' : 'api_update_item.php';
    const formData = {
        item_id: document.getElementById('item_id').value, 
        item_name: document.getElementById('item_name').value,
        item_description: document.getElementById('item_description').value,
        price: parseInt(document.getElementById('price').value),
        stock: parseInt(document.getElementById('stock').value),
        status: document.getElementById('status').value
    };
    try {
        const response = await fetch(`${API_BASE_URL}/${apiUrl}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            resetItemForm();
            loadItemsPage();
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}

function attachItemTableListeners() {
    const tableBody = document.querySelector('#items-table tbody');
    tableBody.addEventListener('click', (event) => {
        const target = event.target;
        const itemId = target.dataset.itemId;
        if (target.classList.contains('btn-delete')) {
            const itemName = target.dataset.name;
            handleDeleteItem(itemId, itemName);
        } else if (target.classList.contains('btn-edit')) {
            const itemData = {...target.dataset};
            populateItemEditForm(itemData);
        }
    });
}

async function handleDeleteItem(itemId, itemName) {
    if (!confirm(`정말 [${itemName} (ID: ${itemId})] 아이템을 삭제하시겠습니까?\n이 아이템을 보유한 모든 캐릭터의 인벤토리에서도 아이템이 삭제됩니다.`)) { return; }
    try {
        const response = await fetch(`${API_BASE_URL}/api_delete_item.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ item_id: itemId })
        });
        const result = await response.json();
        if (result.status === 'success') {
            alert(result.message);
            loadItemsPage();
        } else {
            alert(`삭제 실패: ${result.message}`);
        }
    } catch (error) {
        alert(`삭제 중 오류 발생: ${error}`);
    }
}

function populateItemEditForm(itemData) {
    window.scrollTo(0, 0); 
    const form = document.getElementById('item-form');
    form.querySelector('h3').textContent = '아이템 정보 수정';
    document.getElementById('action_mode').value = 'update';
    document.getElementById('item_id').value = itemData.itemId; 
    document.getElementById('item_name').value = itemData.name;
    document.getElementById('item_description').value = itemData.description;
    document.getElementById('price').value = itemData.price;
    document.getElementById('stock').value = itemData.stock;
    document.getElementById('status').value = itemData.status;
    document.getElementById('form-submit-button').textContent = '수정 완료';
    document.getElementById('form-cancel-button').style.display = 'inline-block';
}

function resetItemForm() {
    const form = document.getElementById('item-form');
    form.querySelector('h3').textContent = '새 아이템 등록';
    document.getElementById('action_mode').value = 'add';
    form.reset(); 
    document.getElementById('item_id').value = ''; 
    document.getElementById('form-submit-button').textContent = '등록하기';
    document.getElementById('form-cancel-button').style.display = 'none';
    document.getElementById('form-message').textContent = '';
    document.getElementById('form-message').className = '';
}

// ---------------------------------------------------------
// 3. 도박 관리
// ---------------------------------------------------------
async function loadGamesPage() {
    const pageHtml = `
        <h2>도박 관리</h2>
        <form id="game-form">
            <input type="hidden" id="action_mode" value="add">
            <input type="hidden" id="game_id" name="game_id" value="">
            
            <h3>도박 게임 등록/수정</h3>
            <div class="form-group">
                <label for="game_name">게임 이름</label>
                <input type="text" id="game_name" name="game_name" required>
            </div>
            <div class="form-group">
                <label for="description">게임 설명</label>
                <textarea id="description" name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label for="outcomes">배율 목록 (쉼표로 구분)</label>
                <input type="text" id="outcomes" name="outcomes" placeholder="-10,-5,0,1,5,10" required>
            </div>
            
            <button type="submit" id="form-submit-button">게임 등록</button>
            <button type="button" id="form-cancel-button" style="display:none;">취소</button>
            <p id="form-message"></p>
        </form>

        <h3>도박 게임 목록</h3>
        <table id="games-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>게임 이름</th>
                    <th>설명</th>
                    <th>배율 목록</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="5">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;

    document.getElementById('game-form').addEventListener('submit', handleGameSubmit);
    document.getElementById('form-cancel-button').addEventListener('click', resetGameForm);

    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_games.php`);
        const result = await response.json();
        const tableBody = document.querySelector('#games-table tbody');

        if (result.status === 'success' && result.data.length > 0) {
            const rowsHtml = result.data.map(game => `
                <tr>
                    <td>${game.game_id}</td>
                    <td>${game.game_name}</td>
                    <td>${game.description}</td>
                    <td>${game.outcomes}</td>
                    <td>
                        <button class="btn-action btn-edit" 
                                data-id="${game.game_id}" 
                                data-name="${game.game_name}" 
                                data-desc="${game.description}"
                                data-outcomes="${game.outcomes}">
                            수정
                        </button>
                        <button class="btn-action btn-delete" 
                                data-id="${game.game_id}"
                                data-name="${game.game_name}">
                            삭제
                        </button>
                    </td>
                </tr>
            `).join('');
            tableBody.innerHTML = rowsHtml;
            attachGameTableListeners();
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="5">등록된 도박 게임이 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="5" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        document.querySelector('#games-table tbody').innerHTML = 
            `<tr><td colspan="5" class="error">데이터 로드 오류: ${error}</td></tr>`;
    }
}

async function handleGameSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('form-message');
    const mode = document.getElementById('action_mode').value;
    const apiUrl = (mode === 'add') ? 'api_add_game.php' : 'api_update_game.php';
    const formData = {
        game_id: document.getElementById('game_id').value,
        game_name: form.game_name.value,
        description: form.description.value,
        outcomes: form.outcomes.value
    };
    try {
        const response = await fetch(`${API_BASE_URL}/${apiUrl}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            resetGameForm();
            loadGamesPage();
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}


function attachGameTableListeners() {
    const tableBody = document.querySelector('#games-table tbody');
    tableBody.addEventListener('click', (event) => {
        const target = event.target;
        const gameId = target.dataset.id;
        if (target.classList.contains('btn-delete')) {
            const gameName = target.dataset.name;
            handleDeleteGame(gameId, gameName);
        } else if (target.classList.contains('btn-edit')) {
            const gameData = {
                id: gameId,
                name: target.dataset.name,
                desc: target.dataset.desc,
                outcomes: target.dataset.outcomes
            };
            populateGameEditForm(gameData);
        }
    });
}

async function handleDeleteGame(gameId, gameName) {
    if (!confirm(`정말 [${gameName}] 게임을 삭제하시겠습니까?`)) { return; }
    try {
        const response = await fetch(`${API_BASE_URL}/api_delete_game.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ game_id: gameId })
        });
        const result = await response.json();
        if (result.status === 'success') {
            alert(result.message);
            loadGamesPage();
        } else {
            alert(`삭제 실패: ${result.message}`);
        }
    } catch (error) {
        alert(`삭제 중 오류 발생: ${error}`);
    }
}

function populateGameEditForm(data) {
    window.scrollTo(0, 0);
    const form = document.getElementById('game-form');
    form.querySelector('h3').textContent = '도박 게임 정보 수정';
    document.getElementById('action_mode').value = 'update';
    document.getElementById('game_id').value = data.id;
    document.getElementById('game_name').value = data.name;
    document.getElementById('description').value = data.desc;
    document.getElementById('outcomes').value = data.outcomes;
    document.getElementById('form-submit-button').textContent = '수정 완료';
    document.getElementById('form-cancel-button').style.display = 'inline-block';
}

function resetGameForm() {
    const form = document.getElementById('game-form');
    form.querySelector('h3').textContent = '도박 게임 등록';
    document.getElementById('action_mode').value = 'add';
    form.reset();
    document.getElementById('game_id').value = '';
    document.getElementById('form-submit-button').textContent = '게임 등록';
    document.getElementById('form-cancel-button').style.display = 'none';
    const msg = document.getElementById('form-message');
    if(msg) { msg.textContent = ''; msg.className = ''; }
}

// ---------------------------------------------------------
// 4. 인벤토리 관리 (수정됨: 그룹화 및 편집 기능 추가)
// ---------------------------------------------------------
async function loadInventoryPage() {
    const pageHtml = `
        <h2>인벤토리 관리</h2>
        
        <div style="background:#fff3e0; padding:10px; border-radius:5px; margin-bottom:15px; border:1px solid #ffe0b2;">
            <strong>💡 아이템 수정/삭제 팁</strong><br>
            - [수정] 버튼을 누르면 수량을 변경할 수 있습니다.<br>
            - 수량을 0으로 입력하고 저장하면 아이템이 삭제(회수)됩니다.
        </div>

        <form id="give-item-form" style="margin-bottom:20px; padding:15px; background:white; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;">관리자 아이템 지급 (추가)</h3>
            <div style="display:flex; gap:10px; align-items:flex-end;">
                <div class="form-group" style="flex:2;">
                    <label for="member_id_select">캐릭터</label>
                    <select id="member_id_select" name="member_id" required style="width:100%;"><option value="">로딩 중...</option></select>
                </div>
                <div class="form-group" style="flex:2;">
                    <label for="item_id_select">아이템</label>
                    <select id="item_id_select" name="item_id" required style="width:100%;"><option value="">로딩 중...</option></select>
                </div>
                <div class="form-group" style="flex:1;">
                    <label for="quantity">수량</label>
                    <input type="number" id="quantity" name="quantity" value="1" min="1" required style="width:100%; box-sizing:border-box;">
                </div>
                <div class="form-group">
                    <button type="submit" style="padding:10px 15px;">지급</button>
                </div>
            </div>
            <p id="form-message"></p>
        </form>

        <h3>전체 인벤토리 목록</h3>
        <table id="inventory-table" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="width:30%;">캐릭터 이름</th>
                    <th style="width:30%;">아이템 이름</th>
                    <th style="width:20%;">보유 수량</th>
                    <th style="width:20%;">관리</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="4">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;

    document.getElementById('give-item-form').addEventListener('submit', handleGiveItem);

    try {
        const [membersRes, itemsRes, inventoryRes] = await Promise.all([
            fetch(`${API_BASE_URL}/api_get_all_members.php`),
            fetch(`${API_BASE_URL}/api_get_all_items.php`),
            fetch(`${API_BASE_URL}/api_get_all_inventory.php`)
        ]);
        const membersResult = await membersRes.json();
        const itemsResult = await itemsRes.json();
        const inventoryResult = await inventoryRes.json();

        populateSelect(document.getElementById('member_id_select'), membersResult.data, 'member_id', 'member_name');
        populateSelect(document.getElementById('item_id_select'), itemsResult.data, 'item_id', 'item_name');

        const tableBody = document.querySelector('#inventory-table tbody');
        
        if (inventoryResult.status === 'success' && inventoryResult.data.length > 0) {
            // 데이터 그룹화: member_id 기준
            const groupedData = {};
            inventoryResult.data.forEach(item => {
                if (!groupedData[item.member_id]) {
                    groupedData[item.member_id] = {
                        name: item.member_name,
                        id: item.member_id,
                        items: []
                    };
                }
                groupedData[item.member_id].items.push(item);
            });

            // 렌더링
            let rowsHtml = '';
            for (const mid in groupedData) {
                const member = groupedData[mid];
                const rowCount = member.items.length;

                member.items.forEach((item, index) => {
                    rowsHtml += `<tr>`;
                    // 첫 번째 아이템일 때만 캐릭터 이름 셀 생성 (Rowspan 적용)
                    if (index === 0) {
                        rowsHtml += `<td rowspan="${rowCount}" style="background-color:#f9f9f9; font-weight:bold; border-right:2px solid #ddd; vertical-align:middle;">
                                        ${member.name}<br><span style="font-size:0.8em; color:#888;">(${member.id})</span>
                                     </td>`;
                    }
                    rowsHtml += `
                        <td>${item.item_name}</td>
                        <td id="qty-${item.member_id}-${item.item_id}">
                            <span class="qty-text">${item.quantity.toLocaleString()} 개</span>
                            <input type="number" class="qty-input" value="${item.quantity}" style="display:none; width:60px;">
                        </td>
                        <td>
                            <button class="btn-action btn-edit" onclick="toggleEditInventory('${item.member_id}', '${item.item_id}')">수정</button>
                            <button class="btn-action btn-save" onclick="saveInventory('${item.member_id}', '${item.item_id}')" style="display:none; background-color:#28a745; color:white;">저장</button>
                            <button class="btn-action btn-delete" onclick="handleDeleteInventory('${item.member_id}', '${item.item_id}')">삭제</button>
                        </td>
                    </tr>`;
                });
            }
            tableBody.innerHTML = rowsHtml;

        } else if (inventoryResult.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="4">인벤토리에 아이템이 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="error">${inventoryResult.message}</td></tr>`;
        }
    } catch (error) {
        contentElement.innerHTML += `<p class="error">로드 오류: ${error}</p>`;
    }
}

// 인벤토리 수정 모드 토글
window.toggleEditInventory = function(memId, itemId) {
    const qtyCell = document.getElementById(`qty-${memId}-${itemId}`);
    const row = qtyCell.parentElement;
    
    const textSpan = qtyCell.querySelector('.qty-text');
    const input = qtyCell.querySelector('.qty-input');
    const btnEdit = row.querySelector('.btn-edit');
    const btnSave = row.querySelector('.btn-save');
    const btnDelete = row.querySelector('.btn-delete');

    if (input.style.display === 'none') {
        // 수정 모드 진입
        textSpan.style.display = 'none';
        input.style.display = 'inline-block';
        btnEdit.textContent = '취소';
        btnEdit.style.backgroundColor = '#6c757d'; // 회색
        btnSave.style.display = 'inline-block';
        btnDelete.style.display = 'none';
    } else {
        // 취소
        textSpan.style.display = 'inline-block';
        input.style.display = 'none';
        input.value = textSpan.textContent.replace(/[^0-9]/g, ''); // 원래 값 복구
        btnEdit.textContent = '수정';
        btnEdit.style.backgroundColor = ''; // 원래 색
        btnSave.style.display = 'none';
        btnDelete.style.display = 'inline-block';
    }
};

// 인벤토리 수량 저장
window.saveInventory = async function(memId, itemId) {
    const qtyCell = document.getElementById(`qty-${memId}-${itemId}`);
    const input = qtyCell.querySelector('.qty-input');
    const newQty = parseInt(input.value);

    try {
        const response = await fetch(`${API_BASE_URL}/api_update_inventory.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ member_id: memId, item_id: parseInt(itemId), quantity: newQty })
        });
        const result = await response.json();
        
        if (result.status === 'success') {
            alert(result.message);
            loadInventoryPage(); // 새로고침
        } else {
            alert("수정 실패: " + result.message);
        }
    } catch (error) {
        alert("오류 발생: " + error);
    }
};

async function handleGiveItem(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('form-message');
    const formData = {
        member_id: form.member_id_select.value,
        item_id: parseInt(form.item_id_select.value),
        quantity: parseInt(form.quantity.value)
    };
    try {
        const response = await fetch(`${API_BASE_URL}/api_admin_give_item.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            form.reset();
            loadInventoryPage();
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}

window.handleDeleteInventory = async function(memberId, itemId) {
    if (!confirm(`정말 삭제하시겠습니까?`)) { return; }
    try {
        const response = await fetch(`${API_BASE_URL}/api_admin_delete_inventory_item.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ member_id: memberId, item_id: itemId })
        });
        const result = await response.json();
        if (result.status === 'success') {
            alert(result.message);
            loadInventoryPage();
        } else {
            alert(`삭제 실패: ${result.message}`);
        }
    } catch (error) {
        alert(`오류 발생: ${error}`);
    }
};

// ---------------------------------------------------------
// 5. 포인트/아이템 양도
// ---------------------------------------------------------
async function loadTransferPointPage() {
    const pageHtml = `
        <h2>포인트 양도</h2>
        <form id="transfer-point-form">
            <h3>포인트 양도</h3>
            <div class="form-group">
                <label for="sender_id_select">보내는 분</label>
                <select id="sender_id_select" name="sender_id" required>
                    <option value="">캐릭터 로딩 중...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="receiver_id_select">받는 분</label>
                <select id="receiver_id_select" name="receiver_id" required>
                    <option value="">캐릭터 로딩 중...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">양도할 포인트</label>
                <input type="number" id="amount" name="amount" value="1" min="1" required>
            </div>
            <button type="submit">포인트 양도 실행</button>
            <p id="form-message"></p>
        </form>
    `;
    contentElement.innerHTML = pageHtml;
    document.getElementById('transfer-point-form').addEventListener('submit', handleTransferPoint);
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_members.php`);
        const result = await response.json();
        const senderSelect = document.getElementById('sender_id_select');
        populateSelect(senderSelect, result.data, 'member_id', 'member_name');
        const receiverSelect = document.getElementById('receiver_id_select');
        populateSelect(receiverSelect, result.data, 'member_id', 'member_name');
    } catch (error) {
        contentElement.innerHTML += `<p class="error">페이지 로드 중 심각한 오류 발생: ${error}</p>`;
    }
}

async function handleTransferPoint(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('form-message');
    const formData = {
        sender_id: form.sender_id.value,
        receiver_id: form.receiver_id.value,
        amount: parseInt(form.amount.value)
    };
    try {
        const response = await fetch(`${API_BASE_URL}/api_transfer_points.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            form.reset();
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}


async function loadTransferItemPage() {
    const pageHtml = `
        <h2>아이템 양도</h2>
        <form id="transfer-item-form">
            <h3>아이템 양도</h3>
            <div class="form-group">
                <label for="sender_id_select">보내는 분</label>
                <select id="sender_id_select" name="sender_id" required>
                    <option value="">캐릭터 로딩 중...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="receiver_id_select">받는 분</label>
                <select id="receiver_id_select" name="receiver_id" required>
                    <option value="">캐릭터 로딩 중...</option>
                </select>
            </div>
            <hr>
            <div class="form-group">
                <label for="item_id_select">보유 아이템 선택</label>
                <select id="item_id_select" name="item_id" required disabled>
                    <option value="">먼저 '보내는 분'을 선택하세요</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">수량</label>
                <input type="number" id="quantity" name="quantity" value="1" min="1" required disabled>
            </div>
            <button type="submit" id="transfer-item-submit" disabled>아이템 양도 실행</button>
            <p id="form-message"></p>
        </form>
    `;
    contentElement.innerHTML = pageHtml;
    document.getElementById('transfer-item-form').addEventListener('submit', handleTransferItem);
    document.getElementById('sender_id_select').addEventListener('change', handleSenderChange);
    document.getElementById('item_id_select').addEventListener('change', handleItemChange);
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_members.php`);
        const result = await response.json();
        const senderSelect = document.getElementById('sender_id_select');
        populateSelect(senderSelect, result.data, 'member_id', 'member_name');
        const receiverSelect = document.getElementById('receiver_id_select');
        populateSelect(receiverSelect, result.data, 'member_id', 'member_name');
    } catch (error) {
        contentElement.innerHTML += `<p class="error">페이지 로드 중 심각한 오류 발생: ${error}</p>`;
    }
}

async function handleSenderChange(event) {
    const senderId = event.target.value;
    const itemSelect = document.getElementById('item_id_select');
    const quantityInput = document.getElementById('quantity');
    const submitButton = document.getElementById('transfer-item-submit');
    itemSelect.innerHTML = '<option value="">불러오는 중...</option>';
    itemSelect.disabled = true;
    quantityInput.disabled = true;
    submitButton.disabled = true;
    if (!senderId) {
        itemSelect.innerHTML = '<option value="">먼저 \'보내는 분\'을 선택하세요</option>';
        return;
    }
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_member_inventory.php?member_id=${senderId}`);
        const result = await response.json();
        if (result.status === 'success') {
            populateSelect(itemSelect, result.data, 'item_id', 'item_name', 'quantity');
        } else {
            populateSelect(itemSelect, [], '', ''); 
        }
    } catch (error) {
        itemSelect.innerHTML = `<option value="">오류: ${error.message}</option>`;
    }
}

function handleItemChange(event) {
    const itemSelect = event.target;
    const quantityInput = document.getElementById('quantity');
    const submitButton = document.getElementById('transfer-item-submit');
    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        quantityInput.value = 1;
        quantityInput.disabled = true;
        submitButton.disabled = true;
        return;
    }
    const maxQuantity = parseInt(selectedOption.dataset.quantity || 0);
    if (maxQuantity > 0) {
        quantityInput.max = maxQuantity; 
        quantityInput.value = 1; 
        quantityInput.disabled = false;
        submitButton.disabled = false;
    }
}

async function handleTransferItem(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('form-message');
    const formData = {
        sender_id: form.sender_id.value,
        receiver_id: form.receiver_id.value,
        item_id: parseInt(form.item_id.value),
        quantity: parseInt(form.quantity.value)
    };
    try {
        const response = await fetch(`${API_BASE_URL}/api_transfer_item.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            loadTransferItemPage();
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}

// ---------------------------------------------------------
// 6. 로그
// ---------------------------------------------------------
async function loadLogsPage() {
    const pageHtml = `
        <h2>포인트 로그</h2>
        <h3>전체 포인트 변동 내역</h3>
        <table id="logs-table">
            <thead>
                <tr>
                    <th>시간</th>
                    <th>캐릭터 ID</th>
                    <th>캐릭터 이름</th>
                    <th>변동 포인트</th>
                    <th>사유</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="5">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_logs.php`);
        const result = await response.json();
        const tableBody = document.querySelector('#logs-table tbody');
        if (result.status === 'success' && result.data.length > 0) {
            const rowsHtml = result.data.map(log => {
                let pointClass = '';
                let pointDisplay = log.point_change;
                if (log.point_change > 0) {
                    pointClass = 'success';
                    pointDisplay = `+${log.point_change.toLocaleString()}`;
                } else if (log.point_change < 0) {
                    pointClass = 'error';
                    pointDisplay = `${log.point_change.toLocaleString()}`;
                }
                return `
                    <tr>
                        <td>${log.log_time}</td>
                        <td>${log.member_id || 'N/A'}</td>
                        <td>${log.member_name || '알 수 없음'}</td>
                        <td class="${pointClass}">${pointDisplay} P</td>
                        <td>${log.reason}</td>
                    </tr>
                `;
            }).join('');
            tableBody.innerHTML = rowsHtml;
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="5">포인트 변동 내역이 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="5" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        document.querySelector('#logs-table tbody').innerHTML = 
            `<tr><td colspan="5" class="error">데이터 로드 오류: ${error}</td></tr>`;
    }
}


async function loadItemLogsPage() {
    const pageHtml = `
        <h2>아이템 로그</h2>
        <h3>전체 아이템 변동 내역</h3>
        <table id="item-logs-table">
            <thead>
                <tr>
                    <th>시간</th>
                    <th>캐릭터 이름</th>
                    <th>아이템 이름</th>
                    <th>변동 수량</th>
                    <th>사유</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="5">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_item_logs.php`);
        const result = await response.json();
        const tableBody = document.querySelector('#item-logs-table tbody');
        if (result.status === 'success' && result.data.length > 0) {
            const rowsHtml = result.data.map(log => {
                let qtyClass = '';
                let qtyDisplay = log.quantity_change;
                if (log.quantity_change > 0) {
                    qtyClass = 'success'; 
                    qtyDisplay = `+${log.quantity_change.toLocaleString()}`;
                } else if (log.quantity_change < 0) {
                    qtyClass = 'error'; 
                    qtyDisplay = `${log.quantity_change.toLocaleString()}`;
                }
                return `
                    <tr>
                        <td>${log.log_time}</td>
                        <td>${log.member_name || '알 수 없음 (삭제됨)'}</td>
                        <td>${log.item_name || '알 수 없음 (삭제됨)'}</td>
                        <td class="${qtyClass}">${qtyDisplay} 개</td>
                        <td>${log.reason}</td>
                    </tr>
                `;
            }).join('');
            tableBody.innerHTML = rowsHtml;
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="5">아이템 변동 내역이 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="5" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        document.querySelector('#item-logs-table tbody').innerHTML = 
            `<tr><td colspan="5" class="error">데이터 로드 오류: ${error}</td></tr>`;
    }
}

// ---------------------------------------------------------
// 7. 설정 (초기화)
// ---------------------------------------------------------
async function loadSettingsPage() {
    const pageHtml = `
        <h2>설정</h2>
        
        <div style="border: 1px solid #ccc; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3>1. 시즌 초기화 (데이터만 삭제)</h3>
            <p><strong>관리자 계정, 상점 아이템, 도박 규칙, 상태 종류 설정</strong>은 유지됩니다.<br>
            그 외 <strong>모든 캐릭터, 인벤토리, 모든 로그</strong>만 삭제됩니다.</p>
            <button id="reset-data-button" class="btn-action btn-delete">시즌 데이터 초기화</button>
        </div>

        <div style="border: 1px solid #ffcccc; padding: 20px; border-radius: 8px; background-color: #fff5f5;">
            <h3 style="color: red;">2. 시스템 완전 초기화 (공장 초기화)</h3>
            <p><strong>경고:</strong> 데이터베이스 파일 자체를 삭제합니다.<br>
            관리자 계정을 포함한 <strong>모든 데이터가 사라지며</strong>, 처음 설치 화면(setup.php)으로 돌아갑니다.</p>
            <button id="factory-reset-button" class="btn-action btn-delete" style="background-color: darkred;">시스템 완전 삭제 (재설치)</button>
        </div>

        <p id="form-message"></p>
    `;
    contentElement.innerHTML = pageHtml;

    document.getElementById('reset-data-button').addEventListener('click', handleResetData);
    document.getElementById('factory-reset-button').addEventListener('click', handleFactoryReset);
}

async function handleResetData() {
    const messageElement = document.getElementById('form-message');
    messageElement.textContent = '';
    messageElement.className = '';

    if (!confirm("정말... 정말로 모든 캐릭터, 인벤토리, 로그 데이터를 삭제하시겠습니까?")) {
        return;
    }
    const confirmation = prompt("데이터 삭제를 확인하려면 '초기화합니다'라고 정확히 입력하세요.");
    if (confirmation !== "초기화합니다") {
        messageElement.textContent = '입력이 일치하지 않아 취소되었습니다.';
        messageElement.className = 'error';
        return;
    }

    try {
        messageElement.textContent = '데이터 초기화 중...';
        const response = await fetch(`${API_BASE_URL}/api_reset_data.php`, {
            method: 'POST'
        });
        const result = await response.json();

        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            alert('데이터가 성공적으로 초기화되었습니다! 페이지를 새로고침합니다.');
            location.reload(); 
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}

async function handleFactoryReset() {
    const messageElement = document.getElementById('form-message');
    messageElement.textContent = '';
    
    if (!confirm("정말 DB 자체를 삭제하시겠습니까?\n모든 설정이 날아가고 관리자 계정도 다시 만들어야 합니다.")) {
        return;
    }
    
    const confirmation = prompt("삭제하려면 '삭제합니다' 라고 입력하세요.");
    if (confirmation !== "삭제합니다") {
        alert("입력이 일치하지 않아 취소합니다.");
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/api_factory_reset.php`, { method: 'POST' });
        const result = await response.json();

        if (result.status === 'success') {
            alert(result.message);
            window.location.href = 'setup.php'; 
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `오류 발생: ${error}`;
        messageElement.className = 'error';
    }
}

function sortTable(tableId, colIndex, type) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    let order = table.getAttribute('data-order') === 'asc' ? 'desc' : 'asc';
    
    if (table.getAttribute('data-col') !== String(colIndex)) {
        order = 'asc';
    }
    
    table.setAttribute('data-order', order);
    table.setAttribute('data-col', colIndex);

    rows.sort((rowA, rowB) => {
        let cellA = rowA.cells[colIndex].innerText.trim();
        let cellB = rowB.cells[colIndex].innerText.trim();

        if (type === 'number') {
            const valA = parseInt(cellA.replace(/[^0-9-]/g, '')) || 0;
            const valB = parseInt(cellB.replace(/[^0-9-]/g, '')) || 0;
            return order === 'asc' ? valA - valB : valB - valA;
        } else {
            return order === 'asc' 
                ? cellA.localeCompare(cellB, undefined, {numeric: true}) 
                : cellB.localeCompare(cellA, undefined, {numeric: true});
        }
    });

    tbody.append(...rows);
}

// ---------------------------------------------------------
// 8. 상태 관리
// ---------------------------------------------------------
async function loadStatusPage() {
    const pageHtml = `
        <h2>상태 이상 관리</h2>
        
        <div style="display:flex; gap: 20px;">
            <div style="flex:1;">
                <h3>1. 상태 종류 만들기/수정</h3>
                <form id="status-type-form">
                    <input type="hidden" id="status_action_mode" value="add"> <input type="hidden" id="status_type_id" name="type_id">
                    
                    <div class="form-group">
                        <label>상태 이름</label>
                        <input type="text" id="status_name" name="status_name" required>
                    </div>
                    <div class="form-group">
                        <label>최대 단계</label>
                        <input type="number" id="max_stage" name="max_stage" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label>자동 악화 주기 (시간 단위, 0은 안 함)</label>
                        <input type="number" id="evolve_interval" name="evolve_interval" value="0" min="0" placeholder="예: 1 (1시간마다)">
                    </div>
                    <div class="form-group">
                        <label>기본 지속시간 (분, -1은 무한)</label>
                        <input type="number" id="default_duration" name="default_duration" value="-1">
                    </div>
                    <div class="form-group-inline">
                         <input type="checkbox" id="can_evolve" name="can_evolve" value="1">
                         <label for="can_evolve">단계 악화 가능 (체크 필수)</label>
                    </div>
                    
                    <button type="submit" id="btn-status-submit">상태 종류 등록</button>
                    <button type="button" id="btn-status-cancel" style="display:none; background-color:#6c757d;">취소</button>
                </form>
                <hr>
                <h4>등록된 상태 목록</h4>
                <table id="status-type-table" style="width:100%; border-collapse: collapse;">
                    <thead><tr style="background:#f1f1f1;"><th>이름</th><th>설정 정보</th><th>관리</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>

            <div style="flex:1; border-left:1px solid #ccc; padding-left:20px;">
                <h3>2. 캐릭터에게 상태 부여/관리</h3>
                <form id="give-status-form">
                    <div class="form-group">
                        <label>대상 캐릭터</label>
                        <select id="status_member_select" name="member_id" required></select>
                    </div>
                    <div class="form-group">
                        <label>적용할 상태</label>
                        <select id="status_type_select" name="type_id" required></select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-edit" style="width:100%;">상태 부여 (1단계 시작)</button>
                    </div>
                    
                    <div class="form-group" style="display:flex; gap:5px;">
                        <button type="button" id="btn-decrease" class="btn-action" style="background:#2196F3; color:white; flex:1;">완화 (▼)</button>
                        <button type="button" id="btn-evolve" class="btn-delete" style="background:orange; flex:1;">악화 (▲)</button>
                        <button type="button" id="btn-cure" class="btn-action" style="background:green; color:white; flex:1;">완전 치료</button>
                    </div>
                </form>
                <p id="status-message" style="margin-top:10px; font-weight:bold;"></p>
            </div>
        </div>
    `;
    contentElement.innerHTML = pageHtml;

    loadStatusTypes();
    loadMemberSelectOptions();

    document.getElementById('status-type-form').addEventListener('submit', handleStatusTypeSubmit);
    document.getElementById('btn-status-cancel').addEventListener('click', resetStatusTypeForm);
    
    document.getElementById('give-status-form').addEventListener('submit', (e) => handleStatusAction(e, 'add'));
    document.getElementById('btn-evolve').addEventListener('click', (e) => handleStatusAction(e, 'evolve'));
    document.getElementById('btn-decrease').addEventListener('click', (e) => handleStatusAction(e, 'decrease'));
    document.getElementById('btn-cure').addEventListener('click', (e) => handleStatusAction(e, 'cure'));
}

async function loadStatusTypes() {
    const res = await fetch(`${API_BASE_URL}/api_get_status_types.php`);
    const json = await res.json();
    const tableBody = document.querySelector('#status-type-table tbody');
    const select = document.getElementById('status_type_select');
    
    tableBody.innerHTML = '';
    select.innerHTML = '<option value="">-- 상태 선택 --</option>';

    if(json.status === 'success') {
        json.data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.status_name}</td>
                <td style="font-size:0.9em; color:#555;">
                    최대 ${item.max_stage}단계<br>
                    ${item.can_evolve == 1 ? '악화가능' : '고정상태'} 
                    (${item.evolve_interval > 0 ? item.evolve_interval + '시간마다' : '자동X'})<br>
                    지속: ${item.default_duration == -1 ? '무한' : item.default_duration + '분'}
                </td>
                <td>
                    <button class="btn-action btn-edit" onclick='editStatusType(${JSON.stringify(item)})'>수정</button>
                    <button class="btn-action btn-delete" onclick="deleteStatusType(${item.type_id}, '${item.status_name}')">삭제</button>
                </td>
            `;
            tableBody.appendChild(tr);

            const opt = document.createElement('option');
            opt.value = item.type_id;
            opt.textContent = item.status_name;
            opt.dataset.duration = item.default_duration;
            select.appendChild(opt);
        });
    }
}

async function handleStatusTypeSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const mode = document.getElementById('status_action_mode').value;
    const apiUrl = (mode === 'add') ? 'api_add_status_type.php' : 'api_update_status_type.php';
    
    const body = {
        type_id: document.getElementById('status_type_id').value,
        status_name: document.getElementById('status_name').value,
        max_stage: document.getElementById('max_stage').value,
        default_duration: document.getElementById('default_duration').value,
        can_evolve: document.getElementById('can_evolve').checked ? 1 : 0,
        evolve_interval: document.getElementById('evolve_interval').value
    };

    const res = await fetch(`${API_BASE_URL}/${apiUrl}`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body)
    });
    const json = await res.json();
    alert(json.message);
    if(json.status === 'success') {
        resetStatusTypeForm();
        loadStatusTypes();
    }
}

window.editStatusType = function(item) {
    document.getElementById('status_action_mode').value = 'update';
    document.getElementById('status_type_id').value = item.type_id;
    
    document.getElementById('status_name').value = item.status_name;
    document.getElementById('max_stage').value = item.max_stage;
    document.getElementById('evolve_interval').value = item.evolve_interval;
    document.getElementById('default_duration').value = item.default_duration;
    document.getElementById('can_evolve').checked = (item.can_evolve == 1);
    
    document.getElementById('btn-status-submit').textContent = '수정 완료';
    document.getElementById('btn-status-cancel').style.display = 'inline-block';
    
    document.getElementById('status-type-form').scrollIntoView({ behavior: 'smooth' });
};

window.deleteStatusType = async function(typeId, name) {
    if (!confirm(`[${name}] 상태를 정말 삭제하시겠습니까?\n이 상태에 걸려있는 모든 캐릭터의 상태도 함께 사라집니다.`)) return;

    const res = await fetch(`${API_BASE_URL}/api_delete_status_type.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ type_id: typeId })
    });
    const json = await res.json();
    alert(json.message);
    if(json.status === 'success') {
        loadStatusTypes();
    }
};

function resetStatusTypeForm() {
    const form = document.getElementById('status-type-form');
    form.reset();
    document.getElementById('status_action_mode').value = 'add';
    document.getElementById('status_type_id').value = '';
    document.getElementById('btn-status-submit').textContent = '상태 종류 등록';
    document.getElementById('btn-status-cancel').style.display = 'none';
}

async function loadMemberSelectOptions() {
    const res = await fetch(`${API_BASE_URL}/api_get_all_members.php`);
    const json = await res.json();
    const select = document.getElementById('status_member_select');
    populateSelect(select, json.data, 'member_id', 'member_name');
}

async function handleStatusAction(e, action) {
    e.preventDefault(); 
    const memberId = document.getElementById('status_member_select').value;
    const typeSelect = document.getElementById('status_type_select');
    const typeId = typeSelect.value;
    const duration = typeSelect.options[typeSelect.selectedIndex]?.dataset.duration || -1;
    const msgBox = document.getElementById('status-message');

    if(!memberId || !typeId) {
        msgBox.textContent = "캐릭터와 상태를 선택해주세요.";
        msgBox.style.color = "red";
        return;
    }

    const res = await fetch(`${API_BASE_URL}/api_set_member_status.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: action,
            member_id: memberId,
            type_id: typeId,
            duration: duration
        })
    });
    const json = await res.json();
    
    msgBox.textContent = json.message;
    msgBox.style.color = json.status === 'success' ? 'green' : 'red';
}

async function loadStatusLogsPage() {
    const pageHtml = `
        <h2>상태 이상 로그</h2>
        <h3>전체 상태 변동 내역</h3>
        <table id="status-logs-table">
            <thead>
                <tr>
                    <th>시간</th>
                    <th>캐릭터 이름</th>
                    <th>상태 이름</th>
                    <th>변동 내용</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="4">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;

    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_status_logs.php`);
        const result = await response.json();
        const tableBody = document.querySelector('#status-logs-table tbody');

        if (result.status === 'success' && result.data.length > 0) {
            const rowsHtml = result.data.map(log => `
                <tr>
                    <td>${log.log_time}</td>
                    <td>${log.member_name} (${log.member_id})</td>
                    <td style="font-weight:bold;">${log.status_name}</td>
                    <td>${log.action_detail}</td>
                </tr>
            `).join('');
            tableBody.innerHTML = rowsHtml;
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="4">기록된 상태 로그가 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        document.querySelector('#status-logs-table tbody').innerHTML = 
            `<tr><td colspan="4" class="error">데이터 로드 오류: ${error}</td></tr>`;
    }
}