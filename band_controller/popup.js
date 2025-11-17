const API_BASE_URL = 'https://주소.dothome.co.kr/';

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
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });
}

async function preloadAllDropdownData() {
    clearMessages();
    try {
        const [membersRes, itemsRes, gamesRes] = await Promise.all([
            fetch(`${API_BASE_URL}api_get_all_members.php`),
            fetch(`${API_BASE_URL}api_get_all_items.php`),
            fetch(`${API_BASE_URL}api_get_all_games.php`)
        ]);

        const membersResult = await membersRes.json();
        const itemsResult = await itemsRes.json();
        const gamesResult = await gamesRes.json();

        if (membersResult.status !== 'success' || itemsResult.status !== 'success' || gamesResult.status !== 'success') {
            throw new Error('데이터 로드 실패. 닷홈 관리자 페이지에 로그인했는지 확인하세요.');
        }

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

    } catch (error) {
        showError(`초기화 실패: ${error.message}`);
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
        if (optionalField && item[optionalField]) {
            text += ` (보유: ${item[optionalField]})`;
        }
        return `<option value="${item[valueField]}" data-quantity="${item[optionalField] || 0}">${text}</option>`;
    });
    
    selectElement.innerHTML = `<option value="">-- 선택 --</option>` + optionsHtml.join('');
    selectElement.disabled = false;
}

async function handlePointForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = {
        member_id: form.member_id.value,
        points: parseInt(form.points.value),
        reason: form.reason.value
    };
    
    const result = await callApi('admin_give_point.php', formData);
    if (result) {
        showResult(result.message);
        form.reset();
    }
}

async function handleTransferPointForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = {
        sender_id: form.sender_id.value,
        receiver_id: form.receiver_id.value,
        amount: parseInt(form.amount.value)
    };

    const result = await callApi('api_transfer_points.php', formData);
    if (result) {
        showResult(result.message);
    }
}

async function handleTransferItemForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = {
        sender_id: form.sender_id.value,
        receiver_id: form.receiver_id.value,
        item_id: parseInt(form.item_id.value),
        quantity: parseInt(form.quantity.value)
    };

    const result = await callApi('api_transfer_item.php', formData);
    if (result) {
        showResult(result.message);
        form.reset();
        document.getElementById('item-id-transfer').innerHTML = '<option value="">먼저 \'보내는 분\'을 선택하세요</option>';
        document.getElementById('item-id-transfer').disabled = true;
        document.getElementById('quantity-transfer').disabled = true;
        document.getElementById('transfer-item-submit').disabled = true;
    }
}

async function handleGambleForm(event) {
    event.preventDefault();
    const form = event.target;
    const formData = {
        member_id: form.member_id.value,
        game_id: parseInt(form.game_id.value),
        bet_amount: parseInt(form.bet_amount.value)
    };

    const result = await callApi('run_gamble.php', formData);
    if (result) {
        showResult(result.message);
    }
}

async function handleItemForm(event) {
    event.preventDefault();
    const form = event.target;
    const isPurchase = document.getElementById('item-is-purchase').checked;
    const endpoint = isPurchase ? 'buy_item.php' : 'api_admin_give_item.php';

    const formData = {
        member_id: form.member_id.value,
        item_id: parseInt(form.item_id.value),
        quantity: parseInt(form.quantity.value)
    };

    const result = await callApi(endpoint, formData);
    if (result) {
        showResult(result.message);
        form.reset();
        document.getElementById('item-is-purchase').checked = false;
    }
}

async function handleInfoForm(event) {
    event.preventDefault();
    clearMessages();
    const form = event.target;
    const memberId = form.member_id.value;

    try {
        const response = await fetch(`${API_BASE_URL}get_user_info.php?member_id=${memberId}`);
        if (!response.ok) {
            throw new Error('서버 응답이 올바르지 않습니다.');
        }

        const result = await response.json();
        if (result.status === 'success') {
            const info = result.data;
            let message = `💬 [${info.member_name} (${info.member_id})] 님 정보\n`;
            message += `====================\n`;
            message += `포인트: ${info.points.toLocaleString()} P\n`;
            message += `--- 인벤토리 ---\n`;
            
            if (info.inventory.length === 0) {
                message += `(아이템 없음)`;
            } else {
                info.inventory.forEach(item => {
                    message += `[${item.item_name}] x ${item.quantity}\n`;
                });
            }
            showResult(message);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        showError(error.message);
    }
}

async function callApi(endpoint, body) {
    clearMessages();
    try {
        const response = await fetch(API_BASE_URL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        });

        if (!response.ok) {
            throw new Error(`서버 오류: ${response.statusText}`);
        }

        const result = await response.json();
        
        if (result.status === 'error') {
            throw new Error(result.message);
        }

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

    itemSelect.innerHTML = '<option value="">불러오는 중...</option>';
    itemSelect.disabled = true;
    quantityInput.disabled = true;
    submitButton.disabled = true;

    if (!senderId) {
        itemSelect.innerHTML = '<option value="">먼저 \'보내는 분\'을 선택하세요</option>';
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
    } catch (error) {
        showError(error.message);
    }
}

function handleItemChangePopup(event) {
    const itemSelect = event.target;
    const quantityInput = document.getElementById('quantity-transfer');
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


function showResult(message) {
    resultBox.value = message;
    errorMessage.textContent = '';
}
function showError(message) {
    errorMessage.textContent = message;
    resultBox.value = '';
}
function clearMessages() {
    resultBox.value = '';
    errorMessage.textContent = '';
}