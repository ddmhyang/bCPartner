/**
 * B-Partner 통합 자바스크립트 v2
 */

const State = {
    sort: JSON.parse(localStorage.getItem('admin_sort')) || {
        members: {
            key: 'member_id',
            dir: 'asc'
        },
        items: {
            key: 'item_id',
            dir: 'asc'
        },
        logs: {
            key: 'log_time',
            dir: 'desc'
        }
    },
    search: '',
    date: ''
};

const App = {
    init() {
        window.addEventListener('hashchange', () => this.router());

        const searchInput = document.getElementById('global-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                State.search = e
                    .target
                    .value
                    .toLowerCase();
                this.router();
            });
        }

        const dateInput = document.getElementById('date-filter');
        if (dateInput) {
            dateInput.addEventListener('change', (e) => {
                State.date = e.target.value;
                this.router();
            });
        }

        this.router();
    },

    async fetchData(action, params = {}) {
        const query = new URLSearchParams({
            action,
            ...params
        }).toString();
        const res = await fetch(`api_handler.php?${query}`);
        return await res.json();
    },

    async postData(action, data = {}) {
        const res = await fetch(`api_handler.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        return await res.json();
    },

    setSort(page, key) {
        if (State.sort[page].key === key) {
            State
                .sort[page]
                .dir = State
                .sort[page]
                .dir === 'asc'
                    ? 'desc'
                    : 'asc';
        } else {
            State
                .sort[page]
                .key = key;
            State
                .sort[page]
                .dir = 'asc';
        }
        localStorage.setItem('admin_sort', JSON.stringify(State.sort));
        this.router();
    },

    applyFilters(list, sortPage) {
        let filtered = [...list];
        if (State.search) {
            filtered = filtered.filter(
                i => Object.values(i).some(v => String(v).toLowerCase().includes(State.search))
            );
        }
        if (
            State.date && filtered[0]
                ?.log_time
        ) {
            filtered = filtered.filter(i => i.log_time.startsWith(State.date));
        }
        const {key, dir} = State.sort[sortPage];
        filtered.sort((a, b) => {
            let vA = a[key],
                vB = b[key];
            if (!isNaN(vA) && !isNaN(vB)) {
                vA = Number(vA);
                vB = Number(vB);
            }
            return dir === 'asc'
                ? (
                    vA > vB
                        ? 1
                        : -1
                )
                : (
                    vA < vB
                        ? 1
                        : -1
                );
        });
        return filtered;
    },

    async router() {
        const hash = location.hash || '#/members';
        const view = document.getElementById('router-view');
        if (!view) 
            return; // innerHTML 에러 방지
        
        // 메뉴 활성화 처리 (classList 에러 방지)
        document
            .querySelectorAll('.nav-link')
            .forEach(link => {
                if (link) 
                    link
                        .classList
                        .toggle('active', link.getAttribute('href') === hash);
                }
            );

        if (hash.startsWith('#/members')) 
            await this.renderMembers(view);
        else if (hash.startsWith('#/manage/shop')) 
            await this.renderShop(view);
        else if (hash.startsWith('#/manage/gamble')) 
            this.renderGamble(view);
        else if (hash.startsWith('#/manage/status')) 
            await this.renderStatus(view);
        else if (hash.startsWith('#/transfer/points')) 
            this.renderTransferPoints(view);
        else if (hash.startsWith('#/transfer/items')) 
            this.renderTransferItems(view);
        else if (hash.startsWith('#/logs/')) 
            await this.renderLogs(view, hash.split('/')[2]);
        else if (hash.startsWith('#/member/')) 
            await this.renderMemberDetail(view, hash.split('/')[2]);
        else if (hash.startsWith('#/settings')) 
            this.renderSettings(view);
        }
    ,

    // --- 렌더링 함수들 ---

    async renderMembers(view) {
        const res = await this.fetchData('get_members');
        const list = this.applyFilters(res.data || [], 'members');
        view.innerHTML = `
            <div class="page-header">
                <h2>캐릭터 관리</h2>
                <div class="action-bar">
                    <input type="text" id="add-name" placeholder="새 캐릭터 이름">
                    <button onclick="App.addMember()">퀵 추가</button>
                    <button onclick="App.bulkAction('point')">포인트 일괄</button>
                    <button onclick="App.bulkAction('item')">아이템 일괄</button>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" onclick="App.toggleAll(this)"></th>
                        <th onclick="App.setSort('members', 'member_id')">번호</th>
                        <th onclick="App.setSort('members', 'member_name')">이름</th>
                        <th onclick="App.setSort('members', 'points')">포인트</th>
                        <th>상태</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    ${list
            .map(
                m => `
                        <tr>
                            <td><input type="checkbox" class="mem-cb" value="${m.member_id}"></td>
                            <td>${m.member_id}</td>
                            <td><a href="#/member/${m.member_id}">${m.member_name}</a></td>
                            <td>${Number(m.points).toLocaleString()}</td>
                            <td>${m.status_names || '-'}</td>
                            <td><button onclick="location.hash='#/member/${m.member_id}'">상세</button></td>
                        </tr>
                    `
            )
            .join('')}
                </tbody>
            </table>
        `;
    },

    async renderMemberDetail(view, id) {
        const res = await this.fetchData('get_member_detail', {member_id: id});
        const m = res.data;
        view.innerHTML = `
            <div class="detail-card">
                <h3>[${m
            .member_id}] ${m
            .member_name} 상세</h3>
                <div class="grid">
                    <div>
                        <label>이름: <input type="text" id="det-name" value="${m
            .member_name}"></label>
                        <label>포인트: <input type="number" id="det-pts" value="${m
            .points}"></label>
                        <button onclick="App.updateMember(${id})">기본정보 수정</button>
                    </div>
                    <div>
                        <h4>인벤토리</h4>
                        ${m
            .inventory
            .map(
                i => `<div>${i.item_name} ${i.quantity}개 <button onclick="App.delInv(${id},${i.item_id})">삭제</button></div>`
            )
            .join('')}
                    </div>
                </div>
            </div>
        `;
    },

    renderGamble(view) {
        view.innerHTML = `
            <div class="card">
                <h2>도박 관리</h2>
                <label>룰렛 배율 (쉼표로 구분, 공백 허용): 
                    <input type="text" id="roul-multi" value="0, 0.5, 1, 2, 5">
                </label>
                <button onclick="App.saveGamble()">설정 저장</button>
                <hr>
                <p>홀짝 / 블랙잭은 기본 승리 배율 2배로 자동 작동합니다.</p>
            </div>
        `;
    },

    async renderStatus(view) {
        const res = await this.fetchData('get_status_types');
        view.innerHTML = `
            <h2>상태이상 관리</h2>
            <table>
                <thead><tr><th>ID</th><th>이름</th><th>진화 시간(h:m)</th><th>관리</th></tr></thead>
                <tbody>
                    ${res
            .data
            .map(
                s => `
                        <tr>
                            <td>${s.type_id}</td>
                            <td>${s.type_name}</td>
                            <td><input type="text" id="time-${s.type_id}" value="${Math.floor(s.evolve_interval / 60)}:${s.evolve_interval % 60}"></td>
                            <td><button onclick="App.saveStatusTime(${s.type_id})">저장</button></td>
                        </tr>
                    `
            )
            .join('')}
                </tbody>
            </table>
        `;
    },

    renderTransferPoints(view) {
        view.innerHTML = `
            <div class="card">
                <h3>포인트 양도 (M:N)</h3>
                <div id="tp-rows"><div class="row"><input class="f-id" placeholder="보내는ID"><input class="t-id" placeholder="받는ID"><input class="amt" placeholder="포인트"></div></div>
                <button onclick="App.addTpRow()">행 추가</button>
                <button onclick="App.execTp()">양도 실행</button>
            </div>
        `;
    },

    async renderLogs(view, type) {
        const res = await this.fetchData('get_logs', {type});
        const list = this.applyFilters(res.data || [], 'logs');
        view.innerHTML = `<h2>${type} 로그</h2>
            <table>
                <thead><tr><th>시간</th><th>대상</th><th>변동</th><th>사유</th></tr></thead>
                <tbody>${list
            .map(
                l => `<tr><td>${l.log_time}</td><td>${l.member_name}</td><td>${l.points_change || l.quantity_change}</td><td>${l.reason}</td></tr>`
            )
            .join('')}</tbody>
            </table>`;
    },

    renderSettings(view) {
        view.innerHTML = `
            <div class="grid">
                <div class="card"><h4>시즌 초기화</h4><button class="btn-del" onclick="App.reset('season')">실행</button></div>
                <div class="card"><h4>전체 초기화</h4><button class="btn-del" onclick="App.reset('factory')">실행</button></div>
                <div class="card"><h4>백업</h4><button onclick="location.href='api_handler.php?action=download_db'">DB 다운로드</button></div>
            </div>
        `;
    },

    // --- 액션 함수들 ---
    async addMember() {
        const name = document
            .getElementById('add-name')
            .value;
        await this.postData('add_member', {name});
        this.router();
    },
    async saveStatusTime(id) {
        const time = document
            .getElementById(`time-${id}`)
            .value;
        await this.postData('update_status_time', {
            type_id: id,
            time_str: time
        });
        alert('저장됨');
    },
    async reset(type) {
        if (confirm('정말 초기화하시겠습니까?')) {
            await this.fetchData(
                type === 'season'
                    ? 'reset_season'
                    : 'factory_reset'
            );
            location.reload();
        }
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());