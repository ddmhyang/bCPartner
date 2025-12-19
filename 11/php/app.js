/**
 * B-Partner Admin v2 - app.js
 * 모든 누락된 함수(renderShop, renderGamble, renderLogs 등)를 포함한 전체 전문
 */

const State = {
    // 정렬 설정을 브라우저에 저장
    sort: JSON.parse(localStorage.getItem('admin_sort')) || {
        members: { key: 'member_id', dir: 'asc' },
        items: { key: 'item_id', dir: 'asc' },
        logs: { key: 'log_time', dir: 'desc' },
        status: { key: 'type_id', dir: 'asc' }
    },
    search: '',
    date: ''
};

const App = {
    init() {
        // 해시 변화 감지 (페이지 전환)
        window.addEventListener('hashchange', () => this.router());

        // 1. 네비게이션 클릭 이벤트 (클릭 시 서브메뉴 열기/닫기)
        document.querySelectorAll('.has-sub > a').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const parent = el.parentElement;
                const isOpen = parent.classList.contains('open');

                // 다른 메뉴들 닫기 (아코디언 효과)
                document.querySelectorAll('.has-sub').forEach(li => li.classList.remove('open'));

                // 선택한 메뉴 토글
                if (!isOpen) {
                    parent.classList.add('open');
                }
            });
        });

        // 2. 검색창 이벤트
        const searchInput = document.getElementById('global-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                State.search = e.target.value.toLowerCase();
                this.router();
            });
        }

        // 3. 날짜 필터 이벤트
        const dateInput = document.getElementById('date-filter');
        if (dateInput) {
            dateInput.addEventListener('change', (e) => {
                State.date = e.target.value;
                this.router();
            });
        }

        this.router();
    },

    // 서버 API 호출 (GET)
    async fetchData(action, params = {}) {
        const query = new URLSearchParams({ action, ...params }).toString();
        const res = await fetch(`api_handler.php?${query}`);
        return await res.json();
    },

    // 서버 API 데이터 전송 (POST)
    async postData(action, data = {}) {
        const res = await fetch(`api_handler.php?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await res.json();
    },

    // 정렬 변경 함수
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

    // 데이터 필터링 및 정렬 적용
    applyFilters(list, sortPage) {
        let filtered = [...list];
        if (State.search) {
            filtered = filtered.filter(i => 
                Object.values(i).some(v => String(v).toLowerCase().includes(State.search))
            );
        }
        if (State.date && (filtered[0]?.log_time || filtered[0]?.created_at)) {
            filtered = filtered.filter(i => (i.log_time || i.created_at).startsWith(State.date));
        }
        const { key, dir } = State.sort[sortPage] || { key: 'id', dir: 'asc' };
        filtered.sort((a, b) => {
            let vA = a[key], vB = b[key];
            if (!isNaN(vA) && !isNaN(vB)) { vA = Number(vA); vB = Number(vB); }
            return dir === 'asc' ? (vA > vB ? 1 : -1) : (vA < vB ? 1 : -1);
        });
        return filtered;
    },

    // 페이지 라우터
    async router() {
        const hash = location.hash || '#/members';
        const view = document.getElementById('router-view');
        if (!view) return;

        // 메뉴 활성화 UI 업데이트
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === hash);
        });

        // 경로에 따른 렌더링 분기
        if (hash.startsWith('#/members')) await this.renderMembers(view);
        else if (hash.startsWith('#/manage/shop')) await this.renderShop(view);
        else if (hash.startsWith('#/manage/gamble')) this.renderGamble(view);
        else if (hash.startsWith('#/manage/status')) await this.renderStatus(view);
        else if (hash.startsWith('#/transfer/points')) this.renderTransferPoints(view);
        else if (hash.startsWith('#/transfer/items')) this.renderTransferItems(view);
        else if (hash.startsWith('#/logs/')) {
            const logType = hash.split('/')[2];
            await this.renderLogs(view, logType);
        }
        else if (hash.startsWith('#/member/')) {
            const memberId = hash.split('/')[2];
            await this.renderMemberDetail(view, memberId);
        }
        else if (hash.startsWith('#/settings')) this.renderSettings(view);
    },

    // --- 페이지별 렌더링 함수 ---

    async renderMembers(view) {
        const res = await this.fetchData('get_members');
        const list = this.applyFilters(res.data || [], 'members');
        view.innerHTML = `
            <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                <h2>캐릭터 관리</h2>
                <div class="action-bar" style="display:flex; gap:15px;">
                    <input type="text" id="add-name" placeholder="신규 이름" style="padding:10px; border-radius:8px; border:1px solid #ddd;">
                    <button onclick="App.addMember()">퀵 추가</button>
                    <button class="btn-sub" onclick="App.bulkAction('point')">포인트 일괄</button>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:50px;"><input type="checkbox" onclick="App.toggleAll(this)"></th>
                        <th onclick="App.setSort('members','member_id')">ID <i class="fa fa-sort"></i></th>
                        <th onclick="App.setSort('members','member_name')">이름 <i class="fa fa-sort"></i></th>
                        <th onclick="App.setSort('members','points')">보유 포인트 <i class="fa fa-sort"></i></th>
                        <th>현재 상태</th>
                        <th style="width:100px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    ${list.map(m => `
                        <tr>
                            <td><input type="checkbox" class="mem-cb" value="${m.member_id}"></td>
                            <td>${m.member_id}</td>
                            <td><a href="#/member/${m.member_id}" style="color:var(--primary); font-weight:600; text-decoration:none;">${m.member_name}</a></td>
                            <td>${Number(m.points).toLocaleString()} P</td>
                            <td>${m.status_names || '<span style="color:#ccc;">-</span>'}</td>
                            <td><button style="padding:6px 12px; font-size:0.85rem;" onclick="location.hash='#/member/${m.member_id}'">수정</button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>`;
    },

    async renderMemberDetail(view, id) {
        const res = await this.fetchData('get_member_detail', { member_id: id });
        const m = res.data;
        if (!m) return view.innerHTML = "데이터를 찾을 수 없습니다.";
        
        view.innerHTML = `
            <div class="card" style="background:white; padding:40px; border-radius:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; border-bottom:2px solid #f1f3f6; padding-bottom:20px;">
                    <h2 style="margin:0;">[${m.member_id}] ${m.member_name} 상세 관리</h2>
                    <button class="btn-sub" onclick="history.back()">뒤로가기</button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">
                    <div>
                        <h4 style="color:var(--gray);">기본 정보 수정</h4>
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:8px; font-weight:600;">캐릭터 이름</label>
                            <input type="text" id="det-name" value="${m.member_name}" style="width:100%; padding:15px; border-radius:10px; border:1px solid #ddd;">
                        </div>
                        <div style="margin-bottom:25px;">
                            <label style="display:block; margin-bottom:8px; font-weight:600;">보유 포인트</label>
                            <input type="number" id="det-pts" value="${m.points}" style="width:100%; padding:15px; border-radius:10px; border:1px solid #ddd;">
                        </div>
                        <button style="width:100%; padding:15px;" onclick="App.updateMember(${id})">정보 저장하기</button>
                    </div>
                    <div>
                        <h4 style="color:var(--gray);">현재 상태 목록</h4>
                        <div id="status-badges" style="margin-bottom:30px;">
                            ${m.statuses && m.statuses.length > 0 
                                ? m.statuses.map(s => `<span class="badge" style="padding:8px 15px; font-size:1rem; background:#e0e7ff; color:#4338ca; border-radius:15px; margin-right:5px;">${s.type_name}</span>`).join('') 
                                : '<p style="color:#bbb;">부여된 상태가 없습니다.</p>'}
                        </div>
                        <h4 style="color:var(--gray);">인벤토리 보유 아이템</h4>
                        <div id="inv-list">
                            ${m.inventory && m.inventory.length > 0 
                                ? m.inventory.map(i => `
                                    <div style="display:flex; justify-content:space-between; align-items:center; background:#f8faff; padding:12px 20px; border-radius:10px; margin-bottom:8px;">
                                        <span><strong>${i.item_name}</strong> (${i.quantity}개)</span>
                                        <button class="btn-del" style="padding:5px 12px; font-size:0.8rem;" onclick="App.delInv(${id},${i.item_id})">회수</button>
                                    </div>`).join('') 
                                : '<p style="color:#bbb;">보유 아이템이 없습니다.</p>'}
                        </div>
                    </div>
                </div>
            </div>`;
    },

    async renderShop(view) {
        const res = await this.fetchData('get_items');
        const list = this.applyFilters(res.data || [], 'items');
        view.innerHTML = `
            <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                <h2>상점 관리</h2>
                <button onclick="App.addItem()">아이템 추가</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th onclick="App.setSort('items','item_id')">ID</th>
                        <th onclick="App.setSort('items','item_name')">이름</th>
                        <th onclick="App.setSort('items','price')">가격</th>
                        <th onclick="App.setSort('items','stock')">재고</th>
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
                            <td><button onclick="App.editItem(${i.item_id})">수정</button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>`;
    },

    renderGamble(view) {
        view.innerHTML = `
            <div class="card" style="background:white; padding:30px; border-radius:15px;">
                <h2>도박 시스템 설정</h2>
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:10px; font-weight:600;">룰렛 배율 목록 (쉼표로 구분, 공백 허용)</label>
                    <input type="text" id="roul-multi" value="0, 0.5, 1, 2, 5" style="width:100%; padding:12px; border-radius:8px; border:1px solid #ddd;">
                    <p style="font-size:0.85rem; color:var(--gray); margin-top:5px;">예: -5, 0, 1, 2, 10 (사용자가 도박 시 이 중 하나가 랜덤 선택됨)</p>
                </div>
                <button onclick="App.saveGamble()">설정 저장</button>
                <hr style="margin:30px 0; border:none; border-top:1px solid #eee;">
                <p>홀짝 및 블랙잭은 기본 승리 배율(2배)로 자동 작동합니다.</p>
            </div>`;
    },

    async renderStatus(view) {
        const res = await this.fetchData('get_status_types');
        const list = res.data || [];
        view.innerHTML = `
            <div class="page-header" style="margin-bottom:30px;">
                <h2>상태이상 관리</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>상태 이름</th>
                        <th>자동 진화 시간 (h:m)</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    ${list.map(s => `
                        <tr>
                            <td>${s.type_id}</td>
                            <td>${s.type_name}</td>
                            <td>
                                <input type="text" id="time-${s.type_id}" value="${Math.floor(s.evolve_interval/60)}:${(s.evolve_interval%60).toString().padStart(2,'0')}" style="width:80px; padding:5px; text-align:center;">
                            </td>
                            <td><button onclick="App.saveStatusTime(${s.type_id})">저장</button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>`;
    },

    renderTransferPoints(view) {
        view.innerHTML = `
            <div class="card" style="background:white; padding:30px; border-radius:15px;">
                <h2>포인트 양도 (M:N)</h2>
                <div id="tp-sender-box" style="margin-bottom:20px;">
                    <input type="number" id="tp-sender" placeholder="보내는 이 ID" style="width:200px; padding:10px;">
                </div>
                <div id="tp-receivers">
                    <div class="row" style="margin-bottom:10px; display:flex; gap:10px;">
                        <input type="number" class="r-id" placeholder="받는 이 ID" style="padding:10px;">
                        <input type="number" class="r-amt" placeholder="포인트 금액" style="padding:10px;">
                    </div>
                </div>
                <button onclick="App.addTpRow()" class="btn-sub">+ 받는 사람 추가</button>
                <button onclick="App.execTp()" style="margin-left:10px;">양도 실행</button>
            </div>`;
    },

    renderTransferItems(view) {
        view.innerHTML = `
            <div class="card" style="background:white; padding:30px; border-radius:15px;">
                <h2>아이템 양도 (M:N)</h2>
                <p>보내는 사람과 받는 사람, 아이템을 선택하여 양도합니다.</p>
                <div id="ti-rows">
                    <div class="row" style="margin-bottom:15px; padding:15px; border:1px solid #eee; border-radius:10px; display:flex; gap:10px; align-items:center;">
                        <input type="number" class="f-id" placeholder="보내는ID" style="width:100px; padding:8px;">
                        <i class="fa fa-arrow-right"></i>
                        <input type="number" class="t-id" placeholder="받는ID" style="width:100px; padding:8px;">
                        <input type="number" class="i-id" placeholder="아이템ID" style="width:100px; padding:8px;">
                        <input type="number" class="i-qty" placeholder="수량" style="width:80px; padding:8px;">
                    </div>
                </div>
                <button onclick="App.addTiRow()" class="btn-sub">+ 양도 건 추가</button>
                <button onclick="App.execTi()" style="margin-left:10px;">양도 실행</button>
            </div>`;
    },

    async renderLogs(view, type) {
        const res = await this.fetchData('get_logs', { type });
        const list = this.applyFilters(res.data || [], 'logs');
        view.innerHTML = `
            <div class="page-header" style="margin-bottom:30px;">
                <h2>${type === 'points' ? '포인트' : (type === 'items' ? '아이템' : '상태')} 로그</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th onclick="App.setSort('logs','log_time')">시간 <i class="fa fa-sort"></i></th>
                        <th>캐릭터</th>
                        <th>변동 내역</th>
                        <th>사유</th>
                    </tr>
                </thead>
                <tbody>
                    ${list.map(l => `
                        <tr>
                            <td style="font-size:0.85rem; color:var(--gray);">${l.log_time}</td>
                            <td><strong>${l.member_name}</strong> <small>(ID:${l.member_id})</small></td>
                            <td style="color:${(l.points_change || l.quantity_change) > 0 ? 'var(--success)' : 'var(--danger)'}; font-weight:bold;">
                                ${(l.points_change || l.quantity_change) > 0 ? '+' : ''}${l.points_change || l.quantity_change}
                            </td>
                            <td>${l.reason}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>`;
    },

    renderSettings(view) {
        view.innerHTML = `
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div class="card" style="background:white; padding:30px; border-radius:15px;">
                    <h3>데이터 초기화</h3>
                    <div style="margin-bottom:20px;">
                        <p style="color:var(--gray);">캐릭터, 인벤토리, 로그 등 시즌 데이터를 삭제합니다.</p>
                        <button onclick="App.resetData('season')" class="btn-del" style="width:100%;">시즌 초기화 실행</button>
                    </div>
                    <div>
                        <p style="color:var(--gray);">데이터베이스 자체를 초기 상태로 만듭니다.</p>
                        <button onclick="App.resetData('factory')" class="btn-del" style="width:100%;">공장 초기화 실행</button>
                    </div>
                </div>
                <div class="card" style="background:white; padding:30px; border-radius:15px;">
                    <h3>시스템 관리</h3>
                    <p style="color:var(--gray);">현재 데이터베이스(database.db)를 다운로드하여 백업합니다.</p>
                    <button onclick="location.href='api_handler.php?action=download_db'" style="width:100%; background:var(--dark);">DB 백업 다운로드</button>
                    <hr style="margin:20px 0; border:none; border-top:1px solid #eee;">
                    <button onclick="location.href='logout.php'" class="btn-sub" style="width:100%;">로그아웃</button>
                </div>
            </div>`;
    },

    // --- 기능 실행부 ---

    toggleAll(master) { 
        document.querySelectorAll('.mem-cb').forEach(cb => cb.checked = master.checked); 
    },

    async addMember() { 
        const name = document.getElementById('add-name').value; 
        if(!name) return alert('이름을 입력하세요.');
        const res = await this.postData('add_member', { name }); 
        if(res.status === 'success') {
            document.getElementById('add-name').value = '';
            this.router(); 
        }
    },

    async updateMember(id) {
        const name = document.getElementById('det-name').value;
        const points = document.getElementById('det-pts').value;
        const res = await this.postData('update_member', { member_id: id, name, points });
        if(res.status === 'success') { 
            alert('성공적으로 수정되었습니다.'); 
            this.router(); 
        }
    },

    async bulkAction(type) {
        const targets = Array.from(document.querySelectorAll('.mem-cb:checked')).map(cb => cb.value);
        if (targets.length === 0) return alert('대상을 하나 이상 선택하세요.');
        
        if (type === 'point') {
            const amount = prompt('변동할 포인트를 입력하세요 (예: 1000, -500)');
            if (amount === null || amount === "") return;
            const res = await this.postData('bulk_point', { targets, amount: parseInt(amount) });
            if (res.status === 'success') {
                alert(`${targets.length}명에게 일괄 처리가 완료되었습니다.`);
                this.router();
            }
        }
    },

    async delInv(mid, iid) {
        if(!confirm('해당 아이템을 회수하시겠습니까?')) return;
        const res = await this.postData('delete_inventory_item', { member_id: mid, item_id: iid });
        if(res.status === 'success') this.router();
    },

    async saveStatusTime(id) {
        const time_str = document.getElementById(`time-${id}`).value;
        const res = await this.postData('update_status_time', { type_id: id, time_str });
        if(res.status === 'success') alert('시간 설정이 저장되었습니다.');
    },

    addTpRow() {
        const div = document.createElement('div');
        div.className = 'row';
        div.style.cssText = 'margin-bottom:10px; display:flex; gap:10px;';
        div.innerHTML = `<input type="number" class="r-id" placeholder="받는 이 ID" style="padding:10px;"> <input type="number" class="r-amt" placeholder="포인트 금액" style="padding:10px;">`;
        document.getElementById('tp-receivers').appendChild(div);
    },

    async execTp() {
        const sender_id = document.getElementById('tp-sender').value;
        const receivers = Array.from(document.querySelectorAll('#tp-receivers .row')).map(row => ({
            id: row.querySelector('.r-id').value,
            amount: parseInt(row.querySelector('.r-amt').value)
        })).filter(r => r.id && r.amount);

        if(!sender_id || receivers.length === 0) return alert('발신자와 수신자 정보를 입력하세요.');
        const res = await this.postData('transfer_points_multi', { sender_id, receivers });
        if(res.status === 'success') { alert('포인트 양도가 완료되었습니다.'); this.router(); }
    },

    async resetData(type) {
        if(!confirm('정말로 초기화하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) return;
        const action = type === 'season' ? 'reset_season' : 'factory_reset';
        const res = await this.fetchData(action);
        alert(res.message);
        if(res.status === 'success') location.reload();
    }
};

// DOM 로드 완료 후 앱 시작
document.addEventListener('DOMContentLoaded', () => App.init());