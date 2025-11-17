<?php
include 'auth_check.php'; 
include 'db_connect.php'; 
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>밴드 상점 관리자 (SPA)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav id="main-nav">
        <h1>관리자 메뉴</h1>
        <ul>
            <li><a href="#" data-page="members">회원 관리</a></li>
            <li><a href="#" data-page="items">상점 관리</a></li>
            <li><a href="#" data-page="games">도박 관리</a></li>
            <li><a href="#" data-page="inventory">인벤토리 관리</a></li>
            <li><a href="#" data-page="transfer_point">포인트 양도</a></li>
            <li><a href="#" data-page="transfer_item">아이템 양도</a></li>
            <li><a href="#" data-page="logs">포인트 로그</a></li>
            <li><a href="#" data-page="item_logs">아이템 로그</a></li>
            <li><a href="#" data-page="settings">설정</a></li> <li><a href="logout.php">로그아웃</a></li>
        </ul>
    </nav>

    <main id="app-content">
        <h2>환영합니다!</h2>
        <p>왼쪽 메뉴를 클릭하여 관리를 시작하세요.</p>
    </main>

    <script src="app.js"></script>
</body>
</html>