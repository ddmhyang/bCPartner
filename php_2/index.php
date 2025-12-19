<?php include 'auth_check.php'; ?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B-Partner Admin v2</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav id="sidebar">
        <div class="sidebar-header"><h3>B-Partner</h3></div>
        <ul class="nav-menu">
            <li><a href="#/members"><i class="fa fa-users"></i> 캐릭터</a></li>
            <li class="has-sub">
                <a><i class="fa fa-cog"></i> 관리</a>
                <ul class="sub-menu">
                    <li><a href="#/manage/shop">상점 관리</a></li>
                    <li><a href="#/manage/gamble">도박 관리</a></li>
                    <li><a href="#/manage/inventory">인벤토리 관리</a></li>
                    <li><a href="#/manage/status">상태이상 관리</a></li>
                </ul>
            </li>
            <li class="has-sub">
                <a><i class="fa fa-exchange-alt"></i> 양도</a>
                <ul class="sub-menu">
                    <li><a href="#/transfer/points">포인트 양도</a></li>
                    <li><a href="#/transfer/items">아이템 양도</a></li>
                </ul>
            </li>
            <li class="has-sub">
                <a><i class="fa fa-list"></i> 로그</a>
                <ul class="sub-menu">
                    <li><a href="#/logs/points">포인트 로그</a></li>
                    <li><a href="#/logs/items">아이템 로그</a></li>
                    <li><a href="#/logs/status">상태 로그</a></li>
                </ul>
            </li>
            <li><a href="#/settings"><i class="fa fa-sliders-h"></i> 설정</a></li>
        </ul>
    </nav>

    <main id="content">
        <header id="top-nav">
            <div class="search-box">
                <i class="fa fa-search"></i>
                <input type="text" id="global-search" placeholder="캐릭터, 아이템, 사유 검색...">
                <input type="date" id="date-filter">
            </div>
            <div class="user-info">
                <span>관리자님</span>
                <button onclick="location.href='logout.php'">로그아웃</button>
            </div>
        </header>
        <div id="router-view">
            </div>
    </main>

    <script src="app.js"></script>
</body>
</html>