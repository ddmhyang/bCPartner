<?php include 'auth_check.php'; include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>밴드 관리 시스템 v2</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav id="main-nav">
        <div class="nav-logo">관리자 패널</div>
        <ul class="menu-list">
            <li><a href="#/members">캐릭터</a></li>
            <li class="has-sub">
                <a href="#/manage">관리</a>
                <ul class="sub-menu">
                    <li><a href="#/manage/shop">상점</a></li>
                    <li><a href="#/manage/gamble">도박</a></li>
                    <li><a href="#/manage/status">상태이상</a></li>
                </ul>
            </li>
            <li class="has-sub">
                <a href="#/transfer">양도</a>
                <ul class="sub-menu">
                    <li><a href="#/transfer/point">포인트</a></li>
                    <li><a href="#/transfer/item">아이템</a></li>
                </ul>
            </li>
            <li><a href="#/logs">로그</a></li>
            <li><a href="#/settings">설정</a></li>
        </ul>
    </nav>

    <main id="app-container">
        <header id="top-bar">
            <input type="text" id="global-search" placeholder="검색어 입력 (캐릭터, 물건, 사유)...">
            <input type="date" id="date-filter">
        </header>
        <div id="app-content"></div>
    </main>
    <script src="app.js"></script>
</body>
</html>