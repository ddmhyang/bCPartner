const State = {
    // [기능] 정렬 상태 기억
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
    date: '',
    currentPage: ''
};

const App = {
    init() {
        window.addEventListener('hashchange', () => this.router());
        document
            .getElementById('global-search')
            .addEventListener('input', (e) => {
                State.search = e.target.value;
                this.router();
            });
        document
            .getElementById('date-filter')
            .addEventListener('change', (e) => {
                State.date = e.target.value;
                this.router();
            });
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

    // [요구사항] 정렬 설정 저장
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

    async router() {
        const hash = location.hash || '#/members';
        const view = document.getElementById('router-view');

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
        } else if (hash.startsWith('#/member/')) {
            const id = hash.split('/')[2];
            await this.renderMemberDetail(view, id);
        }
        // ... 나머지 라우팅 생략 ...
    },

    // [캐릭터 페이지] 이름만 입력해서 추가 + 일괄 작업
    async renderMembers(container) {
        const data = await this.fetchData('get_members');
        let list = data.data;

        // 검색 및 정렬 로직 (기존 로직 확장)
        container.innerHTML = `
            <div class="action-bar">
                <input type="text" id="quick-add-name" placeholder="새 캐릭터 이름">
                <button onclick="App.addMember()">퀵 추가</button>
                <div class="bulk-actions">
                    <button onclick="App.bulkPoint()">포인트 일괄</button>
                    <button onclick="App.bulkItem()">아이템 일괄</button>
                    <button onclick="App.bulkStatus()">상태 일괄</button>
                </div>
            </div>
            <table>
                <thead>
                    <th onclick="App.setSort('members', 'member_id')">번호</th>
                    <th onclick="App.setSort('members', 'member_name')">이름</th>
                    <th onclick="App.setSort('members', 'points')">포인트</th>
                    <th>현재 상태</th>
                    <th>관리</th>
                </thead>
                <tbody>
                    ${list
            .map(
                m => `
                        <tr>
                            <td>${m.member_id}</td>
                            <td><a href="#/member/${m.member_id}">${m.member_name}</a></td>
                            <td>${Number(m.points).toLocaleString()}</td>
                            <td>${m.status || '-'}</td>
                            <td><button onclick="location.hash='#/member/${m.member_id}'">상세</button></td>
                        </tr>
                    `
            )
            .join('')}
                </tbody>
            </table>
        `;
    },

    // [양도] m:n 포인트 양도 로직
    renderTransferPoints(container) {
        container.innerHTML = `
            <div class="transfer-container">
                <h3>포인트 양도 (1:N 가능)</h3>
                <div id="transfer-list">
                    <div class="transfer-row">
                        <input type="text" placeholder="보내는 사람 ID" class="from-id">
                        <div class="to-list">
                            <div class="to-row">
                                <input type="text" placeholder="받는 사람 ID" class="to-id">
                                <input type="number" placeholder="포인트" class="amount">
                            </div>
                        </div>
                        <button onclick="App.addTransferToRow(this)">받는 사람 추가</button>
                    </div>
                </div>
                <button onclick="App.execTransferPoints()">양도 실행</button>
            </div>
        `;
    }
};

App.init();