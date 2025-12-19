const API_BASE_URL = 'https://주소.dothome.co.kr/'

const resultBox = document.getElementById('result-box');
const errorMessage = document.getElementById('error-message');

document.addEventListener('DOMContentLoaded', () => {
    setupTabs();
    
    document.getElementById('point-form').addEventListener('submit', handlePointForm);
    document.getElementById('transfer-point-form').addEventListener('submit', handleTransferPointForm);
    document.getElementById('transfer-item-form').addEventListener('submit', handleTransferItemForm);
    document.getElementById('gamble-form').addEventListener('submit', handleGambleForm);
    document.getElementById('item-form').addEventListener('submit', handleItemForm);
    document.getElementById('info-form').addEventListener('submit', handleInfoForm);

    const btnAdd = document.getElementById('btn-status-add');
    if(btnAdd) btnAdd.addEventListener('click', () => handleStatusAction('add'));
    
    const btnDecrease = document.getElementById('btn-status-decrease');
    if(btnDecrease) btnDecrease.addEventListener('click', () => handleStatusAction('decrease'));
    
    const btnEvolve = document.getElementById('btn-status-evolve');
    if(btnEvolve) btnEvolve.addEventListener('click', () => handleStatusAction('evolve'));
    
    const btnCure = document.getElementById('btn-status-cure');
    if(btnCure) btnCure.addEventListener('click', () => handleStatusAction('cure'));

    preloadAllDropdownData();
    
    const senderSelect = document.getElementById('sender-id-item-transfer');
    if (senderSelect) {
        senderSelect.addEventListener('change', handleSenderChangePopup);
    }
    const itemSelect = document.getElementById('item-id-transfer');
    if (itemSelect) {
        itemSelect.addEventListener('change', handleItemChangePopup);
    }
});

function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;

            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            button.classList.add('active');
            const targetContent = document.getElementById(tabName + '-tab');
            if(targetContent) targetContent.classList.add('active');
        });
    });
}

async function preloadAllDropdownData() {
    clearMessages();
    try {
        const [membersRes, itemsRes, gamesRes, statusRes] = await Promise.all([
            fetch(`${API_BASE_URL}api_get_all_members.php`),
            fetch(`${API_BASE_URL}api_get_all_items.php`),
            fetch(`${API_BASE_URL}api_get_all_games.php`),
            fetch(`${API_BASE_URL}api_get_status_types.php`)
        ]);

        const membersResult = await membersRes.json();
        const itemsResult = await itemsRes.json();
        const gamesResult = await gamesRes.json();
        const statusResult = await statusRes.json();

        const allMemberSelects = document.querySelectorAll(
            'select[name="member_id"], select[name="sender_id"], select[name="receiver_id"]'
        );
        allMemberSelects.forEach(selectBox => {
            populateSelect(selectBox, membersResult.data, 'member_id', 'member_name');
        });

        const itemSelect = document.getElementById('item-id-select');
        populateSelect(itemSelect, itemsResult.data, 'item_id', 'item_name');

        const gameSelect = document.getElementById('game-id-select');
        populateSelect(gameSelect, gamesResult.data, 'game_id', 'game_name');

        const statusSelect = document.getElementById('status-type-select');
        if (statusSelect && statusResult.status === 'success') {
            populateSelect(statusSelect, statusResult.data, 'type_id', 'status_name', 'default_duration');
            
            Array.from(statusSelect.options).forEach(opt => {
                if (opt.value) {
                    const data = statusResult.data.find(s => s.type_id == opt.value);
                    if(data) opt.dataset.duration = data.default_duration;
                }
            });
        }

    } catch (error) {
        showError(`데이터 로드 실패: ${error.message}`);
    }
}

function populateSelect(selectElement, data, valueField, textField, optionalField = null) {
    if (!data || data.length === 0) {
        selectElement.innerHTML = `<option value="">-- 데이터 없음 --</option>`;
        selectElement.disabled = true;
        return;
    }
    
    const optionsHtml = data.map(item => {
        let text = item[textField];
        if (optionalField && item[optionalField] !== undefined && textField !== 'status_name') {
            text += ` (보유: ${item[optionalField]})`;
        }
        return `<option value="${item[valueField]}" data-quantity="${item[optionalField] || 0}">${text}</option>`;
    });
    
    selectElement.innerHTML = `<option value="">-- 선택 --</option>` + optionsHtml.join('');
    selectElement.disabled = false;
}

// 포인트 확인 메시지를 출력하는 공통 함수
async function showUserPointsMessage(memberId) {
    try {
        const response = await fetch(`${API_BASE_URL}get_user_info.php?member_id=${memberId}`);
        const result = await response.json();
        if (result.status === 'success') {
            const p = result.data.points.toLocaleString();
            showResult(`확인했습니다! 현재 포인트는 ${p}p입니다!`);
        }
    } catch (e) {
        console.error(e);
    }
}

async function handleStatusAction(actionType) {
    const memberId = document.getElementById('member-id-status').value;
    const typeSelect = document.getElementById('status-type-select');
    const typeId = typeSelect.value;
    
    if (!memberId || !typeId) {
        showError('회원과 상태 종류를 모두 선택해주세요.');
        return;
    }

    const duration = typeSelect.options[typeSelect.selectedIndex].dataset.duration || -1;

    const formData = {
        action: actionType,
        member_id: memberId,
        type_id: typeId,
        duration: duration
    };

    const result = await callApi('api_set_member_status.php', formData);
    if (result) {
        showResult(result.message);
    }
}

async function handlePointForm(event) {
    event.preventDefault();
    const form = event.target;
    const memberId = form.member_id.value;
    const result = await callApi('admin_give_point.php', {
        member_id: memberId,
        points: parseInt(form.points.value),
        reason: form.reason.value
    });
    if (result) { 
        await showUserPointsMessage(memberId);
        form.reset(); 
    }
}

async function handleTransferPointForm(event) {
    event.preventDefault();
    const form = event.target;
    const senderId = form.sender_id.value;
    const result = await callApi('api_transfer_points.php', {
        sender_id: senderId,
        receiver_id: form.receiver_id.value,
        amount: parseInt(form.amount.value)
    });
    if (result) {
        await showUserPointsMessage(senderId);
    }
}

async function handleTransferItemForm(event) {
    event.preventDefault();
    const form = event.target;
    const result = await callApi('api_transfer_item.php', {
        sender_id: form.sender_id.value,
        receiver_id: form.receiver_id.value,
        item_id: parseInt(form.item_id.value),
        quantity: parseInt(form.quantity.value)
    });
    if (result) {
        showResult(result.message);
        form.reset();
        document.getElementById('item-id-transfer').disabled = true;
        document.getElementById('quantity-transfer').disabled = true;
        document.getElementById('transfer-item-submit').disabled = true;
    }
}

// [수정됨] 도박: 결과 배율을 포함하여 메시지 출력
async function handleGambleForm(event) {
    event.preventDefault();
    const form = event.target;
    const memberId = form.member_id.value;
    
    const result = await callApi('run_gamble.php', {
        member_id: memberId,
        game_id: parseInt(form.game_id.value),
        bet_amount: parseInt(form.bet_amount.value)
    });
    
    if (result) {
        try {
            // 포인트 갱신 조회
            const response = await fetch(`${API_BASE_URL}get_user_info.php?member_id=${memberId}`);
            const infoResult = await response.json();
            
            if (infoResult.status === 'success') {
                const p = infoResult.data.points.toLocaleString();
                // 도박 API가 돌려준 배율(multiplier) 사용
                const multiplier = result.multiplier; 
                
                showResult(`확인했습니다! (${multiplier}배) 현재 포인트는 ${p}p입니다!`);
            }
        } catch (e) {
            console.error(e);
            // 에러 시 기본 메시지라도 출력
            showResult(result.message);
        }
    }
}

async function handleItemForm(event) {
    event.preventDefault();
    const form = event.target;
    const memberId = form.member_id.value;
    const isPurchase = document.getElementById('item-is-purchase').checked;
    const endpoint = isPurchase ? 'buy_item.php' : 'api_admin_give_item.php';
    const result = await callApi(endpoint, {
        member_id: memberId,
        item_id: parseInt(form.item_id.value),
        quantity: parseInt(form.quantity.value)
    });
    if (result) { 
        if (isPurchase) {
            await showUserPointsMessage(memberId);
        } else {
            showResult(result.message);
        }
        form.reset(); 
        document.getElementById('item-is-purchase').checked = false; 
    }
}

async function handleInfoForm(event) {
    event.preventDefault();
    clearMessages();
    const memberId = event.target.member_id.value;
    await showUserPointsMessage(memberId);
}

async function callApi(endpoint, body) {
    clearMessages();
    try {
        const response = await fetch(API_BASE_URL + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const result = await response.json();
        if (result.status === 'error') throw new Error(result.message);
        return result;
    } catch (error) {
        showError(error.message);
        return null;
    }
}

async function handleSenderChangePopup(event) {
    const senderId = event.target.value;
    const itemSelect = document.getElementById('item-id-transfer');
    const quantityInput = document.getElementById('quantity-transfer');
    const submitButton = document.getElementById('transfer-item-submit');

    itemSelect.innerHTML = '<option value="">로딩 중...</option>';
    itemSelect.disabled = true;
    quantityInput.disabled = true;
    submitButton.disabled = true;

    if (!senderId) {
        itemSelect.innerHTML = '<option value="">보내는 분 선택 필요</option>';
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}api_get_member_inventory.php?member_id=${senderId}`);
        const result = await response.json();
        if (result.status === 'success') {
            populateSelect(itemSelect, result.data, 'item_id', 'item_name', 'quantity');
        } else {
            populateSelect(itemSelect, [], '', '');
        }
    } catch (error) { showError(error.message); }
}

function handleItemChangePopup(event) {
    const itemSelect = event.target;
    const quantityInput = document.getElementById('quantity-transfer');
    const submitButton = document.getElementById('transfer-item-submit');
    const selectedOption = itemSelect.options[itemSelect.selectedIndex];
    
    if (!selectedOption || !selectedOption.value) {
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

function showResult(message) { resultBox.value = message; errorMessage.textContent = ''; }
function showError(message) { errorMessage.textContent = message; resultBox.value = ''; }
function clearMessages() { resultBox.value = ''; errorMessage.textContent = ''; }