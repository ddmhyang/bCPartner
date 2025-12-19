<?php 
// 보안 체크: 로그인 안 되어 있으면 로그인 페이지로 이동
include 'auth_check.php'; 
?>
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
        <div class="sidebar-header">
            <h3>B-Partner <small>v2</small></h3>
        </div>
        <ul class="nav-menu">
            <li><a href="#/members" class="nav-link active"><i class="fa fa-users"></i> 캐릭터 관리</a></li>
            
            <li class="has-sub">
                <a class="nav-link"><i class="fa fa-cog"></i> 관리 <i class="fa fa-chevron-down arrow"></i></a>
                <ul class="sub-menu">
                    <li><a href="#/manage/shop">상점 관리</a></li>
                    <li><a href="#/manage/gamble">도박 관리</a></li>
                    <li><a href="#/manage/inventory">인벤토리 관리</a></li>
                    <li><a href="#/manage/status">상태이상 관리</a></li>
                </ul>
            </li>

            <li class="has-sub">
                <a class="nav-link"><i class="fa fa-exchange-alt"></i> 양도 <i class="fa fa-chevron-down arrow"></i></a>
                <ul class="sub-menu">
                    <li><a href="#/transfer/points">포인트 양도</a></li>
                    <li><a href="#/transfer/items">아이템 양도</a></li>
                </ul>
            </li>

            <li class="has-sub">
                <a class="nav-link"><i class="fa fa-list"></i> 로그 <i class="fa fa-chevron-down arrow"></i></a>
                <ul class="sub-menu">
                    <li><a href="#/logs/points">포인트 로그</a></li>
                    <li><a href="#/logs/items">아이템 로그</a></li>
                    <li><a href="#/logs/status">상태 로그</a></li>
                </ul>
            </li>

            <li><a href="#/settings" class="nav-link"><i class="fa fa-sliders-h"></i> 설정</a></li>
        </ul>
        <div class="sidebar-footer">
            <button onclick="location.href='logout.php'"><i class="fa fa-sign-out-alt"></i> 로그아웃</button>
        </div>
    </nav>

    <main id="content">
        <header id="top-nav">
            <div class="search-container">
                <div class="search-box">
                    <i class="fa fa-search"></i>
                    <input type="text" id="global-search" placeholder="검색 (이름, 사유, 아이템)...">
                </div>
                <div class="date-box">
                    <i class="fa fa-calendar"></i>
                    <input type="date" id="date-filter">
                </div>
            </div>
            <div class="admin-profile">
                <span>관리자 모드</span>
            </div>
        </header>

        <section id="router-view">
            <div class="loading">데이터를 불러오는 중입니다...</div>
        </section>
    </main>

    <script src="app.js"></script>
</body>
</html>