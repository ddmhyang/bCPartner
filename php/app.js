const API_BASE_URL = '.'; 

const contentElement = document.getElementById('app-content');

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

async function loadMembersPage() {
    const pageHtml = `
        <h2>캐릭터 관리</h2>
        <form id="member-form">
            <input type="hidden" id="action_mode" value="add">
            <h3>새 캐릭터 등록 (수정 시 여기를 보세요)</h3>
            <div class="form-group">
                <label for="member_id">캐릭터 번호</label>
                <input type="text" id="member_id" name="member_id" required>
            </div>
            <div class="form-group">
                <label for="member_name">이름 (표시용)</label>
                <input type="text" id="member_name" name="member_name" required>
            </div>
            <div class="form-group" id="points-group" style="display:none;">
                <label for="points">포인트</label>
                <input type="number" id="points" name="points" value="0" required>
            </div>
            <button type="submit" id="form-submit-button">등록하기</button>
            <button type="button" id="form-cancel-button" style="display:none;">취소</button>
            <p id="form-message"></p>
        </form>
        <h3>전체 캐릭터 목록</h3>
        <table id="members-table">
            <thead>
                <tr>
                    <th>캐릭터 번호</th>
                    <th>이름</th>
                    <th>보유 포인트</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody><tr><td colspan="4">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;
    document.getElementById('member-form').addEventListener('submit', handleMemberSubmit);
    document.getElementById('form-cancel-button').addEventListener('click', resetMemberForm);
    try {
        const response = await fetch(`${API_BASE_URL}/api_get_all_members.php`);
        const result = await response.json();
        const tableBody = document.querySelector('#members-table tbody');
        if (result.status === 'success' && result.data.length > 0) {
            const rowsHtml = result.data.map(member => `
                <tr data-id="${member.member_id}">
                    <td>${member.member_id}</td>
                    <td>${member.member_name}</td>
                    <td>${member.points.toLocaleString()} P</td>
                    <td>
                        <button class="btn-action btn-edit" 
                                data-id="${member.member_id}" 
                                data-name="${member.member_name}" 
                                data-points="${member.points}">
                            수정
                        </button>
                        <button class="btn-action btn-delete" 
                                data-id="${member.member_id}">
                            삭제
                        </button>
                    </td>
                </tr>
            `).join('');
            tableBody.innerHTML = rowsHtml;
            attachMemberTableListeners();
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="4">등록된 캐릭터가 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        document.querySelector('#members-table tbody').innerHTML = 
            `<tr><td colspan="4" class="error">데이터 로드 오류: ${error}</td></tr>`;
    }
}

async function handleMemberSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('form-message');
    const mode = document.getElementById('action_mode').value;
    const apiUrl = (mode === 'add') ? 'api_add_member.php' : 'api_update_member.php';
    const formData = {
        member_id: form.member_id.value,
        member_name: form.member_name.value,
        points: parseInt(form.points.value)
    };
    if (mode === 'add') { delete formData.points; }
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
            loadMembersPage();
        } else {
            messageElement.textContent = result.message;
            messageElement.className = 'error';
        }
    } catch (error) {
        messageElement.textContent = `전송 오류: ${error}`;
        messageElement.className = 'error';
    }
}

function attachMemberTableListeners() {
    const tableBody = document.querySelector('#members-table tbody');
    tableBody.addEventListener('click', (event) => {
        const target = event.target;
        const memberId = target.dataset.id;
        if (target.classList.contains('btn-delete')) {
            handleDeleteMember(memberId);
        } else if (target.classList.contains('btn-edit')) {
            const memberName = target.dataset.name;
            const memberPoints = parseInt(target.dataset.points);
            populateEditForm(memberId, memberName, memberPoints);
        }
    });
}

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
            loadMembersPage();
        } else {
            alert(`삭제 실패: ${result.message}`);
        }
    } catch (error) {
        alert(`삭제 중 오류 발생: ${error}`);
    }
}

function populateEditForm(id, name, points) {
    window.scrollTo(0, 0); 
    const form = document.getElementById('member-form');
    form.querySelector('h3').textContent = '캐릭터 정보 수정';
    document.getElementById('action_mode').value = 'update';
    document.getElementById('member_id').value = id;
    document.getElementById('member_id').readOnly = true; 
    document.getElementById('member_name').value = name;
    document.getElementById('points').value = points;
    document.getElementById('points-group').style.display = 'block';
    document.getElementById('form-submit-button').textContent = '수정 완료';
    document.getElementById('form-cancel-button').style.display = 'inline-block';
}

function resetMemberForm() {
    const form = document.getElementById('member-form');
    form.querySelector('h3').textContent = '새 캐릭터 등록';
    document.getElementById('action_mode').value = 'add';
    form.reset();
    document.getElementById('member_id').readOnly = false;
    document.getElementById('points-group').style.display = 'none';
    document.getElementById('form-submit-button').textContent = '등록하기';
    document.getElementById('form-cancel-button').style.display = 'none';
    document.getElementById('form-message').textContent = '';
    document.getElementById('form-message').className = '';
}


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
        <h3>상점 아이템 목록</h3>
        <table id="items-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>이름</th>
                    <th>가격</th>
                    <th>재고</th>
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


async function loadGamesPage() {
    const pageHtml = `
        <h2>도박 관리</h2>
        <form id="add-game-form">
            <h3>새 도박 게임 등록</h3>
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
            <button type="submit">게임 등록</button>
            <p id="form-message"></p>
        </form>
        <h3>도박 게임 목록</h3>
        <table id="games-table">
            <thead><tr><th>ID</th><th>게임 이름</th><th>설명</th><th>배율 목록</th></tr></thead>
            <tbody><tr><td colspan="4">데이터 로딩 중...</td></tr></tbody>
        </table>
    `;
    contentElement.innerHTML = pageHtml;
    document.getElementById('add-game-form').addEventListener('submit', handleAddGame);
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
                </tr>
            `).join('');
            tableBody.innerHTML = rowsHtml;
        } else if (result.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="4">등록된 도박 게임이 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="error">${result.message}</td></tr>`;
        }
    } catch (error) {
        document.querySelector('#games-table tbody').innerHTML = 
            `<tr><td colspan="4" class="error">데이터 로드 오류: ${error}</td></tr>`;
    }
}

async function handleAddGame(event) {
    event.preventDefault();
    const form = event.target;
    const messageElement = document.getElementById('form-message');
    const formData = {
        game_name: form.game_name.value,
        description: form.description.value,
        outcomes: form.outcomes.value
    };
    try {
        const response = await fetch(`${API_BASE_URL}/api_add_game.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        });
        const result = await response.json();
        if (result.status === 'success') {
            messageElement.textContent = result.message;
            messageElement.className = 'success';
            form.reset();
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


async function loadInventoryPage() {
    const pageHtml = `
        <h2>인벤토리 관리</h2>
        <form id="give-item-form">
            <h3>관리자 아이템 지급</h3>
            <div class="form-group">
                <label for="member_id_select">캐릭터 선택</label>
                <select id="member_id_select" name="member_id" required>
                    <option value="">캐릭터 로딩 중...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="item_id_select">아이템 선택</label>
                <select id="item_id_select" name="item_id" required>
                    <option value="">아이템 로딩 중...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="quantity">수량</label>
                <input type="number" id="quantity" name="quantity" value="1" min="1" required>
            </div>
            <button type="submit">아이템 지급</button>
            <p id="form-message"></p>
        </form>
        <h3>전체 인벤토리 목록</h3>
        <table id="inventory-table">
            <thead>
                <tr>
                    <th>캐릭터 이름</th>
                    <th>아이템 이름</th>
                    <th>보유 수량</th>
                    <th>관리</th>
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

        const memberSelect = document.getElementById('member_id_select');
        populateSelect(memberSelect, membersResult.data, 'member_id', 'member_name');

        const itemSelect = document.getElementById('item_id_select');
        populateSelect(itemSelect, itemsResult.data, 'item_id', 'item_name');

        const tableBody = document.querySelector('#inventory-table tbody');
        if (inventoryResult.status === 'success' && inventoryResult.data.length > 0) {
            const rowsHtml = inventoryResult.data.map(inv => `
                <tr>
                    <td>${inv.member_name} (${inv.member_id})</td>
                    <td>${inv.item_name} (ID: ${inv.item_id})</td>
                    <td>${inv.quantity.toLocaleString()} 개</td>
                    <td>
                        <button class="btn-action btn-delete" 
                                data-member-id="${inv.member_id}" 
                                data-item-id="${inv.item_id}">
                            삭제
                        </button>
                    </td>
                </tr>
            `).join('');
            tableBody.innerHTML = rowsHtml;
            attachInventoryTableListeners();
        } else if (inventoryResult.status === 'success') {
            tableBody.innerHTML = '<tr><td colspan="4">인벤토리에 아이템이 없습니다.</td></tr>';
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="error">${inventoryResult.message}</td></tr>`;
        }
    } catch (error) {
        contentElement.innerHTML += `<p class="error">페이지 로드 중 심각한 오류 발생: ${error}</p>`;
    }
}

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

function attachInventoryTableListeners() {
    const tableBody = document.querySelector('#inventory-table tbody');
    tableBody.addEventListener('click', async (event) => {
        const target = event.target;
        if (target.classList.contains('btn-delete')) {
            const memberId = target.dataset.memberId;
            const itemId = target.dataset.itemId;
            if (!confirm(`[${memberId}] 캐릭터의 [아이템 ID: ${itemId}]을(를)\n인벤토리에서 전부 삭제하시겠습니까?`)) { return; }
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
                alert(`삭제 중 오류 발생: ${error}`);
            }
        }
    });
}


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


async function loadSettingsPage() {
    const pageHtml = `
        <h2>설정</h2>
        <h3>데이터 초기화 (시즌 2 시작)</h3>
        <div class="form-group">
            <p>이 기능은 <strong>관리자 계정, 상점 아이템, 도박 규칙</strong>을 제외한<br>
            <strong>모든 캐릭터, 인벤토리, 포인트 로그, 아이템 로그</strong>를 영구적으로 삭제합니다.</p>
            <p style="font-weight:bold; color:red;">절대로 되돌릴 수 없습니다. 신중하게 사용하세요.</p>
            
            <button id="reset-data-button" class="btn-action btn-delete">모든 운영 데이터 초기화</button>
        </div>
        <p id="form-message"></p>
    `;
    contentElement.innerHTML = pageHtml;

    document.getElementById('reset-data-button').addEventListener('click', handleResetData);
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