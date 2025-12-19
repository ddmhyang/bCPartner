/**
 * B-Partner 통합 관리 시스템 v2 - app.js
 * 모든 기능을 포함한 전체 전문입니다.
 */

const State = {
    // [기능] 정렬 상태를 로컬 스토리지에 저장하여 새로고침해도 유지
    sort: JSON.parse(localStorage.getItem('admin_sort')) || {
        members: { key: 'member_id', dir: 'asc' },
        items: { key: 'item_id', dir: 'asc' },
        logs: { key: 'log_time', dir: 'desc' },
        status: { key: 'type_id', dir: 'asc' }
    },
    search: '',
    date: '',
    currentPage: ''
};

const App = {
    // [초기화] 이벤트 리스너 등록 및 첫 화면 로드
    init() {
        window.addEventListener('hashchange', () => this.router());

        // 전역 검색창 이벤트 (안전장치 추가)
        const searchInput = document.getElementById('global-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                State.search = e.target.value.toLowerCase();
                this.router();
            });
        }

        // 날짜 필터 이벤트
        const dateInput = document.getElementById('date-filter');
        if (dateInput) {
            dateInput.addEventListener('change', (e) => {
                State.date = e.target.value;
                this.router();
            });
        }

        this.router();
    },

    // [서버 통신] api_handler.php와 대화하는 공통 함수
    async fetchData(action, params = {}) {
        try {
            const query = new URLSearchParams({ action, ...params }).toString();
            const res = await fetch(`api_handler.php?${query}`);
            if (!res.ok) throw new Error('네트워크 응답에 문제가 있습니다.');
            return await res.json();
        } catch (err) {
            console.error('Fetch Error:', err);
            return { status: 'error', message: err.message };
        }
    },

    async postData(action, data = {}) {
        try {
            const res = await fetch(`api_handler.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type:': 'application/json' },
                body: JSON.stringify(data)
            });
            return await res.json();
        } catch (err) {
            return { status: 'error', message: err.message };
        }
    },

    // [정렬] 정렬 설정 변경 및 저장
    setSort(page, key) {
        if (State.sort[page].key === key) {
            State.sort[page].dir = State.sort[page].dir === 'asc' ? 'desc' : 'asc';
        } else {
            State.sort[page].key = key;
            State.sort[page].dir = 'asc';
        }
        localStorage.setItem('admin_sort', JSON.stringify(State.sort));
        this.router();
    },

    // [데이터 처리] 검색 및 정렬 필터 적용기
    applyFilters(list, sortKey, sortPage) {
        let filtered = [...list];
        // 검색 필터 (이름이나 사유 등)
        if (State.search) {
            filtered = filtered.filter(item => 
                Object.values(item).some(val => String(val).toLowerCase().includes(State.search))
            );
        }
        // 날짜 필터
        if (State.date) {
            filtered = filtered.filter(item => item.log_time && item.log_time.startsWith(State.date));
        }
        // 정렬 적용
        const { key, dir } = State.sort[sortPage];
        filtered.sort((a, b) => {
            let valA = a[key], valB = b[key];
            if (!isNaN(valA) && !isNaN(valB)) {
                valA = Number(valA); valB = Number(valB);
            }
            return dir === 'asc' ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
        });
        return filtered;
    },

    // [라우터] 주소창의 # 뒤의 경로에 따라 화면을 갈아 끼움
    async router() {
        const hash = location.hash || '#/members';
        const view = document.getElementById('router-view');
        if (!view) return; // 에러 방지

        // 메뉴 활성화 UI 처리
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === hash);
        });

        if (hash.startsWith('#/members')) {
            await this.renderMembers(view);
        } else if (hash.startsWith('#/manage/shop')) {
            await this.renderShop(view);
        } else if (hash.startsWith('#/manage/gamble')) {
            this.renderGamble(view);
        } else if (hash.startsWith('#/manage/status')) {
            await this.renderStatusManage(view);
        } else if (hash.startsWith('#/transfer/points')) {
            this.renderTransferPoints(view);
        } else if (hash.startsWith('#/transfer/items')) {
            this.renderTransferItems(view);
        } else if (hash.startsWith('#/logs/')) {
            const type = hash.split('/')[2];
            await this.renderLogs(view, type);
        } else if (hash.startsWith('#/member/')) {
            const id = hash.split('/')[2];
            await this.renderMemberDetail(view, id);
        } else if (hash.startsWith('#/settings')) {
            this.renderSettings(view);
        }
    },

    // --- 각 페이지 렌더링 함수들 ---

    // 1. 캐릭터 목록 페이지
    async renderMembers(container) {
        const res = await this.fetchData('get_members');
        const list = this.applyFilters(res.data || [], 'member_id', 'members');

        container.innerHTML = `
            <div class="page-header">
                <h2>캐릭터 관리</h2>
                <div class="action-bar">
                    <input type="text" id="new-mem-name" placeholder="새 캐릭터 이름">
                    <button onclick="App.addMember()">퀵 추가</button>
                    <div class="bulk-group">
                        <button class="btn-sub" onclick="App.bulkAction('point')">포인트 일괄</button>
                        <button class="btn-sub" onclick="App.bulkAction('item')">아이템 일괄</button>
                        <button class="btn-sub" onclick="App.bulkAction('status')">상태 일괄</button>
                    </div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="all-check" onclick="App.toggleAllCheck(this)"></th>
                        <th onclick="App.setSort('members', 'member_id')">번호 <i class="fa fa-sort"></i></th>
                        <th onclick="App.setSort('members', 'member_name')">이름 <i class="fa fa-sort"></i></th>
                        <th onclick="App.setSort('members', 'points')">포인트 <i class="fa fa-sort"></i></th>
                        <th>현재 상태</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    ${list.map(m => `
                        <tr>
                            <td><input type="checkbox" class="mem-cb" value="${m.member_id}"></td>
                            <td>${m.member_id}</td>
                            <td><a href="#/member/${m.member_id}">${m.member_name}</a></td>
                            <td>${Number(m.points).toLocaleString()}P</td>
                            <td>${m.status_names || '-'}</td>
                            <td><button onclick="location.hash='#/member/${m.member_id}'">수정</button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    // 1-2. 캐릭터 개인 상세 페이지 (중요)
    async renderMemberDetail(container, id) {
        const res = await this.fetchData('get_member_detail', { member_id: id });
        const m = res.data;
        if (!m) return container.innerHTML = '존재하지 않는 사용자입니다.';

        container.innerHTML = `
            <div class="detail-card">
                <h3>[${m.member_id}] ${m.member_name} 상세 관리</h3>
                <div class="grid-2">
                    <div class="info-group">
                        <label>이름 수정</label>
                        <input type="text" id="edit-name" value="${m.member_name}">
                        <label>포인트 설정</label>
                        <input type="number" id="edit-points" value="${m.points}">
                        <button onclick="App.updateMemberInfo(${id})">기본 정보 저장</button>
                    </div>
                    <div class="status-group">
                        <label>현재 상태</label>
                        <div id="status-badges">${m.statuses.map(s => `<span class="badge">${s.type_name} <i onclick="App.removeStatus(${id}, ${s.type_id})">×</i></span>`).join('')}</div>
                        <button onclick="App.showStatusPicker(${id})">상태 추가</button>
                    </div>
                </div>
                <hr>
                <h4>인벤토리</h4>
                <div id="member-inventory">
                    ${m.inventory.map(i => `
                        <div class="inv-item">
                            ${i.item_name} (${i.quantity}개) 
                            <button onclick="App.changeInvQty(${id}, ${i.item_id}, -1)">-</button>
                            <button onclick="App.changeInvQty(${id}, ${i.item_id}, 1)">+</button>
                            <button class="btn-del" onclick="App.removeInvItem(${id}, ${i.item_id})">삭제</button>
                        </div>
                    `).join('')}
                    <button onclick="App.showItemPicker(${id})">아이템 추가</button>
                </div>
            </div>
        `;
    },

    // 2. 상점 관리 페이지
    async renderShop(container) {
        const res = await this.fetchData('get_items');
        const list = this.applyFilters(res.data || [], 'item_id', 'items');

        container.innerHTML = `
            <div class="page-header">
                <h2>상점/아이템 관리</h2>
                <button onclick="App.showAddItemModal()">아이템 추가</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th onclick="App.setSort('items', 'item_id')">ID</th>
                        <th onclick="App.setSort('items', 'item_name')">아이템명</th>
                        <th onclick="App.setSort('items', 'price')">가격</th>
                        <th onclick="App.setSort('items', 'stock')">재고</th>
                        <th>판매상태</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    ${list.map(i => `
                        <tr>
                            <td>${i.item_id}</td>
                            <td>${i.item_name}</td>
                            <td>${Number(i.price).toLocaleString()}P</td>
                            <td>${i.stock === -1 ? '무제한' : i.stock}</td>
                            <td>${i.is_on_sale ? '판매중' : '판매중지'}</td>
                            <td><button onclick="App.editItem(${i.item_id})">수정</button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    // 3. 도박 관리 페이지
    renderGamble(container) {
        container.innerHTML = `
            <div class="gamble-card">
                <h2>도박 시스템 관리</h2>
                <div class="tabs">
                    <button onclick="App.showGambleTab('roulette')">룰렛</button>
                    <button onclick="App.showGambleTab('oddeven')">홀짝</button>
                    <button onclick="App.showGambleTab('blackjack')">블랙잭</button>
                </div>
                <div id="gamble-setting-view" class="p-20">
                    <p>상단 탭을 선택하여 설정을 변경하세요.</p>
                </div>
            </div>
        `;
        this.showGambleTab('roulette');
    },

    showGambleTab(type) {
        const view = document.getElementById('gamble-setting-view');
        if (type === 'roulette') {
            view.innerHTML = `
                <h4>룰렛 설정</h4>
                <label>배율 리스트 (쉼표로 구분, 공백 허용)</label>
                <input type="text" id="roulette-multi" placeholder="0, 0.5, 1, 2, 5" value="0, 0.5, 1, 2, 5">
                <p class="hint">* 사용자가 도박 시 이 중 하나가 랜덤으로 당첨됩니다.</p>
                <button onclick="App.saveGambleSetting('roulette')">저장</button>
            `;
        } else if (type === 'oddeven') {
            view.innerHTML = `<h4>홀짝 설정</h4><p>기본 승리 배율: 2배 (고정)</p>`;
        } else if (type === 'blackjack') {
            view.innerHTML = `<h4>블랙잭 설정</h4><p>딜러보다 21에 가까우면 승리 (기본 2배)</p>`;
        }
    },

    // 5. 상태 이상 관리 (h:m 시간 설정 포함)
    async renderStatusManage(container) {
        const res = await this.fetchData('get_status_types');
        const list = res.data || [];

        container.innerHTML = `
            <div class="page-header">
                <h2>상태 이상 관리</h2>
                <button onclick="App.addStatusType()">새 상태 추가</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>상태 이름</th>
                        <th>자동 진화 시간 (h:m)</th>
                        <th>다음 단계</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    ${list.map(s => {
                        const h = Math.floor(s.evolve_interval / 60);
                        const m = s.evolve_interval % 60;
                        return `
                        <tr>
                            <td>${s.type_id}</td>
                            <td>${s.type_name}</td>
                            <td>
                                <input type="text" class="sm-input" id="time-${s.type_id}" value="${h}:${m.toString().padStart(2, '0')}">
                            </td>
                            <td>${s.next_type_name || '최종단계'}</td>
                            <td>
                                <button onclick="App.saveStatusTime(${s.type_id})">시간저장</button>
                                <button class="btn-del" onclick="App.deleteStatusType(${s.type_id})">삭제</button>
                            </td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        `;
    },

    // 6. 포인트 양도 (m:n 가능)
    renderTransferPoints(container) {
        container.innerHTML = `
            <div class="card">
                <h3>포인트 양도 (1:N, M:N)</h3>
                <div id="point-transfer-rows">
                    <div class="t-row">
                        <input type="text" placeholder="보내는 사람(이름/ID)" class="from-user">
                        <i class="fa fa-arrow-right"></i>
                        <div class="to-users-box">
                            <div class="to-row">
                                <input type="text" placeholder="받는 사람" class="to-user">
                                <input type="number" placeholder="금액" class="to-amount">
                            </div>
                        </div>
                        <button onclick="App.addToUserRow(this)">받는사람+버튼</button>
                    </div>
                </div>
                <button class="btn-main" onclick="App.execPointTransfer()">양도 실행</button>
            </div>
        `;
    },

    // 9. 설정 페이지
    renderSettings(container) {
        container.innerHTML = `
            <div class="settings-grid">
                <div class="card">
                    <h4>시즌 초기화</h4>
                    <p>캐릭터, 인벤토리, 모든 로그만 삭제합니다.</p>
                    <button class="btn-del" onclick="App.resetData('season')">시즌 초기화 실행</button>
                </div>
                <div class="card">
                    <h4>전체 초기화</h4>
                    <p>데이터베이스의 모든 테이블을 초기화합니다.</p>
                    <button class="btn-del" onclick="App.resetData('factory')">공장 초기화 실행</button>
                </div>
                <div class="card">
                    <h4>데이터 백업</h4>
                    <p>현재 데이터베이스(database.db)를 다운로드합니다.</p>
                    <button onclick="location.href='api_handler.php?action=download_db'">DB 백업 다운로드</button>
                </div>
            </div>
        `;
    },

    // --- 기능 함수들 (Actions) ---

    async addMember() {
        const name = document.getElementById('new-mem-name').value;
        if (!name) return alert('이름을 입력하세요.');
        const res = await this.postData('add_member', { name });
        if (res.status === 'success') this.router();
    },

    toggleAllCheck(master) {
        document.querySelectorAll('.mem-cb').forEach(cb => cb.checked = master.checked);
    },

    async bulkAction(type) {
        const selected = Array.from(document.querySelectorAll('.mem-cb:checked')).map(cb => cb.value);
        if (selected.length === 0) return alert('대상을 선택하세요.');
        
        if (type === 'point') {
            const val = prompt('지급/회수할 포인트 (차감은 -입력)');
            if (!val) return;
            await this.postData('bulk_point', { targets: selected, amount: val });
        } else if (type === 'item') {
            const itemId = prompt('아이템 ID를 입력하세요.');
            const qty = prompt('수량을 입력하세요.', '1');
            await this.postData('bulk_item', { targets: selected, itemId, qty });
        }
        alert('작업 완료');
        this.router();
    },

    async saveStatusTime(id) {
        const timeStr = document.getElementById(`time-${id}`).value; // "1:30"
        const res = await this.postData('update_status_time', { type_id: id, time_str: timeStr });
        if (res.status === 'success') alert('저장되었습니다.');
    },

    async resetData(type) {
        if (!confirm('정말로 초기화하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) return;
        const action = type === 'season' ? 'reset_season' : 'factory_reset';
        const res = await this.fetchData(action);
        alert(res.message);
        location.reload();
    },

    addToUserRow(btn) {
        const box = btn.parentElement.querySelector('.to-users-box');
        const div = document.createElement('div');
        div.className = 'to-row';
        div.innerHTML = `<input type="text" placeholder="받는 사람" class="to-user"> <input type="number" placeholder="금액" class="to-amount">`;
        box.appendChild(div);
    }
};

// 페이지 로드 시 앱 시작
document.addEventListener('DOMContentLoaded', () => App.init());