/**
 * B-Partner v2 확장 프로그램(밴드 조종기) 통합 로직
 * 모든 개별 API 파일을 api_handler.php의 action 파라미터 호출 방식으로 전환하였습니다.
 */

// 서버의 실제 주소로 변경하여 사용하세요.
const API_BASE_URL = 'https://z3rdk9.dothome.co.kr/'; 
const API_HANDLER = `${API_BASE_URL}api_handler.php`;

const resultBox = document.getElementById('result-box');
const errorMessage = document.getElementById('error-message');

document.addEventListener('DOMContentLoaded', () => {
    // 탭 시스템 초기화
    setupTabs();
    
    // 각 폼(Form)에 대한 제출 이벤트 리스너 등록
    document.getElementById('point-form').addEventListener('submit', handlePointForm);
    document.getElementById('transfer-point-form').addEventListener('submit', handleTransferPointForm);
    document.getElementById('transfer-item-form').addEventListener('submit', handleTransferItemForm);
    document.getElementById('gamble-form').addEventListener('submit', handleGambleForm);
    document.getElementById('item-form').addEventListener('submit', handleItemForm);
    document.getElementById('info-form').addEventListener('submit', handleInfoForm);

    // 상태 관리 버튼들에 대한 리스너 등록
    const btnAdd = document.getElementById('btn-status-add');
    if(btnAdd) btnAdd.addEventListener('click', () => handleStatusAction('add'));
    
    const btnDecrease = document.getElementById('btn-status-decrease');
    if(btnDecrease) btnDecrease.addEventListener('click', () => handleStatusAction('decrease'));
    
    const btnEvolve = document.getElementById('btn-status-evolve');
    if(btnEvolve) btnEvolve.addEventListener('click', () => handleStatusAction('evolve'));
    
    const btnCure = document.getElementById('btn-status-cure');
    if(btnCure) btnCure.addEventListener('click', () => handleStatusAction('cure'));

    // 초기 데이터 로드 (드롭다운 채우기)
    preloadAllDropdownData();
    
    // 양도 페이지 등에서 발신자 변경 시 인벤토리 자동 갱신
    const senderSelect = document.getElementById('sender-id-item-transfer');
    if (senderSelect) {
        senderSelect.addEventListener('change', handleSenderChangePopup);
    }
    const itemSelect = document.getElementById('item-id-transfer');
    if (itemSelect) {
        itemSelect.addEventListener('change', handleItemChangePopup);
    }
});

/**
 * 탭 메뉴 전환 기능
 */
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

/**
 * 통합 API 호출 함수 (V2 api_handler 전용)
 */
async function callV2Api(action, body = {}, method = 'POST') {
    clearMessages();
    try {
        const url = method === 'GET' 
            ? `${API_HANDLER}?action=${action}&${new URLSearchParams(body).toString()}`
            : `${API_HANDLER}?action=${action}`;
        
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };
        
        if (method === 'POST') {
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const result = await response.json();
        
        if (result.status === 'error') throw new Error(result.message);
        return result;
    } catch (error) {
        showError(`에러 발생: ${error.message}`);
        return null;
    }
}

/**
 * 드롭다운 목록을 서버 데이터로 채우는 함수
 */
async function preloadAllDropdownData() {
    try {
        // 병렬로 데이터 호출 (속도 향상)
        const [membersRes, itemsRes, statusRes] = await Promise.all([
            callV2Api('get_members', {}, 'GET'),
            callV2Api('get_items', {}, 'GET'),
            callV2Api('get_status_types', {}, 'GET')
        ]);

        if (membersRes) {
            const selects = document.querySelectorAll('select[name="member_id"], select[name="sender_id"], select[name="receiver_id"]');
            selects.forEach(s => populateSelect(s, membersRes.data, 'member_id', 'member_name'));
        }
        
        if (itemsRes) {
            populateSelect(document.getElementById('item-id-select'), itemsRes.data, 'item_id', 'item_name');
        }

        if (statusRes) {
            populateSelect(document.getElementById('status-type-select'), statusRes.data, 'type_id', 'type_name');
        }

    } catch (error) {
        showError(`데이터 로드 실패: ${error.message}`);
    }
}

function populateSelect(selectElement, data, valueField, textField) {
    if (!selectElement) return;
    if (!data || data.length === 0) {
        selectElement.innerHTML = `<option value="">-- 데이터 없음 --</option>`;
        selectElement.disabled = true;
        return;
    }
    
    const optionsHtml = data.map(item => `<option value="${item[valueField]}">${item[textField]}</option>`);
    selectElement.innerHTML = `<option value="">-- 선택 --</option>` + optionsHtml.join('');
    selectElement.disabled = false;
}

// --- 개별 폼 핸들러 로직 (V2 API 연동) ---

async function handlePointForm(event) {
    event.preventDefault();
    const form = event.target;
    // bulk_point 액션을 사용하여 1명 혹은 다수에게 포인트 지급
    const result = await callV2Api('bulk_point', {
        targets: [form.member_id.value],
        amount: parseInt(form.points.value),
        reason: form.reason.value
    });
    if (result) {
        showResult(`[성공] 포인트 처리가 완료되었습니다.`);
        form.reset();
    }
}

async function handleTransferPointForm(event) {
    event.preventDefault();
    const form = event.target;
    // M:N 양도 API를 1:1 형식으로 호출
    const result = await callV2Api('transfer_points_multi', {
        sender_id: form.sender_id.value,
        receivers: [{
            id: form.receiver_id.value,
            amount: parseInt(form.amount.value)
        }]
    });
    if (result) {
        showResult(`[성공] 포인트 양도가 완료되었습니다.`);
        form.reset();
    }
}

async function handleTransferItemForm(event) {
    event.preventDefault();
    const form = event.target;
    // 아이템 양도 로직 (구현된 경우 호출)
    const result = await callV2Api('transfer_items', {
        sender_id: form.sender_id.value,
        receiver_id: form.receiver_id.value,
        item_id: parseInt(form.item_id.value),
        quantity: parseInt(form.quantity.value)
    });
    if (result) {
        showResult(`[성공] 아이템 양도 완료!`);
        form.reset();
    }
}

async function handleGambleForm(event) {
    event.preventDefault();
    const form = event.target;
    // 도박 실행 (통합 핸들러 호출)
    const result = await callV2Api('run_gamble', {
        member_id: form.member_id.value,
        game_type: form.game_id.value, // roulette, blackjack 등
        bet_amount: parseInt(form.bet_amount.value),
        outcomes: "0, 0.5, 1, 2, 5" // 필요시 설정값 전달
    });
    
    if (result) {
        showResult(`[도박결과] ${result.multiplier}배 당첨!`);
    }
}

async function handleItemForm(event) {
    event.preventDefault();
    const form = event.target;
    const isPurchase = document.getElementById('item-is-purchase').checked;
    
    // 구매 혹은 지급 액션 결정
    const action = isPurchase ? 'buy_item' : 'admin_give_item';
    const result = await callV2Api(action, {
        member_id: form.member_id.value,
        item_id: parseInt(form.item_id.value),
        quantity: parseInt(form.quantity.value)
    });
    if (result) {
        showResult(`[성공] 아이템 처리가 완료되었습니다.`);
        form.reset();
    }
}

async function handleInfoForm(event) {
    event.preventDefault();
    const memberId = event.target.member_id.value;
    const result = await callV2Api('get_member_detail', { member_id: memberId }, 'GET');
    if (result) {
        const pts = Number(result.data.points).toLocaleString();
        showResult(`[조회] ${result.data.member_name}님의 포인트: ${pts}P`);
    }
}

async function handleStatusAction(actionType) {
    const memberId = document.getElementById('member-id-status').value;
    const typeId = document.getElementById('status-type-select').value;
    
    if (!memberId || !typeId) {
        showError('회원과 상태 종류를 모두 선택해주세요.');
        return;
    }

    const result = await callV2Api('set_member_status', {
        action: actionType, // add, decrease, evolve, cure
        member_id: memberId,
        type_id: typeId
    });
    if (result) showResult(`[상태변경] ${result.message || '처리 완료'}`);
}

/**
 * 인벤토리 연동을 위한 발신자 변경 핸들러
 */
async function handleSenderChangePopup(event) {
    const senderId = event.target.value;
    const itemSelect = document.getElementById('item-id-transfer');
    if (!senderId) return;

    itemSelect.innerHTML = '<option>로딩 중...</option>';
    const result = await callV2Api('get_member_detail', { member_id: senderId }, 'GET');
    if (result && result.data.inventory) {
        populateSelect(itemSelect, result.data.inventory, 'item_id', 'item_name');
        document.getElementById('quantity-transfer').disabled = false;
        document.getElementById('transfer-item-submit').disabled = false;
    }
}

function handleItemChangePopup(event) {
    // 수량 제한 등 필요시 로직 추가 가능
}

function showResult(message) { resultBox.value = message; errorMessage.textContent = ''; }
function showError(message) { errorMessage.textContent = message; resultBox.value = ''; }
function clearMessages() { resultBox.value = ''; errorMessage.textContent = ''; }